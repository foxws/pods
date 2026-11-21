#!/usr/bin/env bash
# Installs or updates Pods on this host, as rootless Podman Quadlets for the
# current user:
#
#   curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.10/install.sh | bash
#
# With a Caddy proxy in front, routing a hostname to Pods:
#
#   curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.10/install.sh | bash -s -- --proxy --host pods.example.com
#
# Or only prepare this host for a Pods that runs elsewhere, or as another
# user (lpod and Pods' SSH key, no Pods):
#
#   curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.10/install.sh | bash -s -- --no-pods --authorized-key 'ssh-ed25519 AAAA... pods'
#
# To update, run pods-update, which this installs in ~/.local/bin: it runs
# the newest release's install.sh, which keeps this install's setup.
# "--help" lists every option.
set -euo pipefail

# The release this install.sh belongs to, and the lpod release it needs.
# Set PODS_REF=main and LPOD_REF=main to follow development instead.
PODS_REF="${PODS_REF:-v1.0.0-rc.10}"
LPOD_REF="${LPOD_REF:-v4.0.0-rc.7}"

REPOSITORY="https://github.com/foxws/pods"
LPOD_REPOSITORY="https://github.com/foxws/lpod"
LPOD_RAW="https://raw.githubusercontent.com/foxws/lpod"
CONFIG_HOME="${XDG_CONFIG_HOME:-$HOME/.config}"
QUADLET_DIR="${CONFIG_HOME}/containers/systemd"
PROXY_CONFIG="${CONFIG_HOME}/pods-proxy"
BIN_DIR="${HOME}/.local/bin"
DEFAULT_PORT=9393
LOCAL_PORT=""
LPOD="${BIN_DIR}/lpod"

IDLE=""
IDLE_LISTEN=127.0.0.1
PODS=true
AUTHORIZED_KEY=""
PROXY=false
HOST=""
KEEP_ROUTES=false
HTTP_PORT=80
HTTPS_PORT=443
BEHIND_PROXY=false
UNINSTALL=false
PURGE=false
OWNER_NAME="${PODS_OWNER_NAME:-}"
OWNER_EMAIL="${PODS_OWNER_EMAIL:-}"
SETUP_CODE=""

WORK=""

