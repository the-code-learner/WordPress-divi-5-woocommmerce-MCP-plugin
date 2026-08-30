<?php
/**
 * Normalize persisted Divi attributes without discarding native evidence.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class RuntimeAttributeNormalizer {
	private const DEVICES = array( 'desktop', 'tablet', 'phone' );

	/**
	 * Normalize current native attributes against runtime parameters.
	 *
	 * @param array<string, mixed>             $attributes Native node attributes.
	 * @param array<int, array<string, mixed>> $parameters Runtime parameters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize( array $attributes, array $parameters ): array {
		$properties = array();
		$index      = self::leaf_index( $attributes );

		foreach ( $parameters as $parameter ) {
			if ( ! is_array( $parameter ) || ! isset( $parameter['semantic_path'] ) || ! is_string( $parameter['semantic_path'] ) ) {
				continue;
			}

			$match = self::match_parameter( $parameter, $index );

			if ( null === $match ) {
				continue;
			}

			$value_by_device = isset( $match['value_by_device'] ) && is_array( $match['value_by_device'] )
				? $match['value_by_device']
				: array();
			$value           = array_key_exists( 'desktop', $value_by_device )
				? $value_by_device['desktop']
				: ( array() !== $value_by_device ? reset( $value_by_device ) : null );

			$properties[ $parameter['semantic_path'] ] = array(
				'value'              => $value,
				'value_by_device'    => $value_by_device,
				'devices'            => array_values( array_keys( $value_by_device ) ),
				'native_path'        => $match['canonical_path'],
				'native_value_paths' => $match['value_paths'],
				'native_provenance'  => $match['provenance'],
				'native_confidence'  => $match['confidence'],
			);
		}

		return $properties;
	}

	/**
	 * Strengthen one runtime parameter with evidence from an existing document node.
	 *
	 * @param array<string, mixed> $attributes Existing node attributes.
	 * @param array<string, mixed> $parameter Runtime parameter.
	 * @return array<string, mixed>
	 */
	public static function strengthen_parameter( array $attributes, array $parameter ): array {
		$normalized = self::normalize( $attributes, array( $parameter ) );
		$semantic   = isset( $parameter['semantic_path'] ) && is_string( $parameter['semantic_path'] )
			? $parameter['semantic_path']
			: '';

		if ( '' === $semantic || ! isset( $normalized[ $semantic ] ) ) {
			return $parameter;
		}

		$evidence                       = $normalized[ $semantic ];
		$parameter['native_path']        = $evidence['native_path'];
		$parameter['native_value_paths'] = $evidence['native_value_paths'];
		$parameter['native_provenance']  = $evidence['native_provenance'];
		$parameter['native_confidence']  = $evidence['native_confidence'];

		if ( isset( $evidence['value_by_device'] ) && is_array( $evidence['value_by_device'] ) ) {
			$parameter['devices']     = array_values( array_keys( $evidence['value_by_device'] ) );
			$parameter['breakpoints'] = $parameter['devices'];
		}

		return $parameter;
	}

	/**
	 * @param array<string, mixed> $attributes Native attributes.
	 * @return array<string, array<string, mixed>>
	 */
	private static function leaf_index( array $attributes ): array {
		$index = array();
		self::collect_leaves( $attributes, array(), $index );

		return $index;
	}

	/**
	 * @param mixed                               $value Current value.
	 * @param array<int, string>                  $segments Native path segments.
	 * @param array<string, array<string, mixed>> $index Indexed leaves.
	 * @param string|null                         $device Device envelope.
	 * @param array<int, string>|null             $canonical_override Canonical segments after an envelope.
	 */
	private static function collect_leaves( $value, array $segments, array &$index, ?string $device = null, ?array $canonical_override = null ): void {
		if ( is_array( $value ) ) {
			$has_device = false;

			foreach ( self::DEVICES as $candidate ) {
				if ( array_key_exists( $candidate, $value ) && is_array( $value[ $candidate ] ) && array_key_exists( 'value', $value[ $candidate ] ) ) {
					$has_device   = true;
					$device_value = $value[ $candidate ]['value'];
					$raw_prefix   = array_merge( $segments, array( $candidate, 'value' ) );

					if ( is_array( $device_value ) ) {
						foreach ( $device_value as $child_key => $child_value ) {
							if ( is_string( $child_key ) ) {
								self::collect_leaves(
									$child_value,
									array_merge( $raw_prefix, array( $child_key ) ),
									$index,
									$candidate,
									array_merge( $segments, array( $child_key ) )
								);
							}
						}
					} else {
						self::record_leaf( $segments, $raw_prefix, $device_value, $candidate, $index );
					}
				}
			}

			if ( $has_device ) {
				return;
			}

			foreach ( $value as $key => $child ) {
				if ( is_string( $key ) ) {
					$canonical = null !== $canonical_override
						? array_merge( $canonical_override, array( $key ) )
						: null;
					self::collect_leaves( $child, array_merge( $segments, array( $key ) ), $index, $device, $canonical );
				}
			}

			return;
		}

		$canonical_segments = null !== $canonical_override ? $canonical_override : $segments;
		self::record_leaf( $canonical_segments, $segments, $value, $device, $index );
	}

	/**
	 * @param array<int, string>                  $canonical_segments Canonical path.
	 * @param array<int, string>                  $raw_segments Raw path.
	 * @param mixed                               $value Leaf value.
	 * @param array<string, array<string, mixed>> $index Indexed leaves.
	 */
	private static function record_leaf( array $canonical_segments, array $raw_segments, $value, ?string $device, array &$index ): void {
		$canonical = implode( '.', $canonical_segments );

		if ( '' === $canonical ) {
			return;
		}

		if ( ! isset( $index[ $canonical ] ) ) {
			$index[ $canonical ] = array(
				'canonical_path'  => $canonical,
				'value_by_device' => array(),
				'value_paths'     => array(),
			);
		}

		$key = null !== $device ? $device : 'default';
		$index[ $canonical ]['value_by_device'][ $key ] = $value;
		$index[ $canonical ]['value_paths'][ $key ]     = implode( '.', $raw_segments );
	}

	/**
	 * @param array<string, mixed>                $parameter Runtime parameter.
	 * @param array<string, array<string, mixed>> $index Indexed actual attributes.
	 * @return array<string, mixed>|null
	 */
	private static function match_parameter( array $parameter, array $index ): ?array {
		$semantic = isset( $parameter['semantic_path'] ) && is_string( $parameter['semantic_path'] )
			? $parameter['semantic_path']
			: '';
		$native   = isset( $parameter['native_path'] ) && is_string( $parameter['native_path'] )
			? $parameter['native_path']
			: '';

		foreach ( array_unique( array_filter( array( $native, $semantic ), 'strlen' ) ) as $path ) {
			if ( isset( $index[ $path ] ) ) {
				$record               = $index[ $path ];
				$record['provenance'] = $path === $native && '' !== $native
					? 'document_leaf_native_match'
					: 'document_leaf_exact_match';
				$record['confidence'] = 'high';
				return $record;
			}
		}

		$segments = explode( '.', $semantic );
		$leaf     = (string) end( $segments );
		$root     = isset( $segments[0] ) ? $segments[0] : '';
		$matches  = array();

		foreach ( $index as $path => $record ) {
			$parts = explode( '.', $path );

			if ( (string) end( $parts ) === $leaf && ( '' === $root || $parts[0] === $root ) ) {
				$matches[] = $record;
			}
		}

		if ( 1 === count( $matches ) ) {
			$matches[0]['provenance'] = 'document_leaf_unique_match';
			$matches[0]['confidence'] = 'medium';
			return $matches[0];
		}

		return null;
	}

	private function __construct() {
	}
}
