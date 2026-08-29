<?php
/**
 * Telemetry payload sanitization.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Telemetry;

final class Sanitizer {
	private const MAX_MESSAGE_LENGTH = 500;

	private const MAX_FRAMES = 10;

	public static function sanitize_message( string $message ): string {
		$sanitized = trim( $message );
		$sanitized = (string) preg_replace( '~https?://[^\s<>"\']+~i', '[url]', $sanitized );
		$sanitized = (string) preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $sanitized );
		$sanitized = (string) preg_replace(
			'/(authorization|cookie|token|password|passwd|secret|api[_-]?key)\s*[:=]\s*[^\s,;]+/i',
			'$1=[redacted]',
			$sanitized
		);
		$sanitized = (string) preg_replace( '~(?:[A-Za-z]:[\\/]|/)(?:[^\s:]+[\\/])+[^\s:]*~', '[path]', $sanitized );
		$sanitized = (string) preg_replace( '/\?[A-Za-z0-9_%&=.+-]{3,}/', '?[redacted]', $sanitized );

		return substr( $sanitized, 0, self::MAX_MESSAGE_LENGTH );
	}

	/**
	 * Keep only plugin-owned stack frames and convert absolute paths to relative paths.
	 *
	 * @param array<int, array<string, mixed>> $frames Stack frames.
	 * @return array<int, array<string, int|string>>
	 */
	public static function sanitize_frames( array $frames, string $plugin_dir ): array {
		$plugin_root = rtrim( self::normalize_path( $plugin_dir ), '/' ) . '/';
		$sanitized   = array();

		foreach ( $frames as $frame ) {
			if ( count( $sanitized ) >= self::MAX_FRAMES ) {
				break;
			}

			$file = isset( $frame['file'] ) && is_string( $frame['file'] ) ? self::normalize_path( $frame['file'] ) : '';

			if ( '' === $file || 0 !== strpos( $file, $plugin_root ) ) {
				continue;
			}

			$clean_frame = array(
				'file' => substr( $file, strlen( $plugin_root ) ),
				'line' => isset( $frame['line'] ) ? max( 0, (int) $frame['line'] ) : 0,
			);

			if ( isset( $frame['class'] ) && is_string( $frame['class'] ) && 0 === strpos( $frame['class'], 'CodeLearner\\Divi5WooCommerceMCP\\' ) ) {
				$clean_frame['class'] = $frame['class'];
			}

			if ( isset( $frame['function'] ) && is_string( $frame['function'] ) ) {
				$function = (string) preg_replace( '/[^A-Za-z0-9_]/', '', $frame['function'] );

				if ( '' !== $function ) {
					$clean_frame['function'] = $function;
				}
			}

			$sanitized[] = $clean_frame;
		}

		return $sanitized;
	}

	public static function fingerprint( string $error_class, string $error_code, string $message ): string {
		return hash( 'sha256', $error_class . '|' . $error_code . '|' . self::sanitize_message( $message ) );
	}

	private static function normalize_path( string $path ): string {
		return str_replace( '\\', '/', $path );
	}

	private function __construct() {
	}
}
