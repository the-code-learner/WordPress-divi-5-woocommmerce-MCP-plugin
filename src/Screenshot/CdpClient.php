<?php
/**
 * Minimal Chrome DevTools Protocol client over a local WebSocket.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Screenshot;

use RuntimeException;

// Raw localhost CDP/RFC6455 I/O has no WordPress API equivalent.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fread
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
// WebSocket and CDP encode binary/protocol payloads, not executable PHP code.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
// WordPress helpers are not guaranteed while this protocol class is loaded in standalone tests.
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
// phpcs:disable WordPress.WP.AlternativeFunctions.parse_url_parse_url
// These RuntimeException messages are internal control flow and are never emitted as HTML.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

final class CdpClient {
	/** @var resource|null */
	private $socket;

	private int $next_id = 1;

	/** @var callable|null */
	private $event_handler = null;

	private int $max_message_bytes;

	public function __construct( string $websocket_url, int $max_message_bytes = 40000000 ) {
		$this->max_message_bytes = $max_message_bytes;
		$this->connect( $websocket_url );
	}

	public function __destruct() {
		$this->close();
	}

	public function close(): void {
		if ( is_resource( $this->socket ) ) {
			fclose( $this->socket );
		}
		$this->socket = null;
	}

	public function set_event_handler( ?callable $handler ): void {
		$this->event_handler = $handler;
	}

	/**
	 * @param array<string, mixed> $params CDP parameters.
	 * @return array<string, mixed>
	 */
	public function command( string $method, array $params = array(), float $timeout_seconds = 10.0 ): array {
		$id       = $this->send_async( $method, $params );
		$deadline = microtime( true ) + $timeout_seconds;

		while ( microtime( true ) < $deadline ) {
			$remaining = max( 0.05, $deadline - microtime( true ) );
			$message   = $this->read_json_message( $remaining );
			if ( null === $message ) {
				continue;
			}

			if ( isset( $message['method'] ) && is_string( $message['method'] ) ) {
				$this->dispatch_event( $message );
				continue;
			}

			if ( ! isset( $message['id'] ) || (int) $message['id'] !== $id ) {
				continue;
			}

			if ( isset( $message['error'] ) && is_array( $message['error'] ) ) {
				$detail = isset( $message['error']['message'] ) && is_string( $message['error']['message'] )
					? $message['error']['message']
					: 'Unknown CDP error.';
				throw new RuntimeException( $method . ' failed: ' . $detail );
			}

			return isset( $message['result'] ) && is_array( $message['result'] ) ? $message['result'] : array();
		}

		throw new RuntimeException( $method . ' timed out.' );
	}

	/**
	 * Send a CDP command without waiting for its response.
	 *
	 * @param array<string, mixed> $params CDP parameters.
	 */
	public function send_async( string $method, array $params = array() ): int {
		$id      = $this->next_id++;
		$payload = json_encode(
			array(
				'id'     => $id,
				'method' => $method,
				'params' => $params,
			),
			JSON_UNESCAPED_SLASHES
		);

		if ( ! is_string( $payload ) ) {
			throw new RuntimeException( 'Could not encode CDP command.' );
		}

		$this->write_frame( $payload );
		return $id;
	}

	private function connect( string $websocket_url ): void {
		$parts = parse_url( $websocket_url );
		if ( ! is_array( $parts ) || 'ws' !== ( $parts['scheme'] ?? '' ) ) {
			throw new RuntimeException( 'CDP endpoint must be a ws:// URL.' );
		}

		$host = isset( $parts['host'] ) ? (string) $parts['host'] : '';
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		if ( isset( $parts['query'] ) && '' !== (string) $parts['query'] ) {
			$path .= '?' . $parts['query'];
		}

		if ( '127.0.0.1' !== $host || $port < 1 || $port > 65535 ) {
			throw new RuntimeException( 'CDP endpoint must be bound to 127.0.0.1 on a valid port.' );
		}

		$errno  = 0;
		$errstr = '';
		$socket = @stream_socket_client(
			'tcp://127.0.0.1:' . $port,
			$errno,
			$errstr,
			5.0,
			STREAM_CLIENT_CONNECT
		);
		if ( false === $socket ) {
			throw new RuntimeException( 'Could not connect to the local CDP socket.' );
		}

		stream_set_timeout( $socket, 5 );
		$key     = base64_encode( random_bytes( 16 ) );
		$request = "GET {$path} HTTP/1.1\r\n"
			. "Host: 127.0.0.1:{$port}\r\n"
			. "Upgrade: websocket\r\n"
			. "Connection: Upgrade\r\n"
			. "Sec-WebSocket-Key: {$key}\r\n"
			. "Sec-WebSocket-Version: 13\r\n\r\n";

		$this->write_all( $socket, $request );
		$response = $this->read_http_headers( $socket );

		if ( ! preg_match( '/^HTTP\/1\.[01] 101\b/m', $response ) ) {
			fclose( $socket );
			throw new RuntimeException( 'Local CDP WebSocket upgrade was rejected.' );
		}

		$expected = base64_encode( sha1( $key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true ) );
		if ( ! preg_match( '/^Sec-WebSocket-Accept:\s*(.+)\r?$/mi', $response, $matches )
			|| ! hash_equals( $expected, trim( $matches[1] ) ) ) {
			fclose( $socket );
			throw new RuntimeException( 'Local CDP WebSocket handshake was invalid.' );
		}

		stream_set_blocking( $socket, true );
		$this->socket = $socket;
	}

	/**
	 * @param array<string, mixed> $message CDP event.
	 */
	private function dispatch_event( array $message ): void {
		if ( null !== $this->event_handler ) {
			call_user_func( $this->event_handler, $message, $this );
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function read_json_message( float $timeout_seconds ): ?array {
		$payload = $this->read_message( $timeout_seconds );
		if ( null === $payload ) {
			return null;
		}

		$decoded = json_decode( $payload, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	private function read_message( float $timeout_seconds ): ?string {
		if ( ! is_resource( $this->socket ) ) {
			throw new RuntimeException( 'CDP socket is closed.' );
		}

		$seconds      = (int) floor( $timeout_seconds );
		$microseconds = (int) round( ( $timeout_seconds - $seconds ) * 1000000 );
		stream_set_timeout( $this->socket, max( 0, $seconds ), max( 0, $microseconds ) );

		$message = '';
		$started = false;

		while ( true ) {
			$header = $this->read_exact( $this->socket, 2 );
			if ( null === $header ) {
				return $started ? $message : null;
			}
			$started = true;

			$first  = ord( $header[0] );
			$second = ord( $header[1] );
			$fin    = 0 !== ( $first & 0x80 );
			$opcode = $first & 0x0f;
			$masked = 0 !== ( $second & 0x80 );
			$length = $second & 0x7f;

			if ( 126 === $length ) {
				$extended = $this->read_exact( $this->socket, 2 );
				if ( null === $extended ) {
					throw new RuntimeException( 'Truncated CDP WebSocket frame.' );
				}
				$unpacked = unpack( 'nlength', $extended );
				$length   = (int) $unpacked['length'];
			} elseif ( 127 === $length ) {
				$extended = $this->read_exact( $this->socket, 8 );
				if ( null === $extended ) {
					throw new RuntimeException( 'Truncated CDP WebSocket frame.' );
				}
				$parts  = unpack( 'Nhigh/Nlow', $extended );
				$length = ( (int) $parts['high'] * 4294967296 ) + (int) $parts['low'];
			}

			if ( $length < 0 || $length > $this->max_message_bytes ) {
				throw new RuntimeException( 'CDP WebSocket frame exceeded the configured response limit.' );
			}

			$mask = '';
			if ( $masked ) {
				$mask = $this->read_exact( $this->socket, 4 );
				if ( null === $mask ) {
					throw new RuntimeException( 'Truncated CDP WebSocket mask.' );
				}
			}

			$chunk = $length > 0 ? $this->read_exact( $this->socket, (int) $length ) : '';
			if ( null === $chunk ) {
				throw new RuntimeException( 'Truncated CDP WebSocket payload.' );
			}

			if ( $masked ) {
				$chunk = $this->xor_mask( $chunk, $mask );
			}

			if ( 0x8 === $opcode ) {
				$this->close();
				throw new RuntimeException( 'CDP WebSocket closed unexpectedly.' );
			}

			if ( 0x9 === $opcode ) {
				$this->write_control_frame( 0xA, $chunk );
				continue;
			}

			if ( 0xA === $opcode ) {
				continue;
			}

			if ( 0x1 !== $opcode && 0x0 !== $opcode ) {
				continue;
			}

			$message .= $chunk;
			if ( strlen( $message ) > $this->max_message_bytes ) {
				throw new RuntimeException( 'CDP WebSocket message exceeded the configured response limit.' );
			}

			if ( $fin ) {
				return $message;
			}
		}
	}

	private function write_frame( string $payload ): void {
		$this->write_data_frame( 0x1, $payload );
	}

	private function write_control_frame( int $opcode, string $payload ): void {
		$this->write_data_frame( $opcode, $payload );
	}

	private function write_data_frame( int $opcode, string $payload ): void {
		if ( ! is_resource( $this->socket ) ) {
			throw new RuntimeException( 'CDP socket is closed.' );
		}

		$length = strlen( $payload );
		$mask   = random_bytes( 4 );
		$header = chr( 0x80 | ( $opcode & 0x0f ) );

		if ( $length <= 125 ) {
			$header .= chr( 0x80 | $length );
		} elseif ( $length <= 65535 ) {
			$header .= chr( 0x80 | 126 ) . pack( 'n', $length );
		} else {
			$high    = (int) floor( $length / 4294967296 );
			$low     = $length % 4294967296;
			$header .= chr( 0x80 | 127 ) . pack( 'NN', $high, $low );
		}

		$this->write_all( $this->socket, $header . $mask . $this->xor_mask( $payload, $mask ) );
	}

	/**
	 * @param resource $socket Socket.
	 */
	private function write_all( $socket, string $data ): void {
		$offset = 0;
		$length = strlen( $data );

		while ( $offset < $length ) {
			$written = fwrite( $socket, substr( $data, $offset ) );
			if ( false === $written || 0 === $written ) {
				throw new RuntimeException( 'Could not write to local CDP socket.' );
			}
			$offset += $written;
		}
	}

	/**
	 * @param resource $socket Socket.
	 */
	private function read_http_headers( $socket ): string {
		$response = '';
		while ( false === strpos( $response, "\r\n\r\n" ) ) {
			$chunk = fread( $socket, 1024 );
			if ( false === $chunk || '' === $chunk ) {
				$meta = stream_get_meta_data( $socket );
				if ( ! empty( $meta['timed_out'] ) ) {
					break;
				}
				continue;
			}
			$response .= $chunk;
			if ( strlen( $response ) > 16384 ) {
				break;
			}
		}
		return $response;
	}

	/**
	 * @param resource $socket Socket.
	 */
	private function read_exact( $socket, int $length ): ?string {
		if ( 0 === $length ) {
			return '';
		}

		$data        = '';
		$data_length = 0;
		while ( $data_length < $length ) {
			$chunk = fread( $socket, $length - $data_length );
			if ( false === $chunk || '' === $chunk ) {
				$meta = stream_get_meta_data( $socket );
				if ( ! empty( $meta['timed_out'] ) || feof( $socket ) ) {
					return null;
				}
				continue;
			}
			$data        .= $chunk;
			$data_length += strlen( $chunk );
		}
		return $data;
	}

	private function xor_mask( string $data, string $mask ): string {
		$result = '';
		$length = strlen( $data );
		for ( $i = 0; $i < $length; $i++ ) {
			$result .= $data[ $i ] ^ $mask[ $i % 4 ];
		}
		return $result;
	}
}
