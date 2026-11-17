#!/bin/env bash
# **********************************************************************************
#                                                                                  *
# Description : Client of the `ftp-archive-deploy` action: deploys releases to a   *
#               space reachable through FTP, using the PHP deployer of `server/`   *
#               (installed in `_deployer/<version>/` of the space) to unpack them  *
#               on the server.                                                     *
#                                                                                  *
# **********************************************************************************
#
# Usage: deploy.sh [options] <action> [arguments]
#
# Targets: `pr-<N>` (the preview of PR #N, in `previews/pr-<N>`) or the folder of an environment (e.g. `test`).
#
# Actions:
#   version                             Version of the bundled deployer (its folder in `_deployer/`)
#   render-router <dir>                 Write the router of a space hosting several environments (`.htaccess`, and
#                                       `previews/.htaccess` with the page of the missing previews) into a local folder
#                                       (no FTP involved)
#   install-router                      Upload the router to the root of the space (never deletes anything), unless the
#                                       installed one is already up to date
#   create-archive <file>               Create the release archive of the current folder (no FTP involved)
#   deploy <target> <archive>           Upload the archive and replace the live release of the target with it,
#                                       keeping the `--keep` paths of the live release (e.g. `.env`) and reusing
#                                       the `--reuse` folders
#   hash <target> <path>                SHA-256 of a file of the live release (null when missing), e.g. to know
#                                       whether its `vendor/` can be reused
#   info                                Details of the server (PHP limits, ZipArchive, PDO drivers)
#   list                                Live previews and leftovers of their interrupted deployments
#   extract <target>                    Unpack the uploaded archive of the target into `<target>.next`
#   activate <target>                   Replace the live release with `<target>.next` (and `--keep`/`--reuse` paths)
#   deactivate <target>                 Take a live preview offline (moved to `<target>.old`)
#   delete <target> <variant>           Delete `<target>.<variant>` (next, old or zip)
#   reset-database <target>             Drop the database tables of a preview (`p<N>_*`)
#
# Options:
#   -b, --base-url <url>                URL of the root of the space (the deployer listens on
#                                       `/_deployer/<version>/index.php`, installed by this script)
#   --ftp-hostname, --ftp-username, --ftp-password
#   --keep <path>                       Path of the live release to keep (repeatable; `deploy` and `activate`)
#   --reuse <folder>                    Folder of the live release whose entries missing from the new release are
#                                       moved into it, e.g. `vendor` when the archive carries only its changed parts
#                                       (repeatable; `deploy` and `activate`)
#   --exclude <pattern>                 Additional `zip` pattern to leave out of the archive (repeatable;
#                                       `create-archive`)
#   --include <path>                    Path added back to the archive after the exclusions, e.g. `vendor/composer`
#                                       when `vendor/*` is excluded (repeatable; `create-archive`)
#   --exclude-from <file>               File of paths to leave out of the archive, in git-ftp's `.git-ftp-ignore`
#                                       format: one shell glob per line, matched against the whole path, `*` also
#                                       matching `/`; `#` comments (`create-archive`; they also apply to `--include`)
#   --default-environment <folder>      Router: folder serving the site without subdomain (and `www.`); none: 404
#   --php-handler <handler>             Router: PHP handler of the space (e.g. `av-php82` on Altervista)
#   --document-root <folder>            Router: document root inside each environment (default: `public`; `.` for
#                                       the folder of the environment itself)
#   --repository-url <url>              Router: repository of the PRs, linked from the page of the missing previews
#                                       (default: the one of the GitHub Actions run, if any; empty: no links)
#   --no-previews                       Router: the space hosts no PR previews (no `pr-<N>` rule nor `previews/`)
#   -v, --verbose
#
# Like `remote_artisan.sh`, the requests are authorized with a random token uploaded through FTP, which is always
# deleted afterwards. Every request runs within the time budget of the server, and a failed one is retried (every
# action can be safely repeated) with a smaller budget. The JSON response of the last request is printed on stdout.

set -o pipefail
set -o errtrace
set -o nounset

declare -r MAX_ATTEMPTS=4

