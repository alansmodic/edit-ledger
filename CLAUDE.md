# CLAUDE.md - Edit Ledger

## Project Overview

Edit Ledger is a WordPress plugin that complements WP 7.0's native visual revisions with a sidebar timeline showing who changed what and when, media change tracking, and an admin dashboard with word-level diffs. It adds change type labels and media tracking that the native revisions mode does not provide.

**Version:** 2.0.0 | **License:** GPL v2 | **Requires:** WordPress 7.0+, PHP 7.4+

## Architecture

```
edit-ledger/
├── edit-ledger.php                          # Plugin entry point, constants, bootstrap
├── includes/
│   ├── constants.php                        # Plugin version and path constants
│   ├── class-edit-ledger.php                # Core singleton: registers REST routes + editor assets
│   ├── class-edit-ledger-rest-controller.php  # REST API (extends WP_REST_Controller)
│   ├── class-edit-ledger-diff-generator.php   # LCS-based word-level diff engine
│   ├── class-edit-ledger-ai-summary.php       # AI summary generation via WP AI Client
│   └── class-edit-ledger-abilities.php        # Abilities API registration
├── admin/
│   ├── class-edit-ledger-admin.php          # Admin menu + asset enqueuing
│   └── views/admin-page.php                 # Admin dashboard HTML template
└── assets/
    ├── css/
    │   ├── editor.css                       # Block editor sidebar styles
    │   └── admin.css                        # Admin dashboard styles
    └── js/
        ├── editor.js                        # Block editor sidebar (wp.element components)
        └── admin.js                         # Admin dashboard (jQuery)
```

## Tech Stack

- **Backend:** PHP 7.4+ (WordPress plugin API)
- **Block Editor UI:** Vanilla JS using `wp.element` (React-like, non-JSX)
- **Admin UI:** jQuery (WordPress-bundled)
- **CSS:** Plain CSS, no preprocessors
- **No build step.** No npm, composer, webpack, or transpilation. All files are served directly.

## REST API Endpoints

All routes are under the `edit-ledger/v1` namespace:

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/posts/{post_id}/revisions` | List revisions for a post |
| GET | `/revisions/{revision_id}/diff` | Get word-level diff for a revision |
| POST | `/revisions/{revision_id}/restore` | Restore a revision to parent post |
| POST | `/revisions/{revision_id}/summary` | Generate AI summary for a revision |
| GET | `/revisions/{revision_id}/summary` | Get cached AI summary for a revision |
| GET | `/recent` | Recent revisions across all posts (admin) |

## Key Classes

- **`Edit_Ledger`** (`includes/class-edit-ledger.php`) — Singleton. Hooks into `rest_api_init` and `enqueue_block_editor_assets`. Entry point for plugin logic.
- **`Edit_Ledger_REST_Controller`** (`includes/class-edit-ledger-rest-controller.php`) — Largest file. Handles all REST endpoints, permission checks, HTML stripping for diffs, media extraction (images, video, YouTube, Vimeo, embeds), and media change detection.
- **`Edit_Ledger_Diff_Generator`** (`includes/class-edit-ledger-diff-generator.php`) — Implements LCS (Longest Common Subsequence) algorithm for word-level diff. Tokenizes text, computes diff operations, renders to `<ins>`/`<del>` HTML.
- **`EditLedgerAiSummary`** (`includes/class-edit-ledger-ai-summary.php`) — Stateless service. Generates AI summaries of revision changes via `wp_ai_client_prompt()`, stores results in post meta. Methods: `isAvailable()`, `generate()`, `get()`, `getSummaryText()`.
- **`EditLedgerAbilities`** (`includes/class-edit-ledger-abilities.php`) — Registers `edit-ledger/summarize-revision` ability with the WordPress Abilities API so external AI agents and MCP clients can discover and invoke AI summaries.
- **`Edit_Ledger_Admin`** (`admin/class-edit-ledger-admin.php`) — Singleton. Registers admin menu page, enqueues admin-specific CSS/JS.

## WP 7.0 Visual Revisions Integration

WordPress 7.0 introduces in-editor visual revisions with a revision slider, read-only canvas, and block-level visual diffs. Edit Ledger complements this by providing:

- **Sidebar timeline** with change type labels (auto/manual) and changed field summaries
- **Media change tracking** (added/removed images, videos, embeds) shown inline in expanded timeline cards
- **"View in Revisions" button** that opens WP 7.0's native visual revisions mode
- **Admin dashboard** with word-level diffs and cross-post revision browsing

The editor integration does **not** include its own diff modal, restore functionality, or link hijacking — these are handled natively by WP 7.0.

## Development Conventions

### PHP
- **WordPress coding standards:** snake_case for functions/variables, tabs for indentation
- **Singleton pattern** for main classes (`private __construct()`, `static get_instance()`)
- **Security:** Always escape output (`esc_html()`, `esc_attr()`, `esc_url()`), sanitize input (`sanitize_text_field()`, `absint()`), check capabilities (`current_user_can()`), use `$wpdb->prepare()` for SQL
- **Internationalization:** All user-facing strings wrapped in `__()` with text domain `edit-ledger`
- **PHPDoc comments** on all classes and public methods
- **ABSPATH guard** at the top of every PHP file: `if ( ! defined( 'ABSPATH' ) ) { exit; }`

### JavaScript
- **Block editor:** Component-based with `wp.element.createElement()` (not JSX). Uses `wp.data`, `wp.components`, `wp.apiFetch`
- **Admin page:** jQuery with IIFE for scope isolation
- **camelCase** for JS functions and variables
- **XSS prevention** via `escapeHtml()` utility function in admin JS
- **Localized strings** accessed via `editLedgerData.strings` object (passed from PHP via `wp_localize_script`)

### CSS
- All classes prefixed with `edit-ledger-` to avoid conflicts
- Flexbox layouts
- WordPress admin color conventions (e.g., `#007cba` for primary blue)
- Separate stylesheets for editor vs admin contexts

## Testing

No automated test suite exists. No PHPUnit, Jest, or other test runners are configured. Manual testing against a WordPress installation is the current approach.

## Build & Deploy

There is no build step. To install:
1. Copy the `edit-ledger/` directory into `wp-content/plugins/`
2. Activate via WordPress admin

## Important Patterns

- **Diff generation flow:** REST controller strips HTML from block content via `strip_html_for_diff()` (converting media to `[Image]`, `[Video]`, etc. placeholders first), then passes cleaned text to `Edit_Ledger_Diff_Generator::generate()`
- **Media tracking:** `extract_media()` pulls images, videos, YouTube, and Vimeo URLs from post content; `get_media_changes()` compares two versions to find added/removed media
- **Editor integration:** Two touch points registered via `wp.plugins.registerPlugin()`:
  1. `PluginDocumentSettingPanel` — compact summary (latest revision type, changed fields, media indicator, action buttons) injected into the native document sidebar
  2. `PluginSidebar` — full revision timeline with expanded cards and inline media changes (power-user detail view)
- **Admin dashboard:** Independent of editor integration. Uses the diff REST endpoint and jQuery-based UI with its own diff modal and restore functionality
- **Permission model:** `edit_post` capability for viewing/restoring individual post revisions; `edit_others_posts` for the admin-wide recent revisions view; `prompt_ai` for AI summary generation
- **AI summaries:** Generated via WP AI Client (`wp_ai_client_prompt()`). Auto-generated on revision save (filterable via `edit_ledger_auto_summarize`). Cached in `_edit_ledger_ai_summary` post meta. Exposed via REST endpoint and Abilities API. System instruction filterable via `edit_ledger_ai_system_instruction`.
