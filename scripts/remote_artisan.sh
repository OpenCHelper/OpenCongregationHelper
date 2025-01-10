#!/bin/env bash
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
    local artisan_base_url="" command="" response=""
    local -a curl_opts=(
        --fail --show-error
        -H "Accept: application/json"
        -H "Content-Type: application/json"
    );

    if ! command -v jq &> /dev/null; then
        printf "This script requires 'jq' to be installed\n" >&2
        return 1
    fi

    # From this excellent StackOverflow answer: https://stackoverflow.com/a/14203146/8965861
    OPTIND=1;
    POSITIONAL=();
    while [[ $# -gt 0 ]]; do
        case $1 in
            -v | --verbose)
                curl_opts+=(--verbose)
                shift;
            ;;
            -b | --base-url)
                artisan_base_url="$2";
                shift 2;
            ;;
            -\? | -h |--help)
                # Call to usage function goes here
                return 0;
            ;;
            --)
                shift;
                while (( $# > 0 )); do POSITIONAL+=("$1"); shift; done
                break;
            ;;
            -*)
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

    if [[ -z "$1" ]]; then
      printf "No command provided\n" >&2
      return 1
    fi
    command="$1" && shift
    
    if ! response="$(curl -skL "${artisan_base_url}/api/execute/${command}" \
      -X POST --data "{\"params\": \"$*\"}" \
      "${curl_opts[@]}"
    )"; then
      printf "Failed to execute command '%s'\n" "$command" >&2
      printf "Server response: %s\n" "$response" >&2
      return 1
    fi

    jq -r '.output' <<< "$response"

    return 0;
}

main "$@"