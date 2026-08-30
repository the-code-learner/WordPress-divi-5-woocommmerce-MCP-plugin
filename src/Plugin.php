<?php
/**
 * Main plugin bootstrap.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP;

use CodeLearner\Divi5WooCommerceMCP\Admin\Settings;
use CodeLearner\Divi5WooCommerceMCP\Divi\Abilities as DiviAbilities;
use CodeLearner\Divi5WooCommerceMCP\Divi\NativeModuleAbilities;
use CodeLearner\Divi5WooCommerceMCP\MCP\Server;
use CodeLearner\Divi5WooCommerceMCP\Telemetry\Telemetry;
use CodeLearner\Divi5WooCommerceMCP\Updates\GitHubUpdater;
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

		Settings::hooks();
		GitHubUpdater::boot();
		Telemetry::boot();
		Abilities::hooks();
		DiviAbilities::hooks();
		NativeModuleAbilities::hooks();
		Server::boot();
	}

	public static function render_requirements_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'MCP Bridge for Divi 5 and WooCommerce requires WordPress 6.9 or newer because it uses the core Abilities API.', 'mcp-bridge-for-divi-woocommerce' )
		);
	}

	private function __construct() {
	}
}
