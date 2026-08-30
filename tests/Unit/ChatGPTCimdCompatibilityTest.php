<?php
/**
 * ChatGPT CIMD compatibility tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\OAuth\ChatGPTCimdCompatibility;
use PHPUnit\Framework\TestCase;

final class ChatGPTCimdCompatibilityTest extends TestCase {
	public function test_negotiates_none_from_current_chatgpt_cimd_choices(): void {
		$url      = 'https://chatgpt.com/oauth/client.json';
		$response = $this->response_for(
			$url,
			array(
				'token_endpoint_auth_method'            => 'private_key_jwt',
				'token_endpoint_auth_methods_supported' => array( 'none', 'private_key_jwt' ),
			)
		);

		$normalized = ChatGPTCimdCompatibility::normalize_cimd_response( $response, array(), $url );
		$document   = json_decode( (string) $normalized['body'], true );

		self::assertSame( 'none', $document['token_endpoint_auth_method'] );
		self::assertSame( array( 'none', 'private_key_jwt' ), $document['token_endpoint_auth_methods_supported'] );
		self::assertSame( $url, $document['client_id'] );
	}

	public function test_callback_specific_chatgpt_cimd_uses_same_negotiation(): void {
		$url      = 'https://chatgpt.com/oauth/connector-123/client.json';
		$response = $this->response_for(
			$url,
			array(
				'token_endpoint_auth_method'            => 'private_key_jwt',
				'token_endpoint_auth_methods_supported' => array( 'none', 'private_key_jwt' ),
			)
		);

		$normalized = ChatGPTCimdCompatibility::normalize_cimd_response( $response, array(), $url );
		$document   = json_decode( (string) $normalized['body'], true );

		self::assertSame( 'none', $document['token_endpoint_auth_method'] );
	}

	public function test_does_not_invent_none_when_client_does_not_advertise_it(): void {
		$url      = 'https://chatgpt.com/oauth/client.json';
		$response = $this->response_for(
			$url,
			array(
				'token_endpoint_auth_method'            => 'private_key_jwt',
				'token_endpoint_auth_methods_supported' => array( 'private_key_jwt' ),
			)
		);

		self::assertSame( $response, ChatGPTCimdCompatibility::normalize_cimd_response( $response, array(), $url ) );
	}

	public function test_preserves_exact_client_id_self_binding(): void {
		$url      = 'https://chatgpt.com/oauth/client.json';
		$response = $this->response_for(
			'https://chatgpt.com/oauth/other/client.json',
			array(
				'token_endpoint_auth_method'            => 'private_key_jwt',
				'token_endpoint_auth_methods_supported' => array( 'none', 'private_key_jwt' ),
			)
		);

		self::assertSame( $response, ChatGPTCimdCompatibility::normalize_cimd_response( $response, array(), $url ) );
	}

	public function test_does_not_modify_non_chatgpt_metadata(): void {
		$url      = 'https://example.com/oauth/client.json';
		$response = $this->response_for(
			$url,
			array(
				'token_endpoint_auth_method'            => 'private_key_jwt',
				'token_endpoint_auth_methods_supported' => array( 'none', 'private_key_jwt' ),
			)
		);

		self::assertSame( $response, ChatGPTCimdCompatibility::normalize_cimd_response( $response, array(), $url ) );
	}

	public function test_stable_chatgpt_client_is_added_as_verified_publisher(): void {
		$publishers = ChatGPTCimdCompatibility::add_trusted_publisher( array() );

		self::assertSame( 'chatgpt.com', $publishers['chatgpt']['host'] );
		self::assertSame( array( 'https://chatgpt.com/oauth/client.json' ), $publishers['chatgpt']['client_ids'] );
	}

	public function test_chatgpt_cimd_url_shape_is_narrow(): void {
		self::assertTrue( ChatGPTCimdCompatibility::is_chatgpt_cimd_url( 'https://chatgpt.com/oauth/client.json' ) );
		self::assertTrue( ChatGPTCimdCompatibility::is_chatgpt_cimd_url( 'https://chatgpt.com/oauth/abc/client.json' ) );
		self::assertFalse( ChatGPTCimdCompatibility::is_chatgpt_cimd_url( 'http://chatgpt.com/oauth/client.json' ) );
		self::assertFalse( ChatGPTCimdCompatibility::is_chatgpt_cimd_url( 'https://example.com/oauth/client.json' ) );
		self::assertFalse( ChatGPTCimdCompatibility::is_chatgpt_cimd_url( 'https://chatgpt.com/oauth/jwks.json' ) );
	}

	/**
	 * @param array<string, mixed> $overrides CIMD fields to override.
	 * @return array<string, mixed>
	 */
	private function response_for( string $client_id, array $overrides ): array {
		$document = array_merge(
			array(
				'client_id'     => $client_id,
				'client_name'   => 'ChatGPT',
				'redirect_uris' => array( 'https://chatgpt.com/connector_platform_oauth_redirect' ),
				'grant_types'   => array( 'authorization_code', 'refresh_token' ),
				'response_types' => array( 'code' ),
			),
			$overrides
		);

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => (string) json_encode( $document ),
		);
	}
}
