<?php
/**
 * Divi 5 MCP abilities.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class Abilities {
	private const CATEGORY = 'divi5-woocommerce-mcp';

	public static function hooks(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'divi5-woocommerce-mcp/divi-get-layout',
			array(
				'label'               => __( 'Inspect native Divi 5 layout', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Returns the editable Divi 5 block tree for a page or post, including stable block paths that can be used by the module update ability.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'get_layout' ),
				'permission_callback' => array( self::class, 'can_edit_post' ),
				'input_schema'        => self::post_id_input_schema(),
				'output_schema'       => self::layout_output_schema(),
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-save-layout',
			array(
				'label'               => __( 'Save semantic layout as native Divi 5 modules', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Replaces draft content with a constrained semantic Divi layout. The official Divi 5 converter is used first; unsupported shortcode fallbacks are rejected and replaced with validated native Visual Builder blocks.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'save_layout' ),
				'permission_callback' => array( self::class, 'can_edit_post' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_id', 'layout' ),
					'additionalProperties' => false,
					'properties'           => array(
						'post_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'layout'  => array(
							'type'     => 'array',
							'minItems' => 1,
							'items'    => array(
								'type'                 => 'object',
								'required'             => array( 'type' ),
								'additionalProperties' => true,
								'properties'           => array(
									'type'       => array( 'type' => 'string' ),
									'label'      => array( 'type' => 'string' ),
									'content'    => array( 'type' => 'string' ),
									'attributes' => array(
										'type' => 'object',
										'additionalProperties' => true,
									),
									'children'   => array(
										'type'  => 'array',
										'items' => array(
											'type' => 'object',
											'additionalProperties' => true,
										),
									),
								),
							),
						),
					),
				),
				'output_schema'       => self::layout_output_schema(),
				'meta'                => self::mcp_meta( false, true, false ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-update-module',
			array(
				'label'               => __( 'Update native Divi 5 module attributes', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Patches the native Divi 5 attribute object for one block in draft content. Call divi-get-layout first and pass the returned block path. This is the low-level path for typography, spacing, backgrounds, responsive values, presets, links, and other Divi 5 module settings.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'update_module' ),
				'permission_callback' => array( self::class, 'can_patch_native_module' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_id', 'path', 'attributes' ),
					'additionalProperties' => false,
					'properties'           => array(
						'post_id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'path'       => array(
							'type'    => 'string',
							'pattern' => '^\\d+(?:\\.\\d+)*$',
						),
						'attributes' => array(
							'type'                 => 'object',
							'minProperties'        => 1,
							'additionalProperties' => true,
						),
					),
				),
				'output_schema'       => self::layout_output_schema(),
				'meta'                => self::mcp_meta( false, true, false ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-list-modules',
			array(
				'label'               => __( 'List registered Divi 5 modules', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Lists the native Divi 5 block modules registered by the active Divi runtime, including attribute names and verified nested-module relationships.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'list_modules' ),
				'permission_callback' => array( self::class, 'can_read_modules' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(),
				),
				'output_schema'       => self::module_registry_output_schema(),
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-get-module-schema',
			array(
				'label'               => __( 'Get a registered Divi 5 module schema', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Returns the runtime WordPress block attribute schema, Divi default attributes, supports, and nested-module constraints for one registered native Divi module.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'get_module_schema' ),
				'permission_callback' => array( self::class, 'can_read_modules' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'module_name' ),
					'additionalProperties' => false,
					'properties'           => array(
						'module_name' => array(
							'type'    => 'string',
							'pattern' => '^divi/[a-z0-9-]+$',
						),
					),
				),
				'output_schema'       => self::module_registry_output_schema(),
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-insert-module',
			array(
				'label'               => __( 'Insert a native Divi 5 module', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Inserts one constrained semantic Section, Row, Column, Text, Button, Image, Code, or Divider node at a real parent path and child index returned by layout inspection.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'insert_module' ),
				'permission_callback' => array( self::class, 'can_edit_post' ),
				'input_schema'        => self::insert_module_input_schema(),
				'output_schema'       => self::layout_output_schema(),
				'meta'                => self::mcp_meta( false, true, false ),
			)
		);

		foreach (
			array(
				'divi-delete-module'    => array(
					__( 'Delete native Divi 5 module', 'mcp-bridge-for-divi-woocommerce' ),
					__( 'Deletes one native Divi 5 module identified by a real inspection path. The root placeholder and the last usable native layout cannot be removed.', 'mcp-bridge-for-divi-woocommerce' ),
					'delete_module',
					false,
				),
				'divi-move-module'      => array(
					__( 'Move native Divi 5 module', 'mcp-bridge-for-divi-woocommerce' ),
					__( 'Moves or reorders one native Divi 5 module to a validated parent path and final child index.', 'mcp-bridge-for-divi-woocommerce' ),
					'move_module',
					true,
				),
				'divi-duplicate-module' => array(
					__( 'Duplicate native Divi 5 module', 'mcp-bridge-for-divi-woocommerce' ),
					__( 'Deep-copies one native Divi 5 module, including nested child modules and design attributes, into a validated destination.', 'mcp-bridge-for-divi-woocommerce' ),
					'duplicate_module',
					true,
				),
			) as $ability_name => $definition
		) {
			wp_register_ability(
				'divi5-woocommerce-mcp/' . $ability_name,
				array(
					'label'               => $definition[0],
					'description'         => $definition[1],
					'category'            => self::CATEGORY,
					'execute_callback'    => array( self::class, $definition[2] ),
					'permission_callback' => array( self::class, 'can_edit_post' ),
					'input_schema'        => $definition[3] ? self::relocate_module_input_schema() : self::module_path_input_schema(),
					'output_schema'       => self::layout_output_schema(),
					'meta'                => self::mcp_meta( false, true, false ),
				)
			);
		}
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function get_layout( array $input ): array {
		return LayoutManager::inspect( (int) $input['post_id'] );
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function save_layout( array $input ): array {
		$layout = isset( $input['layout'] ) && is_array( $input['layout'] ) ? $input['layout'] : array();

		return LayoutManager::save_semantic_layout( (int) $input['post_id'], $layout );
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function update_module( array $input ): array {
		$attributes = isset( $input['attributes'] ) && is_array( $input['attributes'] ) ? $input['attributes'] : array();

		return LayoutManager::update_module_attributes(
			(int) $input['post_id'],
			(string) $input['path'],
			$attributes
		);
	}

	/**
	 * @param array<string, mixed>|null $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function list_modules( ?array $input = null ): array {
		unset( $input );

		return ModuleRegistry::catalog();
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function get_module_schema( array $input ): array {
		return ModuleRegistry::schema( (string) $input['module_name'] );
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function insert_module( array $input ): array {
		$module = isset( $input['module'] ) && is_array( $input['module'] ) ? $input['module'] : array();

		return LayoutManager::insert_semantic_module(
			(int) $input['post_id'],
			(string) $input['parent_path'],
			(int) $input['index'],
			$module
		);
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function delete_module( array $input ): array {
		return LayoutManager::delete_module( (int) $input['post_id'], (string) $input['path'] );
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function move_module( array $input ): array {
		return LayoutManager::move_module(
			(int) $input['post_id'],
			(string) $input['path'],
			(string) $input['parent_path'],
			(int) $input['index']
		);
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function duplicate_module( array $input ): array {
		return LayoutManager::duplicate_module(
			(int) $input['post_id'],
			(string) $input['path'],
			(string) $input['parent_path'],
			(int) $input['index']
		);
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 */
	public static function can_edit_post( array $input ): bool {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 */
	public static function can_patch_native_module( array $input ): bool {
		return self::can_edit_post( $input ) && current_user_can( 'unfiltered_html' );
	}

	/**
	 * @param array<string, mixed>|null $input Ability input.
	 */
	public static function can_read_modules( ?array $input = null ): bool {
		unset( $input );

		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function post_id_input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function module_path_input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'path' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'path'    => self::path_schema(),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function relocate_module_input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'path', 'parent_path', 'index' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id'     => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'path'        => self::path_schema(),
				'parent_path' => self::path_schema(),
				'index'       => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function insert_module_input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'parent_path', 'index', 'module' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id'     => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'parent_path' => self::path_schema(),
				'index'       => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'module'      => array(
					'type'                 => 'object',
					'required'             => array( 'type' ),
					'additionalProperties' => false,
					'properties'           => array(
						'type'       => array(
							'type' => 'string',
							'enum' => array( 'section', 'row', 'column', 'text', 'button', 'image', 'code', 'divider' ),
						),
						'label'      => array( 'type' => 'string' ),
						'content'    => array( 'type' => 'string' ),
						'attributes' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'children'   => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'additionalProperties' => true,
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function path_schema(): array {
		return array(
			'type'    => 'string',
			'pattern' => '^\\d+(?:\\.\\d+)*$',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function module_registry_output_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'success', 'error_code', 'error_message' ),
			'additionalProperties' => true,
			'properties'           => array(
				'success'       => array( 'type' => 'boolean' ),
				'module_count'  => array( 'type' => 'integer' ),
				'modules'       => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
				'error_code'    => array( 'type' => array( 'string', 'null' ) ),
				'error_message' => array( 'type' => array( 'string', 'null' ) ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function layout_output_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'success', 'post_id', 'error_code', 'error_message' ),
			'additionalProperties' => true,
			'properties'           => array(
				'success'            => array( 'type' => 'boolean' ),
				'post_id'            => array( 'type' => 'integer' ),
				'post_status'        => array( 'type' => 'string' ),
				'builder_enabled'    => array( 'type' => 'boolean' ),
				'native_authoring'   => array( 'type' => 'boolean' ),
				'source_format'      => array( 'type' => 'string' ),
				'native_block_count' => array( 'type' => 'integer' ),
				'layout'             => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
				'revision_id'        => array( 'type' => array( 'integer', 'null' ) ),
				'write_method'       => array( 'type' => 'string' ),
				'operation'          => array( 'type' => 'string' ),
				'source_path'        => array( 'type' => 'string' ),
				'parent_path'        => array( 'type' => 'string' ),
				'updated_path'       => array( 'type' => 'string' ),
				'updated_block'      => array( 'type' => 'string' ),
				'error_code'         => array( 'type' => array( 'string', 'null' ) ),
				'error_message'      => array( 'type' => array( 'string', 'null' ) ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function mcp_meta( bool $is_readonly, bool $destructive, bool $idempotent ): array {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations'  => array(
				'readonly'    => $is_readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
		);
	}

	private function __construct() {
	}
}
