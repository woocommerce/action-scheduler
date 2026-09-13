<?php
/**
 * Opt-in live integration test for grouped HybridStore claims.
 *
 * Usage:
 * ACTION_SCHEDULER_LIVE_INTEGRATION=1 ACTION_SCHEDULER_EXPECTED_PATH=/path/to/action-scheduler wp --path=/path/to/wordpress eval-file tests/integration/hybrid-store-group-claim.php
 *
 * @package ActionScheduler
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( '1' !== getenv( 'ACTION_SCHEDULER_LIVE_INTEGRATION' ) ) {
	WP_CLI::error( 'Set ACTION_SCHEDULER_LIVE_INTEGRATION=1 to run this live integration test.' );
}

$expected_path = realpath( getenv( 'ACTION_SCHEDULER_EXPECTED_PATH' ) );
if ( false === $expected_path ) {
	WP_CLI::error( 'Set ACTION_SCHEDULER_EXPECTED_PATH to the deployed Action Scheduler directory.' );
}

$store = ActionScheduler::store();
if ( ! $store instanceof ActionScheduler_HybridStore ) {
	WP_CLI::error( 'The active Action Scheduler store is not ActionScheduler_HybridStore.' );
}

$store_reflection  = new ReflectionClass( $store );
$hybrid_reflection = new ReflectionClass( 'ActionScheduler_HybridStore' );
$hybrid_path       = $hybrid_reflection->getFileName();
WP_CLI::line( 'Active store: ' . get_class( $store ) );
WP_CLI::line( 'Active store path: ' . $store_reflection->getFileName() );
WP_CLI::line( 'Active HybridStore path: ' . $hybrid_path );
WP_CLI::line( 'Expected Action Scheduler path: ' . $expected_path );

if ( 0 !== strpos( $hybrid_path, trailingslashit( $expected_path ) ) ) {
	WP_CLI::error( 'The active HybridStore does not come from ACTION_SCHEDULER_EXPECTED_PATH.' );
}

$token             = str_replace( '-', '', wp_generate_uuid4() );
$hook              = 'action_scheduler_live_' . $token;
$group             = 'as-live-' . $token;
$other_group       = 'as-live-other-' . $token;
$missing_group     = 'as-live-missing-' . $token;
$action_ids        = array();
$claims            = array();
$integration_error = null;

try {
	// Future-dated fixtures cannot be picked up by a normal queue runner.
	$timestamp = time() + HOUR_IN_SECONDS;
	$before    = as_get_datetime_object( '+2 hours' );

	$action_ids['group'] = as_schedule_single_action( $timestamp, $hook, array( 'group' ), $group );
	$action_ids['other'] = as_schedule_single_action( $timestamp, $hook, array( 'other' ), $other_group );

	foreach ( $action_ids as $action_id ) {
		if ( empty( $action_id ) ) {
			throw new RuntimeException( 'Unable to create a live integration fixture.' );
		}
	}

	$claims[] = $store->stake_claim( 10, $before, array( $hook ), $group );
	if ( array( $action_ids['group'] ) !== $claims[0]->get_actions() ) {
		throw new RuntimeException( 'Primary-only group claim mismatch: expected ' . wp_json_encode( array( $action_ids['group'] ) ) . ', received ' . wp_json_encode( $claims[0]->get_actions() ) . '.' );
	}
	if ( ActionScheduler_Store::STATUS_PENDING !== $store->get_status( $action_ids['other'] ) ) {
		throw new RuntimeException( 'Primary-only group claim changed an action in another group.' );
	}
	$store->release_claim( $claims[0] );
	array_pop( $claims );

	try {
		$store->stake_claim( 10, $before, array( $hook ), $missing_group );
		throw new RuntimeException( 'Missing group claim did not throw an InvalidArgumentException.' );
	} catch ( InvalidArgumentException $caught_error ) {
		if ( 'The group "' . $missing_group . '" does not exist.' !== $caught_error->getMessage() ) {
			throw $caught_error;
		}
	}
} catch ( Throwable $caught_error ) {
	$integration_error = $caught_error;
} finally {
	foreach ( $claims as $claim ) {
		try {
			$store->release_claim( $claim );
		} catch ( Throwable $cleanup_error ) {
			$integration_error = $integration_error ? $integration_error : $cleanup_error;
		}
	}

	foreach ( $action_ids as $action_id ) {
		if ( ! empty( $action_id ) ) {
			try {
				$store->delete_action( $action_id );
			} catch ( Throwable $cleanup_error ) {
				$integration_error = $integration_error ? $integration_error : $cleanup_error;
			}
		}
	}
}

if ( $integration_error ) {
	WP_CLI::error( $integration_error->getMessage() );
}

WP_CLI::success( 'HybridStore grouped claim integration test passed.' );