# The deployer bundled with this script, installed in a folder named after a hash of its files: every run talks to the
# version matching its own client, even when runs with different versions overlap
SERVER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/server" && pwd)"
ROUTER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/router" && pwd)"
DEPLOYER_VERSION="$(cd "$SERVER_DIR" && find . -type f -print0 | sort -z | xargs -0 sha256sum | sha256sum | cut -c1-12)"
declare -r SERVER_DIR ROUTER_DIR DEPLOYER_VERSION
declare -r DEPLOYER_PATH="_deployer/${DEPLOYER_VERSION}"

# Read by the `EXIT` trap, which runs after `main` has returned: not local
ftp_hostname="" ftp_username="" ftp_password="" token_id="" token_file="" token=""
base_url="" verbose=false server_budget="" server_time_limit="" default_environment="" php_handler="" document_root="public"
router_previews=true
repository_url="${GITHUB_SERVER_URL:+${GITHUB_REPOSITORY:+${GITHUB_SERVER_URL}/${GITHUB_REPOSITORY}}}"
keep=() reuse=() exclude=() include=() exclude_from=""

remove_token () {
    [[ -n "$token_file" ]] && rm -f "$token_file"
    [[ -z "$token_id" ]] && return 0

    ftp <<< "rm -f '${DEPLOYER_PATH}/tokens/${token_id}'" > /dev/null \
        || printf "Failed to remove the token from the server\n" >&2
}

# Run the lftp commands of the standard input on the FTP server
ftp () {
    LFTP_PASSWORD="$ftp_password" lftp --norc \
        -e "set ssl:check-hostname no; set net:max-retries 5; set net:reconnect-interval-base 5" \
        -u "$ftp_username" --env-password "$ftp_hostname"
}

# Install the bundled deployer on the server (only the files that changed are uploaded)
install_deployer () {
    if ! ftp <<< "mkdir -p -f '${DEPLOYER_PATH}/tokens'; mirror --reverse --no-perms '${SERVER_DIR}' '${DEPLOYER_PATH}'" > /dev/null; then
        printf "Failed to install the deployer on the server\n" >&2
        return 1
    fi
}

# Upload a random token for the requests to the deployer, deleted when the script exits
upload_token () {
    token="$(tr -dc A-Za-z0-9 </dev/urandom 2>/dev/null | head -c 64)"
    token_id="$(tr -dc A-Za-z0-9 </dev/urandom 2>/dev/null | head -c 32)"

    if [[ "${GITHUB_ACTIONS:-}" == "true" ]]; then
        printf '::add-mask::%s\n' "$token" >&2
    fi

    token_file="$(mktemp)" && printf '%s' "$token" > "$token_file"
    trap remove_token EXIT

    if ! ftp <<< "put '$token_file' -o '${DEPLOYER_PATH}/tokens/${token_id}'" > /dev/null; then
        printf "Failed to upload the token to the server\n" >&2
        return 1
    fi
}

# Send a request (JSON payload) to the deployer, printing its JSON response. A request that fails because of the
# server or the network (not because it is invalid) is retried, with half the time budget each time.
request () {
    local payload="$1" response http_code curl_status attempt budget

    for (( attempt = 1; attempt <= MAX_ATTEMPTS; attempt++ )); do
        if (( attempt > 1 )) && [[ -n "$server_budget" ]]; then
            budget="$(jq -n --argjson budget "$server_budget" --argjson attempt "$attempt" '$budget / pow(2; $attempt - 1)')"
            payload="$(jq -c --argjson budget "$budget" '. + {budget: $budget}' <<< "$payload")"
        fi

        [[ "$verbose" == true ]] && printf '>>> %s\n' "$payload" >&2

        curl_status=0
        response="$(curl --silent --show-error --location --max-time 120 \
            -H "Accept: application/json" \
            -H "Content-Type: application/json" \
            -H "X-Deployer-Token-Id: ${token_id}" \
            -H "X-Deployer-Token: ${token}" \
            -w '\n%{http_code}' \
            -X POST --data "$payload" "${base_url%/}/${DEPLOYER_PATH}/index.php")" || curl_status=$?

        http_code="$(tail -n 1 <<< "$response")"
        response="$(sed '$d' <<< "$response")"

        if (( curl_status == 0 )) && [[ "$http_code" == "200" ]] && [[ "$(jq -r '.success' <<< "$response" 2> /dev/null)" == "true" ]]; then
            [[ "$verbose" == true ]] && printf '<<< %s\n' "$response" >&2
            printf '%s\n' "$response"
            return 0
        fi

        printf "Deployer request failed (attempt %d/%d, HTTP %s): %s\n" "$attempt" "$MAX_ATTEMPTS" "${http_code:-none}" "$payload" >&2
        printf "Server response: %s\n" "${response:-<none>}" >&2

        # Invalid requests (4xx) would fail again
        if [[ "$http_code" =~ ^4 ]]; then
            return 1
        fi

        (( attempt < MAX_ATTEMPTS )) && sleep $(( 2 ** attempt ))
    done

    return 1
}

