<?php
/**
 * ChatGPT-compatible OAuth discovery metadata.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\OAuth;

final class Discovery {
	/**
	 * Serve authorization-server metadata before the upstream OAuth library handler.
	 *
	 * The upstream library already issues and rotates refresh tokens. ChatGPT also
	 * expects refresh capability to be advertised through an offline-access scope,
	 * so this response adds that capability while preserving the upstream endpoints.
	 */
	public static function maybe_serve_authorization_server_metadata(): void {
		if ( ! Bootstrap::is_https_url( home_url() ) ) {
			return;
		}

		if ( 'authorization-server' !== (string) get_query_var( 'mcp_oauth_discovery', '' ) ) {
			return;
		}

		wp_send_json( self::authorization_server_metadata( home_url() ) );
	}

	/**
	 * Build RFC 8414 metadata for the embedded OAuth 2.1 authorization server.
	 *
	 * @return array<string, mixed>
	 */
	public static function authorization_server_metadata( string $base_url ): array {
		$base_url = rtrim( $base_url, '/' );

		return array(
			'issuer'                                => $base_url,
			'authorization_endpoint'                => $base_url . '/oauth/authorize',
			'token_endpoint'                        => $base_url . '/oauth/token',
			'revocation_endpoint'                   => $base_url . '/oauth/revoke',
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'scopes_supported'                      => array( 'mcp', 'offline_access' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
			'client_id_metadata_document_supported' => true,
			'authorization_response_iss_parameter_supported' => true,
		);
	}

	private function __construct() {
	}
}
