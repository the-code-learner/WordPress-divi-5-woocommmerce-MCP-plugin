<?php
/**
 * One-shot Chrome DevTools Protocol capture session.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Screenshot;

use RuntimeException;
use Throwable;

final class ChromiumCapture {
	private string $binary_path;

	public function __construct( string $binary_path ) {
		$this->binary_path = $binary_path;
	}

	/** @return array<string, mixed> */
	public function smoke_test(): array {
		$process = new ChromiumProcess( $this->binary_path );
		try {
			$process->start( 8.0 );
			$client = new CdpClient( $process->page_websocket_url(), 2000000 );
			$client->command( 'Page.enable' );
			$client->command(
				'Emulation.setDeviceMetricsOverride',
				array(
					'width'             => 32,
					'height'            => 32,
					'deviceScaleFactor' => 1,
					'mobile'            => false,
				)
			);
			$result = $client->command(
				'Page.captureScreenshot',
				array(
					'format'                => 'png',
					'fromSurface'           => true,
					'captureBeyondViewport' => false,
				),
				5.0
			);
			$image = isset( $result['data'] ) && is_string( $result['data'] ) ? base64_decode( $result['data'], true ) : false;
			$client->close();
			$valid = is_string( $image ) && 8 <= strlen( $image ) && "\x89PNG\r\n\x1a\n" === substr( $image, 0, 8 );
			return array(
				'success'       => $valid,
				'cdp_available' => true,
				'error_code'    => $valid ? null : 'renderer_capture_failed',
				'error_message' => $valid ? null : 'Chromium started but did not return a valid PNG smoke-test image.',
			);
		} catch ( Throwable $throwable ) {
			$message = $this->safe_message( $throwable->getMessage() );
			return array(
				'success'       => false,
				'cdp_available' => false,
				'error_code'    => $this->startup_error_code( $message ),
				'error_message' => $message,
			);
		} finally {
			$process->stop();
		}
	}

	/**
	 * @param array<string, mixed> $request Render request.
	 * @return array<string, mixed>
	 */
	public function capture( array $request ): array {
		$url             = isset( $request['url'] ) && is_string( $request['url'] ) ? $request['url'] : '';
		$width           = isset( $request['width'] ) ? (int) $request['width'] : 0;
		$height          = isset( $request['height'] ) && null !== $request['height'] ? (int) $request['height'] : 900;
		$full_page       = ! empty( $request['full_page'] );
		$format          = isset( $request['format'] ) && 'jpeg' === $request['format'] ? 'jpeg' : 'png';
		$quality         = isset( $request['quality'] ) && null !== $request['quality'] ? (int) $request['quality'] : null;
		$timeout_seconds = isset( $request['timeout_seconds'] ) ? max( 1, (int) $request['timeout_seconds'] ) : 30;
		$max_bytes       = isset( $request['max_bytes'] ) ? max( 1024, (int) $request['max_bytes'] ) : 26214400;
		$max_height      = isset( $request['max_image_height'] ) ? max( 100, (int) $request['max_image_height'] ) : 30000;
		$max_pixels      = isset( $request['max_pixels'] ) ? max( 10000, (int) $request['max_pixels'] ) : 100000000;

		if ( '' === $url || $width < 1 ) {
			return $this->failure( 'renderer_request_invalid', 'The renderer request is missing its URL or viewport width.' );
		}
		$origin = self::origin_from_url( $url );
		if ( null === $origin ) {
			return $this->failure( 'renderer_request_invalid', 'The renderer URL is not a valid HTTP(S) origin.' );
		}

		$process          = new ChromiumProcess( $this->binary_path );
		$blocked_requests = 0;
		$deadline         = microtime( true ) + $timeout_seconds;

		try {
			try {
				$process->start( min( 8.0, max( 2.0, $timeout_seconds / 2 ) ) );
				$message_limit = min( 80000000, max( 4000000, ( $max_bytes * 2 ) + 1048576 ) );
				$client        = new CdpClient( $process->page_websocket_url(), (int) $message_limit );
			} catch ( Throwable $throwable ) {
				$message = $this->safe_message( $throwable->getMessage() );
				return $this->failure( $this->startup_error_code( $message ), $message );
			}

			$client->set_event_handler(
				static function ( array $event, CdpClient $cdp ) use ( $origin, &$blocked_requests ): void {
					if ( 'Fetch.requestPaused' !== ( $event['method'] ?? '' )
						|| ! isset( $event['params']['requestId'], $event['params']['request']['url'] ) ) {
						return;
					}

					$request_id  = (string) $event['params']['requestId'];
					$request_url = (string) $event['params']['request']['url'];
					if ( ChromiumCapture::browser_request_allowed( $request_url, $origin ) ) {
						$cdp->send_async( 'Fetch.continueRequest', array( 'requestId' => $request_id ) );
						return;
					}

					++$blocked_requests;
					$cdp->send_async(
						'Fetch.failRequest',
						array(
							'requestId'  => $request_id,
							'errorReason' => 'BlockedByClient',
						)
					);
				}
			);

			$client->command( 'Page.enable', array(), $this->remaining( $deadline ) );
			$client->command( 'Runtime.enable', array(), $this->remaining( $deadline ) );
			$client->command(
				'Fetch.enable',
				array( 'patterns' => array( array( 'urlPattern' => '*' ) ) ),
				$this->remaining( $deadline )
			);
			$client->command(
				'Emulation.setDeviceMetricsOverride',
				array(
					'width'             => $width,
					'height'            => max( 100, $height ),
					'deviceScaleFactor' => 1,
					'mobile'            => false,
				),
				$this->remaining( $deadline )
			);

			try {
				$navigation = $client->command( 'Page.navigate', array( 'url' => $url ), min( 10.0, $this->remaining( $deadline ) ) );
				if ( isset( $navigation['errorText'] ) && '' !== (string) $navigation['errorText'] ) {
					return $this->failure( 'renderer_navigation_failed', 'Chromium could not navigate to the WordPress preview URL.' );
				}
				$this->wait_until_ready( $client, $deadline, $origin );
			} catch ( Throwable $throwable ) {
				$code = microtime( true ) >= $deadline ? 'renderer_navigation_timeout' : 'renderer_navigation_failed';
				return $this->failure( $code, $this->safe_message( $throwable->getMessage() ) );
			}

			$target_height = max( 1, $height );
			if ( $full_page ) {
				try {
					$target_height = $this->stable_document_height( $client, $deadline );
				} catch ( Throwable $throwable ) {
					return $this->failure( 'renderer_render_timeout', $this->safe_message( $throwable->getMessage() ) );
				}
			}

			if ( $target_height > $max_height || ( $width * $target_height ) > $max_pixels ) {
				return $this->failure( 'renderer_output_too_large', 'The rendered page exceeds the configured screenshot dimension or pixel limit.' );
			}

			$capture = array(
				'format'                => $format,
				'fromSurface'           => true,
				'captureBeyondViewport' => true,
				'clip'                  => array(
					'x'      => 0,
					'y'      => 0,
					'width'  => $width,
					'height' => $target_height,
					'scale'  => 1,
				),
			);
			if ( 'jpeg' === $format && null !== $quality ) {
				$capture['quality'] = $quality;
			}

			try {
				$result = $client->command( 'Page.captureScreenshot', $capture, $this->remaining( $deadline ) );
			} catch ( Throwable $throwable ) {
				$code = microtime( true ) >= $deadline ? 'renderer_render_timeout' : 'renderer_capture_failed';
				return $this->failure( $code, $this->safe_message( $throwable->getMessage() ) );
			}

			$image_data = isset( $result['data'] ) && is_string( $result['data'] ) ? base64_decode( $result['data'], true ) : false;
			if ( ! is_string( $image_data ) || '' === $image_data ) {
				return $this->failure( 'renderer_capture_failed', 'Chromium returned no decodable screenshot bytes.' );
			}
			if ( strlen( $image_data ) > $max_bytes ) {
				return $this->failure( 'renderer_output_too_large', 'The Chromium screenshot exceeds the configured byte limit.' );
			}

			$warnings = array();
			if ( $blocked_requests > 0 ) {
				$warnings[] = sprintf( 'Blocked %d cross-origin browser request(s) to preserve the screenshot renderer SSRF boundary.', $blocked_requests );
			}
			$client->close();
			return array(
				'success'       => true,
				'image_data'    => $image_data,
				'render_method' => 'bundled-chromium-cdp',
				'warnings'      => $warnings,
				'error_code'    => null,
				'error_message' => null,
			);
		} finally {
			$process->stop();
		}
	}

	/**
	 * @param array{scheme:string,host:string,port:int} $origin Approved origin.
	 */
	public static function browser_request_allowed( string $url, array $origin ): bool {
		if ( 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, 'blob:' ) || 0 === strpos( $url, 'about:' ) ) {
			return true;
		}
		$request_origin = self::origin_from_url( $url );
		if ( null === $request_origin ) {
			return false;
		}
		return hash_equals( $origin['scheme'], $request_origin['scheme'] )
			&& hash_equals( $origin['host'], $request_origin['host'] )
			&& $origin['port'] === $request_origin['port'];
	}

	/** @return array{scheme:string,host:string,port:int}|null */
	private static function origin_from_url( string $url ): ?array {
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return null;
		}
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		return array(
			'scheme' => $scheme,
			'host'   => $host,
			'port'   => $port,
		);
	}

	/** @param array{scheme:string,host:string,port:int} $origin Approved origin. */
	private function wait_until_ready( CdpClient $client, float $deadline, array $origin ): void {
		$stable_ready = 0;
		while ( microtime( true ) < $deadline ) {
			$result = $client->command(
				'Runtime.evaluate',
				array(
					'expression'    => '({ready:document.readyState==="complete"&&(!document.fonts||document.fonts.status==="loaded"),href:location.href})',
					'returnByValue' => true,
				),
				min( 2.0, $this->remaining( $deadline ) )
			);
			$value = isset( $result['result']['value'] ) ? $result['result']['value'] : null;
			if ( is_array( $value ) ) {
				$current_origin = isset( $value['href'] ) && is_string( $value['href'] ) ? self::origin_from_url( $value['href'] ) : null;
				if ( null === $current_origin
					|| ! hash_equals( $origin['scheme'], $current_origin['scheme'] )
					|| ! hash_equals( $origin['host'], $current_origin['host'] )
					|| $origin['port'] !== $current_origin['port'] ) {
					throw new RuntimeException( 'Chromium navigation left the approved WordPress origin.' );
				}
				if ( ! empty( $value['ready'] ) ) {
					++$stable_ready;
					if ( $stable_ready >= 2 ) {
						usleep( 200000 );
						return;
					}
				} else {
					$stable_ready = 0;
				}
			}
			usleep( 100000 );
		}
		throw new RuntimeException( 'WordPress frontend did not reach a stable ready state before timeout.' );
	}

	private function stable_document_height( CdpClient $client, float $deadline ): int {
		$previous = null;
		$stable   = 0;
		while ( microtime( true ) < $deadline ) {
			$metrics = $client->command( 'Page.getLayoutMetrics', array(), min( 2.0, $this->remaining( $deadline ) ) );
			$size    = isset( $metrics['cssContentSize'] ) && is_array( $metrics['cssContentSize'] )
				? $metrics['cssContentSize']
				: ( isset( $metrics['contentSize'] ) && is_array( $metrics['contentSize'] ) ? $metrics['contentSize'] : array() );
			$height = isset( $size['height'] ) ? (int) ceil( (float) $size['height'] ) : 0;
			if ( $height < 1 ) {
				throw new RuntimeException( 'Chromium did not report a valid document height.' );
			}
			if ( null !== $previous && abs( $height - $previous ) <= 1 ) {
				++$stable;
				if ( $stable >= 2 ) {
					return $height;
				}
			} else {
				$stable = 0;
			}
			$previous = $height;
			usleep( 200000 );
		}
		throw new RuntimeException( 'Document layout did not stabilize before timeout.' );
	}

	private function remaining( float $deadline ): float {
		$remaining = $deadline - microtime( true );
		if ( $remaining <= 0 ) {
			throw new RuntimeException( 'Renderer timeout exceeded.' );
		}
		return max( 0.1, $remaining );
	}

	private function startup_error_code( string $message ): string {
		if ( false !== stripos( $message, 'sandbox' ) ) {
			return 'renderer_sandbox_unavailable';
		}
		if ( false !== stripos( $message, 'CDP' ) || false !== stripos( $message, 'WebSocket' ) ) {
			return 'renderer_cdp_unavailable';
		}
		return 'renderer_start_failed';
	}

	/** @return array<string, mixed> */
	private function failure( string $code, string $message ): array {
		return array(
			'success'       => false,
			'warnings'      => array(),
			'error_code'    => $code,
			'error_message' => $this->safe_message( $message ),
		);
	}

	private function safe_message( string $message ): string {
		$message = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $message );
		$message = is_string( $message ) ? trim( preg_replace( '/\s+/', ' ', $message ) ) : '';
		if ( strlen( $message ) > 600 ) {
			$message = substr( $message, 0, 600 );
		}
		return '' !== $message ? $message : 'Bundled Chromium renderer failed.';
	}
}
