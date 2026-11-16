#!/usr/bin/env bash
# Installs or updates Pods on this host, as rootless Podman Quadlets for the
# current user:
#
#   curl -fsSL https://raw.githubusercontent.com/foxws/pods/main/install.sh | bash
#
# With a Caddy proxy in front, routing a hostname to Pods:
#
#   curl -fsSL https://raw.githubusercontent.com/foxws/pods/main/install.sh | bash -s -- --proxy --host pods.example.com
#
# Run it again to update. "--help" lists every option.
set -euo pipefail

# Pods is still in development: these follow main for now. PODS_REF becomes
# a release tag, and LPOD_REF lpod's v4.0.0, once those are released.
PODS_REF="${PODS_REF:-main}"
LPOD_REF="${LPOD_REF:-main}"

REPOSITORY="https://github.com/foxws/pods"
LPOD_RAW="https://raw.githubusercontent.com/foxws/lpod"
CONFIG_HOME="${XDG_CONFIG_HOME:-$HOME/.config}"
QUADLET_DIR="${CONFIG_HOME}/containers/systemd"
PROXY_CONFIG="${CONFIG_HOME}/pods-proxy"
BIN_DIR="${HOME}/.local/bin"
LOCAL_PORT=9090

PROXY=false
HOST=""
HTTP_PORT=80
HTTPS_PORT=443
BEHIND_PROXY=false
UNINSTALL=false
PURGE=false
OWNER_NAME="${PODS_OWNER_NAME:-}"
OWNER_EMAIL="${PODS_OWNER_EMAIL:-}"

WORK=""

usage() {
    cat <<'EOF'
Installs or updates Pods as rootless Podman Quadlets for the current user.

Usage: install.sh [options]

  --proxy               Also install the Pods proxy (Caddy), and route --host to Pods
  --host NAME           The hostname Pods is reached at through the proxy, e.g. pods.example.com
  --proxy-ports H,S     The proxy's host ports for HTTP and HTTPS (default 80,443),
                        e.g. 28080,28443 on a host shared with other users
  --behind-proxy        Another proxy in front handles HTTPS: the Pods proxy serves plain HTTP
  --name NAME           The owner's name, instead of asking for it
  --email EMAIL         The owner's email address, instead of asking for it
  --uninstall           Remove Pods (and with --proxy, the proxy). Keeps its data
  --purge               With --uninstall, also remove Pods' data and its pods-env secret
  -h, --help            Show this help

Environment: PODS_REF (default main) and LPOD_REF (default main) pick the
versions to install. PODS_OWNER_PASSWORD sets the owner's password without
asking, for unattended installs.

Without --proxy, Pods listens on 127.0.0.1:9090 only. Reach it on this
machine, or through an SSH tunnel: ssh -L 9090:127.0.0.1:9090 HOST
EOF
}

info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$*" >&2; }
fail() { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

cleanup() {
    if [ -n "$WORK" ]; then
        rm -rf "$WORK"
    fi
}
trap cleanup EXIT

parse_arguments() {
    while [ $# -gt 0 ]; do
        case "$1" in
            --proxy) PROXY=true ;;
            --host) HOST="${2:-}"; shift ;;
            --proxy-ports)
                [[ "${2:-}" == *,* ]] || fail "--proxy-ports takes two port numbers, e.g. 28080,28443"
                HTTP_PORT="${2%%,*}"
                HTTPS_PORT="${2#*,}"
                shift
                ;;
            --behind-proxy) BEHIND_PROXY=true ;;
            --name) OWNER_NAME="${2:-}"; shift ;;
            --email) OWNER_EMAIL="${2:-}"; shift ;;
            --uninstall) UNINSTALL=true ;;
            --purge) PURGE=true ;;
            -h|--help) usage; exit 0 ;;
            *) usage >&2; fail "Unknown option: $1" ;;
        esac

        shift
    done

    if [ "$PROXY" = true ] && [ "$UNINSTALL" = false ] && ! [[ "$HOST" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]]; then
        fail "--proxy needs --host with the hostname Pods is reached at, e.g. --host pods.example.com"
    fi

    for port in "$HTTP_PORT" "$HTTPS_PORT"; do
        if ! [[ "$port" =~ ^[0-9]{1,5}$ ]]; then
            fail "--proxy-ports takes two port numbers, e.g. 28080,28443"
        fi
    done
}

