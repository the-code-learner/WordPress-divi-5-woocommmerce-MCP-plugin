<?php
/**
 * Non-blocking telemetry HTTP client.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Telemetry;

use CodeLearner\Divi5WooCommerceMCP\Admin\Settings;

final class Client {
	private const HEARTBEAT_ENDPOINT = 'https://divi-mcp-telemetry.partnerships-536.workers.dev/v1/heartbeat';

	private const ERROR_ENDPOINT = 'https://divi-mcp-telemetry.partnerships-536.workers.dev/v1/error';

	private const HEARTBEAT_KEYS = array(
		'install_id'           => true,
		'plugin_version'       => true,
		'wp_version'           => true,
		'php_version'          => true,
		'divi_detected'        => true,
		'woocommerce_detected' => true,
	);

	private const ERROR_KEYS = array(
		'install_id'     => true,
		'plugin_version' => true,
		'wp_version'     => true,
		'php_version'    => true,
		'error_class'    => true,
		'error_code'     => true,
		'message'        => true,
		'frames'         => true,
		'fingerprint'    => true,
	);

	/** @var callable */
	private $transport;

	/** @var callable */
	private $usage_enabled;

	/** @var callable */
	private $error_enabled;

	/** @var callable */
	private $encoder;

	public function __construct(
		?callable $transport = null,
		?callable $usage_enabled = null,
		?callable $error_enabled = null,
		?callable $encoder = null
	) {
		$this->transport     = $transport ?? static function ( string $url, array $args ) {
			return wp_remote_post( $url, $args );
		};
		$this->usage_enabled = $usage_enabled ?? static function (): bool {
			return Settings::is_usage_telemetry_enabled();
		};
		$this->error_enabled = $error_enabled ?? static function (): bool {
			return Settings::is_error_reporting_enabled();
		};
		$this->encoder       = $encoder ?? static function ( array $payload ) {
			return wp_json_encode( $payload );
		};
	}

	/**
	 * @param array<string, mixed> $payload Heartbeat payload.
	 */
	public function send_heartbeat( array $payload ): bool {
		if ( ! (bool) call_user_func( $this->usage_enabled ) ) {
			return false;
		}

		return $this->post_json( self::HEARTBEAT_ENDPOINT, self::filter_heartbeat_payload( $payload ) );
	}

	/**
	 * @param array<string, mixed> $payload Error payload.
	 */
	public function send_error( array $payload ): bool {
		if ( ! (bool) call_user_func( $this->error_enabled ) ) {
			return false;
		}

		return $this->post_json( self::ERROR_ENDPOINT, self::filter_error_payload( $payload ) );
	}

	/**
	 * @param array<string, mixed> $payload Untrusted heartbeat payload.
	 * @return array<string, mixed>
	 */
	public static function filter_heartbeat_payload( array $payload ): array {
		return array_intersect_key( $payload, self::HEARTBEAT_KEYS );
	}

	/**
	 * @param array<string, mixed> $payload Untrusted error payload.
	 * @return array<string, mixed>
	 */
	public static function filter_error_payload( array $payload ): array {
		return array_intersect_key( $payload, self::ERROR_KEYS );
	}

	/**
	 * @param array<string, mixed> $payload JSON payload.
	 */
	private function post_json( string $url, array $payload ): bool {
		$body = call_user_func( $this->encoder, $payload );

		if ( ! is_string( $body ) || '' === $body ) {
			return false;
		}

		try {
			call_user_func(
				$this->transport,
				$url,
				array(
					'timeout'     => 2,
					'blocking'    => false,
					'redirection' => 0,
					'headers'     => array(
						'Content-Type' => 'application/json',
					),
					'body'        => $body,
				)
			);
		} catch ( \Throwable $throwable ) {
			return false;
		}

		return true;
	}
}
