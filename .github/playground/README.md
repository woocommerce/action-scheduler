# Test builds for pull requests

The **Test build on PR** workflow (`.github/workflows/pr-test-build.yml`) builds the plugin zip for every pull request with `npm run build`, the command the release uses, and posts a "Test the build" comment with WordPress Playground links to try it. Nothing in this folder ships: `.github` is `export-ignore`d.

## What the comment offers

Two links that open the build in the visitor's browser, on **Tools > Scheduled Actions**:

- **On its own.** The pull request build is the only copy of Action Scheduler on the site.
- **With WooCommerce.** WooCommerce bundles its own copy, and the copy that registers the higher version runs. On a tie the pull request build wins: both copies declare the same registration function, and the plugin in `action-scheduler/` is loaded before `woocommerce/`.

WordPress Playground can only fetch public URLs, so the links go through its artifact proxy (`playground.wordpress.net/plugin-proxy.php`), which serves the artifacts of this workflow for a pull request. The proxy finds the workflow by its `name:`, which is why the workflow passes `$GITHUB_WORKFLOW` instead of a literal. The artifact is named after the run ID and kept for 14 days, so a link stops working 14 days after the last build.

The artifact also carries `action-scheduler-playground-preview.php`, which the Blueprint copies into `mu-plugins`. It:

- creates sample actions in the `playground-preview` group: pending, past-due, recurring, complete, failed and canceled. The complete and failed ones go through the queue runner, so their logs are real;
- marks the migration from the legacy store as complete, so the preview does not open on the "migration in progress" notice that a new site shows until its queue first runs;
- shows a notice on the Scheduled Actions screens that says which copy of Action Scheduler is running, and turns into an error when it is not the pull request build;
- adds a **Run the queue now** button to that notice.

## Limits

- **Actions do not run on their own in WordPress Playground.** It disables WP-Cron, and the async request runner does not start a queue there either. Use the **Run** link under an action, the **Run the queue now** button, or `wp action-scheduler run` in Playground's **Dev Tools > Terminal**.
- **Pull requests from forks and from Dependabot get no comment.** Their token is read-only, so the same text goes to the workflow run's summary instead.
- **A link can serve another pull request's build.** The proxy looks a build up by branch name, so a fork whose branch has the same name can be served instead. The preview never asks for credentials, and no Blueprint may contain a secret.
- **The version in the plugin header is a label.** The workflow stamps `x.y.z-dev_PR-<number>_commit-<sha>` on the plugin header and the readme. The version the code registers with `ActionScheduler_Versions`, which decides which copy runs, is left as the pull request has it.

## Running the preview locally

From a checkout, with Node 20.18 or newer:

```bash
npx @wp-playground/cli@latest server --auto-mount --login --mount=.github/playground/action-scheduler-playground-preview.php:/wordpress/wp-content/mu-plugins/action-scheduler-playground-preview.php
```

Then open `/wp-admin/tools.php?page=action-scheduler` on the address it prints. This mounts the working copy, not a built zip.
