<?php
/**
 * Disposable local Chrome Headless Shell process.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Screenshot;

use RuntimeException;

// This class intentionally manages an isolated native process and localhost sockets.
// WordPress filesystem/HTTP abstractions cannot replace these process-level primitives.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
// RuntimeException text is internal control flow and is sanitized before MCP output.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

final class ChromiumProcess {
	/** @var resource|null */
	private $process = null;

	private string $binary_path;

	private string $temp_dir = '';

	private int $port = 0;

	private string $stderr_path = '';

	private string $stdout_path = '';

	public function __construct( string $binary_path ) {
		$this->binary_path = $binary_path;
	}

	public function __destruct() {
		$this->stop();
	}

	public function start( float $timeout_seconds = 8.0 ): void {
		if ( is_resource( $this->process ) ) {
			return;
		}

		if ( ! function_exists( 'proc_open' ) ) {
			throw new RuntimeException( 'proc_open is not available.' );
		}
		if ( ! is_file( $this->binary_path ) ) {
			throw new RuntimeException( 'Chromium binary is missing.' );
		}
		if ( ! is_executable( $this->binary_path ) ) {
			throw new RuntimeException( 'Chromium binary is not executable.' );
		}

		$this->temp_dir = $this->create_temp_dir();
		$this->port     = $this->reserve_local_port();

		$profile_dir       = $this->temp_dir . '/profile';
		$this->stderr_path = $this->temp_dir . '/stderr.log';
		$this->stdout_path = $this->temp_dir . '/stdout.log';

		if ( ! mkdir( $profile_dir, 0700, true ) && ! is_dir( $profile_dir ) ) {
			$this->cleanup_temp_dir();
			throw new RuntimeException( 'Could not create Chromium profile directory.' );
		}

		$command = array(
			$this->binary_path,
			'--headless',
			'--disable-gpu',
			'--remote-debugging-address=127.0.0.1',
			'--remote-debugging-port=' . $this->port,
			'--user-data-dir=' . $profile_dir,
			'--disable-dev-shm-usage',
			'--no-first-run',
			'--no-default-browser-check',
			'--disable-background-networking',
			'--disable-component-update',
			'--disable-sync',
			'--metrics-recording-only',
			'--mute-audio',
			'about:blank',
		);

		$descriptors = array(
			0 => array( 'file', '/dev/null', 'r' ),
			1 => array( 'file', $this->stdout_path, 'a' ),
			2 => array( 'file', $this->stderr_path, 'a' ),
		);

		$options = array( 'bypass_shell' => true );
		$process = @proc_open( $command, $descriptors, $pipes, dirname( $this->binary_path ), null, $options );
		if ( ! is_resource( $process ) ) {
			$this->cleanup_temp_dir();
			throw new RuntimeException( 'Chromium process could not be started.' );
		}

		$this->process = $process;
		$deadline      = microtime( true ) + $timeout_seconds;
		$last_error    = '';

		while ( microtime( true ) < $deadline ) {
			if ( ! $this->is_running() ) {
				$error = $this->stderr_excerpt();
				$this->stop();
				throw new RuntimeException( 'Chromium exited during startup.' . ( '' !== $error ? ' ' . $error : '' ) );
			}

			try {
				$version = $this->http_json( '/json/version', 0.25 );
				if ( isset( $version['webSocketDebuggerUrl'] ) || isset( $version['Browser'] ) ) {
					return;
				}
			} catch ( RuntimeException $exception ) {
				$last_error = $exception->getMessage();
			}

			usleep( 50000 );
		}

		$error = $this->stderr_excerpt();
		if ( '' === $error && '' !== $last_error ) {
			$error = $last_error;
		}
		$this->stop();
		throw new RuntimeException( 'Chromium CDP did not become ready before timeout.' . ( '' !== $error ? ' ' . $error : '' ) );
	}

	public function stop(): void {
		if ( is_resource( $this->process ) ) {
			$status = proc_get_status( $this->process );
			if ( is_array( $status ) && ! empty( $status['running'] ) ) {
				@proc_terminate( $this->process );
				$deadline = microtime( true ) + 1.5;
				while ( microtime( true ) < $deadline ) {
					usleep( 50000 );
					$status = proc_get_status( $this->process );
					if ( ! is_array( $status ) || empty( $status['running'] ) ) {
						break;
					}
				}

				$status = proc_get_status( $this->process );
				if ( is_array( $status ) && ! empty( $status['running'] ) ) {
					@proc_terminate( $this->process, 9 );
				}
			}
			@proc_close( $this->process );
		}

		$this->process = null;
		$this->port    = 0;
		$this->cleanup_temp_dir();
	}

	public function is_running(): bool {
		if ( ! is_resource( $this->process ) ) {
			return false;
		}
		$status = proc_get_status( $this->process );
		return is_array( $status ) && ! empty( $status['running'] );
	}

	public function page_websocket_url(): string {
		$targets = $this->http_json( '/json/list', 2.0 );

		foreach ( $targets as $target ) {
			if ( is_array( $target )
				&& isset( $target['type'], $target['webSocketDebuggerUrl'] )
				&& 'page' === $target['type']
				&& is_string( $target['webSocketDebuggerUrl'] )
				&& '' !== $target['webSocketDebuggerUrl'] ) {
				return $target['webSocketDebuggerUrl'];
			}
		}

		throw new RuntimeException( 'No CDP page target is available.' );
	}

	public function stderr_excerpt(): string {
		if ( '' === $this->stderr_path || ! is_file( $this->stderr_path ) ) {
			return '';
		}

		$data = @file_get_contents( $this->stderr_path );
		if ( ! is_string( $data ) || '' === $data ) {
			return '';
		}

		$data = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/', ' ', $data );
		$data = is_string( $data ) ? trim( preg_replace( '/\s+/', ' ', $data ) ) : '';
		if ( strlen( $data ) > 600 ) {
			$data = substr( $data, -600 );
		}
		return $data;
	}

	/**
	 * @return array<string, mixed>|array<int, mixed>
	 */
	private function http_json( string $path, float $timeout_seconds ): array {
		if ( $this->port < 1 ) {
			throw new RuntimeException( 'Chromium CDP port is not available.' );
		}

		$errno  = 0;
		$errstr = '';
		$socket = @stream_socket_client(
			'tcp://127.0.0.1:' . $this->port,
			$errno,
			$errstr,
			$timeout_seconds,
			STREAM_CLIENT_CONNECT
		);
		if ( false === $socket ) {
			throw new RuntimeException( 'Local CDP HTTP endpoint is unavailable.' );
		}

		$seconds      = (int) floor( $timeout_seconds );
		$microseconds = (int) round( ( $timeout_seconds - $seconds ) * 1000000 );
		stream_set_timeout( $socket, max( 0, $seconds ), max( 0, $microseconds ) );

		$request = "GET {$path} HTTP/1.1\r\n"
			. 'Host: 127.0.0.1:' . $this->port . "\r\n"
			. "Connection: close\r\n\r\n";

		fwrite( $socket, $request );
		$response = stream_get_contents( $socket );
		fclose( $socket );

		if ( ! is_string( $response ) || false === strpos( $response, "\r\n\r\n" ) ) {
			throw new RuntimeException( 'Invalid response from local CDP HTTP endpoint.' );
		}

		list( $headers, $body ) = explode( "\r\n\r\n", $response, 2 );
		if ( ! preg_match( '/^HTTP\/1\.[01] 200\b/', $headers ) ) {
			throw new RuntimeException( 'Local CDP HTTP endpoint returned a non-200 response.' );
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( 'Local CDP HTTP endpoint returned invalid JSON.' );
		}
		return $decoded;
	}

	private function create_temp_dir(): string {
		$base = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR );
		if ( '' === $base || ! is_dir( $base ) || ! is_writable( $base ) ) {
			throw new RuntimeException( 'System temporary directory is not writable.' );
		}

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$path = $base . DIRECTORY_SEPARATOR . 'divi-mcp-chromium-' . bin2hex( random_bytes( 8 ) );
			if ( @mkdir( $path, 0700 ) ) {
				return $path;
			}
		}

		throw new RuntimeException( 'Could not create isolated Chromium temporary directory.' );
	}

	private function reserve_local_port(): int {
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$errno  = 0;
			$errstr = '';
			$server = @stream_socket_server( 'tcp://127.0.0.1:0', $errno, $errstr );
			if ( false === $server ) {
				continue;
			}

			$name = stream_socket_get_name( $server, false );
			fclose( $server );
			if ( is_string( $name ) && preg_match( '/:(\d+)$/', $name, $matches ) ) {
				$port = (int) $matches[1];
				if ( $port > 1024 && $port <= 65535 ) {
					return $port;
				}
			}
		}

		throw new RuntimeException( 'Could not reserve a random localhost CDP port.' );
	}

	private function cleanup_temp_dir(): void {
		if ( '' === $this->temp_dir || ! is_dir( $this->temp_dir ) ) {
			$this->temp_dir = '';
			return;
		}

		$this->remove_tree( $this->temp_dir );
		$this->temp_dir = '';
	}

	private function remove_tree( string $path ): void {
		if ( is_link( $path ) || is_file( $path ) ) {
			@unlink( $path );
			return;
		}

		$items = @scandir( $path );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}
				$this->remove_tree( $path . DIRECTORY_SEPARATOR . $item );
			}
		}
		@rmdir( $path );
	}
}
