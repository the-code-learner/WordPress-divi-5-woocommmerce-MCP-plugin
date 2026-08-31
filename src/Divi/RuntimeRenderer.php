<?php
/**
 * Server-side Divi document render and markup inspection.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

final class RuntimeRenderer {
	/**
	 * Render one WordPress document through the registered block render callbacks.
	 *
	 * @return array<string, mixed>
	 */
	public static function render( int $post_id, bool $include_html = true, string $selector = '' ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return self::failure( $post_id, 'post_not_found', 'The requested post does not exist.' );
		}

		$content  = (string) $post->post_content;
		$warnings = array();
		$html     = '';

		set_error_handler(
			static function ( int $severity, string $message, string $file, int $line ) use ( &$warnings ): bool {
				$warnings[] = array(
					'severity' => $severity,
					'message'  => $message,
					'file'     => basename( $file ),
					'line'     => $line,
				);
				return true;
			}
		);

		try {
			if ( function_exists( 'do_blocks' ) ) {
				$html = (string) do_blocks( $content );
			} elseif ( function_exists( 'apply_filters' ) ) {
				$html = (string) apply_filters( 'the_content', $content );
			} else {
				return self::failure( $post_id, 'render_api_unavailable', 'WordPress block rendering APIs are unavailable.' );
			}
		} catch ( Throwable $throwable ) {
			$warnings[] = array(
				'severity' => 'exception',
				'message'  => $throwable->getMessage(),
				'file'     => basename( $throwable->getFile() ),
				'line'     => $throwable->getLine(),
			);
		} finally {
			restore_error_handler();
		}

		$classes    = self::extract_classes( $html );
		$ids        = self::extract_ids( $html );
		$styles     = self::extract_styles( $html );
		$inspection = '' !== $selector ? self::inspect_markup( $html, $selector ) : null;

		$result = array(
			'success'           => true,
			'post_id'           => $post_id,
			'post_status'       => (string) $post->post_status,
			'render_method'     => function_exists( 'do_blocks' ) ? 'do_blocks' : 'the_content_filter',
			'render_signature'  => hash( 'sha256', $html ),
			'html_length'       => strlen( $html ),
			'generated_classes' => $classes,
			'generated_ids'     => $ids,
			'generated_css'     => $styles,
			'warnings'          => $warnings,
			'inspection'        => $inspection,
			'computed_styles'   => array(
				'status'   => 'unavailable',
				'evidence' => 'server-side markup rendering does not execute a browser layout engine',
			),
			'error_code'        => null,
			'error_message'     => null,
		);

		if ( $include_html ) {
			$result['html'] = $html;
		}

		return $result;
	}

	/**
	 * Limited server-side selector inspection for tag, .class, #id, and [attribute].
	 *
	 * @return array<string, mixed>
	 */
	private static function inspect_markup( string $html, string $selector ): array {
		if ( ! class_exists( DOMDocument::class ) || ! class_exists( DOMXPath::class ) ) {
			return array(
				'status'   => 'unavailable',
				'selector' => $selector,
				'reason'   => 'DOM extension is unavailable.',
			);
		}

		$xpath_query = self::selector_to_xpath( $selector );
		if ( null === $xpath_query ) {
			return array(
				'status'   => 'unsupported-selector',
				'selector' => $selector,
				'reason'   => 'Only tag, .class, #id, and [attribute] selectors are supported by the server-side inspector.',
			);
		}

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadHTML( '<!doctype html><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return array(
				'status'   => 'parse-failed',
				'selector' => $selector,
				'matches'  => array(),
			);
		}

		$xpath   = new DOMXPath( $dom );
		$nodes   = $xpath->query( $xpath_query );
		$matches = array();
		if ( false !== $nodes ) {
			foreach ( $nodes as $node ) {
				if ( ! $node instanceof DOMElement ) {
					continue;
				}
				$attributes = array();
				foreach ( $node->attributes as $attribute ) {
					$attributes[ $attribute->nodeName ] = $attribute->nodeValue;
				}
				$matches[] = array(
					'tag'        => $node->tagName,
					'attributes' => $attributes,
					'outer_html' => (string) $dom->saveHTML( $node ),
				);
				if ( count( $matches ) >= 50 ) {
					break;
				}
			}
		}

		return array(
			'status'      => 'supported',
			'selector'    => $selector,
			'match_count' => false !== $nodes ? $nodes->length : 0,
			'matches'     => $matches,
		);
	}

	private static function selector_to_xpath( string $selector ): ?string {
		$selector = trim( $selector );
		if ( 1 === preg_match( '/^#([A-Za-z][A-Za-z0-9_:\-]*)$/', $selector, $matches ) ) {
			return '//*[@id=' . self::xpath_literal( $matches[1] ) . ']';
		}
		if ( 1 === preg_match( '/^\.([A-Za-z_][A-Za-z0-9_\-]*)$/', $selector, $matches ) ) {
			$class = $matches[1];
			return "//*[contains(concat(' ', normalize-space(@class), ' '), " . self::xpath_literal( ' ' . $class . ' ' ) . ')]';
		}
		if ( 1 === preg_match( '/^\[([A-Za-z_:][A-Za-z0-9_:.\-]*)\]$/', $selector, $matches ) ) {
			return '//*[@' . $matches[1] . ']';
		}
		if ( 1 === preg_match( '/^[A-Za-z][A-Za-z0-9-]*$/', $selector ) ) {
			return '//' . strtolower( $selector );
		}
		return null;
	}

	private static function xpath_literal( string $value ): string {
		if ( false === strpos( $value, "'" ) ) {
			return "'" . $value . "'";
		}
		if ( false === strpos( $value, '"' ) ) {
			return '"' . $value . '"';
		}
		$parts   = explode( "'", $value );
		$encoded = array();
		foreach ( $parts as $part ) {
			$encoded[] = "'" . $part . "'";
		}
		return 'concat(' . implode( ', "\'", ', $encoded ) . ')';
	}

	/**
	 * @return array<int, string>
	 */
	private static function extract_classes( string $html ): array {
		$classes = array();
		if ( preg_match_all( '/\bclass=(?:"([^"]*)"|\'([^\']*)\')/i', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$value = isset( $match[1] ) && '' !== $match[1] ? $match[1] : ( isset( $match[2] ) ? $match[2] : '' );
				foreach ( preg_split( '/\s+/', trim( $value ) ) as $class ) {
					if ( is_string( $class ) && '' !== $class ) {
						$classes[] = $class;
					}
				}
			}
		}
		$classes = array_values( array_unique( $classes ) );
		sort( $classes );
		return $classes;
	}

	/**
	 * @return array<int, string>
	 */
	private static function extract_ids( string $html ): array {
		$ids = array();
		if ( preg_match_all( '/\bid=(?:"([^"]+)"|\'([^\']+)\')/i', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$value = isset( $match[1] ) && '' !== $match[1] ? $match[1] : ( isset( $match[2] ) ? $match[2] : '' );
				if ( '' !== $value ) {
					$ids[] = $value;
				}
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * @return array<int, string>
	 */
	private static function extract_styles( string $html ): array {
		$styles = array();
		if ( preg_match_all( '/<style\b[^>]*>(.*?)<\/style>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $style ) {
				if ( is_string( $style ) && '' !== trim( $style ) ) {
					$styles[] = trim( $style );
				}
			}
		}
		return $styles;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function failure( int $post_id, string $code, string $message ): array {
		return array(
			'success'       => false,
			'post_id'       => $post_id,
			'error_code'    => $code,
			'error_message' => $message,
		);
	}

	private function __construct() {
	}
}
