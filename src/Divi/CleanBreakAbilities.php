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
				'meta'                => self::mcp_meta( true, false, true ),
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
				'meta'                => self::mcp_meta( true, false, true ),
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
						'post_id'        => array(
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
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-document-validate',
			array(
				'label'               => __( 'Validate Divi document mutations', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Dry-runs an atomic semantic mutation batch against one exact Divi document snapshot without persistence.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'document_validate' ),
				'permission_callback' => array( self::class, 'can_read_document' ),
				'input_schema'        => self::document_batch_schema(),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-document-mutate',
			array(
				'label'               => __( 'Mutate Divi document atomically', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Validates a semantic mutation batch against one exact Divi document snapshot and persists it with one native block write only when the full batch is valid.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'document_mutate' ),
				'permission_callback' => array( self::class, 'can_read_document' ),
				'input_schema'        => self::document_batch_schema(),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta( false, true, false ),
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

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function document_validate( array $input ): array {
		return DocumentMutationEngine::validate(
			(int) $input['post_id'],
			(string) $input['document_token'],
			(array) $input['operations']
		);
	}

	/**
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	public static function document_mutate( array $input ): array {
		return DocumentMutationEngine::mutate(
			(int) $input['post_id'],
			(string) $input['document_token'],
			(array) $input['operations']
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
	private static function document_batch_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'document_token', 'operations' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id'        => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'document_token' => array(
					'type'    => 'string',
					'pattern' => '^[a-f0-9]{64}$',
				),
				'operations'     => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => 100,
					'items'    => array(
						'type'                 => 'object',
						'required'             => array( 'op' ),
						'additionalProperties' => true,
						'properties'           => array(
							'op' => array(
								'type' => 'string',
								'enum' => array( 'insert', 'set', 'delete', 'move', 'duplicate', 'responsive', 'state', 'preset' ),
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
	private static function mcp_meta( bool $readonly, bool $destructive, bool $idempotent ): array {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations'  => array(
				'readonly'    => $readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
		);
	}

	private function __construct() {
	}
}
