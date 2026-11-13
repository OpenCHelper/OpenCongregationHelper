#!/usr/bin/env bash
# Convert the paths of a `.git-ftp-ignore` file into the `exclude` globs of SamKirkland/FTP-Deploy-Action.
#
# In git-ftp's format (one shell glob per line, matched against the whole path, `#` comments), `*` also matches `/`;
# in FTP-Deploy's globs it does not: a `*` that is a whole path segment (e.g. `scripts/*`) becomes `**`.
#
# Usage: git_ftp_ignore_globs.sh <file> app|vendor
#   app     the paths outside of `vendor/`
#   vendor  the paths inside `vendor/`, relative to it (for a deployment of the `vendor/` folder on its own)

set -o errexit
set -o nounset
set -o pipefail

file="${1:?The .git-ftp-ignore file is required}"
scope="${2:?The scope is required: app or vendor}"

if [[ "$scope" != "app" ]] && [[ "$scope" != "vendor" ]]; then
    printf "Unknown scope '%s': app or vendor\n" "$scope" >&2
    exit 1
fi

grep -v '^#' "$file" | tr -d '\r' | awk 'NF' | while IFS= read -r pattern; do
    IFS='/' read -r -a segments <<< "$pattern"

    for index in "${!segments[@]}"; do
        if [[ "${segments[$index]}" == '*' ]]; then
            segments[index]='**'
        fi
    done

    glob="$(IFS=/; printf '%s' "${segments[*]}")"

    if [[ "$scope" == "vendor" ]] && [[ "$glob" == vendor/* ]]; then
        printf '%s\n' "${glob#vendor/}"
    elif [[ "$scope" == "app" ]] && [[ "$glob" != vendor/* ]]; then
        printf '%s\n' "$glob"
    fi
done
