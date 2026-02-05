# CLAUDE.md - Edit Ledger

## Project Overview

Edit Ledger is a WordPress plugin that replaces the default revision slider with a visual timeline showing who changed what and when. It provides word-level diff visualization, media change tracking, and revision restoration for editorial teams.

**Version:** 1.0.0 | **License:** GPL v2 | **Requires:** WordPress 6.4+, PHP 7.4+

## Architecture

```
edit-ledger/
├── edit-ledger.php                          # Plugin entry point, constants, bootstrap
├── includes/
│   ├── class-edit-ledger.php                # Core singleton: registers REST routes + editor assets
│   ├── class-edit-ledger-rest-controller.php  # REST API (extends WP_REST_Controller)
│   └── class-edit-ledger-diff-generator.php   # LCS-based word-level diff engine
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
| GET | `/recent` | Recent revisions across all posts (admin) |

## Key Classes

- **`Edit_Ledger`** (`includes/class-edit-ledger.php`) — Singleton. Hooks into `rest_api_init` and `enqueue_block_editor_assets`. Entry point for plugin logic.
- **`Edit_Ledger_REST_Controller`** (`includes/class-edit-ledger-rest-controller.php`) — Largest file. Handles all REST endpoints, permission checks, HTML stripping for diffs, media extraction (images, video, YouTube, Vimeo, embeds), and media change detection.
- **`Edit_Ledger_Diff_Generator`** (`includes/class-edit-ledger-diff-generator.php`) — Implements LCS (Longest Common Subsequence) algorithm for word-level diff. Tokenizes text, computes diff operations, renders to `<ins>`/`<del>` HTML.
- **`Edit_Ledger_Admin`** (`admin/class-edit-ledger-admin.php`) — Singleton. Registers admin menu page, enqueues admin-specific CSS/JS.

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
- **Editor integration:** Plugin registers a sidebar via `wp.plugins.registerPlugin()` with `wp.editPost.PluginSidebar` containing a revision timeline, diff modal, and restore functionality
- **Permission model:** `edit_post` capability for viewing/restoring individual post revisions; `edit_others_posts` for the admin-wide recent revisions view
