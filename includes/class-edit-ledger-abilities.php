<?php

/**
 * Abilities API integration for Edit Ledger.
 *
 * Registers the edit-ledger/summarize-revision ability so external
 * AI agents and MCP clients can discover and invoke AI summaries
 * via the WordPress Abilities API (shipped in WP 6.9).
 */

namespace EditLedger;

if (! defined('ABSPATH')) {
    exit;
}

class EditLedgerAbilities
{
    /**
     * Constructor. Registers hooks for Abilities API initialization.
     */
    public function __construct()
    {
        add_action('wp_abilities_api_categories_init', array( $this, 'registerCategories' ));
        add_action('wp_abilities_api_init', array( $this, 'registerAbilities' ));
    }

    /**
     * Register the content-management category if not already present.
     *
     * @return void
     */
    public function registerCategories()
    {
        if (! function_exists('wp_register_ability_category')) {
            return;
        }

        wp_register_ability_category('content-management', array(
            'label'       => __('Content Management', 'edit-ledger'),
            'description' => __('Abilities related to creating, editing, and managing content.', 'edit-ledger'),
        ));
    }

    /**
     * Register the edit-ledger/summarize-revision ability.
     *
     * @return void
     */
    public function registerAbilities()
    {
        if (! function_exists('wp_register_ability')) {
            return;
        }

        wp_register_ability('edit-ledger/summarize-revision', array(
            'label'               => __('Summarize Revision', 'edit-ledger'),
            'description'         => __(
                'Generate an AI summary of changes in a WordPress post revision. Returns a plain-English changelog describing what was added, removed, or rewritten.',
                'edit-ledger'
            ),
            'category'            => 'content-management',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'revision_id' => array(
                        'type' => 'integer',
                    ),
                ),
                'required'   => array( 'revision_id' ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'summary'      => array(
                        'type' => 'string',
                    ),
                    'revision_id'  => array(
                        'type' => 'integer',
                    ),
                    'generated_at' => array(
                        'type'   => 'string',
                        'format' => 'date-time',
                    ),
                ),
            ),
            'execute_callback'    => function ($input) {
                $ai = new EditLedgerAiSummary();
                return $ai->generate($input['revision_id']);
            },
            'permission_callback' => function ($input) {
                $revision_id = isset($input['revision_id']) ? absint($input['revision_id']) : 0;
                $revision    = get_post($revision_id);

                if (! $revision || 'revision' !== $revision->post_type) {
                    return false;
                }

                if (! current_user_can('edit_post', $revision->post_parent)) {
                    return false;
                }

                return true;
            },
            'meta'                => array(
                'annotations'  => array(
                    'readonly'    => false,
                    'idempotent'  => true,
                    'destructive' => false,
                ),
                'show_in_rest' => true,
            ),
        ));
    }
}
