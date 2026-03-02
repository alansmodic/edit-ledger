<?php

/**
 * AI-powered revision summary generator.
 *
 * Uses the WP AI Client (merged into WP 7.0 core) to produce
 * plain-English summaries of what changed in each revision.
 */

namespace EditLedger;

if (! defined('ABSPATH')) {
    exit;
}

class EditLedgerAiSummary
{
    /**
     * Meta key for the summary text.
     *
     * @var string
     */
    const META_SUMMARY = '_edit_ledger_ai_summary';

    /**
     * Meta key for the generation timestamp.
     *
     * @var string
     */
    const META_GENERATED_AT = '_edit_ledger_ai_summary_generated_at';

    /**
     * Check whether the WP AI Client is available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return function_exists('wp_ai_client_prompt');
    }

    /**
     * Generate an AI summary for a revision.
     *
     * @param int $revision_id The revision post ID.
     * @return array|\WP_Error Summary array on success, WP_Error on failure.
     */
    public function generate($revision_id)
    {
        $revision = get_post($revision_id);
        if (! $revision || 'revision' !== $revision->post_type) {
            return new \WP_Error(
                'invalid_revision',
                __('Invalid revision ID.', 'edit-ledger'),
                array('status' => 404)
            );
        }

        // Get previous revision for comparison.
        $previous = EditLedgerRestController::getPreviousRevision($revision);
        if (! $previous) {
            $previous = get_post($revision->post_parent);
        }

        if (! $previous) {
            return new \WP_Error(
                'no_comparison',
                __('No previous version found for comparison.', 'edit-ledger'),
                array('status' => 404)
            );
        }

        // Build before/after text.
        $title_before   = $previous->post_title;
        $title_after    = $revision->post_title;
        $content_before = EditLedgerRestController::stripHtmlForDiff($previous->post_content);
        $content_after  = EditLedgerRestController::stripHtmlForDiff($revision->post_content);
        $excerpt_before = EditLedgerRestController::stripHtmlForDiff($previous->post_excerpt);
        $excerpt_after  = EditLedgerRestController::stripHtmlForDiff($revision->post_excerpt);

        $diff_context = "TITLE BEFORE: {$title_before}\n"
            . "TITLE AFTER: {$title_after}\n"
            . "CONTENT BEFORE: {$content_before}\n"
            . "CONTENT AFTER: {$content_after}\n"
            . "EXCERPT BEFORE: {$excerpt_before}\n"
            . "EXCERPT AFTER: {$excerpt_after}";

        $system_instruction = apply_filters(
            'edit_ledger_ai_system_instruction',
            'You are an editorial assistant. Summarize what changed in this post revision in 1-2 concise sentences. Focus on the substance of the changes (what was added, removed, or rewritten) not the mechanics. Do not mention "the user" or "the author" — describe the changes directly. If only whitespace or formatting changed, say so briefly.'
        );

        $result = wp_ai_client_prompt($diff_context)
            ->using_system_instruction($system_instruction)
            ->using_temperature(0.3)
            ->generate_text();

        if (is_wp_error($result)) {
            return new \WP_Error(
                'ai_generation_failed',
                $result->get_error_message(),
                array('status' => 502)
            );
        }

        $summary_text = is_string($result) ? trim($result) : '';
        if (empty($summary_text)) {
            return new \WP_Error(
                'ai_generation_failed',
                __('AI returned an empty summary.', 'edit-ledger'),
                array('status' => 502)
            );
        }

        $generated_at = gmdate('c');

        update_metadata('post', $revision_id, self::META_SUMMARY, $summary_text);
        update_metadata('post', $revision_id, self::META_GENERATED_AT, $generated_at);

        return array(
            'summary'      => $summary_text,
            'revision_id'  => $revision_id,
            'generated_at' => $generated_at,
        );
    }

    /**
     * Get cached AI summary for a revision.
     *
     * @param int $revision_id The revision post ID.
     * @return array|null Summary array or null if not generated.
     */
    public function get($revision_id)
    {
        $summary = get_post_meta($revision_id, self::META_SUMMARY, true);
        if (empty($summary)) {
            return null;
        }

        $generated_at = get_post_meta($revision_id, self::META_GENERATED_AT, true);

        return array(
            'summary'      => $summary,
            'revision_id'  => $revision_id,
            'generated_at' => $generated_at ?: null,
        );
    }

    /**
     * Get just the summary text for a revision.
     *
     * @param int $revision_id The revision post ID.
     * @return string|null Summary text or null.
     */
    public function getSummaryText($revision_id)
    {
        $summary = get_post_meta($revision_id, self::META_SUMMARY, true);
        return ! empty($summary) ? $summary : null;
    }
}
