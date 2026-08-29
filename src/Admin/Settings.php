<?php
/**
 * Plugin settings.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Admin;

final class Settings {
	public const OPTION_GITHUB_UPDATES = 'mcp_bridge_github_updates_enabled';

	public const OPTION_USAGE_TELEMETRY = 'mcp_bridge_usage_telemetry_enabled';

	public const OPTION_ERROR_REPORTING = 'mcp_bridge_error_reporting_enabled';

	private const PAGE_SLUG = 'mcp-bridge-settings';

	private const OPTION_GROUP = 'mcp_bridge_settings';

	public static function hooks(): void {
		add_action( 'admin_menu', array( self::class, 'register_page' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	public static function register_page(): void {
		add_options_page(
			__( 'MCP Bridge', 'mcp-bridge-for-divi-woocommerce' ),
			__( 'MCP Bridge', 'mcp-bridge-for-divi-woocommerce' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
	}

	public static function register_settings(): void {
		self::register_boolean_setting(
			self::OPTION_GITHUB_UPDATES,
			array( self::class, 'sanitize_github_updates_enabled' )
		);
		self::register_boolean_setting(
			self::OPTION_USAGE_TELEMETRY,
			array( self::class, 'sanitize_usage_telemetry_enabled' )
		);
		self::register_boolean_setting(
			self::OPTION_ERROR_REPORTING,
			array( self::class, 'sanitize_error_reporting_enabled' )
		);

		add_settings_section(
			'mcp_bridge_updates',
			__( 'Updates', 'mcp-bridge-for-divi-woocommerce' ),
			array( self::class, 'render_updates_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_GITHUB_UPDATES,
			__( 'GitHub updates', 'mcp-bridge-for-divi-woocommerce' ),
			array( self::class, 'render_github_updates_field' ),
			self::PAGE_SLUG,
			'mcp_bridge_updates'
		);

		add_settings_section(
			'mcp_bridge_privacy',
			__( 'Privacy and diagnostics', 'mcp-bridge-for-divi-woocommerce' ),
			array( self::class, 'render_privacy_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_USAGE_TELEMETRY,
			__( 'Usage telemetry', 'mcp-bridge-for-divi-woocommerce' ),
			array( self::class, 'render_usage_telemetry_field' ),
			self::PAGE_SLUG,
			'mcp_bridge_privacy'
		);

		add_settings_field(
			self::OPTION_ERROR_REPORTING,
			__( 'Automatic error reporting', 'mcp-bridge-for-divi-woocommerce' ),
			array( self::class, 'render_error_reporting_field' ),
			self::PAGE_SLUG,
			'mcp_bridge_privacy'
		);
	}

	/**
	 * Normalize the GitHub update setting to a boolean.
	 *
	 * @param mixed $value Submitted option value.
	 */
	public static function sanitize_github_updates_enabled( $value ): bool {
		return ! empty( $value );
	}

	/**
	 * Normalize the usage telemetry setting to a boolean.
	 *
	 * @param mixed $value Submitted option value.
	 */
	public static function sanitize_usage_telemetry_enabled( $value ): bool {
		return ! empty( $value );
	}

	/**
	 * Normalize the error reporting setting to a boolean.
	 *
	 * @param mixed $value Submitted option value.
	 */
	public static function sanitize_error_reporting_enabled( $value ): bool {
		return ! empty( $value );
	}

	public static function is_github_updates_enabled(): bool {
		return (bool) get_option( self::OPTION_GITHUB_UPDATES, true );
	}

	public static function is_usage_telemetry_enabled(): bool {
		return (bool) get_option( self::OPTION_USAGE_TELEMETRY, true );
	}

	public static function is_error_reporting_enabled(): bool {
		return (bool) get_option( self::OPTION_ERROR_REPORTING, true );
	}

	public static function render_updates_section(): void {
		echo '<p>';
		esc_html_e(
			'While the plugin is distributed from GitHub, stable releases can be detected and installed through the normal WordPress update screen.',
			'mcp-bridge-for-divi-woocommerce'
		);
		echo '</p>';
	}

	public static function render_privacy_section(): void {
		echo '<p>';
		esc_html_e(
			'Current pre-WordPress.org builds enable privacy-preserving usage telemetry and plugin error reporting by default. Each can be disabled independently.',
			'mcp-bridge-for-divi-woocommerce'
		);
		echo '</p>';
	}

	public static function render_github_updates_field(): void {
		self::render_checkbox(
			self::OPTION_GITHUB_UPDATES,
			self::is_github_updates_enabled(),
			__( 'Check for stable updates from the official GitHub Releases.', 'mcp-bridge-for-divi-woocommerce' ),
			__( 'Enabled by default. Disable this option to stop this plugin from checking GitHub for updates.', 'mcp-bridge-for-divi-woocommerce' )
		);
	}

	public static function render_usage_telemetry_field(): void {
		self::render_checkbox(
			self::OPTION_USAGE_TELEMETRY,
			self::is_usage_telemetry_enabled(),
			__( 'Send a low-frequency anonymous installation heartbeat.', 'mcp-bridge-for-divi-woocommerce' ),
			__( 'Enabled by default in current GitHub-distributed builds. Disable to stop usage telemetry HTTP requests.', 'mcp-bridge-for-divi-woocommerce' )
		);
	}

	public static function render_error_reporting_field(): void {
		self::render_checkbox(
			self::OPTION_ERROR_REPORTING,
			self::is_error_reporting_enabled(),
			__( 'Send sanitized reports for fatal errors originating inside this plugin.', 'mcp-bridge-for-divi-woocommerce' ),
			__( 'Enabled by default in current GitHub-distributed builds. Disable to stop automatic error-report HTTP requests.', 'mcp-bridge-for-divi-woocommerce' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'mcp-bridge-for-divi-woocommerce' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'MCP Bridge', 'mcp-bridge-for-divi-woocommerce' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param callable $sanitize_callback Sanitization callback.
	 */
	private static function register_boolean_setting( string $option, callable $sanitize_callback ): void {
		register_setting(
			self::OPTION_GROUP,
			$option,
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => $sanitize_callback,
			)
		);
	}

	private static function render_checkbox(
		string $option,
		bool $enabled,
		string $label,
		string $description
	): void {
		?>
		<input type="hidden" name="<?php echo esc_attr( $option ); ?>" value="0" />
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $option ); ?>"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php echo esc_html( $label ); ?>
		</label>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}

	private function __construct() {
	}
}
