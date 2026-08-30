<?php
/**
 * Runtime discovery for native Divi 5 block modules.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

use Throwable;

final class ModuleRegistry {
	private const MODULE_REGISTRATION_CLASS = '\\ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration';

	/**
	 * Divi core nested-module relationships verified from module metadata.
	 * Runtime metadata, when exposed by the registered block type, takes priority.
	 */
	private const CORE_CHILDREN = array(
		'divi/accordion'           => array( 'divi/accordion-item' ),
		'divi/bar-counters'        => array( 'divi/bar-counter' ),
		'divi/contact-form'        => array( 'divi/contact-field' ),
		'divi/fullwidth-slider'    => array( 'divi/slide' ),
		'divi/pricing-tables'      => array( 'divi/pricing-table' ),
		'divi/signup'              => array( 'divi/signup-custom-field' ),
		'divi/slider'              => array( 'divi/slide' ),
		'divi/social-media-follow' => array( 'divi/social-media-follow-network' ),
		'divi/tabs'                => array( 'divi/tab' ),
	);

	/**
	 * List native Divi modules registered on the current WordPress runtime.
	 *
	 * @return array<string, mixed>
	 */
	public static function catalog(): array {
		$registered = self::registered_types();
		$modules    = array();

		foreach ( $registered as $name => $type ) {
			if ( ! LayoutManager::is_semantic_native_block_name( $name ) ) {
				continue;
			}

			$schema    = self::describe_type( $name, $type, false );
			$modules[] = array(
				'name'             => $schema['name'],
				'title'            => $schema['title'],
				'category'         => $schema['category'],
				'parent'           => $schema['parent'],
				'ancestor'         => $schema['ancestor'],
				'allowed_children' => $schema['allowed_children'],
				'attribute_names'  => array_keys( $schema['attributes'] ),
			);
		}

		usort(
			$modules,
			static function ( array $left, array $right ): int {
				return strcmp( (string) $left['name'], (string) $right['name'] );
			}
		);

		return array(
			'success'       => true,
			'module_count'  => count( $modules ),
			'modules'       => $modules,
			'error_code'    => null,
			'error_message' => null,
		);
	}

	/**
	 * Return the registered schema and Divi defaults for one native module.
	 *
	 * @return array<string, mixed>
	 */
	public static function schema( string $module_name ): array {
		if ( ! LayoutManager::is_semantic_native_block_name( $module_name ) ) {
			return self::failure( 'invalid_module_name', 'Module name must identify a native semantic divi/* block.' );
		}

		$registered = self::registered_types();

		if ( ! isset( $registered[ $module_name ] ) || ! is_object( $registered[ $module_name ] ) ) {
			return self::failure( 'module_not_registered', 'The requested Divi module is not registered on this site.' );
		}

		$result                  = self::describe_type( $module_name, $registered[ $module_name ], true );
		$result['success']       = true;
		$result['error_code']    = null;
		$result['error_message'] = null;

		return $result;
	}

	/**
	 * Check an existing native block relationship before a cross-container edit.
	 */
	public static function allows_child( string $parent_name, string $child_name ): bool {
		if ( 'divi/placeholder' === $parent_name ) {
			return 'divi/section' === $child_name;
		}

		if ( 'divi/section' === $parent_name ) {
			return 'divi/row' === $child_name;
		}

		if ( 'divi/row' === $parent_name ) {
			return 'divi/column' === $child_name;
		}

		if ( 'divi/column' === $parent_name ) {
			return LayoutManager::is_semantic_native_block_name( $child_name )
				&& ! in_array( $child_name, array( 'divi/section', 'divi/row', 'divi/column' ), true );
		}

		if ( isset( self::CORE_CHILDREN[ $parent_name ] ) ) {
			return in_array( $child_name, self::CORE_CHILDREN[ $parent_name ], true );
		}

		$registered = self::registered_types();
		$parent     = $registered[ $parent_name ] ?? null;
		$child      = $registered[ $child_name ] ?? null;

		if ( ! is_object( $parent ) || ! is_object( $child ) ) {
			return false;
		}

		$allowed = self::allowed_children( $parent_name, $parent );

		if ( array() !== $allowed ) {
			return in_array( $child_name, $allowed, true );
		}

		$parents = self::string_list_property( $child, 'parent' );

		return array() !== $parents && in_array( $parent_name, $parents, true );
	}

	/**
	 * @return array<string, object>
	 */
	private static function registered_types(): array {
		if ( ! class_exists( '\\WP_Block_Type_Registry' ) ) {
			return array();
		}

		$registry = \WP_Block_Type_Registry::get_instance();

		if ( ! is_object( $registry ) || ! method_exists( $registry, 'get_all_registered' ) ) {
			return array();
		}

		$types = $registry->get_all_registered();

		return is_array( $types ) ? $types : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function describe_type( string $name, object $type, bool $include_defaults ): array {
		$attributes = isset( $type->attributes ) && is_array( $type->attributes ) ? $type->attributes : array();
		$supports   = isset( $type->supports ) && is_array( $type->supports ) ? $type->supports : array();
		$result     = array(
			'name'             => $name,
			'title'            => isset( $type->title ) && is_string( $type->title ) ? $type->title : $name,
			'category'         => isset( $type->category ) && is_string( $type->category ) ? $type->category : '',
			'parent'           => self::string_list_property( $type, 'parent' ),
			'ancestor'         => self::string_list_property( $type, 'ancestor' ),
			'allowed_children' => self::allowed_children( $name, $type ),
			'attributes'       => $attributes,
			'supports'         => $supports,
		);

		if ( $include_defaults ) {
			$result['default_attributes'] = self::default_attributes( $name );
		}

		return $result;
	}

	/**
	 * @return array<int, string>
	 */
	private static function allowed_children( string $name, object $type ): array {
		foreach ( array( 'allowed_blocks', 'childrenName', 'children_name' ) as $property ) {
			$children = self::string_list_property( $type, $property );

			if ( array() !== $children ) {
				return $children;
			}
		}

		return self::CORE_CHILDREN[ $name ] ?? array();
	}

	/**
	 * @return array<int, string>
	 */
	private static function string_list_property( object $object, string $property ): array {
		if ( ! isset( $object->{$property} ) || ! is_array( $object->{$property} ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$object->{$property},
				static function ( $value ): bool {
					return is_string( $value ) && '' !== $value;
				}
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function default_attributes( string $module_name ): array {
		$class = self::MODULE_REGISTRATION_CLASS;

		if ( ! class_exists( $class ) || ! method_exists( $class, 'get_default_attrs' ) ) {
			return array();
		}

		try {
			$defaults = $class::get_default_attrs( $module_name );

			return is_array( $defaults ) ? $defaults : array();
		} catch ( Throwable $throwable ) {
			return array();
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function failure( string $code, string $message ): array {
		return array(
			'success'       => false,
			'error_code'    => $code,
			'error_message' => $message,
		);
	}

	private function __construct() {
	}
}