# Pods runs rootless, as the user that owns the Quadlets, and reaches this
# host over SSH like any other.
check_requirements() {
    if [ "$(id -u)" -eq 0 ]; then
        fail "Run this as the user that should own Pods, not as root."
    fi

    local command
    for command in podman systemctl curl tar jq; do
        command -v "$command" > /dev/null 2>&1 || fail "$command is required. Install it first."
    done

    local version
    version="$(podman version --format '{{.Client.Version}}' 2> /dev/null || true)"

    if [ "${version%%.*}" -lt 6 ] 2> /dev/null || [ -z "$version" ]; then
        fail "Pods needs Podman 6 or newer (found ${version:-none})."
    fi

    systemctl --user show-environment > /dev/null 2>&1 || fail "The systemd user manager isn't reachable. Log in as this user directly, not with su."

    if [ "$(loginctl show-user "$USER" -p Linger --value 2> /dev/null)" != "yes" ]; then
        warn "Lingering is off, so Pods stops when you log out. Turn it on with: sudo loginctl enable-linger $USER"
    fi

    if ! systemctl is-active --quiet sshd 2> /dev/null && ! systemctl is-active --quiet ssh 2> /dev/null; then
        warn "No SSH server seems to run. Pods reaches this host over SSH, so start sshd (or ssh) first."
    fi
}

# lpod from main speaks contract 1, which Pods needs; its latest release
# doesn't yet. An lpod that already speaks contract 1, such as a git
# checkout, is kept as it is.
install_lpod() {
    if command -v lpod > /dev/null 2>&1 && lpod version --json 2> /dev/null | jq -e '.contract == 1' > /dev/null 2>&1; then
        info "lpod is installed ($(lpod version --json | jq -r .lpod))."

        return
    fi

    info "Installing lpod (${LPOD_REF}) in ${BIN_DIR}..."
    mkdir -p "$BIN_DIR"
    # Remove first, so a symlink isn't written through.
    rm -f "${BIN_DIR}/lpod"
    curl -fsSL "${LPOD_RAW}/${LPOD_REF}/bin/lpod" -o "${BIN_DIR}/lpod"
    chmod +x "${BIN_DIR}/lpod"
}

fetch_units() {
    WORK="$(mktemp -d)"

    info "Fetching Pods' units (${PODS_REF})..."
    curl -fsSL "${REPOSITORY}/archive/${PODS_REF}.tar.gz" \
        | tar -xz -C "$WORK" --strip-components=1 --wildcards '*/containers/quadlets/production' '*/containers/proxy'
}

# The app's .env, as the pods-env secret. It's only created once: run with
# --uninstall --purge to start over.
create_env_secret() {
    if podman secret exists pods-env 2> /dev/null; then
        info "The pods-env secret exists; keeping it."

        return
    fi

    local url="http://127.0.0.1:${LOCAL_PORT}"

    if [ "$PROXY" = true ]; then
        url="https://${HOST}"
    fi

    info "Creating the pods-env secret..."

    local file
    file="$(mktemp)"
    chmod 600 "$file"

    cat > "$file" <<EOF
APP_NAME=Pods
APP_ENV=production
APP_KEY=base64:$(head -c 32 /dev/urandom | base64)
APP_DEBUG=false
APP_URL=${url}
LOG_CHANNEL=stderr
DB_CONNECTION=sqlite
DB_DATABASE=/app/storage/app/private/database.sqlite
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=null
SCOUT_DRIVER=database
MAIL_MAILER=log
OCTANE_SERVER=frankenphp
PODS_LOCAL_SSH_HOST=host.containers.internal
PODS_LOCAL_SSH_USER=${USER}
EOF

    podman secret create pods-env "$file" > /dev/null
    rm -f "$file"
}

install_pods() {
    info "Installing Pods' units..."
    podman quadlet install --replace --application=pods "${WORK}/containers/quadlets/production" > /dev/null

    info "Pulling the Pods image..."
    podman pull --quiet "$(sed -n 's/^Image=//p' "${WORK}/containers/quadlets/production/pods.container")" > /dev/null

    info "Starting Pods..."
    systemctl --user daemon-reload
    systemctl --user restart pods.service

    local tries=0
    until systemctl --user is-active --quiet pods.service; do
        tries=$((tries + 1))

        if [ "$tries" -gt 150 ] || systemctl --user is-failed --quiet pods.service; then
            fail "Pods didn't start. See: journalctl --user -u pods -n 50"
        fi

        sleep 2
    done
}

artisan() {
    podman exec -i --user docker systemd-pods php artisan "$@"
}

# Pods' SSH key lives in its storage volume. Its public key goes into this
# user's authorized_keys, so Pods can reach this host as a local environment.
connect_local_host() {
    info "Setting up Pods' SSH key and the local environment..."
    artisan pods:install --no-interaction > /dev/null 2>&1 || true

    local key
    key="$(podman exec --user docker systemd-pods cat /app/storage/app/private/ssh/id_ed25519.pub)"

    mkdir -p "${HOME}/.ssh"
    chmod 700 "${HOME}/.ssh"
    touch "${HOME}/.ssh/authorized_keys"
    chmod 600 "${HOME}/.ssh/authorized_keys"

    if ! grep -qxF "$key" "${HOME}/.ssh/authorized_keys"; then
        echo "$key" >> "${HOME}/.ssh/authorized_keys"
        info "Added Pods' key to ~/.ssh/authorized_keys."
    fi

    artisan pods:install --no-interaction || warn "The local environment isn't connected yet. Fix the problem above, then run: podman exec --user docker systemd-pods php artisan pods:verify"
}

