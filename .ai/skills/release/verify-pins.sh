#!/usr/bin/env bash
# Checks that everything a release pins names the same version, in the working
# tree or at a commit:
#
#   verify-pins.sh v1.3.0 [commit]
set -euo pipefail

version="${1:?usage: verify-pins.sh VERSION [COMMIT]}"
commit="${2:-}"
failed=0

[[ "$version" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ ]] || { echo "error: ${version} isn't a version such as v1.3.0" >&2; exit 2; }

cd "$(git rev-parse --show-toplevel)"
[ -z "$commit" ] || git rev-parse --quiet --verify "${commit}^{commit}" > /dev/null || { echo "error: no commit ${commit}" >&2; exit 2; }

contents() {
    if [ -n "$commit" ]; then git show "${commit}:$1"; else cat "$1"; fi
}

# expect FILE EXPECTED PATTERN: every match of PATTERN's \K part is EXPECTED.
expect() {
    local found

    found="$(contents "$1" | grep -oP "$3" | sort -u || true)"

    if [ "$found" != "$2" ]; then
        echo "FAIL $1: expected $2, found ${found:-nothing} ($3)"
        failed=1
    fi
}

url='foxws/pods/\Kv[0-9][^/]*(?=/install\.sh)'

for file in README.md docs/installation.md install.sh; do
    expect "$file" "$version" "$url"
done

expect install.sh "$version" '^PODS_REF="\$\{PODS_REF:-\K[^}]+'
expect install.sh "$version" 'PODS_REF \(default \K[^)]+'
expect install.sh "$version" 'PODS_REF=\Kv\S+(?= pods-update)'
expect config/catalog.php "$version" "'reference' => '\Kv[^']+"

for unit in pods pods-queue pods-reverb pods-schedule; do
    expect "containers/quadlets/production/${unit}.container" "${version#v}" '^Image=ghcr\.io/foxws/pods:\K\S+'
done

lpod="$(contents install.sh | grep -oP '^LPOD_REF="\$\{LPOD_REF:-\K[^}]+')"
expect install.sh "$lpod" 'LPOD_REF \(default \K[^)]+'
expect config/services.php "$lpod" "env\('PODS_LPOD_VERSION', '\K[^']+"

[ "$failed" -eq 0 ] || exit 1

echo "Every pin names ${version}, with lpod ${lpod}."
