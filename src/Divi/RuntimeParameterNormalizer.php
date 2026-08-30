<?php
/**
 * Normalize Divi runtime settings into conservative semantic parameters.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class RuntimeParameterNormalizer {
	private const DEVICES = array( 'desktop', 'tablet', 'phone' );

	private const STRUCTURAL_KEYS = array(
		'settings',
		'items',
		'item',
		'groups',
		'groupType',
		'component',
		'props',
		'fields',
		'features',
		'options',
	);

	/**
	 * Build the semantic parameter graph from runtime attribute metadata.
	 *
	 * @param array<string, mixed> $attributes Runtime attribute schema.
	 * @param array<string, mixed> $defaults Runtime default attributes.
	 * @return array<int, array<string, mixed>>
	 */
	public static function normalize( array $attributes, array $defaults ): array {
		$parameters    = array();
		$default_index = self::leaf_index( $defaults );

		foreach ( $attributes as $name => $schema ) {
			if ( ! is_string( $name ) || ! is_array( $schema ) ) {
				continue;
			}

			self::walk_schema( $schema, array( $name ), $default_index, $parameters );
		}

		ksort( $parameters );

		return array_values( $parameters );
	}

	/**
	 * @param array<string, mixed>                $node Runtime schema node.
	 * @param array<int, string>                  $segments Semantic path segments.
	 * @param array<string, array<string, mixed>> $default_index Indexed runtime defaults.
	 * @param array<string, array<string, mixed>> $parameters Parameters keyed by semantic path.
	 */
	private static function walk_schema( array $node, array $segments, array $default_index, array &$parameters ): void {
		if ( self::is_control( $node ) ) {
			$parameter = self::normalize_control( $node, $segments, $default_index );

			if ( null !== $parameter ) {
				$parameters[ $parameter['semantic_path'] ] = $parameter;
			}
		}

		foreach ( $node as $key => $value ) {
			if ( ! is_string( $key ) || ! is_array( $value ) ) {
				continue;
			}

			$next = $segments;

			if ( ! in_array( $key, self::STRUCTURAL_KEYS, true ) && ! self::is_numeric_key( $key ) ) {
				$next[] = $key;
			}

			self::walk_schema( $value, $next, $default_index, $parameters );
		}
	}

	/**
	 * @param array<string, mixed> $node Runtime schema node.
	 */
	private static function is_control( array $node ): bool {
		if ( isset( $node['attrName'] ) && is_string( $node['attrName'] ) && '' !== $node['attrName'] ) {
			return true;
		}

		if ( isset( $node['subName'] ) && is_string( $node['subName'] ) && '' !== $node['subName'] ) {
			return true;
		}

		return isset( $node['component'] ) && is_array( $node['component'] )
			&& ( isset( $node['features'] ) || isset( $node['label'] ) || isset( $node['groupSlug'] ) );
	}

	/**
	 * @param array<string, mixed>                $node Runtime schema node.
	 * @param array<int, string>                  $segments Semantic path segments.
	 * @param array<string, array<string, mixed>> $default_index Indexed runtime defaults.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_control( array $node, array $segments, array $default_index ): ?array {
		$attr_name = isset( $node['attrName'] ) && is_string( $node['attrName'] ) ? trim( $node['attrName'] ) : '';
		$sub_name  = isset( $node['subName'] ) && is_string( $node['subName'] ) ? trim( $node['subName'] ) : '';
		$semantic  = '' !== $attr_name && '' === $sub_name ? $attr_name : implode( '.', self::deduplicate_segments( $segments, $sub_name ) );

		if ( '' === $semantic ) {
			return null;
		}

		$runtime_hint = '' !== $attr_name
			? $attr_name . ( '' !== $sub_name ? '.' . $sub_name : '' )
			: $semantic;
		$match        = self::default_match( $semantic, $runtime_hint, $default_index );
		$native_path  = null;
		$provenance   = 'unknown';
		$confidence   = 'unknown';

		if ( '' !== $attr_name && '' === $sub_name ) {
			$native_path = $attr_name;
			$provenance  = 'runtime_attr_name';
			$confidence  = 'high';
		}

		if ( null !== $match ) {
			$native_path = $match['canonical_path'];

			if ( '' !== $attr_name && '' === $sub_name && $attr_name === $match['canonical_path'] ) {
				$provenance = 'runtime_attr_name';
				$confidence = 'high';
			} else {
				$provenance = $match['provenance'];
				$confidence = $match['confidence'];
			}
		}

		$features        = isset( $node['features'] ) && is_array( $node['features'] ) ? $node['features'] : array();
		$component       = isset( $node['component'] ) && is_array( $node['component'] ) ? $node['component'] : array();
		$value_by_device = null !== $match && isset( $match['value_by_device'] ) && is_array( $match['value_by_device'] )
			? $match['value_by_device']
			: array();
		$devices         = array_values( array_keys( $value_by_device ) );
		$default         = array_key_exists( 'desktop', $value_by_device )
			? $value_by_device['desktop']
			: ( array() !== $value_by_device ? reset( $value_by_device ) : null );

		return array(
			'semantic_path'        => $semantic,
			'runtime_hint'         => $runtime_hint,
			'native_path'          => $native_path,
			'native_provenance'    => $provenance,
			'native_confidence'    => $confidence,
			'native_value_paths'   => null !== $match ? $match['value_paths'] : array(),
			'write_mapping'        => null !== $match && array() !== $match['value_paths'] ? 'supported' : 'unavailable',
			'type'                 => self::infer_type( $node, $component, $features, $match ),
			'default'              => $default,
			'default_by_device'    => $value_by_device,
			'raw_default'          => null !== $match ? $match['raw_default'] : null,
			'enum'                 => self::enum_values( $node, $component ),
			'constraints'          => self::constraints( $node ),
			'allowed_units'        => self::discover_scalar_list( $node, array( 'allowedUnits', 'allowed_units', 'units' ) ),
			'responsive'           => self::feature_status( $features, 'responsive' ),
			'devices'              => $devices,
			'breakpoints'          => $devices,
			'hover'                => self::feature_status( $features, 'hover' ),
			'sticky'               => self::feature_status( $features, 'sticky' ),
			'preset_support'       => self::feature_status( $features, 'preset' ),
			'design_variable'      => self::feature_status( $features, 'designVariable', 'designVariables' ),
			'global_value_support' => self::feature_status( $features, 'globalValue', 'globalValues' ),
		);
	}

	/**
	 * @param array<int, string> $segments Existing semantic segments.
	 * @return array<int, string>
	 */
	private static function deduplicate_segments( array $segments, string $sub_name ): array {
		$segments = array_values(
			array_filter(
				$segments,
				static function ( $segment ): bool {
					return is_string( $segment ) && '' !== $segment;
				}
			)
		);

		if ( '' !== $sub_name && ( array() === $segments || end( $segments ) !== $sub_name ) ) {
			$segments[] = $sub_name;
		}

		return $segments;
	}

	/**
	 * Index defaults by their semantic/native leaf path while preserving actual value paths.
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, array<string, mixed>>
	 */
	private static function leaf_index( array $defaults ): array {
		$index = array();
		self::collect_leaves( $defaults, array(), $index );

		return $index;
	}

	/**
	 * @param mixed                                $value Current value.
	 * @param array<int, string>                   $segments Current native segments.
	 * @param array<string, array<string, mixed>>  $index Indexed leaves.
	 * @param string|null                          $device Current device envelope.
	 * @param array<int, string>|null              $canonical_override Canonical path after a device envelope.
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
	 * @param array<int, string>                  $raw_segments Raw storage path.
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
				'raw_default'     => null,
			);
		}

		$key = null !== $device ? $device : 'default';
		$index[ $canonical ]['value_by_device'][ $key ] = $value;
		$index[ $canonical ]['value_paths'][ $key ]     = implode( '.', $raw_segments );
		$index[ $canonical ]['raw_default']             = $index[ $canonical ]['value_by_device'];
	}

	/**
	 * @param array<string, array<string, mixed>> $index Indexed defaults.
	 * @return array<string, mixed>|null
	 */
	private static function default_match( string $semantic, string $runtime_hint, array $index ): ?array {
		foreach ( array_unique( array( $semantic, $runtime_hint ) ) as $path ) {
			if ( isset( $index[ $path ] ) ) {
				return self::decorate_match( $index[ $path ], 'default_leaf_exact_match', 'medium' );
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
			return self::decorate_match( $matches[0], 'default_leaf_unique_match', 'medium' );
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $matched_default Matched default record.
	 * @return array<string, mixed>
	 */
	private static function decorate_match( array $matched_default, string $provenance, string $confidence ): array {
		$matched_default['provenance'] = $provenance;
		$matched_default['confidence'] = $confidence;

		return $matched_default;
	}

	/**
	 * @param array<string, mixed> $features Leaf feature metadata.
	 */
	private static function feature_status( array $features, string ...$keys ): string {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $features ) ) {
				return false === $features[ $key ] || null === $features[ $key ] ? 'unavailable' : 'supported';
			}
		}

		return 'unknown';
	}

	/**
	 * @param array<string, mixed>      $node Runtime schema node.
	 * @param array<string, mixed>      $component Component metadata.
	 * @param array<string, mixed>      $features Leaf feature metadata.
	 * @param array<string, mixed>|null $matched_default Runtime default match.
	 */
	private static function infer_type( array $node, array $component, array $features, ?array $matched_default ): string {
		if ( isset( $node['type'] ) && is_string( $node['type'] ) ) {
			return $node['type'];
		}

		if ( isset( $features['dynamicContent'] ) && is_array( $features['dynamicContent'] )
			&& isset( $features['dynamicContent']['type'] ) && is_string( $features['dynamicContent']['type'] )
		) {
			return $features['dynamicContent']['type'];
		}

		if ( array() !== self::enum_values( $node, $component ) ) {
			return 'string';
		}

		if ( null !== $matched_default && isset( $matched_default['value_by_device'] ) && is_array( $matched_default['value_by_device'] ) ) {
			$values = $matched_default['value_by_device'];
			$value  = array_key_exists( 'desktop', $values )
				? $values['desktop']
				: ( array() !== $values ? reset( $values ) : null );
			$type   = self::value_type( $value );

			if ( 'unknown' !== $type ) {
				return $type;
			}
		}

		if ( isset( $component['name'] ) && is_string( $component['name'] ) ) {
			return $component['name'];
		}

		return 'unknown';
	}

	/**
	 * @param mixed $value Runtime default value.
	 */
	private static function value_type( $value ): string {
		if ( is_string( $value ) ) {
			return 'string';
		}

		if ( is_int( $value ) ) {
			return 'integer';
		}

		if ( is_float( $value ) ) {
			return 'number';
		}

		if ( is_bool( $value ) ) {
			return 'boolean';
		}

		if ( is_array( $value ) ) {
			return 'array';
		}

		if ( null === $value ) {
			return 'null';
		}

		return 'unknown';
	}

	/**
	 * @param array<string, mixed> $node Runtime schema node.
	 * @param array<string, mixed> $component Component metadata.
	 * @return array<int, mixed>
	 */
	private static function enum_values( array $node, array $component ): array {
		if ( isset( $node['enum'] ) && is_array( $node['enum'] ) ) {
			return array_values( $node['enum'] );
		}

		$options = isset( $component['props']['options'] ) && is_array( $component['props']['options'] )
			? $component['props']['options']
			: array();

		return array_values( array_filter( array_keys( $options ), 'is_string' ) );
	}

	/**
	 * @param array<string, mixed> $node Runtime schema node.
	 * @return array<string, mixed>
	 */
	private static function constraints( array $node ): array {
		$constraints = array();

		foreach ( array( 'minimum', 'maximum', 'minLength', 'maxLength', 'pattern', 'minItems', 'maxItems' ) as $key ) {
			$value = self::discover_key( $node, $key );

			if ( null !== $value ) {
				$constraints[ $key ] = $value;
			}
		}

		return $constraints;
	}

	/**
	 * @param array<string, mixed> $node Runtime node.
	 * @return mixed|null
	 */
	private static function discover_key( array $node, string $target ) {
		if ( array_key_exists( $target, $node ) ) {
			return $node[ $target ];
		}

		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				$found = self::discover_key( $value, $target );

				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $node Runtime node.
	 * @param array<int, string>   $targets Target keys.
	 * @return array<int, mixed>
	 */
	private static function discover_scalar_list( array $node, array $targets ): array {
		foreach ( $node as $key => $value ) {
			if ( is_string( $key ) && in_array( $key, $targets, true ) && is_array( $value ) ) {
				return array_values(
					array_filter(
						$value,
						static function ( $item ): bool {
							return is_scalar( $item );
						}
					)
				);
			}

			if ( is_array( $value ) ) {
				$nested = self::discover_scalar_list( $value, $targets );

				if ( array() !== $nested ) {
					return $nested;
				}
			}
		}

		return array();
	}

	private static function is_numeric_key( string $key ): bool {
		return ctype_digit( $key );
	}

	private function __construct() {
	}
}