ask() {
    local prompt="$1" secret="${2:-false}" answer=""

    if [ ! -r /dev/tty ]; then
        return 1
    fi

    if [ "$secret" = true ]; then
        read -r -s -p "$prompt" answer < /dev/tty
        echo > /dev/tty
    else
        read -r -p "$prompt" answer < /dev/tty
    fi

    printf '%s' "$answer"
}

create_owner() {
    if artisan users:exists --super-admin > /dev/null 2>&1; then
        info "Pods has an owner."

        return
    fi

    local password="${PODS_OWNER_PASSWORD:-}"

    if [ -z "$OWNER_NAME" ]; then OWNER_NAME="$(ask 'Owner name: ' || true)"; fi
    if [ -z "$OWNER_EMAIL" ]; then OWNER_EMAIL="$(ask 'Owner email: ' || true)"; fi
    if [ -z "$password" ]; then password="$(ask 'Owner password (12 characters or more): ' true || true)"; fi

    if [ -z "$OWNER_NAME" ] || [ -z "$OWNER_EMAIL" ] || [ -z "$password" ]; then
        warn "No owner created. Create one with: podman exec -it --user docker systemd-pods php artisan users:create --super-admin"

        return
    fi

    info "Creating the owner..."
    printf '%s' "$password" | artisan users:create --super-admin --no-interaction --name="$OWNER_NAME" --email="$OWNER_EMAIL" --password-stdin
}

install_proxy() {
    info "Installing the Pods proxy..."

    local units="${WORK}/proxy-units"
    mkdir -p "$units"
    cp "${WORK}/containers/proxy/"*.container "${WORK}/containers/proxy/"*.volume "$units/"
    podman quadlet install --replace --application=pods-proxy "$units" > /dev/null

    mkdir -p "${PROXY_CONFIG}/sites" "${QUADLET_DIR}/pods-proxy.container.d"
    cp "${WORK}/containers/proxy/sites/pods.Caddyfile" "${PROXY_CONFIG}/sites/pods.Caddyfile"

    cat > "${QUADLET_DIR}/pods-proxy.container.d/pods.conf" <<EOF
# Written by Pods' install.sh: lets the proxy reach Pods, and routes
# ${HOST} to it (see ${PROXY_CONFIG}/sites/pods.Caddyfile).
[Container]
Network=pods.network
Environment=PODS_HOST=${HOST}
EOF

    local settings="${QUADLET_DIR}/pods-proxy.container.d/install.conf"
    {
        echo "# Written by Pods' install.sh: the proxy's ports and scheme."
        echo "[Container]"
        echo "PublishPort=${HTTP_PORT}:80"
        echo "PublishPort=${HTTPS_PORT}:443"
        echo "PublishPort=${HTTPS_PORT}:443/udp"

        if [ "$BEHIND_PROXY" = true ]; then
            echo "Environment=PROXY_SCHEME=http"
        fi
    } > "$settings"

    systemctl --user daemon-reload
    systemctl --user restart pods-proxy.service
}

uninstall() {
    info "Removing Pods..."
    systemctl --user stop pods.service pods-schedule.service 2> /dev/null || true
    podman quadlet rm --force --recursive pods.container > /dev/null 2>&1 || true

    rm -f "${QUADLET_DIR}/pods-proxy.container.d/pods.conf" "${PROXY_CONFIG}/sites/pods.Caddyfile"

    if [ "$PROXY" = true ]; then
        info "Removing the Pods proxy..."
        systemctl --user stop pods-proxy.service 2> /dev/null || true
        podman quadlet rm --force --recursive pods-proxy.container > /dev/null 2>&1 || true
        rm -f "${QUADLET_DIR}/pods-proxy.container.d/install.conf"
    elif systemctl --user is-active --quiet pods-proxy.service 2> /dev/null; then
        systemctl --user daemon-reload
        systemctl --user restart pods-proxy.service
    fi

    if [ "$PURGE" = true ]; then
        info "Removing Pods' data and the pods-env secret..."
        podman volume rm pods-data > /dev/null 2>&1 || true
        podman secret rm pods-env > /dev/null 2>&1 || true
    else
        info "Kept Pods' data (the pods-data volume) and the pods-env secret. Add --purge to remove them."
    fi

    systemctl --user daemon-reload
}

main() {
    parse_arguments "$@"
    check_requirements

    if [ "$UNINSTALL" = true ]; then
        uninstall

        return
    fi

    install_lpod
    fetch_units
    create_env_secret
    install_pods
    connect_local_host
    create_owner

    if [ "$PROXY" = true ]; then
        install_proxy
    fi

    echo
    info "Pods is running."

    if [ "$PROXY" = true ]; then
        info "Open https://${HOST} (point ${HOST} at this host first)."
    else
        info "Open http://127.0.0.1:${LOCAL_PORT} on this host, or tunnel to it: ssh -L ${LOCAL_PORT}:127.0.0.1:${LOCAL_PORT} $(hostname)"
    fi
}

main "$@"
