#!/usr/bin/env bash
# **********************************************************************************
#                                                                                  *
# Author/s    : Luca Salvarani                                                     *
# Created on  : 2025-01-10 14:46:30                                                *
# Description :                                                                    *
#                                                                                  *
# **********************************************************************************


# From: 
#      https://vaneyckt.io/posts/safer_bash_scripts_with_set_euxo_pipefail/
#      https://www.gnu.org/software/bash/manual/html_node/The-Set-Builtin.html
set -o pipefail
set -o errtrace      # Same as `set -E`
set -o nounset       # Same as `set -u`
# set -o errexit     # Same as `set -e`, useful ONLY for short and simple scripts


main () {
    local artisan_base_url="" command="" response="" \
        token_file="storage/framework/.artisan-token" token="" local_token=false \
        ftp_hostname="" ftp_username="" ftp_password=""
    local _verbose=false;

    local -a curl_opts=(
        --fail-with-body --show-error
        -H "Accept: application/json"
        -H "Content-Type: application/json"
    );

    for cmd in curl jq lftp; do
        if ! command -v "$cmd" &> /dev/null; then
            printf "This script requires '%s' to be installed\n" "$cmd" >&2
            return 1
        fi
    done

    # From this excellent StackOverflow answer: https://stackoverflow.com/a/14203146/8965861
    OPTIND=1;
    POSITIONAL=();
    while [[ $# -gt 0 ]]; do
        case $1 in
            -v | --verbose)
                curl_opts+=(--verbose)
                _verbose=true
                shift;
            ;;
            -b | --base-url)
                artisan_base_url="$2";
                shift 2;
            ;;
            -t | --token)
                token="$2";
                shift 2;
            ;;
            --ftp-hostname)
                ftp_hostname="$2";
                shift 2;
            ;;
            --ftp-username)
                ftp_username="$2";
                shift 2;
            ;;
            --ftp-password)
                ftp_password="$2";
                shift 2;
            ;;
            --token-file)
                token_file="$2";
                shift 2;
            ;;
            --local)
                local_token=true
                shift;
            ;;
            -\? | -h |--help)
                printf "Usage: %s [options] <command> [params]\n" "$0" >&2
                printf "Options:\n" >&2
                printf "  -v, --verbose        Enable verbose output\n" >&2
                printf "  -b, --base-url       The base URL of the application (e.g. https://example.com)\n" >&2
                printf "  -t, --token          The token to use for authentication\n" >&2
                printf "  --ftp-hostname       The hostname of the FTP server\n" >&2
                printf "  --ftp-username       The username to use for FTP authentication\n" >&2
                printf "  --ftp-password       The password to use for FTP authentication\n" >&2
                printf "  --token-file         The file to store the token in\n" >&2
                printf "  --local              Store the token in the token file of the application in this directory (e.g. Sail)\n" >&2
                printf "                       instead of uploading it through FTP; removed after the command, like on the server\n" >&2
                return 0;
            ;;
            --)
                shift;
                while (( $# > 0 )); do POSITIONAL+=("$1"); shift; done
                break;
            ;;
            -*)
                # After the command, the options are its own (e.g. `och:post-deploy --no-interaction`)
                if (( ${#POSITIONAL[@]} > 0 )); then
                    while (( $# > 0 )); do POSITIONAL+=("$1"); shift; done
                    break;
                fi

                printf "ERROR: Unknown option '%s'" "$1" >&2;
                return 1;
            ;;
            *)
                POSITIONAL+=("$1");
                shift;
            ;;
        esac;
    done;
    [[ ${#POSITIONAL[@]} -gt 0 ]] && set -- "${POSITIONAL[@]}";

    if [[ -z "${1:-}" ]]; then
      printf "No command provided\n" >&2
      return 1
    fi
    command="$1" && shift

    if [[ -z "$artisan_base_url" ]]; then
      printf "No base URL provided\n" >&2
      return 1
    fi

    # Generate a random token if none is provided
    if [[ -z "$token" ]]; then
      token="$(tr -dc A-Za-z0-9 </dev/urandom 2>/dev/null | head -c 64)"
    fi

    # Hide the token from the GitHub Actions logs (e.g. when `--verbose` traces the curl command).
    # Written to stderr: stdout is the command output, which callers capture and parse.
    if [[ "${GITHUB_ACTIONS:-}" == "true" ]]; then
      printf '::add-mask::%s\n' "$token" >&2
    fi
    curl_opts+=(-H "X-Artisan-Token: ${token}")

    if [[ "$local_token" == true ]]; then
        if ! printf '%s' "$token" > "$token_file"; then
          printf "Failed to store the token in '%s'\n" "$token_file" >&2
          return 1
        fi
        trap "rm -f '$token_file'" EXIT
    else
        if [[ -z "$ftp_hostname" ]] || [[ -z "$ftp_username" ]] || [[ -z "$ftp_password" ]]; then
          printf "FTP credentials not provided\n" >&2
          return 1
        fi

        # Now load the token into the server
        _temp_token=$(mktemp) && printf '%s' "$token" > "$_temp_token" && trap "rm -f '$_temp_token'" EXIT
        if ! lftp -e "set ssl:check-hostname no" -u "$ftp_username,$ftp_password" "$ftp_hostname" <<< "put $_temp_token -o '$token_file'"; then
          printf "Failed to load token into server\n" >&2
          return 1
        fi
    fi
    
    local curl_status=0
    response="$([[ "$_verbose" == true ]] && set -x; curl -skL "${artisan_base_url%/}/api/artisan/execute" \
      -X POST --data "{\"command\": $(jq --raw-input <<< "$command"), \"params\": $(jq --raw-input <<< "$*")}" \
      "${curl_opts[@]}"
    )" || curl_status=$?

    # Always remove the token from the server, even when the command failed,
    # so that it does not remain valid after the deploy (the local one is removed on exit)
    if [[ "$local_token" == false ]]; then
        if ! lftp -e "set ssl:check-hostname no" -u "$ftp_username,$ftp_password" "$ftp_hostname" <<< "rm '$token_file'"; then
          printf "Failed to remove token from server\n" >&2
          return 1
        fi
    fi

    if (( curl_status != 0 )); then
      printf "Failed to execute command '%s'\n" "$command" >&2
      printf "Server response: %s\n" "$response" >&2
      return 1
    fi

    jq -r '.output' <<< "$response"

    # The command itself failed (e.g. `och:post-deploy` stopped at a step): the HTTP request did not
    local return_code
    return_code="$(jq -r '.returnCode // 0' <<< "$response")"
    if [[ "$return_code" != "0" ]]; then
      printf "Command '%s' failed with exit code %s\n" "$command" "$return_code" >&2
      return 1
    fi

    return 0;
}

main "$@"
