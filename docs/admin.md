---
description: Learn how to administer background jobs with the Action Scheduler job queue for WordPress.
---
# Scheduled Actions Administration Screen

Action Scheduler has a built in administration screen for monitoring, debugging and manually triggering scheduled actions.

The administration interface is accessible through both:

1. **Tools > Scheduled Actions**
1. **WooCommerce > Status > Scheduled Actions**, when WooCommerce is installed.

Among other tasks, from the admin screen you can:

* Run a pending action.
* View the scheduled actions with a specific status, like all actions which have failed or are in-progress [(screenshot)](https://cldup.com/NNTwE88Xl8.png).
* View the log entries for a specific action to find out why it failed.
* Sort scheduled actions by hook name, scheduled date, group, claim ID or status.
* Search for actions by hook, args, action ID or claim ID.

Long arguments (more than 500 characters, as displayed) are collapsed by default, so that they do not stretch the table. To expand them, select the summary shown in their place in the **Arguments** column, which states their length.

Still have questions? Check out the [FAQ](/faq).

![](https://cldup.com/5BA2BNB1sw.png)
