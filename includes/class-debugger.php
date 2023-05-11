<?php
/**
 * Newspack Hub Debugger methods.
 *
 * @package Newspack
 */

namespace Newspack_Hub;

/**
 * Class with basic debugger methods
 */
class Debugger {

	/**
	 * Logs a message to the error log
	 *
	 * @param mixed $message If not a string, will be printed with print_r.
	 * @return void
	 */
	public static function log( $message ) {
		if ( ! defined( 'NEWSPACK_HUB_DEBUG' ) || ! NEWSPACK_HUB_DEBUG ) {
			return;
		}
		$caller = debug_backtrace()[0]; //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$pid    = getmypid();
		if ( ! is_string( $message ) || ! is_int( $message ) ) {
			$message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
		error_log( "[{$pid}] {$caller['file']}:{$caller['line']} {$message}" ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

}
