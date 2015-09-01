<?php

/**
 * Plugin Name: Action Scheduler
 * Plugin URI: https://github.com/flightless/action-scheduler
 * Description: A robust action scheduler for WordPress
 * Author: Flightless
 * Author URI: http://flightless.us/
 * Version: 1.4-dev
 */

if ( ! class_exists( 'ActionScheduler_Versions' ) ) {
	require_once( 'classes/ActionScheduler_Versions.php' );
	add_action( 'plugins_loaded', array( 'ActionScheduler_Versions', 'initialize_latest_version' ), 1, 0 );
}