usage() {
    cat <<'EOF'
Installs or updates Pods as rootless Podman Quadlets for the current user.

Usage: install.sh [options]

  --no-pods             Only prepare this host for a Pods that runs elsewhere, or as another
                        user: check the requirements and install lpod. Install the proxy
                        from that Pods, in the host's settings
  --authorized-key KEY  Add this SSH public key to ~/.ssh/authorized_keys, such as Pods'
                        (Hosts, Copy public key), so that Pods can reach this user
  --proxy               Also install the Pods proxy (Caddy), and route --host to Pods
  --host NAME           The hostname Pods is reached at through the proxy, e.g. pods.example.com
  --proxy-ports H,S     The proxy's host ports for HTTP and HTTPS (default 80,443),
                        e.g. 28080,28443 on a host shared with other users
  --behind-proxy        Another proxy in front handles HTTPS: the Pods proxy serves plain HTTP
  --port PORT           The port Pods listens on, on 127.0.0.1 (default 9393, or the one an
                        existing install has). Behind the proxy, its socket listens on every
                        address
  --no-idle             Keep Pods running all the time, instead of starting it on the first
                        request and stopping it after 10 minutes without one
  --idle                Let Pods sleep again after an install with --no-idle
  --name NAME           With --email and PODS_OWNER_PASSWORD, create the owner here
  --email EMAIL         instead of on Pods' setup page
  --uninstall           Remove Pods (and with --proxy, the proxy). Keeps its data
  --purge               With --uninstall, also remove Pods' data and its pods-env secret
  -h, --help            Show this help

Environment: PODS_REF (default v1.0.0-rc.10) and LPOD_REF (default v4.0.0-rc.7) pick the
versions to install. PODS_OWNER_PASSWORD is the owner's password, with --name
and --email, for unattended installs.

Running this again keeps the install's setup: its port, whether Pods sleeps,
and the proxy with Pods' route as set in Pods. Options given again change it.
To update, run pods-update (in ~/.local/bin), which runs the newest release's
install.sh with the options you give it.

Pods listens on 127.0.0.1:9393 (or --port) only. Reach it on this machine,
or through an SSH tunnel: ssh -L 9393:127.0.0.1:9393 HOST
Like cockpit.socket, a socket listens there and starts Pods on the first
request, and Pods stops again when nobody uses it (unless --no-idle). With
a route through the proxy (--proxy, or one set in Pods), the socket listens
on every address instead, since the proxy container can't reach 127.0.0.1:
block the port in the host's firewall to keep Pods off your network.

lpod is installed (or upgraded, when it's older) at LPOD_REF. A git checkout
of lpod, or a build of a branch (lpod self-update main), is kept.
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
            --port) LOCAL_PORT="${2:-}"; shift ;;
            --no-idle) IDLE=false ;;
            --idle) IDLE=true ;;
            --no-pods) PODS=false ;;
            --authorized-key) AUTHORIZED_KEY="${2:-}"; shift ;;
            --name) OWNER_NAME="${2:-}"; shift ;;
            --email) OWNER_EMAIL="${2:-}"; shift ;;
            --uninstall) UNINSTALL=true ;;
            --purge) PURGE=true ;;
            -h|--help) usage; exit 0 ;;
            *) usage >&2; fail "Unknown option: $1" ;;
        esac

        shift
    done

    if [ "$PODS" = false ]; then
        if [ "$PROXY" = true ] || [ "$UNINSTALL" = true ] || [ -n "$HOST" ] || [ -n "$OWNER_NAME" ] || [ -n "$OWNER_EMAIL" ]; then
            fail "--no-pods only installs lpod (and --authorized-key). Install the proxy from Pods, in the host's settings."
        fi
    fi

    # One line of a known key type, so nothing else ends up in authorized_keys.
    if [ -n "$AUTHORIZED_KEY" ] && ! [[ "$AUTHORIZED_KEY" =~ ^(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp(256|384|521)|sk-ssh-ed25519@openssh\.com|sk-ecdsa-sha2-nistp256@openssh\.com)\ [A-Za-z0-9+/]+={0,3}(\ [^[:cntrl:]]*)?$ ]]; then
        fail "--authorized-key takes one SSH public key, e.g. 'ssh-ed25519 AAAA... pods'"
    fi

    if [ "$PROXY" = true ] && [ "$UNINSTALL" = false ] && ! [[ "$HOST" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]]; then
        fail "--proxy needs --host with the hostname Pods is reached at, e.g. --host pods.example.com"
    fi

    # The on-demand socket gives Pods this port + 10000.
    if [ -n "$LOCAL_PORT" ] && { ! [[ "$LOCAL_PORT" =~ ^[1-9][0-9]{0,4}$ ]] || [ "$LOCAL_PORT" -lt 1024 ] || [ "$LOCAL_PORT" -gt 55535 ]; }; then
        fail "--port takes a port number from 1024 to 55535, e.g. --port 9393"
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
        fail "Run this as the user that should own Pods (or with --no-pods, its applications), not as root."
    fi

    local command
    for command in podman systemctl curl jq; do
        command -v "$command" > /dev/null 2>&1 || fail "$command is required. Install it first."
    done

    local version
    version="$(podman version --format '{{.Client.Version}}' 2> /dev/null || true)"

    if [ "${version%%.*}" -lt 6 ] 2> /dev/null || [ -z "$version" ]; then
        fail "Pods needs Podman 6 or newer (found ${version:-none})."
    fi

    if [ "$PODS" = true ]; then
        command -v tar > /dev/null 2>&1 || fail "tar is required. Install it first."
        has_container_init || fail "Podman's container init (catatonit) is required: Pods' units use RunInit=true. Install the catatonit package first."
    fi

    systemctl --user show-environment > /dev/null 2>&1 || fail "The systemd user manager isn't reachable. Log in as this user directly, not with su."

    if [ "$(loginctl show-user "$USER" -p Linger --value 2> /dev/null)" != "yes" ]; then
        warn "Lingering is off, so your units stop when you log out. Turn it on with: sudo loginctl enable-linger $USER"
    fi

    if ! systemctl is-active --quiet sshd 2> /dev/null && ! systemctl is-active --quiet ssh 2> /dev/null; then
        warn "No SSH server seems to run. Pods reaches this host over SSH, so start sshd (or ssh) first."
    fi
}

