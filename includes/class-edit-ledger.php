<?php
/**
 * Core plugin class.
 *
 * @package Edit_Ledger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class responsible for registering REST routes and editor assets.
 */
class Edit_Ledger {

	/**
	 * Singleton instance.
	 *
	 * @var Edit_Ledger|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Edit_Ledger
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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$controller = new Edit_Ledger_REST_Controller();
		$controller->register_routes();
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_editor_assets() {
		global $post;

		if ( ! $post ) {
			return;
		}

		// Load for any post that supports revisions.
		if ( ! post_type_supports( $post->post_type, 'revisions' ) ) {
			return;
		}

		wp_enqueue_style(
			'edit-ledger-editor',
			EDIT_LEDGER_PLUGIN_URL . 'assets/css/editor.css',
			array(),
			EDIT_LEDGER_VERSION
		);

		wp_enqueue_script(
			'edit-ledger-editor',
			EDIT_LEDGER_PLUGIN_URL . 'assets/js/editor.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-api-fetch',
				'wp-i18n',
			),
			EDIT_LEDGER_VERSION,
			true
		);

		wp_localize_script(
			'edit-ledger-editor',
			'editLedgerData',
			array(
				'postId'        => $post->ID,
				'postPermalink' => get_permalink( $post->ID ),
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'siteUrl'       => get_site_url(),
				'strings'       => array(
					'title'           => __( 'Edit Ledger', 'edit-ledger' ),
					'noRevisions'     => __( 'No revisions found.', 'edit-ledger' ),
					'loading'         => __( 'Loading revisions...', 'edit-ledger' ),
					'viewDiff'        => __( 'View Diff', 'edit-ledger' ),
					'preview'         => __( 'Preview Post', 'edit-ledger' ),
					'wpRevisions'     => __( 'WP Revisions', 'edit-ledger' ),
					'changed'         => __( 'Changed:', 'edit-ledger' ),
					'noChanges'       => __( 'No changes detected', 'edit-ledger' ),
					'auto'            => __( 'Auto', 'edit-ledger' ),
					'save'            => __( 'Save', 'edit-ledger' ),
					'ago'             => __( 'ago', 'edit-ledger' ),
					'diffTitle'       => __( 'Revision Diff', 'edit-ledger' ),
					'inline'          => __( 'Inline', 'edit-ledger' ),
					'sideBySide'      => __( 'Side by Side', 'edit-ledger' ),
					'revisionHistory' => __( 'Revision History', 'edit-ledger' ),
					'restore'         => __( 'Restore This Version', 'edit-ledger' ),
					'restoreConfirm'  => __( 'Are you sure you want to restore this revision? This will replace the current content with this older version.', 'edit-ledger' ),
					'restoring'       => __( 'Restoring...', 'edit-ledger' ),
					'restoreSuccess'  => __( 'Revision restored successfully. Reloading...', 'edit-ledger' ),
					'restoreError'    => __( 'Failed to restore revision.', 'edit-ledger' ),
				),
			)
		);
	}
}
