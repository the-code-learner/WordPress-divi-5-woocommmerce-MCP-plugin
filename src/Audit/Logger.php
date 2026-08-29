<?php
/**
 * Audit logging seam.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Audit;

final class Logger {
	/**
	 * Bootstrap placeholder for the future persistent MCP audit log.
	 *
	 * @param string               $action  Ability/action identifier.
	 * @param array<string, mixed> $context Safe structured context.
	 */
	public static function record( string $action, array $context = array() ): void {
		/**
		 * Fires when the plugin records an MCP audit event.
		 *
		 * Persistent storage is intentionally not implemented in the bootstrap.
		 *
		 * @param string               $action  Ability/action identifier.
		 * @param array<string, mixed> $context Safe structured context.
		 */
		do_action( 'divi5_wc_mcp_audit_event', $action, $context );
	}

	private function __construct() {
	}
}
