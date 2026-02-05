<?php
/**
 * Plugin Name: Edit Ledger
 * Description: Editorial-friendly revision history with visual diffs and preview capabilities.
 * Version: 1.0.0
 * Author: Alan
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: edit-ledger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EDIT_LEDGER_VERSION', '1.0.0' );
define( 'EDIT_LEDGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDIT_LEDGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize the plugin.
 */
function edit_ledger_init() {
	require_once EDIT_LEDGER_PLUGIN_DIR . 'includes/class-edit-ledger.php';
	require_once EDIT_LEDGER_PLUGIN_DIR . 'includes/class-edit-ledger-rest-controller.php';
	require_once EDIT_LEDGER_PLUGIN_DIR . 'includes/class-edit-ledger-diff-generator.php';
	require_once EDIT_LEDGER_PLUGIN_DIR . 'admin/class-edit-ledger-admin.php';

	Edit_Ledger::get_instance();
	Edit_Ledger_Admin::get_instance();
}
add_action( 'plugins_loaded', 'edit_ledger_init' );
