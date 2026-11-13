<?php
/**
 * Registers and applies Alpaca Takeover appearance preferences.
 *
 * @package AlpacaTakeover
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
	 * Owns site-wide appearance theme metadata.
 */
final class Alpaca_Takeover_Appearance {

	/**
	 * Site-wide theme option name.
	 *
	 * @var string
	 */
	const THEME_OPTION = 'alpaca_takeover_theme';

	/**
	 * Site-wide Othello board background color option name.
	 *
	 * @var string
	 */
	const OTHELLO_BOARD_COLOR_OPTION = 'alpaca_takeover_othello_board_color';

	/**
	 * Default Othello board background color.
	 *
	 * @var string
	 */
	const DEFAULT_OTHELLO_BOARD_COLOR = '#36749b';

	/**
	 * Default theme ID.
	 *
	 * @var string
	 */
	const DEFAULT_THEME = 'camelid';

	/**
	 * Register appearance hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		$appearance = new self();

		add_action( 'admin_init', array( $appearance, 'register_setting' ) );
		add_action( 'rest_api_init', array( $appearance, 'register_rest_routes' ) );
		add_filter( 'admin_body_class', array( $appearance, 'add_body_classes' ) );
		add_action( 'admin_enqueue_scripts', array( $appearance, 'enqueue_appearance_script' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $appearance, 'enqueue_geranium_script' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $appearance, 'enqueue_theme_styles' ), 20 );
	}

	/**
	 * Prevent creating instances outside the static bootstrap.
	 */
	private function __construct() {}

	/**
	 * Register the site-wide theme option.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			'alpaca_takeover_appearance',
			self::THEME_OPTION,
			array(
				'type'              => 'string',
				'default'           => self::DEFAULT_THEME,
				'sanitize_callback' => array( $this, 'sanitize_theme_option' ),
			)
		);
	}

	/**
	 * Validate the selected theme option.
	 *
	 * @param mixed $theme_id Submitted theme identifier.
	 * @return string Valid theme identifier.
	 */
	public function sanitize_theme_option( $theme_id ) {
		$theme_id = sanitize_key( (string) $theme_id );
		$themes   = self::get_themes();

		return isset( $themes[ $theme_id ] ) ? $theme_id : self::DEFAULT_THEME;
	}

	/**
	 * Register the appearance preference endpoints.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'alpaca-takeover/v1',
			'/appearance',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_appearance' ),
				'permission_callback' => array( $this, 'can_read_appearance' ),
			)
		);

		register_rest_route(
			'alpaca-takeover/v1',
			'/appearance/theme',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_theme' ),
				'permission_callback' => array( $this, 'can_manage_theme' ),
				'args'                => array(
					'theme' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'alpaca-takeover/v1',
			'/appearance/othello-board-color',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_othello_board_color' ),
				'permission_callback' => array( $this, 'can_manage_theme' ),
				'args'                => array(
					'color' => array(
						'required' => true,
						'type'     => 'string',
						'sanitize_callback' => 'sanitize_hex_color',
					),
				),
			)
		);

	}

	/**
	 * Check whether the current user can read appearance preferences.
	 *
	 * @return bool Permission result.
	 */
	public function can_read_appearance() {
		return current_user_can( 'read' );
	}

	/**
	 * Check whether the current user can change the site-wide theme.
	 *
	 * @return bool Permission result.
	 */
	public function can_manage_theme() {
		return current_user_can( 'manage_options' ) && ! self::is_theme_forced();
	}

	/**
	 * Return the appearance state available to the current user.
	 *
	 * @return WP_REST_Response Appearance state.
	 */
	public function get_appearance() {
		$theme_id = self::get_selected_theme_id();
		$themes   = self::get_themes();
		$items    = array();

		foreach ( $themes as $id => $theme ) {
			$items[] = array(
				'id'    => $id,
				'label' => $theme['label'],
			);
		}

		return rest_ensure_response(
			array(
				'theme'          => $theme_id,
				'themes'         => $items,
				'isForced'       => self::is_theme_forced(),
				'canManageTheme' => current_user_can( 'manage_options' ) && ! self::is_theme_forced(),
			)
		);
	}

