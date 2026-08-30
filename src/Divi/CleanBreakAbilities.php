<?php
/**
 * Clean-break schema-driven Divi MCP abilities.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class CleanBreakAbilities {
	private const CATEGORY = 'divi5-woocommerce-mcp';

	public static function hooks(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'divi5-woocommerce-mcp/divi-runtime-describe',
			array(
				'label'               => __( 'Describe Divi runtime', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Describes the active Divi runtime, discovered modules, providers, compatibility modes, and proven or unknown authoring capabilities.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'runtime_describe' ),
				'permission_callback' => array( self::class, 'can_read_runtime' ),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta(),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-module-describe',
			array(
				'label'               => __( 'Describe Divi module', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Returns a normalized parameter graph plus raw runtime schema for one compatible module registered by the active Divi runtime.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'module_describe' ),
				'permission_callback' => array( self::class, 'can_read_runtime' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'module_name' ),
					'additionalProperties' => false,
					'properties'           => array(
						'module_name' => array(
							'type'    => 'string',
							'pattern' => '^[a-z0-9-]+/[a-z0-9-]+$',
						),
					),
				),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta(),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-document-get',
			array(
				'label'               => __( 'Get normalized Divi document', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Returns a normalized Divi document AST with snapshot-scoped stable handles, current numeric paths, runtime provenance, and optional raw native attributes.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'document_get' ),
				'permission_callback' => array( self::class, 'can_read_document' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'post_id' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'include_native' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta(),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function runtime_describe(): array {
		return RuntimeDescriptor::describe();
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function module_describe( array $input ): array {
		return RuntimeModuleRegistry::describe( (string) $input['module_name'] );
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function document_get( array $input ): array {
		return DocumentModel::get(
			(int) $input['post_id'],
			isset( $input['include_native'] ) && true === $input['include_native']
		);
	}

	public static function can_read_runtime(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 */
	public static function can_read_document( array $input ): bool {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function generic_output_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'success', 'error_code', 'error_message' ),
			'additionalProperties' => true,
			'properties'           => array(
				'success'       => array( 'type' => 'boolean' ),
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
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
		);
	}

	private function __construct() {
	}
}
