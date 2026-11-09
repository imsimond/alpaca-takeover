# Theme Development

Alpaca Takeover keeps its administration layout and component geometry fixed. Theme stylesheets control presentation tokens such as color, typography, spacing, borders, and shadows. The selected theme is site-wide, and each theme supplies one fixed color mode. Camelid is light; Othello is dark.

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

Use the `--alpaca-` prefix and replace underscores with hyphens for CSS custom properties: `ink`, `chrome_surface`, `line`, `paper`, `surface`, `dark_surface`, `dark_accent`, `text_on_dark`, `text_on_accent`, `text_muted`, `border_muted`, `accent`, `accent_hover`, `danger`, `danger_strong`, `danger_surface`, `danger_border`, `icon_muted`, and `shadow_color`. Color values should be hexadecimal colors.

Typography and presentation tokens include `font_family`, `font_size_body`, `font_size_small`, `font_size_label`, `font_size_nav`, `font_size_heading`, `font_size_title`, `spacing_unit`, `border_width`, `radius_sm`, `radius_control`, `radius_md`, `radius_lg`, `radius_xl`, `shadow_x`, `shadow_y`, `shadow_blur`, `shadow_opacity`, `popover_shadow_y`, and `popover_shadow_blur`. Size values use `px`, `rem`, or `em`; shadow opacity is a percentage from `0%` to `100%`. Control heights and padding are fixed as part of the component geometry. Font-family values are limited to family names and common separators; plugins that use a custom font should enqueue that font themselves.

## Theme Stylesheet

The first three `--alpaca-palette-*` variables color board columns. Define them in the theme stylesheet for each mode.

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

Theme IDs are sanitized before use. An invalid forced ID does not override the site's selected theme. Theme stylesheets should set presentation custom properties only; do not override layout, positioning, or component dimensions, which are part of Takeover's fixed geometry.
