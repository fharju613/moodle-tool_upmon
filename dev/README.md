# Running the tests

The plugin tests run against real Moodle sites provided by
[moodle-docker](https://github.com/moodlehq/moodle-docker), one per supported
Moodle version (4.5, 5.0, 5.1 and 5.2). `dev/testenv.sh` wires everything
together: it checks out each Moodle version and moodle-docker next to this
repository (in `../.upmon-testenv/<version>` by default), bind mounts this
working tree into the containers, installs the sites and initialises both the
PHPUnit and Behat environments.

Requirements: Docker with the Compose plugin, ~20 GB of disk.

```bash
dev/testenv.sh setup     # one time (or after `down`): ~10 minutes per version
dev/testenv.sh phpunit   # 48 tests per version
dev/testenv.sh behat     # 9 scenarios per version
dev/testenv.sh test      # both, on every version
```

Every command runs against all versions and reports at the end which ones
failed. Restrict it with `UPMON_VERSIONS` while iterating:

```bash
UPMON_VERSIONS=501 dev/testenv.sh phpunit
UPMON_VERSIONS="405 502" dev/testenv.sh test
```

Because the plugin is bind mounted, code changes are picked up immediately — no
re-copying, no container restart. Re-run `setup` only when the Moodle version,
database or PHP version changes, or after `dev/testenv.sh down` destroyed the
containers.

Each version has its own compose project, database and web port:

```
$ dev/testenv.sh versions
Moodle 405: port 8000, plugin at admin/tool/upmon, project upmon405
Moodle 500: port 8001, plugin at admin/tool/upmon, project upmon500
Moodle 501: port 8002, plugin at public/admin/tool/upmon, project upmon501
Moodle 502: port 8003, plugin at public/admin/tool/upmon, project upmon502
```

Moodle 5.1 moved the codebase below `public/`, so the plugin is mounted at
`public/admin/tool/upmon` there while `admin/cli` and `config.php` stay at the
codebase root; the script handles both layouts.

Useful extras:

```bash
dev/testenv.sh phpunit --filter test_monitor_creation
dev/testenv.sh behat --name "Configure the plugin settings"
dev/testenv.sh exec php admin/cli/purge_caches.php
dev/testenv.sh stop            # keep containers/data, free resources
dev/testenv.sh down            # destroy containers and data
```

The manual test sites are served at the ports listed above (login `admin` /
`test`), sent mail is visible at `/_/mail` and Behat failure dumps at
`/_/faildumps/`.

Other configuration is done through environment variables — `UPMON_WORKSPACE`,
`UPMON_WEB_PORT_BASE`, `MOODLE_DOCKER_DB`, `MOODLE_DOCKER_PHP_VERSION` and
`MOODLE_DOCKER_BROWSER`; see the header of `dev/testenv.sh`. To add or drop a
supported version, edit the `allversions` list in that script.
