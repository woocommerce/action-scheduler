# Releasing Action Scheduler

Releases run through the **Release Action Scheduler** workflow (`.github/workflows/release.yml`), triggered manually from the Actions tab.

## Part 1: Release prep

1. Open the [**Release Action Scheduler** workflow](https://github.com/woocommerce/action-scheduler/actions/workflows/release.yml).
2. Click **Run workflow**, set **Version** to the release number (`x.y.z`), set **Which step** to `release prep`, leave **Dry run** checked, and click **Run workflow**.
3. Open the run and read the diff in the job log. It also shows the changelog and the version bump.
4. If the preview looks right, run the workflow again the same way but with **Dry run** unchecked.
   Note that the changelog doesn't have to be perfect here, as it can be edited later.
5. Confirm that branch `release/x.y.z` was created and a PR `Release prep: x.y.z` PR opened.
6. On that PR, curate the changelog in `readme.txt` and confirm `Requires at least`, `Requires PHP`, and `Tested up to` are all correct.
7. Merge the prep PR into `release/x.y.z`.

## Part 2: Release

1. Open the [**Release Action Scheduler** workflow](https://github.com/woocommerce/action-scheduler/actions/workflows/release.yml) again.
2. Click **Run workflow**, use the same **Version** from Part 1, set **Which step** to `release`, leave **Dry run** checked, and click **Run workflow**.
3. The dry run syncs `changelog.txt`, builds the package, and stages the WordPress.org upload without committing. Take a look at the SVN diff in the workflow logs and download the `action-scheduler-x.y.z-dryrun` artifact to check the generated plugin file.
4. If everything looks good, run the workflow again with **Dry run** unchecked.
6. Confirm that SVN's trunk on wporg was updated and that tag `x.y.z` was created both on [WordPress.org SVN](https://plugins.svn.wordpress.org/action-scheduler/) and in this repo.
7. Fetch WordPress.org credentials for one of our accounts from the secret store and [confirm the release](https://wordpress.org/plugins/developers/releases/).
8. Merge the `Merge release/x.y.z into trunk` PR that was opened to bring the version bump and changelog back into `trunk`.
