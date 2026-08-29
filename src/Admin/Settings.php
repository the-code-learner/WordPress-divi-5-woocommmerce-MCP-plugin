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
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_GITHUB_UPDATES,
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => array( self::class, 'sanitize_github_updates_enabled' ),
			)
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
	}

	/**
	 * Normalize the update setting to a boolean.
	 *
	 * @param mixed $value Submitted option value.
	 */
	public static function sanitize_github_updates_enabled( $value ): bool {
		return ! empty( $value );
	}

	public static function is_github_updates_enabled(): bool {
		return (bool) get_option( self::OPTION_GITHUB_UPDATES, true );
	}

	public static function render_updates_section(): void {
		echo '<p>';
		esc_html_e(
			'While the plugin is distributed from GitHub, stable releases can be detected and installed through the normal WordPress update screen.',
			'mcp-bridge-for-divi-woocommerce'
		);
		echo '</p>';
	}

	public static function render_github_updates_field(): void {
		?>
		<input
			type="hidden"
			name="<?php echo esc_attr( self::OPTION_GITHUB_UPDATES ); ?>"
			value="0"
		/>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_GITHUB_UPDATES ); ?>"
				value="1"
				<?php checked( self::is_github_updates_enabled() ); ?>
			/>
			<?php
			esc_html_e(
				'Check for stable updates from the official GitHub Releases.',
				'mcp-bridge-for-divi-woocommerce'
			);
			?>
		</label>
		<p class="description">
			<?php
			esc_html_e(
				'Enabled by default. Disable this option to stop this plugin from checking GitHub for updates.',
				'mcp-bridge-for-divi-woocommerce'
			);
			?>
		</p>
		<?php
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

	private function __construct() {
	}
}