	/**
	 * Save the site-wide theme selection.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error Updated appearance state or an error.
	 */
	public function update_theme( $request ) {
		$theme_id = sanitize_key( (string) $request->get_param( 'theme' ) );
		$themes   = self::get_themes();

		if ( ! isset( $themes[ $theme_id ] ) ) {
			return new WP_Error(
				'alpaca_takeover_invalid_theme',
				esc_html__( 'Choose an available appearance theme.', 'alpaca-takeover' ),
				array( 'status' => 400 )
			);
		}

		update_option( self::THEME_OPTION, $theme_id );

		return $this->get_appearance();
	}

	/**
	 * Save the Othello board background color.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error Updated color or an error.
	 */
	public function update_othello_board_color( $request ) {
		$color = sanitize_hex_color( (string) $request->get_param( 'color' ) );

		if ( null === $color ) {
			return new WP_Error(
				'alpaca_takeover_invalid_othello_board_color',
				esc_html__( 'Choose a valid board background color.', 'alpaca-takeover' ),
				array( 'status' => 400 )
			);
		}

		$color = strtolower( $color );
		update_option( self::OTHELLO_BOARD_COLOR_OPTION, $color );

		return rest_ensure_response( array( 'color' => $color ) );
	}

	/**
	 * Add the selected theme and its fixed color mode to the admin body.
	 *
	 * @param string $classes Existing body classes.
	 * @return string Updated body classes.
	 */
	public function add_body_classes( $classes ) {
		if ( is_network_admin() ) {
			return $classes;
		}

		$theme_id = self::get_selected_theme_id();
		$mode     = self::get_effective_mode( $theme_id );
		$screen   = get_current_screen();
		$classes .= ' alpaca-theme-' . $theme_id . ' alpaca-mode-' . $mode;

		if (
			$screen &&
			(
				'toplevel_page_project-board' === $screen->id ||
				0 === strpos( $screen->id, 'project-board_page_' )
			)
		) {
			$classes .= ' alpaca-takeover-screen';
		}

		return $classes;
	}

	/**
	 * Enqueue the Configure Appearance tab before Alpaca's settings bundle.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_appearance_script( $hook_suffix ) {
		if ( is_network_admin() || 'project-board_page_alpaca-settings' !== $hook_suffix || self::is_theme_forced() ) {
			return;
		}

		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$script_file      = $plugin_directory . '/assets/js/appearance.js';
		$script_version   = file_exists( $script_file ) ? (string) filemtime( $script_file ) : '0.1.0';

		wp_enqueue_script(
			'alpaca-takeover-appearance',
			plugin_dir_url( $plugin_file ) . 'assets/js/appearance.js',
			array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			$script_version,
			true
		);

		wp_localize_script(
			'alpaca-takeover-appearance',
			'alpacaTakeoverSettings',
			array(
				'appearancePath' => '/alpaca-takeover/v1/appearance',
				'themePath'      => '/alpaca-takeover/v1/appearance/theme',
				'theme'          => self::get_selected_theme_id(),
				'themes'         => self::get_client_themes(),
				'canManageTheme' => current_user_can( 'manage_options' ) && ! self::is_theme_forced(),
			)
		);
	}

	/**
	 * Enqueue Geranium card behavior before Alpaca renders the board.
	 *
	 * @return void
	 */
	public function enqueue_geranium_script() {
		if ( is_network_admin() || 'geranium' !== self::get_selected_theme_id() ) {
			return;
		}

		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$script_file      = $plugin_directory . '/assets/js/themes/geranium.js';
		$script_version   = file_exists( $script_file ) ? (string) filemtime( $script_file ) : null;

		wp_enqueue_script(
			'alpaca-takeover-theme-geranium',
			plugin_dir_url( $plugin_file ) . 'assets/js/themes/geranium.js',
			array( 'wp-element', 'wp-hooks' ),
			$script_version,
			true
		);
	}

