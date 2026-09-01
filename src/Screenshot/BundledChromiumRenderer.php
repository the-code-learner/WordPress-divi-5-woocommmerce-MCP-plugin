<?php
/**
 * Bundled Chrome Headless Shell screenshot renderer.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Screenshot;

use CodeLearner\Divi5WooCommerceMCP\Divi\ScreenshotEngineInterface;
use Throwable;

// The executable bit and temp-dir checks operate on the plugin's fixed bundled binary path.
// WordPress filesystem abstractions do not preserve the native executable semantics required here.
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_chmod
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable

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
		return dirname( __DIR__, 2 ) . '/bin/linux-x86_64/chrome-headless-shell/chrome-headless-shell';
	}

	public function is_available(): bool {
		$status = $this->status( false );
		return true === ( $status['ready'] ?? false );
	}

	/**
	 * Probe the renderer environment and optionally execute a real PNG smoke test.
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
		$status            = array(
			'engine'              => $this->id(),
			'engine_version'      => self::BUNDLE_VERSION,
			'platform'            => self::PLATFORM,
			'binary_path'         => $this->binary_path,
			'platform_supported'  => self::platform_supported(),
			'binary_present'      => $binary_present,
			'proc_open_available' => self::proc_open_available(),
			'binary_executable'   => $binary_executable,
			'temp_writable'       => self::temp_writable(),
			'cdp_available'       => false,
			'smoke_test'          => $smoke_test ? false : null,
			'ready'               => false,
			'error_code'          => null,
			'error_message'       => null,
		);

		if ( ! $status['platform_supported'] ) {
			return $this->cache_status( $cache_key, $transient_key, $this->with_error( $status, 'renderer_platform_unsupported', 'The bundled renderer currently supports Linux x86_64 only.' ) );
		}
		if ( ! $status['binary_present'] ) {
			return $this->cache_status( $cache_key, $transient_key, $this->with_error( $status, 'renderer_binary_missing', 'The bundled Chrome Headless Shell binary is not present in this plugin package.' ) );
		}
		if ( ! $status['proc_open_available'] ) {
			return $this->cache_status( $cache_key, $transient_key, $this->with_error( $status, 'renderer_process_disabled', 'PHP proc_open is unavailable or disabled.' ) );
		}
		if ( ! $status['binary_executable'] ) {
			return $this->cache_status( $cache_key, $transient_key, $this->with_error( $status, 'renderer_not_executable', 'The bundled Chrome Headless Shell binary is not executable. The filesystem may be mounted noexec.' ) );
		}
		if ( ! $status['temp_writable'] ) {
			return $this->cache_status( $cache_key, $transient_key, $this->with_error( $status, 'renderer_temp_unavailable', 'The PHP temporary directory is not writable.' ) );
		}

		if ( ! $smoke_test ) {
			$status['ready'] = true;
			return $this->cache_status( $cache_key, $transient_key, $status );
		}

		try {
			$smoke                   = ( new ChromiumCapture( $this->binary_path ) )->smoke_test();
			$status['cdp_available'] = true === ( $smoke['cdp_available'] ?? false );
			$status['smoke_test']    = true === ( $smoke['success'] ?? false );
			$status['ready']         = true === $status['smoke_test'];
			if ( ! $status['ready'] ) {
				$status = $this->with_error(
					$status,
					isset( $smoke['error_code'] ) && is_string( $smoke['error_code'] ) ? $smoke['error_code'] : 'renderer_capture_failed',
					isset( $smoke['error_message'] ) && is_string( $smoke['error_message'] ) ? $smoke['error_message'] : 'The PNG smoke test failed.'
				);
			}
		} catch ( Throwable $throwable ) {
			$status = $this->with_error( $status, 'renderer_start_failed', $throwable->getMessage() );
		}

		return $this->cache_status( $cache_key, $transient_key, $status );
	}

	/**
	 * Render real frontend pixels for the request prepared by ScreenshotRenderer.
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

		try {
			return ( new ChromiumCapture( $this->binary_path ) )->capture( $request );
		} catch ( Throwable $throwable ) {
			return $this->failure( 'renderer_capture_failed', $throwable->getMessage() );
		}
	}

	/**
	 * Whether a browser subrequest remains inside the approved target origin.
	 *
	 * @param array{scheme:string,host:string,port:int} $origin Target origin.
	 */
	public static function browser_request_allowed( string $url, array $origin ): bool {
		return ChromiumCapture::browser_request_allowed( $url, $origin );
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

		// Plugin archive extraction can normalize file modes to 0644. The path is fixed
		// inside the pinned plugin bundle and cannot be supplied by an MCP caller.
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
	 * @param array<string, mixed> $status Status.
	 * @return array<string, mixed>
	 */
	private function with_error( array $status, string $code, string $message ): array {
		$status['ready']         = false;
		$status['error_code']    = $code;
		$status['error_message'] = $this->safe_message( $message );
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
