<?php
/**
 * Generic Divi runtime bridge abilities.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class RuntimeBridgeAbilities {
	private const CATEGORY = 'divi5-woocommerce-mcp';

	public static function hooks(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'divi5-woocommerce-mcp/divi-runtime-list-registries',
			array(
				'label'               => __( 'List Divi runtime registries', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Discovers runtime registries such as modules, field components, option groups, breakpoints, states, dynamic content capabilities, attribute roots, providers, and layout engines, with schema fingerprints.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'list_registries' ),
				'permission_callback' => array( self::class, 'can_read_runtime' ),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-runtime-describe-registry',
			array(
				'label'               => __( 'Describe Divi runtime registry', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Returns the current entries and evidence for one generic Divi runtime registry. Unknown registries remain explicitly unknown instead of being treated as unsupported.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'describe_registry' ),
				'permission_callback' => array( self::class, 'can_read_runtime' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'registry' ),
					'additionalProperties' => false,
					'properties'           => array(
						'registry' => array(
							'type'    => 'string',
							'pattern' => '^[a-z0-9-]+$',
						),
					),
				),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-document-native-validate',
			array(
				'label'               => __( 'Validate generic Divi native mutations', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Dry-runs schema/runtime-validated native mutations, including raw set/unset, responsive/state values, module presets, and semantic Custom Attributes, without persistence.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'native_validate' ),
				'permission_callback' => array( self::class, 'can_read_document' ),
				'input_schema'        => self::native_batch_schema(),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-document-native-mutate',
			array(
				'label'               => __( 'Mutate generic Divi native attributes', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Atomically writes only runtime-proven or adapter-proven Divi native paths on draft/pending content, guarded by a snapshot token and block schema validation.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'native_mutate' ),
				'permission_callback' => array( self::class, 'can_mutate_native_document' ),
				'input_schema'        => self::native_batch_schema(),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta( false, true, false ),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/divi-render',
			array(
				'label'               => __( 'Render and inspect Divi document', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Renders a Divi document server-side through WordPress block callbacks, returning HTML metadata, generated classes/IDs, inline CSS, warnings, and optional markup inspection. Browser computed styles are reported separately as unavailable.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'render' ),
				'permission_callback' => array( self::class, 'can_read_document' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'post_id'      => array( 'type' => 'integer', 'minimum' => 1 ),
						'include_html' => array( 'type' => 'boolean', 'default' => true ),
						'selector'     => array( 'type' => 'string', 'maxLength' => 255, 'default' => '' ),
					),
				),
				'output_schema'       => self::generic_output_schema(),
				'meta'                => self::mcp_meta( true, false, true ),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function list_registries(): array {
		return RuntimeRegistryDiscovery::list_registries();
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function describe_registry( array $input ): array {
		return RuntimeRegistryDiscovery::describe_registry( (string) $input['registry'] );
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function native_validate( array $input ): array {
		return RuntimeNativeWriter::validate( (int) $input['post_id'], (string) $input['document_token'], (array) $input['operations'] );
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function native_mutate( array $input ): array {
		return RuntimeNativeWriter::mutate( (int) $input['post_id'], (string) $input['document_token'], (array) $input['operations'] );
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @return array<string, mixed>
	 */
	public static function render( array $input ): array {
		return RuntimeRenderer::render(
			(int) $input['post_id'],
			! isset( $input['include_html'] ) || true === $input['include_html'],
			isset( $input['selector'] ) && is_string( $input['selector'] ) ? $input['selector'] : ''
		);
	}

	public static function can_read_runtime(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param array<string, mixed> $input Input.
	 */
	public static function can_read_document( array $input ): bool {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * @param array<string, mixed> $input Input.
	 */
	public static function can_mutate_native_document( array $input ): bool {
		return self::can_read_document( $input ) && current_user_can( 'unfiltered_html' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function native_batch_schema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'post_id', 'document_token', 'operations' ),
			'additionalProperties' => false,
			'properties'           => array(
				'post_id'        => array( 'type' => 'integer', 'minimum' => 1 ),
				'document_token' => array( 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
				'operations'     => array(
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => 100,
					'items'    => array(
						'type'                 => 'object',
						'required'             => array( 'op', 'handle' ),
						'additionalProperties' => true,
						'properties'           => array(
							'op'          => array( 'type' => 'string', 'enum' => array( 'set', 'unset', 'attribute', 'responsive', 'state', 'preset' ) ),
							'handle'      => array( 'type' => 'string', 'minLength' => 1 ),
							'native_path' => array( 'type' => 'string' ),
							'property'    => array( 'type' => 'string' ),
							'breakpoint'  => array( 'type' => 'string' ),
							'state'       => array( 'type' => 'string' ),
							'target'      => array( 'type' => 'string' ),
							'name'        => array( 'type' => 'string' ),
							'preset_id'   => array( 'type' => 'string' ),
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
	private static function mcp_meta( bool $is_readonly, bool $destructive, bool $idempotent ): array {
		return array(
			'public'       => true,
			'show_in_rest' => false,
			'mcp'          => array( 'public' => true, 'type' => 'tool' ),
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