	/**
	 * Enqueue theme behavior and the selected theme stylesheet.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_theme_styles( $hook_suffix ) {
		if ( is_network_admin() ) {
			return;
		}

		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$style_file       = $plugin_directory . '/dist/themes/themes.css';
		$style_version    = file_exists( $style_file ) ? (string) filemtime( $style_file ) : '0.1.0';
		$theme_id         = self::get_selected_theme_id();
		$themes           = self::get_themes();

		wp_enqueue_style(
			'alpaca-takeover-themes',
			plugin_dir_url( $plugin_file ) . 'dist/themes/themes.css',
			array( 'alpaca-takeover-admin' ),
			$style_version
		);

		$theme_stylesheet_file = $plugin_directory . '/dist/themes/' . $theme_id . '.css';
		$theme_stylesheet_version = file_exists( $theme_stylesheet_file ) ? (string) filemtime( $theme_stylesheet_file ) : null;

		wp_enqueue_style(
			'alpaca-takeover-theme-' . $theme_id,
			$themes[ $theme_id ]['stylesheet'],
			array( 'alpaca-takeover-themes' ),
			$theme_stylesheet_version
		);

		if ( 'othello' === $theme_id ) {
			$board_color = self::get_othello_board_color();
			$display_color = self::get_othello_display_color( $board_color );

			wp_add_inline_style(
				'alpaca-takeover-theme-othello',
				sprintf(
					'body.wp-admin.alpaca-theme-othello { --alpaca-paper: %1$s; --alpaca-othello-hue-color: %1$s; }',
					$display_color
				)
			);
		}

		if ( 'material' === $theme_id ) {
			$theme_script_file    = $plugin_directory . '/assets/js/themes/material.js';
			$theme_script_version = file_exists( $theme_script_file ) ? (string) filemtime( $theme_script_file ) : null;

			wp_enqueue_script(
				'alpaca-takeover-theme-material',
				plugin_dir_url( $plugin_file ) . 'assets/js/themes/material.js',
				array(),
				$theme_script_version,
				true
			);
		}

		if ( 'othello' === $theme_id ) {
			$theme_script_file    = $plugin_directory . '/assets/js/themes/othello.js';
			$theme_script_version = file_exists( $theme_script_file ) ? (string) filemtime( $theme_script_file ) : null;
			$theme_script_dependencies = array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-hooks', 'wp-i18n' );

			if ( 'project-board_page_alpaca-settings' === $hook_suffix && ! self::is_theme_forced() ) {
				$theme_script_dependencies[] = 'alpaca-takeover-appearance';
			}

			wp_enqueue_script(
				'alpaca-takeover-theme-othello',
				plugin_dir_url( $plugin_file ) . 'assets/js/themes/othello.js',
				$theme_script_dependencies,
				$theme_script_version,
				true
			);

			wp_localize_script(
				'alpaca-takeover-theme-othello',
				'alpacaTakeoverOthelloSettings',
				array(
					'boardColor'           => self::get_othello_board_color(),
					'boardColorPath'       => '/alpaca-takeover/v1/appearance/othello-board-color',
					'appearanceTabName'    => 'alpaca-takeover-appearance',
					'canManageBoardColor'  => current_user_can( 'manage_options' ) && ! self::is_theme_forced(),
				)
			);
		}

	}

	/**
	 * Return the theme names for the settings UI.
	 *
	 * @return array<int, array<string, string|bool>> Client-safe theme information.
	 */
	private static function get_client_themes() {
		$client_themes = array();

		foreach ( self::get_themes() as $theme_id => $theme ) {
			$client_themes[] = array(
				'value' => $theme_id,
				'label' => $theme['label'],
			);
		}

		return $client_themes;
	}

