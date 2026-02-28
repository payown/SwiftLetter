# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

All commands run from the plugin root: `app/public/wp-content/plugins/SwiftLetter/`

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Build JS assets (outputs to build/)
npm run build

# Watch JS assets during development
npm run start

# Lint JavaScript
npm run lint:js

# Lint CSS
npm run lint:css

# Run the standalone DOCX export test (no WP environment needed)
php tests/test-docx-export.php
```

The local WordPress environment is managed by Local (by Flywheel) at `app/public/`. There is no automated PHP test suite — the only test file is `tests/test-docx-export.php`, a standalone script that stubs WordPress functions.

## Architecture

### PHP — `includes/` (PSR-4: `SwiftLetter\`)

Entry point is `swiftletter.php`, which bootstraps `Plugin::instance()->init()` via `plugins_loaded`. The singleton `Plugin` class wires up all hooks.

**Post types** (`includes/PostTypes/`):
- `swl_newsletter` — container post, holds metadata about the newsletter
- `swl_article` — content post edited in Gutenberg; belongs to a newsletter via `_swl_newsletter_id` meta

**REST Controllers** (`includes/REST/`) — all extend `WP_REST_Controller`, namespace `swiftletter/v1`:
- `NewslettersController` — newsletter CRUD, article list with ordering
- `ArticlesController` — article CRUD, DOCX import, version management
- `AIController` — AI refinement (`/ai-refine`), review confirmation, alt-text generation
- `TTSController` — voice list, preview, per-article audio generation, newsletter-level audio generation
- `ExportController` — `POST /newsletters/{id}/publish-post` publishes to a WP post; `rebuild_published_post()` (static) is called after any article save or audio generation to keep the live post current
- `SettingsController` — exposes settings to the React UI

**Services**:
- `AI/AIService` — dispatches to `ClaudeProvider` or `OpenAIProvider` based on `swl_active_ai` option
- `TTS/TTSService` — extracts plain text from Gutenberg block HTML and calls `OpenAITTSProvider`
- `Settings/Encryption` — encrypts/decrypts API keys stored in wp_options
- `Audit/AuditLog` — writes to `{prefix}swl_audit_log` table
- `Database/Schema` — creates `swl_audit_log` and `swl_article_versions` tables via `dbDelta`

**Key lifecycle hook** — `Plugin::on_article_save()`: when a `swl_article` is saved, it resets review confirmation (if confirmed) and calls `ExportController::rebuild_published_post()` to sync the live post.

**Settings options**: `swl_active_ai`, `swl_openai_key`, `swl_claude_key`, `swl_tts_voice`, `swl_typography`, `swl_ffmpeg_available`, `swl_ffmpeg_path`

### JavaScript — `src/` (built with `@wordpress/scripts`)

Two webpack entry points, both output to `build/`:

**`src/dashboard/`** — React SPA mounted on `#swiftletter-dashboard` in the WP admin page (`admin.php?page=swiftletter`):
- `index.js` — root `App` component; manages `view` state (`newsletters-list` | `create-newsletter` | `newsletter-detail`) and a `newsletter_id` URL param for returning from the Gutenberg editor
- `views/newsletters-list.js` — list and delete newsletters
- `views/create-newsletter.js` — create form
- `views/newsletter-detail.js` — main builder UI: article ordering, per-article actions (AI refine, audio, review), publish controls, newsletter-level audio generation
- `components/keyboard-shortcuts-help-modal.js` — modal listing shortcuts
- `hooks/use-keyboard-shortcuts.js` — `Alt+Shift+<key>` shortcut registration

**`src/article-sidebar/`** — Gutenberg plugin sidebar registered for `swl_article` post type:
- `index.js` — registers sidebar, shows "Return to Newsletter Builder" link at top
- `panels/ai-refinement.js` — trigger AI refinement from editor
- `panels/review-status.js` — confirm/reset review
- `panels/version-history.js` — browse and restore previous versions
- `panels/audio.js` — generate audio from the editor

The sidebar reads `window.swiftletterData` (set via `wp_localize_script`): `{ restUrl, nonce, dashboardUrl, newsletterId }`.

### DOCX Generation

