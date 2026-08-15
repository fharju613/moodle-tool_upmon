# Running the tests

The plugin tests run against a real Moodle site provided by
[moodle-docker](https://github.com/moodlehq/moodle-docker). `dev/testenv.sh`
wires everything together: it checks out Moodle and moodle-docker next to this
repository (in `../.upmon-testenv` by default), bind mounts this working tree at
`admin/tool/upmon` inside the containers, installs the site and initialises both
the PHPUnit and Behat environments.

Requirements: Docker with the Compose plugin, ~5 GB of disk.

```bash
dev/testenv.sh setup     # one time (or after `down`): ~5-10 minutes
dev/testenv.sh phpunit   # 48 tests
dev/testenv.sh behat     # 9 scenarios
dev/testenv.sh test      # both
```

Because the plugin is bind mounted, code changes are picked up immediately — no
re-copying, no container restart. Re-run `setup` only when the Moodle version,
database or PHP version changes, or after `dev/testenv.sh down` destroyed the
containers.

Useful extras:

```bash
dev/testenv.sh phpunit --filter test_monitor_creation
dev/testenv.sh behat --name "Configure the plugin settings"
dev/testenv.sh exec php admin/cli/purge_caches.php
dev/testenv.sh stop            # keep containers/data, free resources
dev/testenv.sh down            # destroy containers and data
```

The manual test site is served at http://localhost:8000 (login `admin` /
`test`), sent mail is visible at http://localhost:8000/_/mail and Behat failure
dumps at http://localhost:8000/_/faildumps/.

Configuration is done through environment variables, e.g. to test against
Moodle 5.0 on MariaDB:

```bash
MOODLE_BRANCH=MOODLE_500_STABLE MOODLE_DOCKER_DB=mariadb dev/testenv.sh setup
```

`MOODLE_BRANCH`, `UPMON_WORKSPACE`, `MOODLE_DOCKER_DB`,
`MOODLE_DOCKER_PHP_VERSION`, `MOODLE_DOCKER_BROWSER` and
`MOODLE_DOCKER_WEB_PORT` are all honoured; see the header of `dev/testenv.sh`.
