# Edit Ledger

Editorial-friendly revision history for WordPress — media change tracking, change type labels, and an admin dashboard that complements WP 7.0's native visual revisions.

[![Try in WordPress Playground](https://img.shields.io/badge/Try%20it-WordPress%20Playground-3858e9?logo=wordpress)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/alansmodic/edit-ledger/master/blueprint.json)

## Description

Edit Ledger adds a sidebar timeline to the block editor showing who changed what and when, with media change tracking that WordPress's native revisions don't provide. Click "View in Revisions" to jump into WP 7.0's built-in visual diff mode.

## Features

- **Block Editor Sidebar Timeline**: Visual timeline of revision cards showing author, timestamp, and change type (auto/manual)
- **Media Change Tracking**: See which images, videos, and embeds were added or removed, inline in each revision card
- **WP 7.0 Integration**: "View in Revisions" button opens the native visual revisions mode with block-level diffs
- **Admin Dashboard**: Overview of recent revisions across all posts with filtering and word-level diffs
- **Clean Text Diffs**: Strips HTML to show editors clean, readable text changes (admin dashboard)
- **AI Revision Summaries**: Plain-English summaries of what changed in each revision (e.g., "Headline rewritten for clarity. New section added: Pricing.") powered by the WP AI Client
- **Abilities API**: Exposes `edit-ledger/summarize-revision` via the WordPress Abilities API for external AI agents and MCP clients

## Requirements

- WordPress 7.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the `edit-ledger` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Open any post in the block editor and click "Edit Ledger" in the sidebar

## Usage

### Block Editor
- Open any post/page in the block editor
- Click the clock icon or "Edit Ledger" in the sidebar menu
- Click any revision card to expand and see what changed, including media changes
- Use "View in Revisions" to enter WP 7.0's native visual revisions mode

### AI Summaries
- When an AI provider is configured, a "Summarize" button appears on expanded revision cards
- Summaries are auto-generated on save if the author has the `prompt_ai` capability
- Disable auto-summarization: `add_filter('edit_ledger_auto_summarize', '__return_false');`
- Customize the AI prompt: use the `edit_ledger_ai_system_instruction` filter

### Abilities API
- The `edit-ledger/summarize-revision` ability is registered for external discovery
- Invoke via `POST /wp-json/wp-abilities/v1/abilities/edit-ledger/summarize-revision/run` with `{"input":{"revision_id":123}}`

### Admin Dashboard
- Navigate to "Edit Ledger" in the WordPress admin menu
- Filter revisions by author or date range
- Click "View Diff" on any revision to see changes

## License

GPL v2 or later
