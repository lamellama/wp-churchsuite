<?php
/**
 * Admin settings page.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings handler.
 */
class ChurchSuite_Events_Settings {
	const OPTION_KEY = 'churchsuite_events_settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'account_id' => '',
			'cache_ttl'  => HOUR_IN_SECONDS,
		);
	}

	/**
	 * Hook into admin.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page under Settings.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'ChurchSuite Events', 'churchsuite-events' ),
			__( 'ChurchSuite Events', 'churchsuite-events' ),
			'manage_options',
			'churchsuite-events',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'churchsuite_events',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'churchsuite_events_main',
			__( 'ChurchSuite API', 'churchsuite-events' ),
			'__return_false',
			'churchsuite_events'
		);

		add_settings_field(
			'churchsuite_events_account_id',
			__( 'Account ID', 'churchsuite-events' ),
			array( $this, 'render_account_id_field' ),
			'churchsuite_events',
			'churchsuite_events_main'
		);

		add_settings_field(
			'churchsuite_events_cache_ttl',
			__( 'Cache Duration (seconds)', 'churchsuite-events' ),
			array( $this, 'render_cache_field' ),
			'churchsuite_events',
			'churchsuite_events_main'
		);
	}

	/**
	 * Sanitize options.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();

		$output = array(
			'account_id' => isset( $input['account_id'] ) ? sanitize_text_field( $input['account_id'] ) : $defaults['account_id'],
			'cache_ttl'  => isset( $input['cache_ttl'] ) ? max( 300, absint( $input['cache_ttl'] ) ) : $defaults['cache_ttl'],
		);

		return wp_parse_args( $output, $defaults );
	}

	/**
	 * Render account ID field.
	 *
	 * @return void
	 */
	public function render_account_id_field() {
		$settings   = $this->get_settings();
		$account_id = $settings['account_id'];
		?>
		<input name="<?php echo esc_attr( self::OPTION_KEY ); ?>[account_id]" type="text" class="regular-text" value="<?php echo esc_attr( $account_id ); ?>" />
		<p class="description"><?php esc_html_e( 'Your ChurchSuite account ID (e.g. mychurch).', 'churchsuite-events' ); ?></p>
		<?php
	}

	/**
	 * Render cache field.
	 *
	 * @return void
	 */
	public function render_cache_field() {
		$settings  = $this->get_settings();
		$cache_ttl = isset( $settings['cache_ttl'] ) ? absint( $settings['cache_ttl'] ) : self::defaults()['cache_ttl'];
		?>
		<input name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cache_ttl]" type="number" min="300" step="60" value="<?php echo esc_attr( $cache_ttl ); ?>" />
		<p class="description"><?php esc_html_e( 'How long to cache ChurchSuite responses (seconds). Minimum 5 minutes.', 'churchsuite-events' ); ?></p>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sync_status = isset( $_GET['churchsuite_synced'] ) ? sanitize_text_field( wp_unslash( $_GET['churchsuite_synced'] ) ) : '';
		$sync_error  = isset( $_GET['churchsuite_error'] ) ? sanitize_text_field( wp_unslash( $_GET['churchsuite_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ChurchSuite Events', 'churchsuite-events' ); ?></h1>

			<?php if ( '' !== $sync_status ) : ?>
				<?php if ( '1' === $sync_status ) : ?>
					<div class="notice notice-success is-dismissible">
						<p><?php esc_html_e( 'Events synced successfully.', 'churchsuite-events' ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-error is-dismissible">
						<p>
							<?php
							echo esc_html(
								$sync_error
									? sprintf( __( 'Sync failed: %s', 'churchsuite-events' ), $sync_error )
									: __( 'Sync failed.', 'churchsuite-events' )
							);
							?>
						</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'churchsuite_events' );
				do_settings_sections( 'churchsuite_events' );
				submit_button();
				?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'churchsuite_events_sync' ); ?>
				<input type="hidden" name="action" value="churchsuite_events_sync" />
				<?php submit_button( __( 'Sync now', 'churchsuite-events' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Get stored settings with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		return wp_parse_args(
			get_option( self::OPTION_KEY, array() ),
			self::defaults()
		);
	}

	/**
	 * Helper to fetch single value.
	 *
	 * @param string $key Option key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_settings();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}