# Unpack the uploaded archive of a target, a batch per request
extract () {
    local target="$1" next=0 response

    while :; do
        response="$(request "$(jq -cn --arg target "$target" --argjson offset "$next" '{action: "extract", target: $target, offset: $offset}')")" || return 1
        next="$(jq -r '.next' <<< "$response")"
        printf 'Unpacked %s/%s entries\n' "$next" "$(jq -r '.total' <<< "$response")" >&2

        if [[ "$(jq -r '.done' <<< "$response")" == "true" ]]; then
            printf '%s\n' "$response"
            return 0
        fi
    done
}

# Delete a leftover of a target, a batch per request
delete () {
    local target="$1" variant="$2" response

    while :; do
        response="$(request "$(jq -cn --arg target "$target" --arg variant "$variant" '{action: "delete", target: $target, variant: $variant}')")" || return 1

        if [[ "$(jq -r '.done' <<< "$response")" == "true" ]]; then
            printf '%s\n' "$response"
            return 0
        fi

        printf 'Still deleting %s.%s...\n' "$target" "$variant" >&2
    done
}

activate () {
    local target="$1"

    request "$(jq -cn --arg target "$target" \
        --argjson keep "$(jq -cn '$ARGS.positional' --args "${keep[@]}")" \
        --argjson reuse "$(jq -cn '$ARGS.positional' --args "${reuse[@]}")" \
        '{action: "activate", target: $target, keep: $keep, reuse: $reuse}')"
}

# Read the time budget of the server, to halve it on retries, and show its execution time limit
read_server_info () {
    local response

    response="$(request '{"action": "info"}')" || return 1
    server_budget="$(jq -r '.time_budget' <<< "$response")"
    server_time_limit="$(jq -r '.max_execution_time' <<< "$response")"

    printf 'Server: PHP %s, max_execution_time=%s, time budget per request=%ss\n' \
        "$(jq -r '.php_version' <<< "$response")" "$(jq -r '.max_execution_time' <<< "$response")" "$server_budget" >&2
    printf '%s\n' "$response"
}

