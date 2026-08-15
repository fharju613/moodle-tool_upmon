#!/usr/bin/env bash
#
# Development helper: run the tool_upmon PHPUnit and Behat tests inside
# moodle-docker (https://github.com/moodlehq/moodle-docker) against every
# supported Moodle version.
#
# Each version gets its own workspace, docker compose project and web port, and
# the plugin working tree is bind mounted into the containers, so local edits
# are picked up without re-copying anything. Moodle 5.1 and later use the
# public/ codebase layout, so the plugin is mounted at public/admin/tool/upmon
# there and at admin/tool/upmon before that.
#
# Usage:
#   dev/testenv.sh checkout       # only clone moodle + moodle-docker and write the docker config
#   dev/testenv.sh setup          # checkout, start containers, init phpunit & behat
#   dev/testenv.sh phpunit [args] # run the plugin PHPUnit tests
#   dev/testenv.sh behat [args]   # run the plugin Behat features
#   dev/testenv.sh test           # run both
#   dev/testenv.sh up|stop|down   # container lifecycle
#   dev/testenv.sh exec <cmd...>  # arbitrary command in the webserver container
#   dev/testenv.sh versions       # list the configured versions, ports and paths
#
# Every command runs for all supported versions; restrict it with
# UPMON_VERSIONS, e.g. `UPMON_VERSIONS=501 dev/testenv.sh phpunit`.
#
# Configuration (environment variables):
#   UPMON_VERSIONS        versions to act on (default: 405 500 501 502)
#   UPMON_WORKSPACE       where the per version checkouts live (default: ../.upmon-testenv)
#   UPMON_WEB_PORT_BASE   web port of the first version, incremented per version (default: 8000)
#   MOODLE_DOCKER_DB      database server (default: pgsql)
#   MOODLE_DOCKER_PHP_VERSION  php version (default: 8.3)
#   MOODLE_DOCKER_BROWSER browser used by Behat (default: chrome)

set -euo pipefail

plugindir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
workspace="${UPMON_WORKSPACE:-$(dirname "$plugindir")/.upmon-testenv}"
versions=(${UPMON_VERSIONS:-405 500 501 502})
portbase="${UPMON_WEB_PORT_BASE:-8000}"

# All supported versions, in order. The index in this list determines the web
# port, so ports stay stable when UPMON_VERSIONS selects a subset.
allversions=(405 500 501 502)

export MOODLE_DOCKER_DB="${MOODLE_DOCKER_DB:-pgsql}"
export MOODLE_DOCKER_PHP_VERSION="${MOODLE_DOCKER_PHP_VERSION:-8.3}"
export MOODLE_DOCKER_BROWSER="${MOODLE_DOCKER_BROWSER:-chrome}"
export TOOL_UPMON_PATH="$plugindir"

# 5.1 moved the codebase (including admin/tool) below public/; config.php,
# composer and admin/cli stayed at the root.
coderoot() {
    if [ "$1" -ge 501 ]; then echo "public/"; fi
}

web_port() {
    local i=0
    for v in "${allversions[@]}"; do
        if [ "$v" = "$1" ]; then echo $((portbase + i)); return; fi
        i=$((i + 1))
    done
    echo "Unknown Moodle version: $1 (supported: ${allversions[*]})" >&2
    exit 1
}

# Configure the environment for one version; must be called before compose().
use_version() {
    version="$1"
    versiondir="$workspace/$version"
    moodledir="$versiondir/moodle"
    dockerdir="$versiondir/moodle-docker"
    export MOODLE_DOCKER_WWWROOT="$moodledir"
    export MOODLE_DOCKER_WEB_PORT="127.0.0.1:$(web_port "$version")"
    export COMPOSE_PROJECT_NAME="upmon$version"
}

compose() {
    "$dockerdir/bin/moodle-docker-compose" "$@"
}

web() {
    compose exec -T webserver "$@"
}

checkout() {
    mkdir -p "$versiondir"
    if [ ! -d "$dockerdir" ]; then
        git clone --depth 1 https://github.com/moodlehq/moodle-docker.git "$dockerdir"
    fi
    if [ ! -d "$moodledir" ]; then
        git clone --depth 1 --branch "MOODLE_${version}_STABLE" \
            https://github.com/moodle/moodle.git "$moodledir"
    fi

    cp "$dockerdir/config.docker-template.php" "$moodledir/config.php"

    # Bind mount the plugin working tree into the containers. The mount point
    # has to exist on the host because the selenium container mounts the
    # codebase read only.
    mkdir -p "$moodledir/$(coderoot "$version")admin/tool/upmon"
    cat > "$dockerdir/local.yml" <<YAML
services:
  webserver:
    volumes:
      - "\${TOOL_UPMON_PATH}:/var/www/html/$(coderoot "$version")admin/tool/upmon"
  selenium:
    volumes:
      - "\${TOOL_UPMON_PATH}:/var/www/html/$(coderoot "$version")admin/tool/upmon:ro"
YAML
}

setup() {
    checkout
    compose up -d
    "$dockerdir/bin/moodle-docker-wait-for-db"
    web php admin/cli/install_database.php --agree-license --fullname="Docker moodle $version" \
        --shortname="docker_moodle_$version" --summary="Docker moodle site" \
        --adminpass="test" --adminemail="admin@example.com"
    web php "$(coderoot "$version")admin/tool/phpunit/cli/init.php"
    web php "$(coderoot "$version")admin/tool/behat/cli/init.php"
    echo "Moodle $version ready at http://localhost:$(web_port "$version") (admin / test)"
}

phpunit() {
    web vendor/bin/phpunit --testsuite tool_upmon_testsuite "$@"
}

behat() {
    compose exec -T -u www-data webserver php "$(coderoot "$version")admin/tool/behat/cli/run.php" \
        --tags=@tool_upmon "$@"
}

list_versions() {
    for v in "${allversions[@]}"; do
        echo "Moodle $v: port $(web_port "$v"), plugin at $(coderoot "$v")admin/tool/upmon, project upmon$v"
    done
}

run_for_each() {
    local action="$1"
    shift
    local failed=()
    for v in "${versions[@]}"; do
        use_version "$v"
        echo
        echo "=== Moodle $v: $action ==="
        if "$action" "$@"; then
            :
        else
            failed+=("$v")
        fi
    done
    if [ ${#failed[@]} -gt 0 ]; then
        echo
        echo "FAILED for Moodle: ${failed[*]}"
        return 1
    fi
    echo
    echo "OK for Moodle: ${versions[*]}"
}

both() {
    phpunit && behat
}

up() {
    compose up -d
    "$dockerdir/bin/moodle-docker-wait-for-db"
}

command="${1:-}"
[ $# -gt 0 ] && shift || true

case "$command" in
    checkout) run_for_each checkout ;;
    setup)    run_for_each setup ;;
    phpunit)  run_for_each phpunit "$@" ;;
    behat)    run_for_each behat "$@" ;;
    test)     run_for_each both ;;
    up)       run_for_each up ;;
    stop)     run_for_each compose stop ;;
    down)     run_for_each compose down ;;
    exec)     run_for_each web "$@" ;;
    versions) list_versions ;;
    *)        sed -n '3,33p' "${BASH_SOURCE[0]}"; exit 1 ;;
esac
