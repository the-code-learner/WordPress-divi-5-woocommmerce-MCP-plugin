<?php
/**
 * Real-pixel screenshot orchestration for WordPress/Divi documents.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use Throwable;

final class ScreenshotRenderer {
	private const MIN_WIDTH             = 240;
	private const MAX_WIDTH             = 4096;
	private const MIN_VIEWPORT_HEIGHT   = 100;
	private const MAX_VIEWPORT_HEIGHT   = 8192;
	private const DEFAULT_HEIGHT        = 900;
	private const MAX_IMAGE_HEIGHT      = 30000;
	private const MAX_PIXELS            = 100000000;
	private const MAX_BYTES             = 26214400;
	private const TIMEOUT_SECONDS       = 30;
	private const PREVIEW_TOKEN_TTL     = 60;
	private const PREVIEW_TOKEN_MAX_TTL = 120;

	private const QUERY_FLAG   = '_divi_mcp_screenshot';
	private const QUERY_POST   = '_divi_mcp_ss_post';
	private const QUERY_USER   = '_divi_mcp_ss_user';
	private const QUERY_EXP    = '_divi_mcp_ss_exp';
	private const QUERY_TARGET = '_divi_mcp_ss_target';
	private const QUERY_SIG    = '_divi_mcp_ss_sig';

	public static function hooks(): void {
		add_filter( 'determine_current_user', array( self::class, 'determine_preview_user' ), 5 );
		add_filter( 'show_admin_bar', array( self::class, 'filter_admin_bar' ), PHP_INT_MAX );
	}

	/**
	 * Report whether a real screenshot renderer has been registered and is usable.
	 *
	 * @return array<string, mixed>
	 */
	public static function capability(): array {
		$engine = self::resolve_engine();
		if ( ! $engine instanceof ScreenshotEngineInterface ) {
			return array(
				'status'        => 'unavailable',
				'render_engine' => null,
				'evidence'      => 'No backend raster renderer implementing ScreenshotEngineInterface is registered.',
			);
		}

		try {
			$available = $engine->is_available();
		} catch ( Throwable $throwable ) {
			return array(
				'status'        => 'unavailable',
				'render_engine' => self::engine_id( $engine ),
				'evidence'      => 'The registered backend raster renderer failed its availability probe: ' . $throwable->getMessage(),
			);
		}

		return array(
			'status'        => $available ? 'supported' : 'unavailable',
			'render_engine' => self::engine_id( $engine ),
			'evidence'      => $available
				? 'A backend renderer explicitly declared that it can produce real frontend pixels.'
				: 'A backend raster renderer is registered but is not available in the current hosting environment.',
		);
	}

	/**
	 * Render one WordPress page to real PNG/JPEG bytes through a registered backend engine.
	 *
	 * @return array<string, mixed>
	 */
	public static function render(
		int $post_id,
		int $width,
		bool $full_page = true,
		?int $height = null,
		string $format = 'png',
		?int $quality = null
	): array {
		$warnings = array();
		$format   = strtolower( trim( $format ) );

		if ( $width < self::MIN_WIDTH || $width > self::MAX_WIDTH ) {
			return self::failure( $post_id, '', $width, $height, $full_page, $format, $quality, $warnings, 'invalid_viewport_width', 'Viewport width must be between 240 and 4096 pixels.' );
		}

		if ( ! in_array( $format, array( 'png', 'jpeg' ), true ) ) {
			return self::failure( $post_id, '', $width, $height, $full_page, $format, $quality, $warnings, 'invalid_image_format', 'Image format must be png or jpeg.' );
		}

		if ( null !== $quality && ( $quality < 1 || $quality > 100 ) ) {
			return self::failure( $post_id, '', $width, $height, $full_page, $format, $quality, $warnings, 'invalid_image_quality', 'Image quality must be between 1 and 100.' );
		}

		if ( $full_page ) {
			if ( null !== $height ) {
				$warnings[] = 'height is ignored when full_page is true.';
			}
			$viewport_height = null;
		} else {
			$viewport_height = null !== $height ? $height : self::DEFAULT_HEIGHT;
			if ( null === $height ) {
				$warnings[] = 'height was not supplied; viewport height defaulted to 900 pixels.';
			}
			if ( $viewport_height < self::MIN_VIEWPORT_HEIGHT || $viewport_height > self::MAX_VIEWPORT_HEIGHT ) {
				return self::failure( $post_id, '', $width, $viewport_height, false, $format, $quality, $warnings, 'invalid_viewport_height', 'Viewport height must be between 100 and 8192 pixels.' );
			}
		}

		if ( 'png' === $format && null !== $quality ) {
			$warnings[] = 'quality is only applied to jpeg output and was ignored for png.';
		}

		$post = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return self::failure( $post_id, '', $width, $viewport_height, $full_page, $format, $quality, $warnings, 'post_not_found', 'The requested post does not exist.' );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return self::failure( $post_id, '', $width, $viewport_height, $full_page, $format, $quality, $warnings, 'permission_denied', 'The current user cannot read this post for screenshot rendering.' );
		}

		$post_type = isset( $post->post_type ) ? (string) $post->post_type : '';
		if ( '' !== $post_type && function_exists( 'get_post_type_object' ) && function_exists( 'is_post_type_viewable' ) ) {
			$post_type_object = get_post_type_object( $post_type );
			if ( is_object( $post_type_object ) && ! is_post_type_viewable( $post_type_object ) ) {
				return self::failure( $post_id, '', $width, $viewport_height, $full_page, $format, $quality, $warnings, 'post_type_not_viewable', 'The requested post type does not have a frontend representation that can be screenshotted.' );
			}
		}

		$page_url = (string) get_permalink( $post );
		if ( '' === $page_url || ! self::is_internal_url( $page_url ) ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'invalid_internal_page_url', 'WordPress did not generate a valid same-site frontend URL for this post.' );
		}

		$render_url = $page_url;
		$post_status = isset( $post->post_status ) ? (string) $post->post_status : '';
		if ( 'publish' !== $post_status ) {
			$preview_url = function_exists( 'get_preview_post_link' ) ? get_preview_post_link( $post ) : '';
			if ( ! is_string( $preview_url ) || '' === $preview_url ) {
				$preview_url = function_exists( 'add_query_arg' ) ? (string) add_query_arg( 'preview', 'true', $page_url ) : $page_url;
			}

			if ( ! self::is_internal_url( $preview_url ) ) {
				return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'invalid_internal_preview_url', 'WordPress did not generate a valid same-site preview URL for this post.' );
			}

			$render_url = self::sign_preview_url( $preview_url, $post_id );
			if ( '' === $render_url ) {
				return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'preview_authorization_unavailable', 'A secure short-lived preview URL could not be generated for this non-public post.' );
			}
		}

		$engine = self::resolve_engine(
			array(
				'post_id'     => $post_id,
				'post_status' => $post_status,
				'post_type'   => $post_type,
			)
		);
		if ( ! $engine instanceof ScreenshotEngineInterface ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_engine_unavailable', 'No compatible backend raster renderer is registered. WordPress/PHP alone cannot produce a faithful CSS layout screenshot.' );
		}

		try {
			if ( ! $engine->is_available() ) {
				return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_engine_unavailable', 'The registered backend raster renderer is not available in the current hosting environment.', self::engine_id( $engine ) );
			}
		} catch ( Throwable $throwable ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_engine_unavailable', 'The registered backend raster renderer failed its availability probe: ' . $throwable->getMessage(), self::engine_id( $engine ) );
		}

		$request = array(
			'post_id'          => $post_id,
			'url'              => $render_url,
			'width'            => $width,
			'height'           => $viewport_height,
			'full_page'        => $full_page,
			'format'           => $format,
			'quality'          => 'jpeg' === $format ? $quality : null,
			'timeout_seconds'  => self::TIMEOUT_SECONDS,
			'max_bytes'        => self::MAX_BYTES,
			'max_image_height' => self::MAX_IMAGE_HEIGHT,
			'max_pixels'       => self::MAX_PIXELS,
		);

		try {
			$engine_result = $engine->render( $request );
		} catch ( Throwable $throwable ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_failed', 'The backend raster renderer threw an exception: ' . $throwable->getMessage(), self::engine_id( $engine ) );
		}

		if ( ! is_array( $engine_result ) || ! isset( $engine_result['success'] ) || true !== $engine_result['success'] ) {
			$error_code    = isset( $engine_result['error_code'] ) && is_string( $engine_result['error_code'] ) ? $engine_result['error_code'] : 'render_failed';
			$error_message = isset( $engine_result['error_message'] ) && is_string( $engine_result['error_message'] ) ? $engine_result['error_message'] : 'The backend raster renderer did not return a successful image result.';
			$engine_warn   = isset( $engine_result['warnings'] ) && is_array( $engine_result['warnings'] ) ? $engine_result['warnings'] : array();
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, array_merge( $warnings, $engine_warn ), $error_code, $error_message, self::engine_id( $engine ) );
		}

		$image_data = isset( $engine_result['image_data'] ) && is_string( $engine_result['image_data'] ) ? $engine_result['image_data'] : '';
		if ( '' === $image_data ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_output_invalid', 'The renderer reported success but did not return raw image bytes.', self::engine_id( $engine ) );
		}

		if ( strlen( $image_data ) > self::MAX_BYTES ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_output_too_large', 'The rendered image exceeded the 25 MiB response limit.', self::engine_id( $engine ) );
		}

		$image_info = self::image_info( $image_data );
		if ( null === $image_info ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_output_invalid', 'The renderer output is not a valid PNG or JPEG image.', self::engine_id( $engine ) );
		}

		if ( $format !== $image_info['format'] ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_output_format_mismatch', 'The renderer output format does not match the requested format.', self::engine_id( $engine ) );
		}

		if ( $width !== $image_info['width'] ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_output_width_mismatch', 'The rendered image width does not match the requested viewport width.', self::engine_id( $engine ) );
		}

		if ( ! $full_page && $viewport_height !== $image_info['height'] ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, false, $format, $quality, $warnings, 'render_output_height_mismatch', 'The rendered image height does not match the requested viewport height.', self::engine_id( $engine ) );
		}

		if ( $image_info['height'] > self::MAX_IMAGE_HEIGHT || ( $image_info['width'] * $image_info['height'] ) > self::MAX_PIXELS ) {
			return self::failure( $post_id, $page_url, $width, $viewport_height, $full_page, $format, $quality, $warnings, 'render_output_dimensions_exceeded', 'The rendered image exceeded the configured maximum dimensions or pixel count.', self::engine_id( $engine ) );
		}

		$engine_warn = isset( $engine_result['warnings'] ) && is_array( $engine_result['warnings'] ) ? $engine_result['warnings'] : array();
		$warnings    = array_merge( $warnings, $engine_warn );
		$render_method = isset( $engine_result['render_method'] ) && is_string( $engine_result['render_method'] ) && '' !== trim( $engine_result['render_method'] )
			? trim( $engine_result['render_method'] )
			: self::engine_id( $engine );

		$image_file = sprintf(
			'divi-%d-%d-%s.%s',
			$post_id,
			$width,
			$full_page ? 'full' : (string) $viewport_height,
			'jpeg' === $format ? 'jpg' : 'png'
		);

		$metadata = array(
			'success'        => true,
			'post_id'        => $post_id,
			'page_url'       => $page_url,
			'width'          => $width,
			'height'         => $viewport_height,
			'full_page'      => $full_page,
			'format'         => $format,
			'quality'        => $quality,
			'image_file'     => $image_file,
			'image_width'    => $image_info['width'],
			'image_height'   => $image_info['height'],
			'render_method'  => $render_method,
			'warnings'       => $warnings,
			'error_code'     => null,
			'error_message'  => null,
		);

		return array_merge(
			$metadata,
			array(
				'type'     => 'image',
				'results'  => $image_data,
				'mimeType' => $image_info['mime_type'],
				'_meta'    => $metadata,
			)
		);
	}

	/**
	 * Authenticate only a short-lived, exact-target screenshot preview request.
	 *
	 * @param int|false $user_id Existing user identity.
	 * @return int|false
	 */
	public static function determine_preview_user( $user_id ) {
		if ( is_int( $user_id ) && $user_id > 0 ) {
			return $user_id;
		}

		if ( '1' !== self::query_scalar( self::QUERY_FLAG ) ) {
			return $user_id;
		}

		$post_id     = (int) self::query_scalar( self::QUERY_POST );
		$preview_uid = (int) self::query_scalar( self::QUERY_USER );
		$expires     = (int) self::query_scalar( self::QUERY_EXP );
		$target_hash = self::query_scalar( self::QUERY_TARGET );
		$signature   = self::query_scalar( self::QUERY_SIG );
		$now         = time();

		if ( $post_id < 1 || $preview_uid < 1 || $expires < $now || ( $expires - $now ) > self::PREVIEW_TOKEN_MAX_TTL ) {
			return $user_id;
		}
		if ( 64 !== strlen( $target_hash ) || 64 !== strlen( $signature ) || ! ctype_xdigit( $target_hash ) || ! ctype_xdigit( $signature ) ) {
			return $user_id;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $request_uri ) {
			return $user_id;
		}

		$current_target_hash = hash( 'sha256', self::canonical_target( $request_uri ) );
		if ( ! hash_equals( strtolower( $target_hash ), $current_target_hash ) ) {
			return $user_id;
		}

		$payload  = self::token_payload( $post_id, $preview_uid, $expires, $current_target_hash );
		$expected = hash_hmac( 'sha256', $payload, self::signing_key() );
		if ( ! hash_equals( $expected, strtolower( $signature ) ) ) {
			return $user_id;
		}

		if ( ! user_can( $preview_uid, 'edit_post', $post_id ) ) {
			return $user_id;
		}

		$GLOBALS['divi5_mcp_screenshot_preview_active'] = true;
		return $preview_uid;
	}

	/**
	 * Suppress the WordPress admin bar in authenticated draft screenshots.
	 */
	public static function filter_admin_bar( bool $show ): bool {
		return ! empty( $GLOBALS['divi5_mcp_screenshot_preview_active'] ) ? false : $show;
	}

	/**
	 * @param array<string, mixed> $context Engine discovery context.
	 */
	private static function resolve_engine( array $context = array() ): ?ScreenshotEngineInterface {
		if ( ! function_exists( 'apply_filters' ) ) {
			return null;
		}

		/**
		 * Register a backend renderer capable of producing real frontend pixels.
		 *
		 * The returned object must implement ScreenshotEngineInterface. The plugin
		 * intentionally does not bundle or approximate a browser/CSS layout engine.
		 *
		 * @param ScreenshotEngineInterface|null $engine  Registered renderer.
		 * @param array<string, mixed>            $context Current post/runtime context.
		 */
		$engine = apply_filters( 'divi5_woocommerce_mcp_screenshot_engine', null, $context );
		return $engine instanceof ScreenshotEngineInterface ? $engine : null;
	}

	private static function engine_id( ScreenshotEngineInterface $engine ): string {
		try {
			$id = strtolower( trim( $engine->id() ) );
		} catch ( Throwable $throwable ) {
			return 'unknown-renderer';
		}

		$id = (string) preg_replace( '/[^a-z0-9._:-]+/', '-', $id );
		return '' !== trim( $id, '-' ) ? trim( $id, '-' ) : 'unknown-renderer';
	}

	private static function sign_preview_url( string $preview_url, int $post_id ): string {
		if ( ! function_exists( 'add_query_arg' ) || ! function_exists( 'get_current_user_id' ) ) {
			return '';
		}

		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return '';
		}

		$expires     = time() + self::PREVIEW_TOKEN_TTL;
		$target_hash = hash( 'sha256', self::canonical_target( $preview_url ) );
		$signature   = hash_hmac( 'sha256', self::token_payload( $post_id, $user_id, $expires, $target_hash ), self::signing_key() );

		return (string) add_query_arg(
			array(
				self::QUERY_FLAG   => '1',
				self::QUERY_POST   => (string) $post_id,
				self::QUERY_USER   => (string) $user_id,
				self::QUERY_EXP    => (string) $expires,
				self::QUERY_TARGET => $target_hash,
				self::QUERY_SIG    => $signature,
			),
			$preview_url
		);
	}

	private static function token_payload( int $post_id, int $user_id, int $expires, string $target_hash ): string {
		return implode( '|', array( (string) $post_id, (string) $user_id, (string) $expires, $target_hash ) );
	}

	private static function signing_key(): string {
		if ( function_exists( 'wp_salt' ) ) {
			return (string) wp_salt( 'auth' );
		}
		if ( defined( 'AUTH_SALT' ) ) {
			return (string) AUTH_SALT;
		}
		return 'divi5-mcp-screenshot-unavailable-key';
	}

	private static function canonical_target( string $url_or_uri ): string {
		$parts = parse_url( $url_or_uri );
		$path  = isset( $parts['path'] ) && is_string( $parts['path'] ) && '' !== $parts['path'] ? $parts['path'] : '/';
		$query = array();

		if ( isset( $parts['query'] ) && is_string( $parts['query'] ) && '' !== $parts['query'] ) {
			parse_str( $parts['query'], $query );
		}

		foreach ( self::token_query_keys() as $key ) {
			unset( $query[ $key ] );
		}
		ksort( $query );

		$encoded = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		return '' !== $encoded ? $path . '?' . $encoded : $path;
	}

	/**
	 * @return array<int, string>
	 */
	private static function token_query_keys(): array {
		return array(
			self::QUERY_FLAG,
			self::QUERY_POST,
			self::QUERY_USER,
			self::QUERY_EXP,
			self::QUERY_TARGET,
			self::QUERY_SIG,
		);
	}

	private static function query_scalar( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}
		return trim( (string) wp_unslash( $_GET[ $key ] ) );
	}

	private static function is_internal_url( string $url ): bool {
		if ( '' === $url || ! function_exists( 'home_url' ) ) {
			return false;
		}

		$home_parts = wp_parse_url( home_url( '/' ) );
		$url_parts  = wp_parse_url( $url );
		if ( ! is_array( $home_parts ) || ! is_array( $url_parts ) ) {
			return false;
		}

		$home_host = isset( $home_parts['host'] ) ? strtolower( (string) $home_parts['host'] ) : '';
		$url_host  = isset( $url_parts['host'] ) ? strtolower( (string) $url_parts['host'] ) : '';
		if ( '' === $home_host || ! hash_equals( $home_host, $url_host ) ) {
			return false;
		}

		$scheme = isset( $url_parts['scheme'] ) ? strtolower( (string) $url_parts['scheme'] ) : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}

		$home_port = isset( $home_parts['port'] ) ? (int) $home_parts['port'] : self::default_port( isset( $home_parts['scheme'] ) ? (string) $home_parts['scheme'] : '' );
		$url_port  = isset( $url_parts['port'] ) ? (int) $url_parts['port'] : self::default_port( $scheme );
		return $home_port === $url_port;
	}

	private static function default_port( string $scheme ): int {
		return 'https' === strtolower( $scheme ) ? 443 : 80;
	}

	/**
	 * @return array{format:string,mime_type:string,width:int,height:int}|null
	 */
	private static function image_info( string $data ): ?array {
		$format = self::image_format_from_signature( $data );
		if ( null === $format ) {
			return null;
		}

		if ( function_exists( 'getimagesizefromstring' ) ) {
			$size = getimagesizefromstring( $data );
			if ( is_array( $size ) && isset( $size[0], $size[1] ) ) {
				return array(
					'format'    => $format,
					'mime_type' => 'jpeg' === $format ? 'image/jpeg' : 'image/png',
					'width'     => (int) $size[0],
					'height'    => (int) $size[1],
				);
			}
		}

		return 'png' === $format ? self::png_info( $data ) : self::jpeg_info( $data );
	}

	private static function image_format_from_signature( string $data ): ?string {
		if ( strlen( $data ) >= 24 && "\x89PNG\r\n\x1a\n" === substr( $data, 0, 8 ) ) {
			return 'png';
		}
		if ( strlen( $data ) >= 4 && "\xFF\xD8\xFF" === substr( $data, 0, 3 ) ) {
			return 'jpeg';
		}
		return null;
	}

	/**
	 * @return array{format:string,mime_type:string,width:int,height:int}|null
	 */
	private static function png_info( string $data ): ?array {
		if ( strlen( $data ) < 24 || 'IHDR' !== substr( $data, 12, 4 ) ) {
			return null;
		}

		$width  = unpack( 'Nvalue', substr( $data, 16, 4 ) );
		$height = unpack( 'Nvalue', substr( $data, 20, 4 ) );
		if ( ! is_array( $width ) || ! is_array( $height ) || empty( $width['value'] ) || empty( $height['value'] ) ) {
			return null;
		}

		return array(
			'format'    => 'png',
			'mime_type' => 'image/png',
			'width'     => (int) $width['value'],
			'height'    => (int) $height['value'],
		);
	}

	/**
	 * @return array{format:string,mime_type:string,width:int,height:int}|null
	 */
	private static function jpeg_info( string $data ): ?array {
		$length = strlen( $data );
		$offset = 2;
		$sof    = array( 0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF );

		while ( $offset + 8 < $length ) {
			if ( 0xFF !== ord( $data[ $offset ] ) ) {
				++$offset;
				continue;
			}

			while ( $offset < $length && 0xFF === ord( $data[ $offset ] ) ) {
				++$offset;
			}
			if ( $offset >= $length ) {
				break;
			}

			$marker = ord( $data[ $offset ] );
			++$offset;
			if ( 0xD9 === $marker || 0xDA === $marker ) {
				break;
			}
			if ( $offset + 1 >= $length ) {
				break;
			}

			$segment_length = ( ord( $data[ $offset ] ) << 8 ) + ord( $data[ $offset + 1 ] );
			if ( $segment_length < 2 || ( $offset + $segment_length ) > $length ) {
				return null;
			}

			if ( in_array( $marker, $sof, true ) && $segment_length >= 7 ) {
				$height = ( ord( $data[ $offset + 3 ] ) << 8 ) + ord( $data[ $offset + 4 ] );
				$width  = ( ord( $data[ $offset + 5 ] ) << 8 ) + ord( $data[ $offset + 6 ] );
				if ( $width < 1 || $height < 1 ) {
					return null;
				}
				return array(
					'format'    => 'jpeg',
					'mime_type' => 'image/jpeg',
					'width'     => $width,
					'height'    => $height,
				);
			}

			$offset += $segment_length;
		}

		return null;
	}

	/**
	 * @param array<int, mixed> $warnings Warnings collected before failure.
	 * @return array<string, mixed>
	 */
	private static function failure(
		int $post_id,
		string $page_url,
		int $width,
		?int $height,
		bool $full_page,
		string $format,
		?int $quality,
		array $warnings,
		string $code,
		string $message,
		?string $render_method = null
	): array {
		return array(
			'success'        => false,
			'post_id'        => $post_id,
			'page_url'       => $page_url,
			'width'          => $width,
			'height'         => $height,
			'full_page'      => $full_page,
			'format'         => $format,
			'quality'        => $quality,
			'image_file'     => null,
			'image_width'    => null,
			'image_height'   => null,
			'render_method'  => $render_method,
			'warnings'       => $warnings,
			'error_code'     => $code,
			'error_message'  => $message,
		);
	}

	private function __construct() {
	}
}