	/**
	 * Return theme labels and available modes after applying extension filters.
	 *
	 * @return array<string, array<string, mixed>> Validated theme metadata.
	 */
	public static function get_themes() {
		$defaults = self::get_default_themes();
		$filtered = apply_filters( 'alpaca_takeover_themes', $defaults );

		if ( ! is_array( $filtered ) ) {
			$filtered = $defaults;
		}

		$themes = array();

		foreach ( $filtered as $theme_id => $theme ) {
			$theme_id = sanitize_key( (string) $theme_id );

			if ( '' === $theme_id || ! is_array( $theme ) || empty( $theme['label'] ) || empty( $theme['stylesheet'] ) ) {
				continue;
			}

			$label      = sanitize_text_field( (string) $theme['label'] );
			$stylesheet = esc_url_raw( (string) $theme['stylesheet'] );

			if ( '' === $label || '' === $stylesheet ) {
				continue;
			}

			$themes[ $theme_id ] = array(
				'label'      => $label,
				'stylesheet' => $stylesheet,
				'mode'       => isset( $theme['mode'] ) && 'dark' === $theme['mode'] ? 'dark' : 'light',
			);
		}

		if ( ! isset( $themes[ self::DEFAULT_THEME ] ) ) {
			$themes[ self::DEFAULT_THEME ] = array(
				'label'      => $defaults[ self::DEFAULT_THEME ]['label'],
				'stylesheet' => $defaults[ self::DEFAULT_THEME ]['stylesheet'],
				'mode'       => $defaults[ self::DEFAULT_THEME ]['mode'],
			);
		}

		return $themes;
	}