DOCX files are produced by `ExportController::generate_newsletter_docx()` using PhpWord:
```php
\PhpOffice\PhpWord\Shared\Html::addHtml($section, '<div>' . $html . '</div>', false, false);
$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpword, 'Word2007');
```
Files are saved to `uploads/swiftletter/docx/newsletter-{id}.docx` and the path is stored in `_swl_newsletter_docx_file_path` post meta. The docx and audio directories are publicly accessible (no `.htaccess` deny); the exports directory is protected.

### Published Post Structure

When a newsletter is published (`ExportController::publish_post`), a standard `post` is created with Gutenberg block markup in this order:
1. "Available Formats" section (newsletter DOCX download + audio player/download) — only if files exist
2. Table of Contents (links to anchored article headings)
3. Article sections (H2 heading with anchor + raw block content)

`rebuild_published_post()` regenerates this content in-place whenever articles are saved or audio is generated.

## Accessibility Requirements (WCAG 2.1 AA)

This plugin's core purpose is accessible newsletter publishing. Every UI change must maintain full screen reader and keyboard-only operability.

### Keyboard shortcuts

All dashboard shortcuts use `Alt+Shift+<key>` (implemented in `hooks/use-keyboard-shortcuts.js`). The hook automatically suppresses shortcuts when focus is inside INPUT, TEXTAREA, SELECT, or a `contentEditable` element. New shortcuts must follow this convention, be added to the help modal (`components/keyboard-shortcuts-help-modal.js`), and be annotated on their triggering element with `aria-keyshortcuts`. Decorative shortcut badges shown next to button text must carry `aria-hidden="true"`.

Current bindings: `H` (help modal), `N` (new newsletter), `B` (back), `A` (add article), `E` (AI evaluate), `R` (review confirm), `↑`/`↓` (reorder articles).

### Live regions and announcements

Dynamic changes that a sighted user sees visually must be announced to screen readers:
- Transient notifications: `role="status"` + `aria-live="polite"` (already on the global notification bar in `index.js`)
- Article reorder confirmation: the `<div className="swl-sr-only">` live region in `newsletter-detail.js` must be updated with a text description (e.g. "Article moved up") every time an article changes position
- Error messages that require immediate attention: `role="alert"` (implicit `aria-live="assertive"`)

Do not use `role="alert"` for non-urgent status updates; use `role="status"` instead.

### Focus management

After any action that removes or replaces the focused element, focus must be restored to a logical target:
- Article reorder: restore focus to the moved article's reorder button (pattern already in `newsletter-detail.js` using `btn.focus()` inside `setTimeout(0)`)
- Modal open: focus moves to the modal; modal close returns focus to the trigger element
- Destructive action (delete): after deletion, move focus to the next item or the list container

New modals must use the WordPress `Modal` component, which handles focus trap and `aria-modal` automatically. Do not build custom modals from scratch.

### Interactive element labelling

- Every icon-only button needs `aria-label` describing its action and target (e.g. `"Move 'Intro' up"`), not just the action
- Form inputs must have an associated `<label>` or `aria-label`; never rely on `placeholder` alone
- Tables must use `<th scope="col">` for column headers (the pattern is already established in `newsletters-list.js` and the keyboard shortcuts modal)

### Screen reader utility class

Use `.swl-sr-only` (defined in `src/dashboard/style.css`) for text that must be read by screen readers but not displayed visually. Do not use `display:none` or `visibility:hidden` for content that should be accessible — those remove elements from the accessibility tree entirely.

### Audio elements

`<audio>` elements must include fallback inner text ("Your browser does not support audio playback.") and carry a descriptive `aria-label` identifying what the audio contains (e.g. article title or "Newsletter audio"). The `controls` attribute is required so keyboard users can operate the player.

### Image alt text

The AI alt-text generation feature (`AIController::generate_attachment_alt_text`) exists precisely to prevent empty alt attributes on images in articles. When an article has images missing alt text, the dashboard must display a warning (already implemented via `role="alert"` in `newsletter-detail.js`) and block publishing is not enforced in code — do not remove the warning UI.

### Color

Do not use color as the sole means of conveying state. Review status badges already pair color with text labels; maintain this pattern for any new status indicators.
