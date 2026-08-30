<?php
/**
 * MCP Adapter bootstrap.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\MCP;

use CodeLearner\Divi5WooCommerceMCP\OAuth\Bootstrap as OAuthBootstrap;
use WP\MCP\Core\McpAdapter;

final class Server {
	public static function boot(): void {
		if ( ! class_exists( McpAdapter::class ) ) {
			add_action( 'admin_notices', array( self::class, 'render_missing_adapter_notice' ) );
			return;
		}

		OAuthBootstrap::boot();
		McpAdapter::instance();
	}

	public static function render_missing_adapter_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'MCP Bridge for Divi 5 and WooCommerce could not load the official WordPress MCP Adapter package.', 'mcp-bridge-for-divi-woocommerce' )
		);
	}

	private function __construct() {
	}
}
