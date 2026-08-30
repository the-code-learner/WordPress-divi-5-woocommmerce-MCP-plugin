<?php
/**
 * Runtime-native Divi 5 module authoring abilities.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class NativeModuleAbilities {
	private const CATEGORY = 'divi5-woocommerce-mcp';

	public static function hooks(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'divi5-woocommerce-mcp/divi-insert-native-module',
			array(
				'label'               => __( 'Insert registered native Divi 5 module', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Inserts any registered native divi/* module, including validated nested child modules. Call divi-list-modules and divi-get-module-schema first, then provide the runtime-native attributes object. Draft or pending content and unfiltered_html permission are required.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'insert_native_module' ),
				'permission_callback' => array( self::class, 'can_insert_native_module' ),
				'input_schema'        => self::input_schema(),
				'output_schema'       => self::output_schema(),
				'meta'                => self::mcp_meta(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function insert_native_module( array $input ): array {
		$module = isset( $input['module'] ) && is_array( $input['module'] ) ? $input['module'] : array();

		return NativeModuleManager::insert(
			(int) $input['post_id'],
			(string) $input['parent_path'],
			(int) $input['index'],
			$module
		);
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 */
	public static function can_insert_native_module( array $input ): bool {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		return $post_id > 0
			&& current_user_can( 'edit_post', $post_id )
			&& current_user_can( 'unfiltered_html' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'parent_path', 'index', 'module' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id'     => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'parent_path' => array(
					'type'    => 'string',
					'pattern' => '^\\d+(?:\\.\\d+)*$',
				),
				'index'       => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'module'      => array(
					'type'                 => 'object',
					'required'             => array( 'module_name' ),
					'additionalProperties' => false,
					'properties'           => array(
						'module_name' => array(
							'type'    => 'string',
							'pattern' => '^divi/[a-z0-9-]+$',
						),
						'attributes'  => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'children'    => array(
							'type'  => 'array',
							'items' => array(
								'type'                 => 'object',
								'required'             => array( 'module_name' ),
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
	private static function output_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'success', 'post_id', 'error_code', 'error_message' ),
			'additionalProperties' => true,
			'properties'           => array(
				'success'       => array( 'type' => 'boolean' ),
				'post_id'       => array( 'type' => 'integer' ),
				'error_code'    => array( 'type' => array( 'string', 'null' ) ),
				'error_message' => array( 'type' => array( 'string', 'null' ) ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function mcp_meta(): array {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations'  => array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			),
		);
	}

	private function __construct() {
	}
}
