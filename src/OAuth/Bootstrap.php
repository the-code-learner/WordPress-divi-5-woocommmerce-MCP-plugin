<?php
/**
 * OAuth server bootstrap and transport guardrails.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\OAuth;

use WPMedia\MCP\OAuth\Bootstrap as OAuthServerBootstrap;

final class Bootstrap {
	/**
	 * Register the OAuth layer without replacing the existing WordPress-authenticated MCP server.
	 */
	public static function boot(): void {
		add_filter( 'wpmedia_mcp_oauth_server_enabled', array( self::class, 'require_https' ), PHP_INT_MAX );
		add_action( 'template_redirect', array( Discovery::class, 'maybe_serve_authorization_server_metadata' ), 1 );

		if ( ! class_exists( OAuthServerBootstrap::class ) ) {
			add_action( 'admin_notices', array( self::class, 'render_missing_oauth_notice' ) );
			return;
		}

		if ( ! self::is_https_url( home_url() ) ) {
			add_action( 'admin_notices', array( self::class, 'render_https_required_notice' ) );
		}

		OAuthServerBootstrap::instance();
	}

	/**
	 * Keep OAuth disabled unless the canonical WordPress Site Address uses HTTPS.
	 *
	 * @param mixed $enabled Upstream enable flag.
	 */
	public static function require_https( $enabled ): bool {
		return (bool) $enabled && self::is_https_url( home_url() );
	}

	/**
	 * Check a canonical URL without requiring WordPress runtime helpers.
	 */
	public static function is_https_url( string $url ): bool {
		return 1 === preg_match( '#^https://#i', trim( $url ) );
	}

	public static function render_missing_oauth_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'MCP Bridge for Divi 5 and WooCommerce could not load its OAuth 2.1 support package. The existing WordPress-authenticated MCP endpoint remains available.', 'mcp-bridge-for-divi-woocommerce' )
		);
	}

	public static function render_https_required_notice(): void {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'MCP OAuth is disabled because the WordPress Site Address is not HTTPS. Configure an HTTPS Site Address before connecting an OAuth MCP client.', 'mcp-bridge-for-divi-woocommerce' )
		);
	}

	private function __construct() {
	}
}
