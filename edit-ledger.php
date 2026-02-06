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

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/class-edit-ledger.php';
require_once __DIR__ . '/includes/class-edit-ledger-rest-controller.php';
require_once __DIR__ . '/includes/class-edit-ledger-diff-generator.php';
require_once __DIR__ . '/admin/class-edit-ledger-admin.php';

add_action('plugins_loaded', static function () {
    \EditLedger\EditLedger::getInstance();
    \EditLedger\EditLedgerAdmin::getInstance();
});
