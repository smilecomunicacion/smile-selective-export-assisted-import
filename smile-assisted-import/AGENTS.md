# SMiLE Basic Web Coding Guidelines

## Scope
These rules apply to every file in this repository unless a subdirectory contains its own `AGENTS.md` file.

## Project context
- SMiLE Basic Web is a WordPress plugin that centralizes several features. At this stage we focus on the advanced contact form functionality while keeping the codebase ready to host additional features in the future.
- The plugin must support multiple custom contact forms. Each form has its own custom field definitions and visual appearance, while SMTP and reCAPTCHA settings remain global for the entire site.
- Provide admin interfaces to create, edit, and delete forms and to customize their appearance.

## General engineering rules
- Follow current [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/) and best practices in PHP, JavaScript, and CSS.
- Generate complete, production-ready code. Do not provide partial implementations or placeholders.
- Preserve existing functionality unless a change is required to add the requested features or to fix an issue. If you remove code, state the technical reason in the PR description.
- Avoid introducing naming collisions. Prefix all new identifiers (functions, classes, hooks, settings, files, etc.) with `smssmpt_`.
- Never rename existing identifiers unless required to fix a bug or to prevent conflicts. If renaming is unavoidable, document the reason in the PR body and reference the affected files and lines.
- Use vanilla JavaScript instead of jQuery.
- Implement AJAX when asynchronous behavior is required.

## Security and data handling
- Do not access the database directly; prefer WordPress APIs. When a direct query is unavoidable, combine `$wpdb->prepare()` with `wp_cache_get()`, `wp_cache_set()`, or `wp_cache_delete()`.
- Use `WP_Query` or modern APIs instead of deprecated helpers such as `get_page_by_title()`.
- Sanitize, validate, and escape all data at the latest possible stage. Escape attributes with `esc_attr()`, `esc_attr_e()`, or `esc_attr__()` and use the text domain `smile-basic-web`. Escape URLs with `esc_url()` and HTML output with `esc_html()`, `esc_html_e()`, or `esc_html__()`.
- For strings with placeholders passed to translation functions, add a translators comment immediately above the call (e.g., `/* translators: %s is the field label. */`).
- Always verify nonces before processing input from `$_GET`, `$_POST`, `$_REQUEST`, or AJAX payloads to prevent CSRF vulnerabilities.
- Use `gmdate()` instead of `date()` to avoid timezone inconsistencies.

## Documentation and comments
- File headers must use a docblock containing `@package`.
- Parameter documentation must use `@param`, `@return`, and related tags.
- Docblock short descriptions start with a capital letter.
- Section comments must follow the exact template:
  ```
  /*
   * -------------------------------------------------------------------
   *  Section name
   * -------------------------------------------------------------------
   */
  ```
- Inline comments end with a period, exclamation point, or question mark.

## Output and presentation
- Escape all dynamic output before rendering.
- For HTML attributes, call `esc_attr_e()` when printing translated text directly. Use `esc_attr()` when returning a value for concatenation.
- Ensure admin and front-end interfaces are presented in English.
- Avoid the `<canvas>` element unless explicitly required.

## File-specific expectations
- When asked to update `content-search.php`, deliver a complete, standards-compliant template following all rules above.
- Provide all code inline in responses; do not link to external files or images. If a screenshot is required, follow the project-wide instructions.

