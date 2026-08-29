<?php
/**
 * Main plugin bootstrap.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP;

use CodeLearner\Divi5WooCommerceMCP\MCP\Server;
use CodeLearner\Divi5WooCommerceMCP\WordPress\Abilities;

final class Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		if ( ! function_exists( 'wp_register_ability' ) ) {
			add_action( 'admin_notices', array( self::class, 'render_requirements_notice' ) );
			return;
		}

		Abilities::hooks();
		Server::boot();
	}

	public static function render_requirements_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Divi 5 + WooCommerce MCP requires WordPress 6.9 or newer because it uses the core Abilities API.', 'divi-5-woocommerce-mcp' )
		);
	}

	private function __construct() {
	}
}
