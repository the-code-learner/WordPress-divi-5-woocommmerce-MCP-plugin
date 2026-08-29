<?php
/**
 * MCP Adapter bootstrap.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\MCP;

use WP\MCP\Core\McpAdapter;

final class Server {
	public static function boot(): void {
		if ( ! class_exists( McpAdapter::class ) ) {
			add_action( 'admin_notices', array( self::class, 'render_missing_adapter_notice' ) );
			return;
		}

		McpAdapter::instance();
	}

	public static function render_missing_adapter_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Divi 5 + WooCommerce MCP could not load the official WordPress MCP Adapter package.', 'divi-5-woocommerce-mcp' )
		);
	}

	private function __construct() {
	}
}
