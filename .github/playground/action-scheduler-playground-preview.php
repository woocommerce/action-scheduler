<?php
/**
 * Sample actions and a status notice for the WordPress Playground previews of pull requests.
 *
 * This is not part of Action Scheduler and never ships: .github is export-ignored. The "Test build on PR"
 * workflow uploads it next to the plugin zip, and the preview Blueprint copies it into mu-plugins.
 *
 * @package ActionScheduler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'action_scheduler_init', 'action_scheduler_playground_preview_seed' );
add_action( 'admin_notices', 'action_scheduler_playground_preview_notice' );
add_action( 'admin_post_action_scheduler_playground_preview_run', 'action_scheduler_playground_preview_run_queue' );
add_action( 'action_scheduler_playground_preview_fails', 'action_scheduler_playground_preview_fail' );

// An action whose hook has no callback is marked as failed, so the other sample hooks get one that does nothing.
foreach ( array( 'succeeds', 'hourly', 'nightly' ) as $action_scheduler_playground_preview_hook ) {
	add_action( 'action_scheduler_playground_preview_' . $action_scheduler_playground_preview_hook, 'action_scheduler_playground_preview_succeed' );
}
unset( $action_scheduler_playground_preview_hook );

/**
 * The callback of the sample actions that succeed.
 */
function action_scheduler_playground_preview_succeed() {}

/**
 * The callback of the sample action that fails.
 *
 * @throws RuntimeException Always.
 */
function action_scheduler_playground_preview_fail() {
	throw new RuntimeException( 'This sample action always fails.' );
}

/**
 * Creates the sample actions, once: pending, past-due, recurring, complete, failed and canceled.
 *
 * The complete and failed ones go through the runner, so their logs are the real ones. The migration from
 * the legacy store is marked as complete first, so the preview does not open on the "migration in progress"
 * notice of a new site.
 */
function action_scheduler_playground_preview_seed() {
	if ( get_option( 'action_scheduler_playground_preview_seeded' ) ) {
		return;
	}

	update_option( 'action_scheduler_playground_preview_seeded', 'yes', false );

	$group = 'playground-preview';
	$now   = time();

	try {
		// A new site has nothing to migrate from the legacy store, but it only finds that out when the queue runs.
		if ( ! ActionScheduler_DataController::is_migration_complete() ) {
			ActionScheduler_DataController::mark_migration_complete();
		}

		// History first: once the past-due action exists, the runner would pick it up too.
		$ran = array(
			as_schedule_single_action( $now - HOUR_IN_SECONDS, 'action_scheduler_playground_preview_succeeds', array( 'example' => 'complete' ), $group ),
			as_schedule_single_action( $now - HOUR_IN_SECONDS, 'action_scheduler_playground_preview_fails', array(), $group ),
		);

		foreach ( $ran as $action_id ) {
			ActionScheduler::runner()->process_action( $action_id, 'Playground preview' );
		}

		as_schedule_single_action( $now + DAY_IN_SECONDS, 'action_scheduler_playground_preview_canceled', array(), $group );
		as_unschedule_action( 'action_scheduler_playground_preview_canceled', array(), $group );

		as_schedule_single_action( $now - 5 * MINUTE_IN_SECONDS, 'action_scheduler_playground_preview_succeeds', array( 'example' => 'past-due' ), $group );
		as_schedule_single_action( $now + HOUR_IN_SECONDS, 'action_scheduler_playground_preview_succeeds', array( 'example' => 'pending' ), $group );
		as_schedule_single_action( $now + 2 * HOUR_IN_SECONDS, 'action_scheduler_playground_preview_fails', array(), $group );
		as_schedule_recurring_action( $now + 30 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 'action_scheduler_playground_preview_hourly', array(), $group );
		as_schedule_cron_action( $now, '0 3 * * *', 'action_scheduler_playground_preview_nightly', array(), $group );
	} catch ( Throwable $e ) {
		update_option( 'action_scheduler_playground_preview_error', $e->getMessage(), false );
	}
}

/**
 * Runs the queue the way WP-Cron would, because WordPress Playground has WP-Cron disabled in the browser.
 */
function action_scheduler_playground_preview_run_queue() {
	check_admin_referer( 'action_scheduler_playground_preview_run' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You are not allowed to run the queue.' );
	}

	$processed = class_exists( 'ActionScheduler', false ) ? (int) ActionScheduler::runner()->run( 'Playground preview' ) : 0;

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'                       => 'action-scheduler',
				'playground_preview_run_ran' => $processed,
			),
			admin_url( 'tools.php' )
		)
	);
	exit;
}

/**
 * Says, on the Scheduled Actions screens, which copy of Action Scheduler is running and how to run actions.
 */
function action_scheduler_playground_preview_notice() {
	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->id, array( 'tools_page_action-scheduler', 'woocommerce_page_wc-status' ), true ) ) {
		return;
	}

	if ( ! class_exists( 'ActionScheduler', false ) || ! class_exists( 'ActionScheduler_SystemInformation' ) ) {
		wp_admin_notice( '<strong>Playground preview:</strong> Action Scheduler did not load, or the active copy is too old to say where it comes from.', array( 'type' => 'error' ) );
		return;
	}

	$path     = ActionScheduler_SystemInformation::active_source_path();
	$version  = ActionScheduler_Versions::instance()->latest_version();
	$pr_build = 0 === strpos( $path, trailingslashit( WP_PLUGIN_DIR ) . 'action-scheduler' );
	$error    = get_option( 'action_scheduler_playground_preview_error' );
	$run_url  = wp_nonce_url( admin_url( 'admin-post.php?action=action_scheduler_playground_preview_run' ), 'action_scheduler_playground_preview_run' );

	$message = $pr_build
		? sprintf( 'The pull request build is the active copy of Action Scheduler (version %1$s, from %2$s).', esc_html( $version ), '<code>' . esc_html( $path ) . '</code>' )
		: sprintf( 'The pull request build is <strong>not</strong> the active copy of Action Scheduler: version %1$s from %2$s is running, because it registered a higher version.', esc_html( $version ), '<code>' . esc_html( $path ) . '</code>' );

	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		$message .= ' WP-Cron is disabled in WordPress Playground, so pending actions do not run on their own here: use the <em>Run</em> link under an action, or run the queue.';
	}

	$message .= sprintf( ' <a class="button button-small" href="%s">Run the queue now</a>', esc_url( $run_url ) );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
	if ( isset( $_GET['playground_preview_run_ran'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$message .= sprintf( ' The queue run processed %d action(s).', absint( $_GET['playground_preview_run_ran'] ) );
	}

	if ( $error ) {
		$message .= sprintf( ' Creating the sample actions failed: %s', esc_html( $error ) );
	}

	wp_admin_notice(
		'<strong>Playground preview:</strong> ' . $message,
		array( 'type' => $pr_build && ! $error ? 'info' : 'error' )
	);
}
