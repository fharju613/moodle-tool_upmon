#!/usr/bin/env bash
#
# Development helper: run the tool_upmon PHPUnit and Behat tests inside
# moodle-docker (https://github.com/moodlehq/moodle-docker).
#
# The plugin working tree is bind mounted into the webserver container at
# admin/tool/upmon, so local edits are picked up without re-copying anything.
#
# Usage:
#   dev/testenv.sh checkout       # only clone moodle + moodle-docker and write the docker config
#   dev/testenv.sh setup          # checkout, start containers, init phpunit & behat
#   dev/testenv.sh phpunit [args] # run the plugin PHPUnit tests
#   dev/testenv.sh behat [args]   # run the plugin Behat features
#   dev/testenv.sh test           # run both
#   dev/testenv.sh up|stop|down   # container lifecycle
#   dev/testenv.sh exec <cmd...>  # arbitrary command in the webserver container
#
# Configuration (environment variables):
#   UPMON_WORKSPACE       where moodle and moodle-docker are checked out (default: ../.upmon-testenv)
#   MOODLE_BRANCH         moodle branch to test against (default: MOODLE_405_STABLE)
#   MOODLE_DOCKER_DB      database server (default: pgsql)
#   MOODLE_DOCKER_PHP_VERSION  php version (default: 8.3)
#   MOODLE_DOCKER_BROWSER browser used by Behat (default: chrome)
#   MOODLE_DOCKER_WEB_PORT web port of the test site (default: 127.0.0.1:8000)

set -euo pipefail

plugindir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
workspace="${UPMON_WORKSPACE:-$(dirname "$plugindir")/.upmon-testenv}"
moodlebranch="${MOODLE_BRANCH:-MOODLE_405_STABLE}"
moodledir="$workspace/moodle"
dockerdir="$workspace/moodle-docker"

export MOODLE_DOCKER_WWWROOT="$moodledir"
export MOODLE_DOCKER_DB="${MOODLE_DOCKER_DB:-pgsql}"
export MOODLE_DOCKER_PHP_VERSION="${MOODLE_DOCKER_PHP_VERSION:-8.3}"
export MOODLE_DOCKER_BROWSER="${MOODLE_DOCKER_BROWSER:-chrome}"
export MOODLE_DOCKER_WEB_PORT="${MOODLE_DOCKER_WEB_PORT:-127.0.0.1:8000}"
export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-upmon}"
export TOOL_UPMON_PATH="$plugindir"

compose() {
    "$dockerdir/bin/moodle-docker-compose" "$@"
}

web() {
    compose exec -T webserver "$@"
}

checkout() {
    mkdir -p "$workspace"
    if [ ! -d "$dockerdir" ]; then
        git clone --depth 1 https://github.com/moodlehq/moodle-docker.git "$dockerdir"
    fi
    if [ ! -d "$moodledir" ]; then
        git clone --depth 1 --branch "$moodlebranch" https://github.com/moodle/moodle.git "$moodledir"
    fi

    cp "$dockerdir/config.docker-template.php" "$moodledir/config.php"

    # Bind mount the plugin working tree into the containers. The mount point
    # has to exist on the host because the selenium container mounts the
    # codebase read only.
    mkdir -p "$moodledir/admin/tool/upmon"
    cat > "$dockerdir/local.yml" <<YAML
services:
  webserver:
    volumes:
      - "\${TOOL_UPMON_PATH}:/var/www/html/admin/tool/upmon"
  selenium:
    volumes:
      - "\${TOOL_UPMON_PATH}:/var/www/html/admin/tool/upmon:ro"
YAML
}

setup() {
    checkout
    compose up -d
    "$dockerdir/bin/moodle-docker-wait-for-db"
    web php admin/cli/install_database.php --agree-license --fullname="Docker moodle" \
        --shortname="docker_moodle" --summary="Docker moodle site" \
        --adminpass="test" --adminemail="admin@example.com"
    web php admin/tool/phpunit/cli/init.php
    web php admin/tool/behat/cli/init.php
    echo "Test environment ready. Site: http://${MOODLE_DOCKER_WEB_PORT#*:} (admin / test)"
}

phpunit() {
    web vendor/bin/phpunit --testsuite tool_upmon_testsuite "$@"
}

behat() {
    compose exec -T -u www-data webserver php admin/tool/behat/cli/run.php --tags=@tool_upmon "$@"
}

command="${1:-}"
[ $# -gt 0 ] && shift || true

case "$command" in
    checkout) checkout ;;
    setup)   setup ;;
    phpunit) phpunit "$@" ;;
    behat)   behat "$@" ;;
    test)    phpunit && behat ;;
    up)      compose up -d && "$dockerdir/bin/moodle-docker-wait-for-db" ;;
    stop)    compose stop ;;
    down)    compose down ;;
    exec)    web "$@" ;;
    *)       sed -n '3,27p' "${BASH_SOURCE[0]}"; exit 1 ;;
esac