# Write the router of the space into the given folder, from the templates of `router/`
render_router () {
    local folder="$1" site site_pattern content default_rule default_description handler_lines root_path previews
    local missing_page previews_summary previews_rule

    site="${base_url#*://}"
    site="${site%%/*}"
    site="${site%%:*}"
    site="${site,,}"

    if [[ ! "$site" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ ]]; then
        printf "Invalid host in the base URL: '%s'\n" "$base_url" >&2
        return 1
    fi

    if [[ -n "$default_environment" ]] && { [[ ! "$default_environment" =~ ^[a-z][a-z0-9-]{0,30}$ ]] || [[ "$default_environment" == "previews" ]]; }; then
        printf "Invalid default environment: '%s'\n" "$default_environment" >&2
        return 1
    fi

    if [[ -n "$php_handler" ]] && [[ ! "$php_handler" =~ ^[A-Za-z0-9_-]+$ ]]; then
        printf "Invalid PHP handler: '%s'\n" "$php_handler" >&2
        return 1
    fi

    # `<folder>/` inside each environment, or nothing for the folder of the environment itself
    if [[ "$document_root" == "." ]]; then
        root_path=""
    elif [[ "$document_root" =~ ^[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$ ]]; then
        root_path="${document_root}/"
    else
        printf "Invalid document root: '%s'\n" "$document_root" >&2
        return 1
    fi

    # Written in a PHP string and in links of the page of the missing previews
    if [[ -n "$repository_url" ]] && [[ ! "$repository_url" =~ ^https?://[A-Za-z0-9.-]+(:[0-9]+)?(/[A-Za-z0-9_.-]+)+$ ]]; then
        printf "Invalid repository URL: '%s'\n" "$repository_url" >&2
        return 1
    fi

    site_pattern="${site//./\\.}"

    if [[ -n "$default_environment" ]]; then
        default_rule="RewriteRule ^(.*)\$ ${default_environment}/${root_path}\$1 [L]"
        default_description="\`${default_environment}/${root_path}\`"
    else
        default_rule="RewriteRule ^ - [R=404,L]"
        default_description="nothing (404)"
    fi

    if [[ -n "$php_handler" ]]; then
        handler_lines=$'\n# PHP version of the space\n'"AddHandler ${php_handler} .php"$'\n'
    else
        handler_lines=""
    fi

    # `&` in the replacements must stay literal (Bash 5.2)
    shopt -u patsub_replacement 2> /dev/null || true

    # Lines of the PR previews in the template, placeholders included: removed when the space hosts none
    if [[ "$router_previews" == "true" ]]; then
        previews_summary='#  - `pr-<N>.{{SITE}}`        => `previews/` (the PR previews, see `previews/.htaccess`);'
        previews_rule='    # pr-<N>.{{SITE}} => the PR previews'$'\n'
        previews_rule+='    RewriteCond %{HTTP_HOST} ^pr-[0-9]+\.{{SITE_PATTERN}}(:[0-9]+)?$ [NC]'$'\n'
        previews_rule+='    RewriteRule ^(.*)$ previews/$1 [L]'$'\n'
    else
        previews_summary="" previews_rule=""
    fi

    # Read from the environment, since `awk -v` would interpret the backslashes
    content="$(PREVIEWS_SUMMARY="$previews_summary" PREVIEWS_RULE="$previews_rule" awk '
        $0 == "{{PREVIEWS_SUMMARY}}" { if (ENVIRON["PREVIEWS_SUMMARY"] != "") print ENVIRON["PREVIEWS_SUMMARY"]; next }
        $0 == "{{PREVIEWS_RULE}}" { if (ENVIRON["PREVIEWS_RULE"] != "") print ENVIRON["PREVIEWS_RULE"]; next }
        { print }
    ' "${ROUTER_DIR}/root.htaccess")"
    content="${content//\{\{PHP_HANDLER\}\}/$handler_lines}"
    content="${content//\{\{DEFAULT_RULE\}\}/$default_rule}"
    content="${content//\{\{DEFAULT_DESCRIPTION\}\}/$default_description}"
    content="${content//\{\{SITE_PATTERN\}\}/$site_pattern}"
    content="${content//\{\{SITE\}\}/$site}"
    content="${content//\{\{DOCUMENT_ROOT\}\}/$root_path}"

    previews="$(< "${ROUTER_DIR}/previews.htaccess")"
    previews="${previews//\{\{DOCUMENT_ROOT\}\}/$root_path}"

    missing_page="$(< "${ROUTER_DIR}/404.php")"
    missing_page="${missing_page//\{\{REPOSITORY_URL\}\}/${repository_url}}"

    mkdir -p "${folder}"
    printf '%s\n' "$content" > "${folder}/.htaccess"

    if [[ "$router_previews" == "true" ]]; then
        mkdir -p "${folder}/previews"
        printf '%s\n' "$previews" > "${folder}/previews/.htaccess"
        printf '%s\n' "$missing_page" > "${folder}/previews/404.php"
        cp "${ROUTER_DIR}/robots.txt" "${folder}/previews/robots.txt"
    fi
}

install_router () {
    local folder status=0

    folder="$(mktemp -d)"
    render_router "$folder" || status=$?

    # Every deployment installs the router, but uploading a `.htaccess` truncates it on the server for a moment, during
    # which every environment of the space answers with a 404: a deployment running at the same time (e.g. the preview
    # of another PR) would fail. The router is uploaded only when it differs from the installed one.
    if (( status == 0 )) && router_is_installed "$folder"; then
        printf "The router of the space is already up to date\n" >&2
    elif (( status == 0 )); then
        ftp <<< "mirror --reverse --no-perms --verbose '${folder}' ." || status=$?
    fi

    rm -rf "$folder"
    return "$status"
}

# Whether the files of the router rendered in the given folder are the ones installed on the server, byte for byte
router_is_installed () {
    local folder="$1" installed path status=0
    local -a commands=()

    installed="$(mktemp -d)"

    while IFS= read -r path; do
        mkdir -p "${installed}/$(dirname "$path")"
        commands+=("get '${path}' -o '${installed}/${path}'")
    done < <(cd "$folder" && find . -type f | sed 's|^\./||' | sort)

    # A file missing on the server is not downloaded, so it makes the comparison fail
    ftp <<< "$(printf '%s\n' "${commands[@]}")" > /dev/null 2>&1 || true
    diff -rq "$folder" "$installed" > /dev/null 2>&1 || status=1

    rm -rf "$installed"
    return "$status"
}

create_archive () {
    local archive="$1"
    local -a ignored=()
    local -a always_excluded=(
        '.git/*' '*/.git/*' '.github/*' '.ftp-archive-deploy-source/*' '.vscode/*' '.devcontainer/*' '.act/*'
        'node_modules/*' 'tests/*' 'docker/*' 'storage/logs/*.log'
        '.env.*' '_ide_helper*.php' '.phpstorm.meta.php' 'docker-compose.yml' '*.sql' 'public/hot'
        '.ftp-deploy-sync-state*'
    )

    if [[ -n "$exclude_from" ]]; then
        if [[ ! -f "$exclude_from" ]]; then
            printf "The file of the paths to exclude does not exist: '%s'\n" "$exclude_from" >&2
            return 1
        fi

        # Read like git-ftp does: comments and blank lines skipped, CRs stripped. Its globs match like the `zip` ones
        mapfile -t ignored < <(grep -v '^#' "$exclude_from" | tr -d '\r' | awk 'NF')
    fi

    rm -f "$archive"
    zip -qr "$archive" . -x "${always_excluded[@]}" ${ignored[0]:+"${ignored[@]}"} ${exclude[0]:+"${exclude[@]}"} \
        || return 1

    # Added back after the `--exclude` patterns, but never with the files that are always left out
    if (( ${#include[@]} > 0 )); then
        zip -qr "$archive" "${include[@]}" -x "${always_excluded[@]}" ${ignored[0]:+"${ignored[@]}"} || return 1
    fi

    printf 'Archive: %s, %s entries\n' "$(du -h "$archive" | cut -f1)" "$(unzip -Z1 "$archive" | wc -l)" >&2
}

# Upload a release archive and replace the live release of the target with it, printing a JSON summary
deploy () {
    local target="$1" archive="$2" remote_archive started_at unpacked activated
    started_at="$(date +%s)"

    if [[ "$target" =~ ^pr-[0-9]+$ ]]; then
        remote_archive="previews/${target}.zip"
    else
        remote_archive="${target}.zip"
    fi

    # Leftovers of a cancelled deployment, including a partial archive: resuming its upload (`put -c`) would append the
    # new archive to the old one
    delete "$target" next > /dev/null || return 1
    delete "$target" old > /dev/null || return 1
    delete "$target" zip > /dev/null || return 1

    printf 'Uploading the archive...\n' >&2
    ftp <<< "mkdir -p -f previews; put -c '${archive}' -o '${remote_archive}'" || return 1

    printf 'Unpacking the archive...\n' >&2
    unpacked="$(extract "$target")" || return 1

    printf 'Replacing the live release%s%s...\n' \
        "$( (( ${#keep[@]} > 0 )) && printf ', keeping: %s' "${keep[*]}")" \
        "$( (( ${#reuse[@]} > 0 )) && printf ', reusing: %s' "${reuse[*]}")" >&2
    activated="$(activate "$target")" || return 1

    printf 'Deleting the previous release...\n' >&2
    delete "$target" old > /dev/null || return 1

    jq -cn \
        --arg target "$target" \
        --arg version "$DEPLOYER_VERSION" \
        --arg max_execution_time "$server_time_limit" \
        --argjson time_budget "$server_budget" \
        --argjson unpacked "$unpacked" \
        --argjson activated "$activated" \
        --argjson duration "$(( $(date +%s) - started_at ))" \
        '{success: true, target: $target, deployer_version: $version, max_execution_time: $max_execution_time,
          time_budget: $time_budget, entries: $unpacked.total, replaced: $activated.replaced, kept: $activated.kept,
          reused: $activated.reused, duration: $duration}'
}

# Check that the given commands are installed (each action only needs some of them)
require () {
    local cmd

    for cmd in "$@"; do
        if ! command -v "$cmd" &> /dev/null; then
            printf "This script requires '%s' to be installed\n" "$cmd" >&2
            return 1
        fi
    done
}

main () {
    local action=""
    local -a positional=()

    if [[ "${1:-}" == "version" ]]; then
        printf '%s\n' "$DEPLOYER_VERSION"
        return 0
    fi

    while [[ $# -gt 0 ]]; do
        case $1 in
            -v | --verbose)     verbose=true; shift ;;
            -b | --base-url)    base_url="$2"; shift 2 ;;
            --ftp-hostname)     ftp_hostname="$2"; shift 2 ;;
            --ftp-username)     ftp_username="$2"; shift 2 ;;
            --ftp-password)     ftp_password="$2"; shift 2 ;;
            --keep)             keep+=("$2"); shift 2 ;;
            --reuse)            reuse+=("$2"); shift 2 ;;
            --exclude)          exclude+=("$2"); shift 2 ;;
            --include)          include+=("$2"); shift 2 ;;
            --exclude-from)     exclude_from="$2"; shift 2 ;;
            --default-environment) default_environment="$2"; shift 2 ;;
            --php-handler)      php_handler="$2"; shift 2 ;;
            --document-root)    document_root="$2"; shift 2 ;;
            --repository-url)   repository_url="$2"; shift 2 ;;
            --no-previews)      router_previews=false; shift ;;
            -h | --help)
                sed -n '/^# Usage:/,/^# action can be safely/p' "$0" | sed 's/^# \{0,1\}//' >&2
                return 0
            ;;
            -*)
                printf "ERROR: Unknown option '%s'\n" "$1" >&2
                return 1
            ;;
            *)                  positional+=("$1"); shift ;;
        esac
    done

    action="${positional[0]:-}"

    if [[ -z "$action" ]]; then
        printf "No action provided\n" >&2
        return 1
    fi

    if [[ "$action" == "create-archive" ]]; then
        require zip unzip || return 1
        create_archive "${positional[1]:?The path of the archive is required}"
        return
    fi

    if [[ -z "$base_url" ]]; then
        printf "The base URL is required\n" >&2
        return 1
    fi

    if [[ "$action" == "render-router" ]]; then
        render_router "${positional[1]:?The folder is required}"
        return
    fi

    # Every other action talks to the server
    require curl jq lftp || return 1

    if [[ -z "$ftp_hostname" ]] || [[ -z "$ftp_username" ]] || [[ -z "$ftp_password" ]]; then
        printf "The FTP credentials are required\n" >&2
        return 1
    fi

    if [[ "$action" == "install-router" ]]; then
        install_router
        return
    fi

    install_deployer || return 1
    upload_token || return 1

    if [[ "$action" == "info" ]]; then
        read_server_info
        return
    fi

    read_server_info > /dev/null || return 1

    local target="${positional[1]:-}"

    case "$action" in
        deploy)         deploy "$target" "${positional[2]:?The path of the archive is required}" ;;
        extract)        extract "$target" ;;
        activate)       activate "$target" ;;
        hash)
            request "$(jq -cn --arg target "$target" --arg path "${positional[2]:?The path of the file is required}" \
                '{action: "hash", target: $target, path: $path}')"
        ;;
        delete)         delete "$target" "${positional[2]:-}" ;;
        list)           request '{"action": "list"}' ;;
        deactivate | reset-database)
            request "$(jq -cn --arg action "$action" --arg target "$target" '{action: $action, target: $target}')"
        ;;
        *)
            printf "ERROR: Unknown action '%s'\n" "$action" >&2
            return 1
        ;;
    esac
}

main "$@"
