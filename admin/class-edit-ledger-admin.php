<?php
/**
 * Admin page for Edit Ledger.
 *
 * @package Edit_Ledger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin menu page and enqueues admin-specific assets.
 */
class Edit_Ledger_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var Edit_Ledger_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Edit_Ledger_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Edit Ledger', 'edit-ledger' ),
			__( 'Edit Ledger', 'edit-ledger' ),
			'edit_others_posts',
			'edit-ledger',
			array( $this, 'render_page' ),
			'dashicons-clock',
			26
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_edit-ledger' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'edit-ledger-admin',
			EDIT_LEDGER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			EDIT_LEDGER_VERSION
		);

		wp_enqueue_script(
			'edit-ledger-admin',
			EDIT_LEDGER_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-api-fetch' ),
			EDIT_LEDGER_VERSION,
			true
		);

		wp_localize_script(
			'edit-ledger-admin',
			'editLedgerAdmin',
			array(
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'apiBase' => rest_url( 'edit-ledger/v1/' ),
				'strings' => array(
					'loading'     => __( 'Loading...', 'edit-ledger' ),
					'error'       => __( 'An error occurred.', 'edit-ledger' ),
					'noRevisions' => __( 'No revisions found.', 'edit-ledger' ),
					'viewDiff'    => __( 'View Diff', 'edit-ledger' ),
					'editPost'    => __( 'Edit Post', 'edit-ledger' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		include EDIT_LEDGER_PLUGIN_DIR . 'admin/views/admin-page.php';
	}
}