	/**
	 * Return the selected theme, honoring a valid forced-theme filter.
	 *
	 * @return string Theme identifier.
	 */
	private static function get_selected_theme_id() {
		$themes = self::get_themes();
		$forced = sanitize_key( (string) apply_filters( 'alpaca_takeover_forced_theme', '' ) );

		if ( '' !== $forced && isset( $themes[ $forced ] ) ) {
			return $forced;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if (
			current_user_can( 'manage_options' ) &&
			isset( $_GET['theme'] ) &&
			is_string( $_GET['theme'] ) &&
			$screen &&
			(
				'toplevel_page_project-board' === $screen->id ||
				0 === strpos( $screen->id, 'project-board_page_' )
			)
		) {
			$requested = sanitize_key( wp_unslash( $_GET['theme'] ) );

			if ( isset( $themes[ $requested ] ) ) {
				return $requested;
			}
		}

		$selected = sanitize_key( (string) get_option( self::THEME_OPTION, self::DEFAULT_THEME ) );

		return isset( $themes[ $selected ] ) ? $selected : self::DEFAULT_THEME;
	}

	/**
	 * Determine whether a valid forced theme is active.
	 *
	 * @return bool Whether a registered theme is forced.
	 */
	private static function is_theme_forced() {
		$forced = sanitize_key( (string) apply_filters( 'alpaca_takeover_forced_theme', '' ) );

		return '' !== $forced && isset( self::get_themes()[ $forced ] );
	}

	/**
	 * Return the fixed color mode for a theme.
	 *
	 * @param string $theme_id Theme identifier.
	 * @return string Color mode.
	 */
	private static function get_effective_mode( $theme_id ) {
		$themes = self::get_themes();

		return isset( $themes[ $theme_id ]['mode'] ) ? $themes[ $theme_id ]['mode'] : 'light';
	}

	/**
	 * Return the saved Othello board background color.
	 *
	 * @return string Hexadecimal color.
	 */
	private static function get_othello_board_color() {
		$color = sanitize_hex_color(
			(string) get_option( self::OTHELLO_BOARD_COLOR_OPTION, self::DEFAULT_OTHELLO_BOARD_COLOR )
		);

		return null !== $color ? strtolower( $color ) : self::DEFAULT_OTHELLO_BOARD_COLOR;
	}

	/**
	 * Return the Othello board color adjusted for its hue on the first paint.
	 *
	 * @param string $color Saved hexadecimal board color.
	 * @return string Display hexadecimal color.
	 */
	private static function get_othello_display_color( $color ) {
		$hexadecimal = substr( $color, 1 );

		if ( 3 === strlen( $hexadecimal ) ) {
			$hexadecimal = $hexadecimal[0] . $hexadecimal[0] . $hexadecimal[1] . $hexadecimal[1] .
				$hexadecimal[2] . $hexadecimal[2];
		}

		$rgb        = array(
			hexdec( substr( $hexadecimal, 0, 2 ) ) / 255,
			hexdec( substr( $hexadecimal, 2, 2 ) ) / 255,
			hexdec( substr( $hexadecimal, 4, 2 ) ) / 255,
		);
		$maximum    = max( $rgb );
		$minimum    = min( $rgb );
		$difference = $maximum - $minimum;
		$hue        = 0;

		if ( 0 < $difference ) {
			if ( $maximum === $rgb[0] ) {
				$hue = 60 * fmod( ( $rgb[1] - $rgb[2] ) / $difference, 6 );
			} elseif ( $maximum === $rgb[1] ) {
				$hue = 60 * ( ( $rgb[2] - $rgb[0] ) / $difference + 2 );
			} else {
				$hue = 60 * ( ( $rgb[0] - $rgb[1] ) / $difference + 4 );
			}

			if ( 0 > $hue ) {
				$hue += 360;
			}
		}

		$hue               = (int) round( $hue ) % 360;
		$hue_radians       = ( 2 * M_PI * ( $hue - 125 ) ) / 360;
		$wave_progress     = ( 1 + cos( $hue_radians ) ) / 2;
		$max_luminance     = 0.16 + ( 0.1 - 0.16 ) * $wave_progress;
		$weights           = array( 0.2126, 0.7152, 0.0722 );
		$current_luminance = 0;

		foreach ( $rgb as $index => $channel ) {
			$linear_channel     = $channel <= 0.04045
				? $channel / 12.92
				: pow( ( $channel + 0.055 ) / 1.055, 2.4 );
			$current_luminance += $linear_channel * $weights[ $index ];
		}

		if ( $current_luminance <= $max_luminance ) {
			return $color;
		}

		$luminance_scale = $max_luminance / $current_luminance;
		$display_color   = '#';

		foreach ( $rgb as $channel ) {
			$linear_channel   = $channel <= 0.04045
				? $channel / 12.92
				: pow( ( $channel + 0.055 ) / 1.055, 2.4 );
			$adjusted_channel = $linear_channel * $luminance_scale;
			$srgb_channel     = $adjusted_channel <= 0.0031308
				? $adjusted_channel * 12.92
				: 1.055 * pow( $adjusted_channel, 1 / 2.4 ) - 0.055;
			$display_color   .= str_pad(
				dechex( (int) round( $srgb_channel * 255 ) ),
				2,
				'0',
				STR_PAD_LEFT
			);
		}

		return $display_color;
	}

	/**
	 * Return the built-in themes and their fixed color modes.
	 *
	 * @return array<string, array<string, mixed>> Built-in theme metadata.
	 */
	private static function get_default_themes() {
		$plugin_file = dirname( __DIR__ ) . '/alpaca-takeover.php';
		$theme_url   = plugin_dir_url( $plugin_file ) . 'dist/themes/';

		return array(
			'camelid' => array(
				'label'      => __( 'Camelid', 'alpaca-takeover' ),
				'stylesheet' => $theme_url . 'camelid.css',
				'mode'       => 'light',
			),
			'geranium' => array(
				'label'      => __( 'Geranium', 'alpaca-takeover' ),
				'stylesheet' => $theme_url . 'geranium.css',
				'mode'       => 'light',
			),
			'material' => array(
				'label'      => __( 'Material', 'alpaca-takeover' ),
				'stylesheet' => $theme_url . 'material.css',
				'mode'       => 'light',
			),
			'othello' => array(
				'label'      => __( 'Othello', 'alpaca-takeover' ),
				'stylesheet' => $theme_url . 'othello.css',
				'mode'       => 'dark',
			),
		);
	}
}