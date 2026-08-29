<?php
/**
 * Minimal telemetry payload builders.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Telemetry;

use CodeLearner\Divi5WooCommerceMCP\Divi\Detector as DiviDetector;
use CodeLearner\Divi5WooCommerceMCP\Version;
use CodeLearner\Divi5WooCommerceMCP\WooCommerce\Detector as WooCommerceDetector;

final class Payload {
	/**
	 * Build the anonymous heartbeat payload.
	 *
	 * @return array<string, bool|string>
	 */
	public static function heartbeat(): array {
		return array(
			'install_id'           => InstallIdentity::get(),
			'plugin_version'       => Version::NUMBER,
			'wp_version'           => (string) get_bloginfo( 'version' ),
			'php_version'          => self::php_version(),
			'divi_detected'        => DiviDetector::is_available(),
			'woocommerce_detected' => WooCommerceDetector::is_available(),
		);
	}

	/**
	 * Build an error payload for a plugin-owned fatal PHP error.
	 *
	 * @param array<string, mixed> $error Error returned by error_get_last().
	 * @return array<string, mixed>
	 */
	public static function fatal_error( array $error ): array {
		$error_class = 'php_fatal';
		$error_code  = isset( $error['type'] ) ? (string) (int) $error['type'] : '0';
		$message     = isset( $error['message'] ) && is_string( $error['message'] ) ? Sanitizer::sanitize_message( $error['message'] ) : '';
		$frames      = Sanitizer::sanitize_frames(
			array(
				array(
					'file' => isset( $error['file'] ) && is_string( $error['file'] ) ? $error['file'] : '',
					'line' => isset( $error['line'] ) ? (int) $error['line'] : 0,
				),
			),
			DIVI5_WC_MCP_DIR
		);

		return array(
			'install_id'     => InstallIdentity::get(),
			'plugin_version' => Version::NUMBER,
			'wp_version'     => (string) get_bloginfo( 'version' ),
			'php_version'    => self::php_version(),
			'error_class'    => $error_class,
			'error_code'     => $error_code,
			'message'        => $message,
			'frames'         => $frames,
			'fingerprint'    => Sanitizer::fingerprint( $error_class, $error_code, $message ),
		);
	}

	public static function php_version(): string {
		return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
	}

	private function __construct() {
	}
}