# Whether Podman finds catatonit for RunInit=true, in the helper
# directories it searches or on the PATH.
has_container_init() {
    local path

    for path in /usr/local/libexec/podman /usr/local/lib/podman /usr/libexec/podman /usr/lib/podman; do
        if [ -x "${path}/catatonit" ]; then
            return 0
        fi
    done

    command -v catatonit > /dev/null 2>&1
}

# Whether release A (such as 4.0.0-rc.1) is older than release B, where a
# pre-release comes before the release itself.
version_lt() {
    local a="${1#v}" b="${2#v}"
    local a_base="${a%%-*}" b_base="${b%%-*}"

    if [ "$a" = "$b" ]; then
        return 1
    fi

    if [ "$a_base" != "$b_base" ]; then
        [ "$(printf '%s\n%s\n' "$a_base" "$b_base" | sort -V | head -n 1)" = "$a_base" ]

        return
    fi

    # The same release: 4.0.0-rc.2 comes before 4.0.0.
    if [ "$a" = "$a_base" ]; then
        return 1
    fi

    if [ "$b" = "$b_base" ]; then
        return 0
    fi

    [ "$(printf '%s\n%s\n' "${a#*-}" "${b#*-}" | sort -V | head -n 1)" = "${a#*-}" ]
}

is_release() {
    [[ "${1#v}" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]
}

# Installs lpod at LPOD_REF with lpod's own installer, which verifies a
# release against its checksums and bakes its version in (a raw bin/lpod
# reports "dev"). A branch, such as main, comes from that branch.
fetch_lpod() {
    local installer
    installer="$(mktemp)"

    if is_release "$LPOD_REF"; then
        curl -fsSL "${LPOD_REPOSITORY}/releases/download/v${LPOD_REF#v}/install.sh" -o "$installer" \
            || { rm -f "$installer"; fail "lpod ${LPOD_REF} isn't released. Set LPOD_REF to a release or a branch."; }
        LPOD_INSTALL_DIR="$BIN_DIR" LPOD_VERSION="$LPOD_REF" bash "$installer" < /dev/null > /dev/null
    else
        curl -fsSL "${LPOD_RAW}/${LPOD_REF}/install.sh" -o "$installer" \
            || { rm -f "$installer"; fail "lpod has no branch ${LPOD_REF}. Set LPOD_REF to a release or a branch."; }
        LPOD_INSTALL_DIR="$BIN_DIR" LPOD_BRANCH="$LPOD_REF" bash "$installer" < /dev/null > /dev/null
    fi

    rm -f "$installer"
    LPOD="${BIN_DIR}/lpod"
}

# Pods needs an lpod that speaks contract 1, which lpod 4 does, at LPOD_REF
# or newer. An older release, or a plain copy that reports "dev" (as earlier
# install.sh versions installed it), is upgraded. A git checkout, or a build
# of a branch ("dev-main"), is kept as it is.
install_lpod() {
    local installed=""

    if command -v lpod > /dev/null 2>&1 && lpod version --json 2> /dev/null | jq -e '.contract == 1' > /dev/null 2>&1; then
        LPOD="$(command -v lpod)"
        installed="$("$LPOD" version --json | jq -r .lpod)"
    fi

    if [ -z "$installed" ]; then
        info "Installing lpod (${LPOD_REF}) in ${BIN_DIR}..."
        fetch_lpod

        return
    fi

    if [ "$installed" = "dev" ] && git -C "$(dirname "$(readlink -f "$LPOD")")" rev-parse --is-inside-work-tree > /dev/null 2>&1; then
        info "lpod is a git checkout ($(dirname "$(readlink -f "$LPOD")")); keeping it."

        return
    fi

    if [ "$installed" != "dev" ] && { ! is_release "$installed" || ! is_release "$LPOD_REF" || ! version_lt "$installed" "$LPOD_REF"; }; then
        info "lpod is installed (${installed})."

        return
    fi

    if [ "$LPOD" != "${BIN_DIR}/lpod" ]; then
        warn "lpod ${installed} in ${LPOD} is older than ${LPOD_REF}. Installing ${LPOD_REF} in ${BIN_DIR}; make sure it comes first on your PATH."
    fi

    info "Updating lpod (${installed}) to ${LPOD_REF}..."
    fetch_lpod
}

fetch_units() {
    WORK="$(mktemp -d)"

    info "Fetching Pods' units (${PODS_REF})..."
    curl -fsSL "${REPOSITORY}/archive/${PODS_REF}.tar.gz" \
        | tar -xz -C "$WORK" --strip-components=1 --wildcards '*/containers/quadlets/production' '*/containers/proxy'
}

# Reverb, which tells open pages when Pods changes a host. Pods reaches it
# on pods.network, and forwards browsers to it on its own address
# (OCTANE_REVERB_UPSTREAM), so VITE_REVERB_HOST stays empty.
reverb_env() {
    cat <<EOF
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=pods
REVERB_APP_KEY=$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')
REVERB_APP_SECRET=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
REVERB_HOST=systemd-pods-reverb
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_HOST=
OCTANE_REVERB_UPSTREAM=systemd-pods-reverb:8080
EOF
}

# A pods-env secret from before Reverb gets its settings once, in place of
# BROADCAST_CONNECTION=null; the rest of it stays as it is.
add_reverb_env() {
    local current
    current="$(podman secret inspect --showsecret --format '{{.SecretData}}' pods-env)"

    if grep -q '^REVERB_APP_KEY=' <<< "$current"; then
        return
    fi

    info "Adding Reverb to the pods-env secret..."

    local file
    file="$(mktemp)"
    chmod 600 "$file"

    {
        grep -v '^BROADCAST_CONNECTION=' <<< "$current" || true
        reverb_env
    } > "$file"

    podman secret create --replace pods-env "$file" > /dev/null
    rm -f "$file"
}

# A pods-env secret from before the queue worker ran its jobs in the request
# (QUEUE_CONNECTION=sync); pods-queue runs them now, such as volume exports.
use_queue_worker() {
    local current
    current="$(podman secret inspect --showsecret --format '{{.SecretData}}' pods-env)"

    if ! grep -q '^QUEUE_CONNECTION=sync$' <<< "$current"; then
        return
    fi

    info "Letting pods-queue run Pods' jobs..."

    local file
    file="$(mktemp)"
    chmod 600 "$file"

    {
        grep -v '^QUEUE_CONNECTION=' <<< "$current" || true
        echo "QUEUE_CONNECTION=database"
    } > "$file"

    podman secret create --replace pods-env "$file" > /dev/null
    rm -f "$file"
}

# The app's .env, as the pods-env secret. It's only created once: run with
# --uninstall --purge to start over.
create_env_secret() {
    if podman secret exists pods-env 2> /dev/null; then
        info "The pods-env secret exists; keeping it."
        add_reverb_env
        use_queue_worker

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
QUEUE_CONNECTION=database
SCOUT_DRIVER=database
MAIL_MAILER=log
OCTANE_SERVER=frankenphp
PODS_LOCAL_SSH_HOST=host.containers.internal
PODS_LOCAL_SSH_USER=${USER}
EOF
    reverb_env >> "$file"

    podman secret create pods-env "$file" > /dev/null
    rm -f "$file"
}

install_pods() {
    info "Installing Pods' units..."
    podman quadlet install --replace --application=pods "${WORK}/containers/quadlets/production" > /dev/null

    info "Pulling the Pods image..."
    podman pull --quiet "$(sed -n 's/^Image=//p' "${WORK}/containers/quadlets/production/pods.container")" > /dev/null

    publish_port

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

# The port an earlier install publishes Pods on: the on-demand socket's,
# else the last PublishPort= of its unit and drop-ins. Run it before the
# install replaces the unit.
installed_port() {
    local status unit_path

    status="$("$LPOD" pods ondemand status --json 2> /dev/null || true)"

    if jq -e '.ondemand.enabled' > /dev/null 2>&1 <<< "$status"; then
        jq -r '.ondemand.port' <<< "$status"

        return
    fi

    unit_path="$(podman quadlet list --format json 2> /dev/null | jq -r 'first(.[] | select(.Name == "pods.container") | .Path) // empty' 2> /dev/null || true)"

    if [ -z "$unit_path" ] || [ ! -f "$unit_path" ]; then
        return
    fi

    # Without drop-ins the glob stays as it is, and cat fails on it, which
    # would end install.sh under pipefail; so only existing files are read.
    local file

    for file in "$unit_path" "${unit_path}.d"/*.conf; do
        if [ -f "$file" ]; then
            cat "$file"
        fi
    done | sed -n 's/^PublishPort=127\.0\.0\.1:\([0-9]*\):8000$/\1/p' | tail -n 1
}

# The unit publishes the default port; another one goes in a drop-in, which
# resets PublishPort= first. lpod's on-demand drop-in sorts after it.
publish_port() {
    local unit_path dropin
    unit_path="$(podman quadlet list --format json | jq -r 'first(.[] | select(.Name == "pods.container") | .Path) // empty')"
    dropin="${unit_path}.d/install-port.conf"

    if [ "$LOCAL_PORT" = "$DEFAULT_PORT" ]; then
        rm -f "$dropin"
        rmdir "${unit_path}.d" 2> /dev/null || true

        return
    fi

    mkdir -p "${unit_path}.d"
    printf '# Written by install.sh --port.\n[Container]\nPublishPort=\nPublishPort=127.0.0.1:%s:8000\n' "$LOCAL_PORT" > "$dropin"
}

# Whether Pods has a route through the proxy, so the proxy has to reach
# its socket.
has_routes() {
    "$LPOD" pods routes --json 2> /dev/null | jq -e '.routes | length > 0' > /dev/null 2>&1
}

# Whether an earlier install.sh installed Pods for this user.
is_installed() {
    podman quadlet list --format json 2> /dev/null | jq -e 'any(.[]; .Name == "pods.container")' > /dev/null 2>&1
}

# Whether the Pods proxy is installed for this user, by install.sh or Pods.
has_proxy() {
    podman quadlet list --format json 2> /dev/null | jq -e 'any(.[]; .Name == "pods-proxy.container")' > /dev/null 2>&1
}

# The hostname of Pods' main route through the proxy, if it has one.
routed_host() {
    "$LPOD" pods routes --json 2> /dev/null | jq -r 'first(.routes[] | select(.main) | .host) // empty' 2> /dev/null || true
}

# Running this again keeps what the earlier install set up, unless the
# options say otherwise: Pods sleeps when it did, and an installed proxy is
# updated with the route Pods has, which may have been edited in Pods since.
# Run it before disable_idle.
keep_setup() {
    if ! is_installed; then
        IDLE="${IDLE:-true}"

        return
    fi

    if [ -z "$IDLE" ]; then
        if is_idle; then
            IDLE=true
        else
            IDLE=false
        fi
    fi

    if [ "$PROXY" = false ] && has_proxy; then
        HOST="$(routed_host)"

        if [ -n "$HOST" ]; then
            PROXY=true
            KEEP_ROUTES=true
        fi
    fi
}

# Whether Pods runs on demand now, from an earlier install.
is_idle() {
    "$LPOD" pods ondemand status --json 2> /dev/null | jq -e '.ondemand.enabled' > /dev/null 2>&1
}

# An on-demand Pods only starts through its socket, so run it normally while
# installing, and let enable_idle put the socket back at the end.
disable_idle() {
    if is_idle; then
        info "Running Pods normally while it's updated..."
        "$LPOD" pods ondemand disable --json > /dev/null
    fi
}

# Like cockpit.socket: lpod's socket listens on 127.0.0.1:PORT and starts
# Pods on the first request, and Pods (published on 127.0.0.1:PORT+10000 then)
# stops after 10 minutes without one. The scheduler only runs at 01:00 UTC,
# when Pods' daily housekeeping is due (routes/console.php). The queue worker
# keeps running while Pods sleeps, so an export finishes, and lpod's idle
# check stops it once the queue is empty.
#
# Behind the proxy, the socket listens on every address: the proxy container
# reaches the host as host.containers.internal, which isn't 127.0.0.1, and
# lpod points Pods' route there, so a request through the proxy wakes Pods.
# A unix socket doesn't work instead, since SELinux doesn't let a container
# connect to a socket the user's systemd holds, and a user's socket units
# can't limit who connects (IPAddressAllow= needs a system unit).
enable_idle() {
    info "Letting Pods sleep when nobody uses it..."

    if ! "$LPOD" pods ondemand enable --json \
        ${IDLE_LISTEN:+--listen "$IDLE_LISTEN"} --port "$LOCAL_PORT" --app-port 8000 \
        --worker pods-queue --idle-container pods-queue --idle-user docker \
        --idle-command "php -d variables_order=EGPCS /app/artisan pods:queue-idle" \
        --schedule pods-schedule --schedule-at 01:00 \
        --schedule-env "APP_COMMAND=php -d variables_order=EGPCS /app/artisan schedule:run" > /dev/null; then
        warn "Pods runs all the time instead. Update lpod (lpod self-update) and run this again, or pass --no-idle."
        IDLE=false

        return
    fi

    if [ -z "$IDLE_LISTEN" ]; then
        warn "So the proxy can wake Pods, its socket listens on port ${LOCAL_PORT} on every address. Block that port in the host's firewall to keep Pods off your network, or pass --no-idle."
    fi
}

# pods-update runs the newest release's install.sh (or PODS_REF's), so an
# update needs no options or version: install.sh keeps the setup it finds.
install_update_command() {
    mkdir -p "$BIN_DIR"

    cat > "${BIN_DIR}/pods-update" <<'EOF'
#!/usr/bin/env bash
# Written by Pods' install.sh. Updates Pods to its newest release (or to
# PODS_REF), keeping this install's setup. Options go to install.sh, such as
# pods-update --no-idle; "pods-update --help" lists them.
set -euo pipefail

ref="${PODS_REF:-}"

if [ -z "$ref" ]; then
    ref="$(curl -fsSL 'https://api.github.com/repos/foxws/pods/releases?per_page=1' | jq -r '.[0].tag_name // empty')"
fi

if [ -z "$ref" ]; then
    echo "error: Pods' newest release wasn't found. Name one, e.g. PODS_REF=v1.0.0 pods-update" >&2
    exit 1
fi

script="$(mktemp)"
trap 'rm -f "$script"' EXIT

curl -fsSL "https://raw.githubusercontent.com/foxws/pods/${ref}/install.sh" -o "$script" 2> /dev/null \
    || { echo "error: Pods has no release or branch ${ref}." >&2; exit 1; }
PODS_REF="$ref" bash "$script" "$@"
EOF

    chmod 755 "${BIN_DIR}/pods-update"
}

artisan() {
    podman exec -i --user docker systemd-pods php artisan "$@"
}

# Adds a public key to this user's authorized_keys, once.
authorize_key() {
    local key="$1"

    mkdir -p "${HOME}/.ssh"
    chmod 700 "${HOME}/.ssh"
    touch "${HOME}/.ssh/authorized_keys"
    chmod 600 "${HOME}/.ssh/authorized_keys"

    if ! grep -qxF "$key" "${HOME}/.ssh/authorized_keys"; then
        # Start on a new line, or the key would be glued onto the last one.
        if [ -s "${HOME}/.ssh/authorized_keys" ] && [ -n "$(tail -c 1 "${HOME}/.ssh/authorized_keys")" ]; then
            echo >> "${HOME}/.ssh/authorized_keys"
        fi

        echo "$key" >> "${HOME}/.ssh/authorized_keys"
        info "Added the key to ~/.ssh/authorized_keys."
    else
        info "The key is in ~/.ssh/authorized_keys."
    fi
}

# Pods' SSH key lives in its storage volume. Its public key goes into this
# user's authorized_keys, so Pods can reach this host as a local environment.
connect_local_host() {
    info "Setting up Pods' SSH key and the local environment..."
    artisan pods:install --no-interaction > /dev/null 2>&1 || true

    authorize_key "$(podman exec --user docker systemd-pods cat /app/storage/app/private/ssh/id_ed25519.pub)"

    artisan pods:install --no-interaction || warn "The local environment isn't connected yet. Fix the problem above, then run: podman exec --user docker systemd-pods php artisan pods:verify"
}

# Without --name, --email and PODS_OWNER_PASSWORD, the owner is created on
# Pods' setup page, with the one-time code it asks for.
create_owner() {
    if artisan users:exists --super-admin > /dev/null 2>&1; then
        info "Pods has an owner."

        return
    fi

    if [ -z "$OWNER_NAME" ] || [ -z "$OWNER_EMAIL" ] || [ -z "${PODS_OWNER_PASSWORD:-}" ]; then
        SETUP_CODE="$(artisan pods:setup --code --no-interaction | tr -d '[:space:]')"

        return
    fi

    info "Creating the owner..."
    printf '%s' "$PODS_OWNER_PASSWORD" | artisan users:create --super-admin --no-interaction --name="$OWNER_NAME" --email="$OWNER_EMAIL" --password-stdin
}

install_proxy() {
    info "Installing the Pods proxy..."

    local units="${WORK}/proxy-units"
    mkdir -p "$units"
    cp "${WORK}/containers/proxy/"*.container "${WORK}/containers/proxy/"*.volume "$units/"
    podman quadlet install --replace --application=pods-proxy "$units" > /dev/null

    mkdir -p "${PROXY_CONFIG}/sites" "${QUADLET_DIR}/pods-proxy.container.d"

    # The proxy's ports and certificates. lpod writes them the first time;
    # after that they're changed in the Proxy section of the host's settings
    # in Pods, so running this again keeps them. A proxy installed by an older
    # install.sh keeps its install.conf until those settings are saved...
    if [ ! -f "${PROXY_CONFIG}/Caddyfile" ]; then
        local mode=letsencrypt

        if [ "$BEHIND_PROXY" = true ]; then
            mode=http
        fi

        jq -n --arg mode "$mode" --argjson http "$HTTP_PORT" --argjson https "$HTTPS_PORT" \
            '{mode: $mode, http_port: $http, https_port: $https}' | "$LPOD" proxy settings set --stdin --json > /dev/null
    elif [ ! -f "${QUADLET_DIR}/pods-proxy.container.d/lpod-proxy.conf" ]; then
        {
            echo "# Written by Pods' install.sh: the proxy's ports and scheme."
            echo "[Container]"
            echo "PublishPort=${HTTP_PORT}:80"
            echo "PublishPort=${HTTPS_PORT}:443"
            echo "PublishPort=${HTTPS_PORT}:443/udp"

            if [ "$BEHIND_PROXY" = true ]; then
                echo "Environment=PROXY_SCHEME=http"
            fi
        } > "${QUADLET_DIR}/pods-proxy.container.d/install.conf"
    fi

    if [ "$KEEP_ROUTES" = false ]; then
        route_pods
    fi

    systemctl --user daemon-reload
    systemctl --user restart pods-proxy.service
}

# Pods' own hostname is a route of the application pods, like any other's:
# lpod writes it into the proxy's sites and joins the proxy to pods.network,
# and it's edited on the pods application's page in Pods from then on. An
# older install.sh wrote sites/pods.Caddyfile and a pods.conf drop-in instead.
route_pods() {
    rm -f "${QUADLET_DIR}/pods-proxy.container.d/pods.conf" "${PROXY_CONFIG}/sites/pods.Caddyfile"

    if ! jq -n --arg host "$HOST" \
        '{routes: [{role: "app", host: $host, upstream: "systemd-pods:8000", main: true}]}' \
        | "$LPOD" pods routes set --stdin --json > /dev/null; then
        warn "lpod didn't route ${HOST} to Pods. Set the route on the pods application's page in Pods."
    fi
}

uninstall() {
    info "Removing Pods..."

    if command -v lpod > /dev/null 2>&1; then
        LPOD="$(command -v lpod)"
    fi

    if [ -x "$LPOD" ] && is_idle; then
        "$LPOD" pods ondemand disable --json > /dev/null 2>&1 || true
    fi

    systemctl --user stop pods.service pods-schedule.service pods-reverb.service pods-queue.service 2> /dev/null || true

    local unit_path
    unit_path="$(podman quadlet list --format json 2> /dev/null | jq -r 'first(.[] | select(.Name == "pods.container") | .Path) // empty' 2> /dev/null || true)"

    if [ -n "$unit_path" ]; then
        rm -f "${unit_path}.d/install-port.conf"
    fi

    podman quadlet rm --force --recursive pods.container > /dev/null 2>&1 || true

    if [ -x "$LPOD" ]; then
        "$LPOD" pods routes remove --json > /dev/null 2>&1 || true
    fi

    rm -f "${QUADLET_DIR}/pods-proxy.container.d/pods.conf" "${PROXY_CONFIG}/sites/pods.Caddyfile"

    if [ "$PROXY" = true ]; then
        info "Removing the Pods proxy..."
        systemctl --user stop pods-proxy.service 2> /dev/null || true
        podman quadlet rm --force --recursive pods-proxy.container > /dev/null 2>&1 || true
        rm -f "${QUADLET_DIR}/pods-proxy.container.d/install.conf" "${QUADLET_DIR}/pods-proxy.container.d/lpod-proxy.conf"
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

    rm -f "${BIN_DIR}/pods-update"

    systemctl --user daemon-reload
}

# With --no-pods: lpod and the key only, for a Pods elsewhere to manage this
# user. A Pods on this machine as another user reaches it as
# host.containers.internal; one elsewhere by this host's name.
prepare_host() {
    install_lpod

    if [ -n "$AUTHORIZED_KEY" ]; then
        authorize_key "$AUTHORIZED_KEY"
    fi

    echo
    info "This host is ready for Pods. In Pods, use Hosts, Add environment with:"
    echo
    echo "    User: ${USER}"
    echo "    Host: host.containers.internal (Pods on this machine, as another user), or $(hostname) (Pods elsewhere)"
    echo

    if [ -z "$AUTHORIZED_KEY" ]; then
        info "First add Pods' public key (Hosts, Copy public key) to ~/.ssh/authorized_keys, or run this again with --authorized-key."
    fi
}

main() {
    parse_arguments "$@"
    check_requirements

    if [ "$UNINSTALL" = true ]; then
        uninstall

        return
    fi

    if [ "$PODS" = false ]; then
        prepare_host

        return
    fi

    install_lpod

    if [ -n "$AUTHORIZED_KEY" ]; then
        authorize_key "$AUTHORIZED_KEY"
    fi

    if [ -z "$LOCAL_PORT" ]; then
        LOCAL_PORT="$(installed_port)"
        LOCAL_PORT="${LOCAL_PORT:-$DEFAULT_PORT}"
    elif [ -n "$(installed_port)" ] && [ "$(installed_port)" != "$LOCAL_PORT" ]; then
        warn "Pods moves from port $(installed_port) to ${LOCAL_PORT}. The APP_URL in the pods-env secret still has the old one."
    fi

    keep_setup

    # A route through the proxy, from --proxy now or earlier, or set in Pods,
    # needs a socket the proxy container can reach.
    if [ "$PROXY" = true ] || has_routes; then
        IDLE_LISTEN=""
    fi

    disable_idle
    fetch_units
    create_env_secret
    install_pods
    connect_local_host
    create_owner

    if [ "$PROXY" = true ]; then
        install_proxy
    fi

    if [ "$IDLE" = true ]; then
        enable_idle
    fi

    install_update_command

    local url="http://127.0.0.1:${LOCAL_PORT}"

    if [ "$PROXY" = true ]; then
        url="https://${HOST}"
    fi

    echo
    if [ "$IDLE" = true ]; then
        info "Pods starts on the first visit to ${url}, and sleeps after 10 minutes without one."
    else
        info "Pods is running at ${url}"
    fi

    if [ "$PROXY" = true ]; then
        info "Point ${HOST} at this host first."
    elif [ "$IDLE" = false ] || [ -n "$IDLE_LISTEN" ]; then
        info "It only listens on this host. From elsewhere, tunnel to it: ssh -L ${LOCAL_PORT}:127.0.0.1:${LOCAL_PORT} $(hostname)"
    fi

    if [ -n "$SETUP_CODE" ]; then
        echo
        info "Create the owner at ${url}/setup with this setup code:"
        echo
        echo "    ${SETUP_CODE}"
        echo
    fi

    info "To update Pods later, run: pods-update"
}

main "$@"
