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
