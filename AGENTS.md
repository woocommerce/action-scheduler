Action Scheduler Technical Overview and Development Guide
===

Refer to ./README.md and ./readme.txt for high-level information about the goals of this plugin/library.

## Setup

- Action Scheduler requires at least the version of PHP noted by the `Requires PHP:` field in the file docblock found at the top of ./action-scheduler.php.
- The same file provides the minimum required WordPress version under the `Requires at least:` field.
- You will additionally need [Composer](https://getcomposer.org/) and Node/NPM, which may for instance be obtained by using [NVM](https://github.com/nvm-sh/nvm).
- With all of the above in place, run `composer install` and `npm install`.
- To set up the PHPUnit-based test suite, the WordPress test library must be installed first, via `tests/bin/install.sh <db-name> <db-user> <db-password> [db-host] [wp-version] [skip-database-creation]` (see ./tests/README.md for details). Thereafter, run `composer run test`.
- ./tests/bootstrap.php looks for the test library via the `WP_TESTS_DIR` environment variable, falling back to `sys_get_temp_dir() . '/wordpress-tests-lib'`. If the suite fails immediately with a missing `includes/functions.php`, the fallback path is being resolved somewhere unexpected (`sys_get_temp_dir()` honors `TMPDIR`): set `WP_TESTS_DIR` explicitly rather than moving the library. Either form works, as Composer passes the environment through to its scripts:

```
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer run test
WP_TESTS_DIR=/path/to/wordpress-tests-lib ./vendor/bin/phpunit tests -c tests/phpunit.xml.dist --filter Class_Name_to_Match
```

## Commands

- `composer run test` → as detailed above, used to run the test suite. Setup must be performed first.
  - `composer run test -- --filter Class_Name_to_Match` as an example of filtering the test class(es) to be run. Note the `--` separator: without it, Composer tries to interpret `--filter` as one of its own options and errors out.
  - The underlying command is `./vendor/bin/phpunit tests -c tests/phpunit.xml.dist`. The `tests` path argument makes PHPUnit scan that directory and *ignore* the `<testsuites>` declared in ./tests/phpunit.xml.dist; dropping it honors them instead. Both forms currently collect the same set, so keep the two in step: a new test file placed outside the directories the testsuites already list needs its own `<file>` entry, or it will be collected by `composer run test` and silently skipped by the testsuite form. PHPUnit does not warn about a `<file>` path that does not exist.
- `composer run phpcs` → performs linting.
  - `composer run phpcs path/to/file.php` as an example of linting a single file.
- `composer run phpcbf` → corrects linting errors where possible.
- `composer run lint` → run phpcs against PHP files with unstaged changes.
- `composer run lint-staged` →  run phpcs against PHP files with staged changes.
- `composer run lint-branch` → run phpcs against PHP files changed on the current branch compared to its base branch (defaults to `origin/trunk`; pass an alternative base as an argument).
- `npm run build` → builds the plugin (output is a plugin zip file). Note that this reinstalls dependencies without dev packages (`composer install --no-dev` followed by `npm install --only=prod`), so PHPUnit, phpcs and phpcs-changed (which the three `lint*` scripts wrap) will be missing afterwards: run `composer install` again before testing or linting.

Additionally, in terms of GitHub actions, the following are supported:

- `pr-lint` (./.github/workflows/pr-lint.yml) → lints the changed code.
- `pr-unit-tests` (./.github/workflows/pr-unit-tests.yml) → runs the full test matrix (runs the test suite using a range of different PHP and WordPress versions).
- `release` (./.github/workflows/release.yml) → prepares and builds a new release. Manually dispatched, and run in two steps (`release prep`, then `release`), each of which should first be run as a dry-run. WooRelease handles the version bump only; the changelog is managed by the workflow itself. See ./RELEASING.md for the full runbook.

Linting checks and test suite runs under the test matrix are always performed within GitHub before a pull request is accepted and merged. Pull requests target `trunk`.

Linting rules live in ./phpcs.xml (the `WooCommerce-Core` ruleset). Note that ./docs/, ./lib/ and ./deprecated/ are excluded from linting, as are ./node_modules/ and ./vendor/, so a clean phpcs run does not imply those paths were checked.

## Structure

The public API surface is considered to be the top-level functions found in ./functions.php. However, it is common for third parties to also directly utilize:

- `ActionScheduler` (defined in ./classes/abstracts/ActionScheduler.php) and especially `ActionScheduler::store()`.
- `ActionScheduler_Store` (defined in ./classes/abstracts/ActionScheduler_Store.php) and by extension any of its subclasses. The concrete implementations of `ActionScheduler_Store::query_actions()` in particular are frequently used.

Many existing classes exist in the global namespace, but it is preferred that new classes be namespaced under `Action_Scheduler` (and under appropriate sub-namespaces), following [PSR-4 conventions](https://www.php-fig.org/psr/psr-4/) where practical — see `Action_Scheduler\WP_CLI\Action\Cancel_Command` (./classes/WP_CLI/Action/Cancel_Command.php).

`ActionScheduler::autoload()` (./classes/abstracts/ActionScheduler.php) is *not* PSR-4, and adding a class usually means editing it:

- Namespaced classes are rejected unless prefixed `Action_Scheduler`. The namespace is then discarded entirely, and only the bare class name is resolved — so a new sub-namespace does not map to a new directory on its own.
- Resolution runs through hardcoded `static` arrays, one per helper (`is_class_abstract()`, `is_class_migration()`, `is_class_cli()`), plus suffix branches for `Schedule`/`Action`/`Schema`/`Deprecated` and a prefix branch for `ActionScheduler_`.
- A name matching none of the above falls through to the top level of ./classes/, or is not loaded at all.

Action Scheduler also provides a number of action and filter hooks, allowing site operators to alter its behavior and change its performance characteristics. Lastly, it exposes a number of capabilities and tools as WP CLI commands (implemented in ./classes/WP_CLI/ and documented in ./docs/wp-cli.md).

The ./lib/ directory is used to house vendor libraries which we have modified directly, as opposed to traditional Composer-managed dependencies which live in ./vendor/. It currently contains `cron-expression` and `WP_Async_Request` (vendored from [wp-background-processing](https://github.com/deliciousbrains/wp-background-processing) at the commit recorded in its docblock, guarded by a `class_exists()` check since other plugins commonly bundle the same class, and extended by `ActionScheduler_AsyncRequest_QueueRunner`). Each has its own branch in `ActionScheduler::autoload()`, so anything added here needs one too.

### Code layout

Within ./classes/, code is generally grouped by type (though a number of classes, principally geared toward orchestration and integration, sit at the top-level):

- abstracts/ → the base classes third parties are most likely to extend or call, including `ActionScheduler`, `ActionScheduler_Store`, `ActionScheduler_Logger`, `ActionScheduler_Lock` and `ActionScheduler_Abstract_QueueRunner`.
- actions/ → the action objects themselves (`ActionScheduler_Action` plus the canceled, finished and null variants).
- schedules/ → the schedule types an action can carry (simple, interval, cron, canceled, null).
- data-stores/ → the concrete store and logger implementations. `ActionScheduler_DBStore` (custom tables) is the modern default; `ActionScheduler_wpPostStore` is the legacy post-based store; `ActionScheduler_HybridStore` bridges the two while a site is still migrating. Loggers follow the same pattern (`ActionScheduler_DBLogger` and `ActionScheduler_wpCommentLogger`).
- schema/ → custom table definitions for the store and logger.
- migration/ → the machinery that moves sites from the post store to the custom tables, including a dry-run mode.
- WP_CLI/ → command implementations. Newer commands are namespaced (see ./classes/WP_CLI/Action/), older ones are not.

The admin surface is one of the top-level groupings referred to above, and is worth knowing about before touching anything user-facing:

- `ActionScheduler_AdminView` → menu registration, the past-due admin notice, help tabs, and (when WooCommerce is present) the System Status tab and report.
- `ActionScheduler_ListTable` → the actions table itself, extending `ActionScheduler_Abstract_ListTable` in abstracts/.
- `ActionScheduler_DataController` → not admin UI, but sits alongside it: holds the store/logger class constants and the migration status flag, so it is what decides whether the custom tables are in play.

Additionally:

- ./deprecated/ → retained purely for backwards compatibility. Do not extend it or add to it.
- ./tests/phpunit/ → the test suite, grouped by concern (jobs/, jobstore/, logging/, lock/, runner/, schedules/, migration/, versioning/, procedural_api/) rather than strictly mirroring ./classes/. Two further directories are not concern-based: helpers/ (shared test fixtures, plus a couple of test classes with no natural home) and deprecated/ (a legacy base test case, loaded only under PHPUnit older than 6.0). A few loose files also sit directly in ./tests/phpunit/ — the shared mocks (`ActionScheduler_Mocker`, `ActionScheduler_Mock_Async_Request_QueueRunner`) plus one test class. ./tests/bin/ holds the test library installer, and ./tests/bootstrap.php is the entry point.

### Runtime flow

Worth internalizing before changing anything in the runner or store, as the pieces are spread across several directories:

1. Scheduling. An `as_schedule_*()` call (./functions.php) hands off to `ActionScheduler_ActionFactory`, which pairs an `ActionScheduler_Action` with one of the schedule objects from ./classes/schedules/, then persists it via `ActionScheduler::store()->save_action()`.
2. Triggering. Execution begins with the `action_scheduler_run_queue` hook (`ActionScheduler_QueueRunner::WP_CRON_HOOK`). It is fired by WP Cron, by `ActionScheduler_AsyncRequest_QueueRunner` dispatching a loopback request, or by the WP CLI runner — hence the `$context` string threaded through the runner methods.
3. Batching. `ActionScheduler_QueueRunner::run()` repeatedly calls `do_batch()` until it runs out of actions, time or memory (`batch_limits_exceeded()`). Batch size defaults to 25 and is filterable via `action_scheduler_queue_runner_batch_size`.
4. Claiming. `do_batch()` calls `$store->stake_claim()`. Claims are what stop concurrent runners double-processing an action, and are the single most important concept in the store implementations. Note that each action re-checks `get_claim_id()` immediately before running and bails if the claim was lost mid-batch.
5. Execution. `ActionScheduler_Abstract_QueueRunner::process_action()` fires `action_scheduler_before_execute`, invokes the callback, then records the outcome in the store and the logger. `ActionScheduler_FatalErrorMonitor` hooks that same action at priority 0 purely so an in-flight action can be attributed if the request dies.
6. Follow-up. Recurring actions get their successor scheduled by `schedule_next_instance()`; `ActionScheduler_QueueCleaner` handles timeouts, failure marking and deletion.

### Documentation structure

./docs/ holds the user-facing documentation and is deployed as a GitHub Pages site, which accounts for the assets, layouts and config files sitting alongside the Markdown. Most page names describe themselves (admin.md, api.md, faq.md, perf.md, wp-cli.md). Three are worth calling out:

- index.md → the landing page, rather than a table of contents.
- usage.md → covers the recommended strategy for *loading* the library, plus API examples. Distinct from api.md, which is the reference for ./functions.php.
- version3-0.md → historic guidance for the 2.x-3.0 update. Effectively frozen; new material does not belong here.

Fit new documentation into the existing pages where it belongs, but creating a new Markdown file (and therefore a new page) is fine when nothing fits.

## Bootstrapping

Multiple plugins, all embedding different versions of Action Scheduler, may be active simultaneously. In these cases, the most recent version of Action Scheduler should 'win' and be loaded. The way this works:

- Plugins are expected to require ./action-scheduler.php before the `plugins_loaded` action fires. Most commonly, this is done via top-level code in the main plugin file.
- Each individual ./action-scheduler.php will check if the `ActionScheduler_Versions` class has been defined, loading it if it has not, and then uses that class to register itself and its version. Caveat: if another plugin already registered the exact same version, it does nothing.
- During `plugins_loaded` (at priority 1) `ActionScheduler_Versions` will examine the registered versions, and load the most recent one.
- Top-level Action Scheduler functions can only be safely called from `action_scheduler_init` onwards.

Plugins can also load Action Scheduler in atypical ways, occasionally leaving an older-than-expected version initialized. We cannot control this, but should keep it in mind when introducing new APIs.

## Best practices and recommendations

- The minimum supported PHP version is a hard constraint on the syntax we can use, not just a metadata field. Code must run on the version noted by `Requires PHP:` in ./action-scheduler.php (7.2 at time of writing), which rules out arrow functions, typed properties, `??=`, constructor property promotion, and other later additions. This is the most common way otherwise-sound code fails the test matrix. Note that phpcs is configured to check against this floor (see the `testVersion` config in ./phpcs.xml), but its `minimum_supported_wp_version` setting is *not* currently kept in step with the `Requires at least:` field, so it should not be treated as a guide to the WordPress floor.
- `as_supports()` lets library consumers check whether the loaded version supports a given feature. Use it whenever a new feature might not be available at runtime, per the older-than-expected version problem noted above. Its feature list is a short, hardcoded `$supported_features` array inside the function itself (./functions.php), derived from nothing: a feature not explicitly added to that array reports itself as unsupported, so extending it is part of shipping the feature, not a follow-up.
- If a new feature is added and the public API surface is changed, we should always make appropriate changes to our docs/.
- It is normally expected for each feature or fix to result in a changelog entry. Changelogs are contained in ./changelog.txt.
  - We should only add changelog entries to the open changelog block. Create this if it does not exist. Example (noting that `x.y.z` ought to be the next *expected* version, and `xxxx-xx-xx` is literally written that way until it is overwritten with the actual release date, as part of the release process):

```
= x.y.z - xxxx-xx-xx =
* Individual changelog entry
```

  - Two changelog files exist, and they are handled differently:
    - ./changelog.txt → the all-time history, and the one we edit during development, via the open block described above.
    - ./readme.txt → the WordPress.org changelog. Its entry is *generated* during release prep from the titles of the PRs merged since the last release, not derived from ./changelog.txt, so curation happens here, on the release prep PR.
    - Release then copies that entry back into ./changelog.txt, but skips the copy if the version being released is already listed there. A hand-maintained open block therefore stays authoritative for its version, and its `xxxx-xx-xx` placeholder must be corrected by hand during release prep rather than being stamped automatically (WooRelease is invoked with `--generate_changelog`, which tells it to leave changelogs alone).

