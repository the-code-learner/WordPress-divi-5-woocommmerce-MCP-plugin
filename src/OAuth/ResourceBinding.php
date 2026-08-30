<?php
/**
 * OAuth protected-resource binding for the single MCP resource.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\OAuth;

use WPMedia\MCP\OAuth\Logging\McpLogger;

final class ResourceBinding {
	/**
	 * Validate ChatGPT/MCP resource indicators before the upstream OAuth router.
	 *
	 * The embedded OAuth server exposes exactly one protected resource. The
	 * upstream package predates MCP's resource-indicator requirement and does not
	 * retain the `resource` request parameter. Enforcing the exact same canonical
	 * resource independently at both authorization and token boundaries, while
	 * the upstream token issuer fixes the access-token `aud` to this same URL,
	 * provides the required single-resource binding without weakening PKCE.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'validate_request' ), -10 );
	}

	/**
	 * Validate the resource parameter on OAuth authorization and token requests.
	 */
	public static function validate_request(): void {
		$endpoint = (string) get_query_var( 'mcp_oauth_endpoint', '' );
		if ( ! in_array( $endpoint, array( 'authorize', 'token' ), true ) ) {
			return;
		}

		$expected = get_rest_url( null, 'mcp/mcp-oauth-server' );
		$provided = 'authorize' === $endpoint ? self::authorization_resource() : self::token_resource();

		if ( self::matches( $provided, $expected ) ) {
			return;
		}

		McpLogger::log(
			'RESOURCE',
			'rejected: OAuth resource missing or does not match protected MCP resource',
			array(
				'endpoint'     => $endpoint,
				'has_resource' => '' !== $provided ? 'yes' : 'no',
				'provided'     => $provided,
				'expected'     => $expected,
			)
		);

		self::send_invalid_target();
	}

	/**
	 * Exact resource comparison, isolated for unit coverage.
	 */
	public static function matches( string $provided, string $expected ): bool {
		return '' !== $provided && '' !== $expected && hash_equals( $expected, $provided );
	}

	private static function authorization_resource(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- external OAuth authorization request; state + PKCE provide request integrity.
		return esc_url_raw( wp_unslash( $_GET['resource'] ?? '' ) );
	}

	private static function token_resource(): string {
		$content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) ) : '';

		if ( false !== strpos( $content_type, 'application/json' ) ) {
			$raw  = substr( (string) file_get_contents( 'php://input' ), 0, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- OAuth request body, not a filesystem path.
			$body = json_decode( '' !== $raw ? $raw : '{}', true );
			if ( ! is_array( $body ) ) {
				return '';
			}

			return isset( $body['resource'] ) && is_string( $body['resource'] ) ? esc_url_raw( $body['resource'] ) : '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- OAuth token requests use grant credentials and PKCE, not WordPress nonces.
		return esc_url_raw( wp_unslash( $_POST['resource'] ?? '' ) );
	}

	private static function send_invalid_target(): void {
		nocache_headers();
		wp_send_json(
			array(
				'error'             => 'invalid_target',
				'error_description' => 'The OAuth resource must match the protected MCP resource.',
			),
			400
		);
	}

	private function __construct() {
	}
}
