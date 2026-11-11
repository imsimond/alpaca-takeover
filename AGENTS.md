# Alpaca Takeover Development Guidance

## Scope for Re-theming

When a task involves re-theming or visually adjusting an Alpaca takeover situation, make changes only in this `alpaca-takeover` plugin by default. Keep theme styles, theme-specific assets, and takeover presentation changes within this plugin.

Do not modify the base `alpaca` plugin as part of this work unless it is genuinely necessary. If a change to the base plugin appears necessary, stop and ask the user for explicit permission before editing any `alpaca` files. Do not treat a general request to re-theme or fix the takeover appearance as permission to change the base plugin. If permission is not granted, continue with a takeover-only solution or explain the blocker.

Before changing theme styling, read [`docs/theme-development.md`](docs/theme-development.md) and preserve its constraints, especially that themes may change presentation and sizing but must not reposition components or change the overall administration layout.

## Rapid Theme Preview

Use the `theme` query parameter to preview a registered theme without changing the saved site-wide theme. Append `&theme=<theme-id>` to the Project Board URL, for example:

```text
wp-admin/admin.php?page=project-board&theme=camelid
wp-admin/admin.php?page=project-board&theme=geranium
wp-admin/admin.php?page=project-board&theme=material
wp-admin/admin.php?page=project-board&theme=othello
```

This preview override is available to administrators on Project Board screens and accepts only registered theme IDs. It is temporary and does not save the selected theme. A valid forced theme configured by `alpaca_takeover_forced_theme` takes precedence over the query parameter. Use this parameter while iterating on takeover theme changes to compare the result across themes quickly.
