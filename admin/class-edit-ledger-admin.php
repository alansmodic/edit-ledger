<?php

/**
 * Admin page for Edit Ledger.
 */

namespace EditLedger;

class EditLedgerAdmin
{
    /**
     * Singleton instance.
     *
     * @var EditLedgerAdmin|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return EditLedgerAdmin
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        add_action('admin_menu', array( $this, 'addMenuPage' ));
        add_action('admin_enqueue_scripts', array( $this, 'enqueueScripts' ));
    }

    /**
     * Add admin menu page.
     */
    public function addMenuPage()
    {
        add_menu_page(
            __('Edit Ledger', 'edit-ledger'),
            __('Edit Ledger', 'edit-ledger'),
            'edit_others_posts',
            'edit-ledger',
            array( $this, 'renderPage' ),
            'dashicons-clock',
            26
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueueScripts($hook)
    {
        if ('toplevel_page_edit-ledger' !== $hook) {
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
                'nonce'   => wp_create_nonce('wp_rest'),
                'apiBase' => rest_url('edit-ledger/v1/'),
                'strings' => array(
                    'loading'     => __('Loading...', 'edit-ledger'),
                    'error'       => __('An error occurred.', 'edit-ledger'),
                    'noRevisions' => __('No revisions found.', 'edit-ledger'),
                    'viewDiff'    => __('View Diff', 'edit-ledger'),
                    'editPost'    => __('Edit Post', 'edit-ledger'),
                ),
            )
        );
    }

    /**
     * Render admin page.
     */
    public function renderPage()
    {
        include EDIT_LEDGER_PLUGIN_DIR . 'admin/views/admin-page.php';
    }
}
