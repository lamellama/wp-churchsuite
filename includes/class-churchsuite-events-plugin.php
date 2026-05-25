<?php
/**
 * Main plugin orchestrator.
 *
 * @package ChurchSuiteEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin class.
 */
class ChurchSuite_Events_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var ChurchSuite_Events_Plugin|null
	 */
	private static $instance = null;

	/**
	 * CPT handler.
	 *
	 * @var ChurchSuite_Events_CPT
	 */
	private $cpt;

	/**
	 * Settings handler.
	 *
	 * @var ChurchSuite_Events_Settings
	 */
	private $settings;

	/**
	 * Sync handler.
	 *
	 * @var ChurchSuite_Events_Sync
	 */
	private $sync;

	/**
	 * Template/pattern helper.
	 *
	 * @var ChurchSuite_Events_Templates
	 */
	private $templates;

	/**
	 * Query Loop integration helper.
	 *
	 * @var ChurchSuite_Events_Query_Loop
	 */
	private $query_loop;

	/**
	 * Category filter block helper.
	 *
	 * @var ChurchSuite_Events_Category_Filter_Block
	 */
	private $category_filter_block;

	/**
	 * Calendar block helper.
	 *
	 * @var ChurchSuite_Events_Calendar_Block
	 */
	private $calendar_block;

	/**
	 * Taxonomy helper.
	 *
	 * @var ChurchSuite_Events_Taxonomy
	 */
	private $taxonomy;

	const OPTION_REWRITE_FLUSHED = 'churchsuite_events_rewrite_flushed';

	/**
	 * Get singleton.
	 *
	 * @return ChurchSuite_Events_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_components' ), 5 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_once' ), 20 );
		add_action( 'update_option_' . ChurchSuite_Events_Settings::OPTION_KEY, array( $this, 'handle_settings_updated' ), 10, 3 );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'churchsuite-events',
			false,
			dirname( plugin_basename( CHURCHSUITE_EVENTS_FILE ) ) . '/languages'
		);
	}

	/**
	 * Wire plugin pieces.
	 *
	 * @return void
	 */
	public function init_components() {
		if ( ! $this->cpt ) {
			$this->cpt = new ChurchSuite_Events_CPT();
		}

		if ( ! $this->settings ) {
			$this->settings = new ChurchSuite_Events_Settings();
		}

		if ( ! $this->taxonomy ) {
			$this->taxonomy = new ChurchSuite_Events_Taxonomy();
		}

		if ( ! $this->sync && $this->settings ) {
			$this->sync = new ChurchSuite_Events_Sync( $this->settings );
		}

		if ( ! $this->templates ) {
			$this->templates = new ChurchSuite_Events_Templates();
		}

		if ( ! $this->query_loop ) {
			$this->query_loop = new ChurchSuite_Events_Query_Loop();
		}

		if ( ! $this->category_filter_block ) {
			$this->category_filter_block = new ChurchSuite_Events_Category_Filter_Block();
		}

		if ( ! $this->calendar_block ) {
			$this->calendar_block = new ChurchSuite_Events_Calendar_Block();
		}
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public function activate() {
		$this->init_components();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Get settings helper.
	 *
	 * @return ChurchSuite_Events_Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get sync helper.
	 *
	 * @return ChurchSuite_Events_Sync|null
	 */
	public function sync() {
		return $this->sync;
	}

	/**
	 * Get template helper.
	 *
	 * @return ChurchSuite_Events_Templates|null
	 */
	public function templates() {
		return $this->templates;
	}

	/**
	 * Get taxonomy helper.
	 *
	 * @return ChurchSuite_Events_Taxonomy|null
	 */
	public function taxonomy() {
		return $this->taxonomy;
	}

	/**
	 * Refresh publish dates/statuses when the lead time setting changes.
	 *
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $value     New option value.
	 * @param string $option    Option name.
	 * @return void
	 */
	public function handle_settings_updated( $old_value, $value, $option ) {
		unset( $option );

		$old_settings = wp_parse_args( is_array( $old_value ) ? $old_value : array(), ChurchSuite_Events_Settings::defaults() );
		$new_settings = wp_parse_args( is_array( $value ) ? $value : array(), ChurchSuite_Events_Settings::defaults() );

		if ( (int) $old_settings['publish_lead_days'] === (int) $new_settings['publish_lead_days'] ) {
			return;
		}

		if ( ! $this->sync ) {
			$this->init_components();
		}

		if ( $this->sync ) {
			$this->sync->refresh_publish_statuses();
		}
	}

	/**
	 * Flush rewrite rules once after structural changes.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_once() {
		if ( get_option( self::OPTION_REWRITE_FLUSHED ) ) {
			return;
		}

		flush_rewrite_rules();
		update_option( self::OPTION_REWRITE_FLUSHED, 1, false );
	}
}
