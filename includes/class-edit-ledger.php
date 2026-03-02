<?php

/**
 * Core plugin class.
 */

namespace EditLedger;

class EditLedger
{
    /**
     * Singleton instance.
     *
     * @var EditLedger|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return EditLedger
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
        add_action('rest_api_init', array( $this, 'registerRestRoutes' ));
        add_action('enqueue_block_editor_assets', array( $this, 'enqueueEditorAssets' ));
        add_action('wp_save_post_revision', array( $this, 'maybeAutoSummarize' ), 10, 1);
    }

    /**
     * Automatically generate an AI summary when a revision is saved.
     *
     * @param int $revision_id The revision post ID.
     * @return void
     */
    public function maybeAutoSummarize($revision_id)
    {
        if (! apply_filters('edit_ledger_auto_summarize', true, $revision_id)) {
            return;
        }

        $ai = new EditLedgerAiSummary();

        if (! $ai->isAvailable()) {
            return;
        }

        $revision = get_post($revision_id);
        if (! $revision) {
            return;
        }

        if (! user_can($revision->post_author, 'prompt_ai')) {
            return;
        }

        $ai->generate($revision_id);
    }

    /**
     * Register REST API routes.
     */
    public function registerRestRoutes()
    {
        $controller = new EditLedgerRestController();
        $controller->registerRoutes();
    }

    /**
     * Enqueue block editor assets.
     */
    public function enqueueEditorAssets()
    {
        global $post;

        if (! $post) {
            return;
        }

        // Load for any post that supports revisions.
        if (! post_type_supports($post->post_type, 'revisions')) {
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
                'wp-editor',
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
                'postPermalink' => get_permalink($post->ID),
                'restNonce'     => wp_create_nonce('wp_rest'),
                'siteUrl'       => get_site_url(),
                'strings'       => array(
                    'title'            => __('Edit Ledger', 'edit-ledger'),
                    'noRevisions'      => __('No revisions found.', 'edit-ledger'),
                    'loading'          => __('Loading revisions...', 'edit-ledger'),
                    'changed'          => __('Changed:', 'edit-ledger'),
                    'noChanges'        => __('No changes detected', 'edit-ledger'),
                    'auto'             => __('Auto', 'edit-ledger'),
                    'save'             => __('Save', 'edit-ledger'),
                    'ago'              => __('ago', 'edit-ledger'),
                    'revisionHistory'  => __('Revision History', 'edit-ledger'),
                    'revisions'        => __('revisions', 'edit-ledger'),
                    'viewInRevisions'  => __('View in Revisions', 'edit-ledger'),
                    'viewTimeline'     => __('Full Timeline', 'edit-ledger'),
                    'aiSummary'        => __('AI Summary', 'edit-ledger'),
                    'summarize'        => __('Summarize', 'edit-ledger'),
                    'summarizing'      => __('Summarizing...', 'edit-ledger'),
                    'summaryError'     => __('Could not generate summary.', 'edit-ledger'),
                    'retry'            => __('Retry', 'edit-ledger'),
                ),
                'aiAvailable'   => function_exists('wp_ai_client_prompt') && current_user_can('prompt_ai'),
            )
        );
    }
}
