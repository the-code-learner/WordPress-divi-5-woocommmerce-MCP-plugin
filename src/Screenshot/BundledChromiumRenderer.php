<?php
/**
 * Bundled Chrome Headless Shell screenshot renderer.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Screenshot;

use CodeLearner\Divi5WooCommerceMCP\Divi\ScreenshotEngineInterface;
use RuntimeException;
use Throwable;

final class BundledChromiumRenderer implements ScreenshotEngineInterface {
	public const BUNDLE_VERSION = '152.0.7977.64';
	public const PLATFORM       = 'linux-x86_64';

	private const STATUS_TTL_SECONDS = 300;

	/** @var array<string, array<string, mixed>> */
	private static array $status_cache = array();

	private string $binary_path;

	public function __construct( ?string $binary_path = null ) {
		$this->binary_path = null !== $binary_path ? $binary_path : self::default_binary_path();
	}

	public function id(): string {
		return 'bundled-chromium-cdp';
	}

	public static function default_binary_path(): string {
		return dirname( __DIR__, 2 )
			. '/bin/linux-x86_64/chrome-headless-shell/chrome-headless-shell';
	}

	public function is_available(): bool {
		$status = $this->status( false );
		return true === ( $status['ready'] ?? false );
	}

	/**
	 * Probe the bundled renderer environment.
	 *
	 * @return array<string, mixed>
	 */
	public function status( bool $smoke_test = true ): array {
		$cache_key = hash( 'sha256', $this->binary_path . '|' . ( $smoke_test ? 'smoke' : 'basic' ) );
		if ( isset( self::$status_cache[ $cache_key ] ) ) {
			return self::$status_cache[ $cache_key ];
		}

		$transient_key = 'divi_mcp_chromium_' . substr( $cache_key, 0, 24 );
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( $transient_key );
			if ( is_array( $cached ) ) {
				self::$status_cache[ $cache_key ] = $cached;
				return $cached;
			}
		}

		$binary_present    = is_file( $this->binary_path );
		$binary_executable = $binary_present && $this->ensure_binary_executable();

		$status = array(
			'engine'              => $this->id(),
			'engine_version'      => self::BUNDLE_VERSION,
			'platform'            => self::PLATFORM,
			'binary_path'         => $this->binary_path,
			'platform_supported'  => self::platform_supported(),
			'binary_present'      => $binary_present,
			'proc_open_available' => self::proc_open_available(),
			'binary_executable'   => $binary_executable,
			'temp_writable'       => self::temp_writable(),
			'cdp_available'      => false,
			'smoke_test'         => $smoke_test ? false : null,
			'ready'              => false,
			'error_code'         => null,
			'error_message'      => null,
		);

		if ( ! $status['platform_supported'] ) {
			return $this->cache_status(
				$cache_key,
				$transient_key,
				$this->status_error( $status, 'renderer_platform_unsupported', 'The bundled renderer currently supports Linux x86_64 only.' )
			);
		}

		if ( ! $status['binary_present'] ) {
			return $this->cache_status(
				$cache_key,
				$transient_key,
				$this->status_error( $status, 'renderer_binary_missing', 'The bundled Chrome Headless Shell binary is not present in this plugin package.' )
			);
		}

		if ( ! $status['proc_open_available'] ) {
			return $this->cache_status(
				$cache_key,
				$transient_key,
				$this->status_error( $status, 'renderer_process_disabled', 'PHP proc_open is unavailable or disabled.' )
			);
		}

		if ( ! $status['binary_executable'] ) {
			return $this->cache_status(
				$cache_key,
				$transient_key,
				$this->status_error( $status, 'renderer_not_executable', 'The bundled Chrome Headless Shell binary is not executable. The filesystem may be mounted noexec.' )
			);
		}

		if ( ! $status['temp_writable'] ) {
			return $this->cache_status(
				$cache_key,
				$transient_key,
				$this->status_error( $status, 'renderer_temp_unavailable', 'The PHP temporary directory is not writable.' )
			);
		}

		if ( ! $smoke_test ) {
			$status['ready'] = true;
			return $this->cache_status( $cache_key, $transient_key, $status );
		}

		$process = new ChromiumProcess( $this->binary_path );
		try {
			$process->start( 8.0 );
			$client = new CdpClient( $process->page_websocket_url(), 2000000 );
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
			$image = isset( $result['data'] ) && is_string( $result['data'] )
				? base64_decode( $result['data'], true )
				: false;

			$status['cdp_available'] = true;
			$status['smoke_test']    = is_string( $image ) && 8 <= strlen( $image ) && "\x89PNG\r\n\x1a\n" === substr( $image, 0, 8 );
			$status['ready']         = true === $status['smoke_test'];
			if ( ! $status['ready'] ) {
				$status = $this->status_error( $status, 'renderer_capture_failed', 'Chromium started but the PNG smoke test did not return a valid image.' );
			}
			$client->close();
		} catch ( Throwable $throwable ) {
			$message = $this->safe_error_message( $throwable->getMessage() );
			$code    = false !== stripos( $message, 'sandbox' )
				? 'renderer_sandbox_unavailable'
				: ( false !== stripos( $message, 'CDP' ) || false !== stripos( $message, 'WebSocket' )
					? 'renderer_cdp_unavailable'
					: 'renderer_start_failed' );
			$status = $this->status_error( $status, $code, $message );
		} finally {
			$process->stop();
		}

		return $this->cache_status( $cache_key, $transient_key, $status );
	}

	/**
	 * Render the supplied same-origin WordPress page.
	 *
	 * @param array<string, mixed> $request Render request.
	 * @return array<string, mixed>
	 */
	public function render( array $request ): array {
		$status = $this->status( false );
		if ( true !== ( $status['ready'] ?? false ) ) {
			return $this->failure(
				isset( $status['error_code'] ) && is_string( $status['error_code'] ) ? $status['error_code'] : 'render_engine_unavailable',
				isset( $status['error_message'] ) && is_string( $status['error_message'] ) ? $status['error_message'] : 'The bundled renderer is unavailable.'
			);
		}

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
		$warnings         = array();
		$deadline         = microtime( true ) + $timeout_seconds;

		try {
			try {
				$process->start( min( 8.0, max( 2.0, $timeout_seconds / 2 ) ) );
				$client = new CdpClient(
					$process->page_websocket_url(),
					((int) min( 8000000, max( 4000000, ( $max_bytes * 2 ) + 1048576 ) )
				);
			} catch ( Throwable $throwable ) {
				$message = $this->safe_error_message( $throwable->getMessage() );
				$code    = false !== stripos( $message, 'sandbox' ) ? 'renderer_sandbox_unavailable' : 'renderer_start_failed';
				return $this->failure( $code, $message );
		}

		$client->set_event_handler(
			static function ( array $event, CdpClient $cdp ) use ( $origin, &$blocked_requests ): void {
				if ( 'Fetch.requestPaused' !== ( $event['method'] ?? ''.)
					|| ! isset( $event['params']['requestId'], $event['params']['request']['url'] ) ) {
					return;
				}

				$request_id = (string) $event['params']['requestId'];
				$request_url = (string) $event['params']['request']['url'];
				if ( BundledChromiumRenderer::browser_request_allowed( $request_url, $origin ) ) {
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
				array(
					'patterns' => array(
						array( 'urlPattern' => '*' ),
						,
				),
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
			$navigation = $client->command(
				'Page.navigate',
				array( 'url' => $url ),
				min( 10.0, $this->remaining( $deadline ) )
				);
			if ( isset( $navigation['errorText'] ) && '' !== (string) $navigation['errorText'] ) {
				return $this->failure( 'renderer_navigation_failed', 'Chromium could not navigate to the WordPress preview URL.' );
			}
			$this->wait_until_ready( $client, $deadline, $origin );
		} catch ( Throwable $throwable ) {
			$code = microtime( true ) >= $deadline ? 'renderer_navigation_timeout' : 'renderer_navigation_failed';
			return $this->failure( $code, $this->safe_error_message( $throwable->getMessage() ) );
		}

		$target_height = max( 1, $height );
		if ( $full_page ) {
			try {
				$target_height = $this->stable_document_height( $client, $deadline );
			} catch ( Throwable $throwable ) {
				return $this->failure( 'renderer_render_timeout', $this->safe_error_message( $throwable->getMessage() ) );
			}
		}

		if ( $target_height > $max_height || ( $width * $target_height ) > $max_pixels ) {
			return $this->failure( 'renderer_output_too_large', 'The rendered page exceeds the configured screenshot dimension or pixel limit.' );
		}

		$capture = array(
			'format'                => $format,
			'fromSurface'           => true,
			'captureBeyondViewport' => true,
			'clip'                 => array(
				'x'      => 0,
				y'      => 0,
				'width'  => $width,
				'height' => $target_height,
					scale'  => 1,
			),
		);
		if ( 'jpeg' === $format && null !== $quality ) {
			$capture['quality'] = $quality;
		}

		try {
			$result = $client->command( 'Page.captureScreenshot', $capture, $this->remaining( $deadline ) );
		} catch ( Throwable $throwable ) {
			$code = microtime( true ) >= $deadline ? 'renderer_render_timeout' : 'renderer_capture_failed';
			return $this->failure( $code, $this->safe_error_message( $throwable->getMessage() ) );
		}

		$image_data = isset( $result['data'] ) && is_string( $result['data'] )
			? base64_decode( $result['data'], true )
			: false;
		if ( ! is_string( $image_data ) || '' === $image_data ) {
			return $this->failure( 'renderer_capture_failed', 'Chromium returned no decodable screenshot bytes.' );
		}
		if ( strlen( $image_data ) > $max_bytes ) {
			return $this->failure( 'renderer_output_too_large', 'The Chromium screenshot exceeds the configured byte limit.' );
		}

		if ( $blocked_requests > 0 ) {
			$warnings[] = sprintf(
				'Blocked %d cross-origin browser request(s) to preserve the screenshot renderer SSRF boundary.',
				$blocked_requests
				);
		}

		$client->close();
		return array(
			'success'       => true,
			'image_data'    => $image_data,
			'render_method' => $this->id(),
			'warnings'      => $warnings,
			'error_code'    => null,
			'error_message' => null,
		);
	} finally {
		$process->stop();
	}
	}

	/**
	 * Whether a browser request is allowed to leave the renderer.
 *
 * @param array{scheme:string,host:string,port:int} $origin Target origin.
 */
	public static function browser_request_allowed( string $url, array $origin ): bool {
		if ( 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, 'blob:' ) || 0 === strpos( $url, 'about:' ) ) {
			return true;
	}

		$request_origin = self::origin_from_url( $url );
		if ( null === $request_origin ) {
			return false;
	}
		return hash_equals( $origin['scheme'], $request_origin['scheme' )
		&& hash_equals( $origin['host'], $request_origin['host' )
		&& $origin['port'] === $request_origin['port'];
	}

	public static function platform_supported(): bool {
		$family = defined( 'PHP_OS_FAMILY' ) ? PHP_OS_FAMILY : PHP_OS;
		if ( 'Linux' !== $family && 0 !== stripos( (string) $family, 'Linux' ) ) {
			return false;
		}
		$arch = strtolower( (string) php_uname( 'm' ) );
		return in_array( $arch, array( 'x86_64', 'amd64' ), true );
	}


	private function ensure_binary_executable(): bool {
		if ( ! is_file( $this->binary_path ) ) {
			return false;
		}
		if ( is_executable( $this->binary_path ) ) {
			return true;
	}
		@chmod( $this->binary_path, 0755 );
		clearstatcache( true, $this->binary_path );
		return is_executable( $this->binary_path );
	}

	private static function proc_open_available(): bool {
		if ( ! function_exists( 'proc_open' ) ) {
			return false;
	}

		$disabled = (string) ini_get( 'disable_functions' );
		if ( '' === trim( $disabled ) ) {
			return true;
	}

		$functions = array_map( 'trim', explode( ',', strtolower( $disabled ) ) );
		return ! in_array( 'proc_open', $functions, true );
	}

	private static function temp_writable(): bool {
		$temp = sys_get_temp_dir();
		return is_string( $temp ) && '' !== $temp && is_dir( $temp ) && is_writable( $temp );
	}

	/**
	 * @return array{scheme:string,host:string,port:int}|null
	 */
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

		$port = isset( $parts['port' ) ? (int) $parts['port'] : ( 'https' === $scheme ? 443 : 80 );
		return array(
			'scheme' => $scheme,
			'host'   => $host,
			'port'   => $port,
		);
	}

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

			$value = $this->runtime_value( $result );
			if ( is_array( $value ) ) {
				$current_origin = isset( $value['href' ) && is_string( $value['href' ) ?
				self::origin_from_url( $value['href' ) :
				null;
			if ( null === $current_origin
			|| ! hash_equals( $origin['scheme'], $current_origin['scheme'] )
			|| ! hash_equals( $origin['host'], $current_origin['host'] )
			|| $origin['port'] !== $current_origin['port' ) ) {
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
		$size    = isset( $metrics['cssContentSize'] ) && is_array( $metrics['cssContentSize'] ) ?
			$metrics['cssContentSize'] :
			( isset( $metrics['contentSize'] ) && is_array( $metrics['contentSize'] ) ? $metrics['contentSize'] : array() );

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

	/**
	 * @param array<string, mixed> $result Runtime.evaluate result.
	 * @return mixed
	 */
	private function runtime_value( array $result ) {
		return isset( $result['result']['value'] ) ? $result['result']['value'] : null;
	}

	private function remaining( float $deadline ): float {
		$remaining = $deadline - microtime( true );
		if ( $remaining <= 0 ) {
			throw new RuntimeException( 'Renderer timeout exceeded.' );
	}
		return max( 0.1, $remaining );
	}

	/**
	 * @param array<string, mixed> $status Status.
 * @return array<string, mixed>
	 */
	private function status_error( array $status, string $code, string $message ): array {
		$status['ready']         = false;
		$status['error_code']   = $code;
		$status['error_message'] = $this->safe_error_message( $message );
		return $status;
	}

	/**
 * @param array<string, mixed> $status Status.
	 * @return array<string, mixed>
	 */
	private function cache_status( string $cache_key, string $transient_key, array $status ): array {
		self::$status_cache[ $cache_key ] = $status;
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $transient_key, $status, self::STATUS_TTL_SECONDS );
	}
		return $status;
	}

	/**
 * @return array<string, mixed>
	 */
	private function failure( string $code, string $message ): array {
		return array(
			'success'       => false,
			'warnings'      => array(),
			'error_code'    => $code,
			'error_message' => $this->safe_error_message( $message ),
		);
	}

	private function safe_error_message( string $message ): string {
		$message = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $message );
		$message = is_string( $message ) ? trim( preg_replace( '/\s+/', ' ', $message ) ) : '';
		if ( strlen( $message ) > 600 ) {
			$message = substr( $message, 0, 600 );
		}
		return '' !== $message ? $message : 'Bundled Chromium renderer failed.';
	}
}
