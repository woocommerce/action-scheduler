---
description: Reference guide for background processing functions provided by the Action Scheduler job queue for WordPress.
---
# API Reference

Action Scheduler provides a range of functions for scheduling hooks to run at some time in the future on one or more occasions.

To understand the scheduling functions, it can help to think of them as extensions to WordPress' `do_action()` function that add the ability to delay and repeat when the hook will be triggered.

## WP-Cron APIs vs. Action Scheduler APIs

The Action Scheduler API functions are designed to mirror the WordPress [WP-Cron API functions](http://codex.wordpress.org/Category:WP-Cron_Functions).

Functions return similar values and accept similar arguments to their WP-Cron counterparts. The notable differences are:

* `as_schedule_single_action()` & `as_schedule_recurring_action()` will return the ID of the scheduled action rather than boolean indicating whether the event was scheduled
* `as_schedule_recurring_action()` takes an interval in seconds as the recurring interval rather than an arbitrary string
* `as_schedule_single_action()` & `as_schedule_recurring_action()` can accept a `$group` parameter to group different actions for the one plugin together (see [Action Groups](#action-groups)).
* the `wp_` prefix is substituted with `as_` and the term `event` is replaced with `action`

## API Function Availability

As mentioned in the [Usage - Load Order](usage.md#load-order) section, Action Scheduler will initialize itself on the `'init'` hook with priority `1`. While API functions are loaded prior to this and can be called, they should not be called until after `'init'` with priority `1`, because each component, like the data store, has not yet been initialized.

Do not use Action Scheduler API functions prior to `'init'` hook with priority `1`. Doing so could lead to unexpected results, like data being stored in the incorrect location. To make this easier:

- Action Scheduler provides `Action_Scheduler::is_initialized()` for use in hooks to confirm that the data stores have been initialized.
- It also provides the `'action_scheduler_init'` action hook. It is safe to call API functions during or after this event has fired (tip: you can take advantage of WordPress's [did_action()](https://developer.wordpress.org/reference/functions/did_action/) function to check this).

## Action Groups

The `$group` parameter accepted by the scheduling functions is an optional label, such as your plugin's slug, that is stored alongside the action. Groups are created automatically the first time they are used.

Under the default queue runner, a group has no effect on when or in what order actions run. The actions in a group are not run together or as a single batch. Instead, groups make it easier to find and manage related actions:

* The group of each action is shown on the [Scheduled Actions administration screen](admin.md), where actions can be sorted by group.
* `as_get_scheduled_actions()` can return only the actions in a given group, via its `'group'` argument.
* `as_unschedule_action()`, `as_unschedule_all_actions()`, `as_next_scheduled_action()` and `as_has_scheduled_action()` only match actions in the given group. When the group is omitted (or is an empty string), they match actions in any group.
* `as_unschedule_all_actions()` can cancel every pending action in a group, whatever its hook, by passing an empty hook: `as_unschedule_all_actions( '', array(), 'my-plugin' )`.
* The `wp action-scheduler run` [WP CLI command](wp-cli.md) can process only the actions in a group (`--group`), or skip the actions in some groups (`--exclude-groups`).
* The group is part of what makes an action [unique](#unique-actions).

## Unique Actions

When the `$unique` parameter of a scheduling function is `true`, the action will only be scheduled if there is no other pending or in-progress action with the same hook, arguments and group. If there is, the action is not scheduled, and the function returns `0`.

```php
// Schedules the action and returns its ID.
as_schedule_single_action( time() + HOUR_IN_SECONDS, 'my_plugin_sync', array( 'post_id' => 123 ), 'my-plugin', true );

// Returns 0: an equivalent action is already pending, even though it is scheduled for a different time.
as_enqueue_async_action( 'my_plugin_sync', array( 'post_id' => 123 ), 'my-plugin', true );

// Schedules the action: the arguments are different.
as_enqueue_async_action( 'my_plugin_sync', array( 'post_id' => 456 ), 'my-plugin', true );
```

Some details are worth bearing in mind:

* Only the hook, arguments and group are compared (versions of Action Scheduler prior to 4.0.0 did not compare the arguments). The type of schedule (single, async, recurring or cron), the scheduled date and the priority are ignored.
* The arguments must be identical, including their order and type: `array( 'post_id' => 123 )` and `array( 'post_id' => '123' )` are different arguments.
* An empty group only matches actions that have no group. This is different from `as_has_scheduled_action()` and similar functions, where an empty group matches any group.
* Only pending and in-progress actions are checked. Once an action is complete, failed or canceled, an equivalent action can be scheduled again.
* Any matching action blocks the new one, including one that was scheduled without `$unique`.
* Uniqueness is only checked by calls where `$unique` is `true`. A later call made without `$unique` schedules a duplicate, even when a matching action exists.
* When later instances of a recurring or cron action are scheduled (internally, by Action Scheduler), uniqueness is not checked.
* With the default data store, which uses custom database tables, uniqueness is enforced atomically (since Action Scheduler 4.2.0), even when several requests try to schedule the same action at once. Other data stores, including the one used while a site is still migrating to the custom tables, rely on a lookup made before the action is saved, so concurrent requests could occasionally schedule a duplicate action.

## Function Reference / `as_enqueue_async_action()`

### Description

Enqueue an action to run one time, as soon as possible.

### Usage

```php
as_enqueue_async_action( $hook, $args, $group, $unique, $priority );
```

### Parameters

- **$hook** (string)(required) Name of the action hook.
- **$args** (array) Arguments to pass to callbacks when the hook triggers. Default: _`array()`_.
- **$group** (string) The group to assign this job to. See [Action Groups](#action-groups). Default: _''_.
- **$unique** (boolean) Whether the action should be unique. See [Unique Actions](#unique-actions). Default: _`false`_.
- **$priority** (integer) Lower values take precedence over higher values. Defaults to 10, with acceptable values falling in the range 0-255.

### Return value

`(integer)` the action's ID. Zero if the action was not scheduled, because `$unique` is `true` and a matching action exists, or because of an error. Errors are sent to `error_log`.

## Function Reference / `as_schedule_single_action()`

### Description

Schedule an action to run one time at some defined point in the future.

### Usage

```php
as_schedule_single_action( $timestamp, $hook, $args, $group, $unique, $priority );
```

### Parameters

- **$timestamp** (integer)(required) The Unix timestamp representing the date you want the action to run.
- **$hook** (string)(required) Name of the action hook.
- **$args** (array) Arguments to pass to callbacks when the hook triggers. Default: _`array()`_.
- **$group** (string) The group to assign this job to. See [Action Groups](#action-groups). Default: _''_.
- **$unique** (boolean) Whether the action should be unique. See [Unique Actions](#unique-actions). Default: _`false`_.
- **$priority** (integer) Lower values take precedence over higher values. Defaults to 10, with acceptable values falling in the range 0-255.)

### Return value

`(integer)` the action's ID. Zero if the action was not scheduled, because `$unique` is `true` and a matching action exists, or because of an error. Errors are sent to `error_log`.

## Function Reference / `as_schedule_recurring_action()`

### Description

Schedule an action to run repeatedly with a specified interval in seconds.

### Usage

```php
as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args, $group, $unique, $priority );
```

### Parameters

- **$timestamp** (integer)(required) The Unix timestamp representing the date you want the action to run.
- **$interval_in_seconds** (integer)(required) How long to wait between runs.
- **$hook** (string)(required) Name of the action hook.
- **$args** (array) Arguments to pass to callbacks when the hook triggers. Default: _`array()`_.
- **$group** (string) The group to assign this job to. See [Action Groups](#action-groups). Default: _''_.
- **$unique** (boolean) Whether the action should be unique. See [Unique Actions](#unique-actions). Default: _`false`_.
- **$priority** (integer) Lower values take precedence over higher values. Defaults to 10, with acceptable values falling in the range 0-255.

### Return value

`(integer)` the action's ID. Zero if the action was not scheduled, because `$unique` is `true` and a matching action exists, or because of an error. Errors are sent to `error_log`.

## Function Reference / `as_schedule_cron_action()`

### Description

Schedule an action that recurs on a cron-like schedule.

If execution of a cron-like action is delayed, the next attempt will still be scheduled according to the provided cron expression.

### Usage

```php
as_schedule_cron_action( $timestamp, $schedule, $hook, $args, $group, $unique, $priority );
```

### Parameters

- **$timestamp** (integer)(required) The Unix timestamp representing the date you want the action to run.
- **$schedule** (string)(required) $schedule A cron-like schedule string, see http://en.wikipedia.org/wiki/Cron.
- **$hook** (string)(required) Name of the action hook.
- **$args** (array) Arguments to pass to callbacks when the hook triggers. Default: _`array()`_.
- **$group** (string) The group to assign this job to. See [Action Groups](#action-groups). Default: _''_.
- **$unique** (boolean) Whether the action should be unique. See [Unique Actions](#unique-actions). Default: _`false`_.
- **$priority** (integer) Lower values take precedence over higher values. Defaults to 10, with acceptable values falling in the range 0-255.

### Return value

`(integer)` the action's ID. Zero if the action was not scheduled, because `$unique` is `true` and a matching action exists, or because of an error. Errors are sent to `error_log`.

## Function Reference / `as_unschedule_action()`

### Description

Cancel the next occurrence of a scheduled action.

### Usage

```php
as_unschedule_action( $hook, $args, $group );
```

### Parameters

- **$hook** (string)(required) Name of the action hook.
- **$args** (array) Arguments passed to callbacks when the hook triggers. Default: _`array()`_.
- **$group** (string) The group the job is assigned to. Default: _''_.

### Return value

`(null)`

## Function Reference / `as_unschedule_all_actions()`

### Description

Cancel all occurrences of a scheduled action.

### Usage

```php
as_unschedule_all_actions( $hook, $args, $group )
```

### Parameters

- **$hook** (string)(required) Name of the action hook.
- **$args** (array) Arguments passed to callbacks when the hook triggers. Default: _`array()`_.
- **$group** (string) The group the job is assigned to. Default: _''_.

### Return value

`(string|null)` The scheduled action ID if a scheduled action was found, or null if no matching action found.

## Function Reference / `as_next_scheduled_action()`

### Description

Returns the next timestamp for a scheduled action.

### Usage

```php
as_next_scheduled_action( $hook, $args, $group );
```

### Parameters

- **$hook** (string)(required) Name of the action hook. Default: _none_.
- **$args** (array) Arguments passed to callbacks when the hook triggers. Default: _`array()`_.
- **$group** (string) The group the job is assigned to. Default: _''_.

### Return value

`(integer|boolean)` The timestamp for the next occurrence of a pending scheduled action, true for an async or in-progress action or false if there is no matching action.

## Function Reference / `as_has_scheduled_action()`

### Description

Check if there is a scheduled action in the queue, but more efficiently than as_next_scheduled_action(). It's recommended to use this function when you need to know whether a specific action is currently scheduled. _Available since 3.3.0._

### Usage

```php
as_has_scheduled_action( $hook, $args, $group );
```

### Parameters

- **$hook** (string)(required) Name of the action hook. Default: _none_.
- **$args** (array) Arguments passed to callbacks when the hook triggers. Default: _`array()`_.
- **$group** (string) The group the job is assigned to. Default: _''_.

### Return value

`(boolean)` True if a matching action is pending or in-progress, false otherwise.

## Function Reference / `as_get_scheduled_actions()`

### Description

Find scheduled actions.

### Usage

```php
as_get_scheduled_actions( $args, $return_format );
```

### Parameters

- **$args** (array) Arguments to search and filter results by. Possible arguments, with their default values:
    * `'hook' => ''` - the name of the action that will be triggered
    * `'args' => NULL` - the args array that will be passed with the action
    * `'date' => NULL` - the scheduled date of the action. Expects a DateTime object, a unix timestamp, or a string that can parsed with strtotime().
    * `'date_compare' => '<=`' - operator for testing "date". accepted values are '!=', '>', '>=', '<', '<=', '='
    * `'modified' => NULL` - the date the action was last updated. Expects a DateTime object, a unix timestamp, or a string that can parsed with strtotime().
    * `'modified_compare' => '<='` - operator for testing "modified". accepted values are '!=', '>', '>=', '<', '<=', '='
    * `'group' => ''` - the group the action belongs to
    * `'status' => ''` - ActionScheduler_Store::STATUS_COMPLETE or ActionScheduler_Store::STATUS_PENDING
    * `'claimed' => NULL` - TRUE to find claimed actions, FALSE to find unclaimed actions, a string to find a specific claim ID
    * `'per_page' => 5` - Number of results to return
    * `'offset' => 0`
    * `'orderby' => 'date'` - accepted values are 'hook', 'group', 'modified', or 'date'
    * `'order' => 'ASC'`
- **$return_format** (string) The format in which to return the scheduled actions: 'OBJECT', 'ARRAY_A', or 'ids'. Default: _'OBJECT'_.

### Return value

`(array)` Array of action rows matching the criteria specified with `$args`.

## Function Reference / `as_supports()`

### Description

Check if a specific feature is supported by the current version of Action Scheduler.

*Available since 3.9.3.*

### Usage

```php
as_supports( $feature );
```

### Parameters

**$feature** (string) (required)  
The feature to check support for.  
Currently supported:
- `'ensure_recurring_actions_hook'` — Indicates support for the `action_scheduler_ensure_recurring_actions` hook.
- `'uninstall_bootstrap'` - Indicates that Action Scheduler skips its runtime scheduling (the `action_scheduler_run_queue` cron event, the async request dispatcher and the housekeeping action) when it is initialized from a plugin's `uninstall.php`.

### Return value

`(boolean)`  
True if the feature is supported, false otherwise.

### Example

```php
if ( as_supports( 'ensure_recurring_actions_hook' ) ) {
    // Safe to depend on the 'action_scheduler_ensure_recurring_actions' hook.
    add_action( 'action_scheduler_ensure_recurring_actions', 'my_plugin_schedule_my_recurring_action' );
}
```
