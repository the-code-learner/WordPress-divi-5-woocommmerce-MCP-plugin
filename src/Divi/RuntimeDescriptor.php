<?php
/**
 * Clean-break Divi runtime capability negotiation.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class RuntimeDescriptor {
	private const API_GENERATION = 'clean-break-1';
	private const API_VERSION    = '1.0.0-alpha.1';

	/**
	 * Describe the active Divi runtime without claiming unsupported features.
	 *
	 * @return array<string, mixed>
	 */
	public static function describe(): array {
		$catalog      = RuntimeModuleRegistry::catalog();
		$modules      = isset( $catalog['modules'] ) && is_array( $catalog['modules'] ) ? $catalog['modules'] : array();
		$providers    = self::providers( $modules );
		$breakpoints  = self::breakpoints( $modules );
		$runtime_info = self::runtime_build();
		$capabilities = array();

		$capabilities['module_discovery'] = self::capability(
			array() !== $modules ? 'supported' : 'unknown',
			array() !== $modules ? 'runtime block registry contains compatible Divi modules' : 'no compatible module registration was observed'
		);

		$capabilities['nested_modules'] = self::aggregate_module_capability( $modules, 'nested_modules' );

		$capabilities['responsive'] = self::aggregate_module_capability( $modules, 'responsive' );

		$capabilities['breakpoints'] = array(
			'status'   => array() !== $breakpoints ? 'supported' : 'unknown',
			'values'   => $breakpoints,
			'evidence' => array() !== $breakpoints ? 'breakpoint keys observed in runtime parameter metadata/defaults' : 'runtime did not expose breakpoint keys in inspected schemas',
		);

		$capabilities['hover'] = self::aggregate_module_capability( $modules, 'hover' );

		$capabilities['sticky'] = self::aggregate_module_capability( $modules, 'sticky' );

		$capabilities['presets'] = self::aggregate_module_capability( $modules, 'presets' );

		$capabilities['design_variables'] = self::aggregate_module_capability( $modules, 'design_variables' );

		$capabilities['global_values'] = self::aggregate_module_capability( $modules, 'global_values' );

		$capabilities['raw_native'] = array(
			'read'  => self::capability( 'supported', 'module and document descriptors can include raw runtime/native data on request' ),
			'write' => self::capability( 'unavailable', 'clean-break atomic mutation engine is not part of the read foundation milestone' ),
		);

		$capabilities['document_get'] = self::capability(
			function_exists( 'parse_blocks' ) && function_exists( 'get_post' ) ? 'supported' : 'unavailable',
			'WordPress post and block parsing APIs'
		);

		$capabilities['document_validate'] = self::capability( 'unavailable', 'planned next clean-break milestone' );

		$capabilities['document_mutate'] = self::capability( 'unavailable', 'planned next clean-break milestone' );

		$capabilities['render'] = self::capability( 'unavailable', 'real-page render primitive is not implemented in this milestone' );

		$capabilities['inspect'] = self::capability( 'unavailable', 'DOM/computed-style inspector is not implemented in this milestone' );

		$compatibility = array();

		$compatibility['legacy_v0_4_abilities'] = 'retained-as-shims';

		$compatibility['primary_api'] = 'clean-break-runtime-document';

		return array(
			'success'       => true,
			'api'           => array(
				'generation' => self::API_GENERATION,
				'version'    => self::API_VERSION,
			),
			'divi_runtime'  => $runtime_info,
			'module_count'  => count( $modules ),
			'modules'       => array_map(
				static function ( array $module ): array {
					return array(
						'name'               => $module['name'],
						'title'              => $module['title'],
						'provider'           => $module['provider'],
						'compatibility_mode' => $module['compatibility_mode'],
						'introspection'      => $module['introspection'],
						'capabilities'       => $module['capabilities'],
					);
				},
				$modules
			),
			'providers'     => $providers,
			'capabilities'  => $capabilities,
			'compatibility' => $compatibility,
			'error_code'    => null,
			'error_message' => null,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $modules Modules.
	 * @return array<int, array<string, mixed>>
	 */
	private static function providers( array $modules ): array {
		$providers = array();

		foreach ( $modules as $module ) {
			$provider = isset( $module['provider'] ) && is_array( $module['provider'] ) ? $module['provider'] : array();
			$id       = isset( $provider['id'] ) && is_string( $provider['id'] ) ? $provider['id'] : 'unknown';

			if ( ! isset( $providers[ $id ] ) ) {
				$providers[ $id ] = array(
					'id'           => $id,
					'provenance'   => isset( $provider['provenance'] ) ? $provider['provenance'] : 'unknown',
					'module_count' => 0,
				);
			}

			++$providers[ $id ]['module_count'];
		}

		ksort( $providers );

		return array_values( $providers );
	}

	/**
	 * @param array<int, array<string, mixed>> $modules Modules.
	 * @return array<int, string>
	 */
	private static function breakpoints( array $modules ): array {
		$breakpoints = array();

		foreach ( $modules as $module ) {
			$parameters = isset( $module['parameters'] ) && is_array( $module['parameters'] ) ? $module['parameters'] : array();

			foreach ( $parameters as $parameter ) {
				$values = isset( $parameter['breakpoints'] ) && is_array( $parameter['breakpoints'] ) ? $parameter['breakpoints'] : array();

				foreach ( $values as $value ) {
					if ( is_string( $value ) && '' !== $value ) {
						$breakpoints[] = $value;
					}
				}
			}
		}

		$breakpoints = array_values( array_unique( $breakpoints ) );
		sort( $breakpoints );

		return $breakpoints;
	}

	/**
	 * @param array<int, array<string, mixed>> $modules Modules.
	 * @return array<string, string>
	 */
	private static function aggregate_module_capability( array $modules, string $key ): array {
		$has_unavailable = false;

		foreach ( $modules as $module ) {
			$capabilities = isset( $module['capabilities'] ) && is_array( $module['capabilities'] ) ? $module['capabilities'] : array();
			$status       = isset( $capabilities[ $key ] ) && is_string( $capabilities[ $key ] ) ? $capabilities[ $key ] : 'unknown';

			if ( 'supported' === $status ) {
				return self::capability( 'supported', 'observed in at least one registered runtime module schema' );
			}

			if ( 'unavailable' === $status ) {
				$has_unavailable = true;
			}
		}

		return self::capability(
			$has_unavailable ? 'unavailable' : 'unknown',
			$has_unavailable ? 'runtime metadata explicitly disabled the feature where inspected' : 'runtime metadata did not prove feature support'
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function capability( string $status, string $evidence ): array {
		return array(
			'status'   => $status,
			'evidence' => $evidence,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function runtime_build(): array {
		$result = array(
			'detected' => Detector::is_available(),
			'version'  => null,
			'build'    => null,
			'source'   => 'unknown',
		);

		if ( ! function_exists( 'wp_get_theme' ) ) {
			return $result;
		}

		$theme = wp_get_theme();

		if ( ! is_object( $theme ) || ! method_exists( $theme, 'get' ) ) {
			return $result;
		}

		$name     = (string) $theme->get( 'Name' );
		$version  = (string) $theme->get( 'Version' );
		$template = method_exists( $theme, 'get_template' ) ? (string) $theme->get_template() : '';
		$identity = strtolower( $name . ' ' . $template );

		if ( false === strpos( $identity, 'divi' ) || '' === $version ) {
			return $result;
		}

		$result['version'] = $version;
		$result['source']  = 'active-theme-metadata';

		return $result;
	}

	private function __construct() {
	}
}
