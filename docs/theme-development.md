# Theme Development

Alpaca Takeover keeps its overall administration layout and component placement fixed. Themes may vary presentation and component sizing, spacing, and dimensions, but should not reposition components or change the overall layout. The selected theme is site-wide, and each theme supplies one fixed color mode. Camelid and Material 3 are light; Othello is dark.

## Register a Theme

Use the `alpaca_takeover_themes` PHP filter to register a theme with a label, stylesheet URL, and fixed `mode` (`light` or `dark`). The stylesheet URL is required; built-in themes already provide their own. Token values belong in CSS, with custom properties on `body.wp-admin.alpaca-theme-{id}`.

```php
add_filter(
	'alpaca_takeover_themes',
	function ( $themes ) {
		$themes['field-notes'] = array(
			'label'      => __( 'Field Notes', 'my-plugin' ),
			'stylesheet' => plugin_dir_url( __FILE__ ) . 'assets/css/field-notes.css',
			'mode'       => 'dark',
		);

		return $themes;
	}
);
```

Use the `--alpaca-` prefix and replace underscores with hyphens for CSS custom properties: `ink`, `chrome_surface`, `line`, `paper`, `surface`, `dark_surface`, `dark_accent`, `text_on_dark`, `text_on_accent`, `text_muted`, `border_muted`, `accent`, `accent_hover`, `danger`, `danger_strong`, `danger_surface`, `danger_border`, `icon_muted`, and `shadow_color`. Color values should be hexadecimal colors. Takeover provides shared defaults for common typography, spacing, border, radius, and shadow geometry tokens; themes can override these defaults by setting the same custom properties on their theme body selector. Themes should define their own palette and shadow color and opacity.

Typography and presentation tokens include `font_family`, `font_size_body`, `font_size_small`, `font_size_label`, `font_size_nav`, `font_size_heading`, `font_size_title`, `space_unit`, `border_width`, `radius_sm`, `radius_control`, `radius_md`, `radius_lg`, `radius_xl`, `shadow_x`, `shadow_y`, `shadow_blur`, `shadow_opacity`, `popover_shadow_y`, and `popover_shadow_blur`. Size values use `px`, `rem`, or `em`; shadow opacity is a percentage from `0%` to `100%`. Themes may vary component sizes, spacing, dimensions, and padding, but should not change component placement or the overall administration layout. Font-family values are limited to family names and common separators; plugins that use a custom font should enqueue that font themselves.

## Theme Stylesheet

Built-in theme styles are authored in `src/scss/themes/`. Install the development dependencies with `npm install`, then run `npm run build` to compile the stylesheets into `dist/themes/`. The build keeps the stable CSS filenames used by the plugin. Commit both SCSS sources and generated CSS so plugin installations do not need Node.js or a build step. Run `npm run watch` while iterating on themes.

The shared `_mixins.scss` file provides `admin-theme-tokens()` for a base token map and an optional light-mode map, `admin-theme()` and `board-theme()` scopes, and focus-ring and transition helpers. Use `light-theme` and `dark-theme` inside a theme scope to group mode-specific adjustments. Pass `$admin-bar: true` to `light-theme` to apply the shared admin-bar treatment:

```scss
@include board-theme("field-notes") {
	@include light-theme {
		#project-board .alpaca-container:nth-child(3n) {
			--alpaca-columnbg: var(--alpaca-dark-surface);
		}
	}
}
```

Use the button helpers to keep primary, secondary, and tertiary states consistent while using the active theme's tokens. `button-variants()` applies the recipes to Gutenberg's `.is-primary`, `.is-secondary`, and `.is-tertiary` button classes. Include it only in the component scope the theme intends to style. `button-primary()`, `button-secondary()`, and `button-tertiary()` can be included directly for custom controls; each accepts an optional border radius, defaulting to `--alpaca-radius-control`.

Keep palettes in token maps and component rules in the theme stylesheet:

```scss
@include admin-theme-tokens(
  "field-notes",
  (
    font-size-nav: 14px,
    radius-control: 4px,
  ),
  (
    ink: #172b4d,
    accent: #0c66e4,
  )
);
```

Use the focus and transition helpers for repeated state styling; keep theme-specific values in the token maps.

Board columns use `--alpaca-columnbg`. Set its default on the theme body, then override it for subsequent columns with `nth-child()` selectors, as the built-in light themes do.

## Force a Theme

A site plugin can enforce a registered theme with `alpaca_takeover_forced_theme`. The Configure > Appearance tab is omitted while a valid registered theme is forced. Return a theme ID, not CSS or a URL.

```php
add_filter(
	'alpaca_takeover_forced_theme',
	function () {
		return 'field-notes';
	}
);
```

Theme IDs are sanitized before use. An invalid forced ID does not override the site's selected theme. Theme stylesheets may set presentation custom properties and theme-specific sizing, spacing, and dimensions. Do not override component positioning or the overall layout owned by Takeover.
