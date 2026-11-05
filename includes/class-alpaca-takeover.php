<?php
/**
 * Applies the standalone Project Board site policy.
 *
 * @package AlpacaTakeover
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restricts the site to the Alpaca Project Board experience.
 */
final class Alpaca_Takeover {

	/**
	 * Register the takeover hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		$takeover = new self();

		add_filter( 'login_headerurl', array( $takeover, 'login_header_url' ) );
		add_filter( 'login_headertext', array( $takeover, 'login_header_text' ) );
		add_action( 'login_enqueue_scripts', array( $takeover, 'enqueue_login_styles' ) );
		add_filter( 'login_redirect', array( $takeover, 'redirect_after_login' ), 10, 3 );
		add_action( 'template_redirect', array( $takeover, 'block_frontend' ), 0 );
		add_filter( 'rest_authentication_errors', array( $takeover, 'require_rest_authentication' ) );
		add_filter( 'rest_pre_dispatch', array( $takeover, 'block_core_content_rest_routes' ), 10, 3 );
		add_filter( 'alpaca_user_can', array( $takeover, 'filter_board_read_permissions' ), 10, 3 );
		add_filter( 'pre_option_alpaistr_enable_context_capture', array( $takeover, 'disable_frontend_issue_capture' ), 999 );
		add_filter( 'register_post_type_args', array( $takeover, 'lock_core_content_types' ), 10, 2 );
		add_filter( 'custom_menu_order', array( $takeover, 'enable_custom_menu_order' ) );
		add_filter( 'menu_order', array( $takeover, 'move_project_board_to_top' ), 999 );
		add_action( 'admin_menu', array( $takeover, 'remove_admin_menus' ), 999 );
		add_action( 'admin_menu', array( $takeover, 'allow_board_read_access' ), 1000 );
		add_action( 'admin_enqueue_scripts', array( $takeover, 'enqueue_admin_bar_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $takeover, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $takeover, 'enqueue_admin_navigation_script' ) );
		add_action( 'admin_enqueue_scripts', array( $takeover, 'enqueue_settings_styles' ) );
		add_action( 'admin_bar_menu', array( $takeover, 'replace_wordpress_logo' ), 11 );
		add_action( 'admin_bar_menu', array( $takeover, 'remove_admin_bar_content_links' ), 999 );
		add_action( 'current_screen', array( $takeover, 'block_admin_screens' ) );
		add_action( 'current_screen', array( $takeover, 'filter_discussion_title' ) );
	}

	/**
	 * Prevent creating instances outside the static bootstrap.
	 */
	private function __construct() {}

	/**
	 * Link the login logo to Project Board.
	 *
	 * @param string $login_header_url The default login logo URL.
	 * @return string The Project Board URL.
	 */
	public function login_header_url( $login_header_url ) {
		unset( $login_header_url );

		return admin_url( 'admin.php?page=project-board' );
	}

	/**
	 * Set the accessible name of the login logo.
	 *
	 * @param string $login_header_text The default login logo text.
	 * @return string The accessible name for the Alpaca logo.
	 */
	public function login_header_text( $login_header_text ) {
		unset( $login_header_text );

		return __( 'Alpaca Project Board', 'alpaca-takeover' );
	}

	/**
	 * Enqueue the custom login-screen logo style.
	 *
	 * @return void
	 */
	public function enqueue_login_styles() {
		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$style_file       = $plugin_directory . '/assets/css/login.css';
		$style_version    = file_exists( $style_file ) ? (string) filemtime( $style_file ) : '0.1.0';

		wp_enqueue_style(
			'alpaca-takeover-login',
			plugin_dir_url( $plugin_file ) . 'assets/css/login.css',
			array( 'login' ),
			$style_version
		);
	}

	/**
	 * Redirect board-capable users to Project Board after login.
	 *
	 * @param string           $redirect_to           The requested redirect destination.
	 * @param string           $requested_redirect_to The redirect destination requested by the user.
	 * @param WP_User|WP_Error $user                  The authenticated user or an authentication error.
	 * @return string The destination URL.
	 */
	public function redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
		unset( $requested_redirect_to );

		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) || ! self::is_alpaca_active() ) {
			return $redirect_to;
		}

		return admin_url( 'admin.php?page=project-board' );
	}

	/**
	 * Allow logged-in users to read the Project Board and issue details.
	 *
	 * @param bool   $allowed Whether the current user is allowed.
	 * @param string $action  Alpaca permission action.
	 * @param array  $args    Contextual permission arguments.
	 * @return bool Permission result.
	 */
	public function filter_board_read_permissions( $allowed, $action, $args ) {
		unset( $args );

		$read_actions = array(
			'view_board',
			'get_issue',
			'comment_count',
			'list_users',
			'presence',
			'watchlist',
			'get_statuses',
		);

		if ( ! in_array( $action, $read_actions, true ) ) {
			return $allowed;
		}

		return is_user_logged_in();
	}

	/**
	 * Return a plain-text forbidden response for WordPress frontend requests.
	 *
	 * @return void
	 */
	public function block_frontend() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html__( 'This site is available through the Alpaca Project Board.', 'alpaca-takeover' );
		exit;
	}

	/**
	 * Deny REST requests from unauthenticated visitors.
	 *
	 * @param WP_Error|null $result Authentication result, if already determined.
	 * @return WP_Error|null Authentication result.
	 */
	public function require_rest_authentication( $result ) {
		if ( is_wp_error( $result ) || is_user_logged_in() ) {
			return $result;
		}

		return new WP_Error(
			'alpaca_takeover_rest_authentication_required',
			esc_html__( 'Authentication is required to access this site.', 'alpaca-takeover' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Disable Alpaca's front-end issue capture setting while takeover is active.
	 *
	 * @param mixed $pre_option The value to short-circuit the option lookup with.
	 * @return string Disabled option value.
	 */
	public function disable_frontend_issue_capture( $pre_option ) {
		unset( $pre_option );

		return '0';
	}

	/**
	 * Enqueue styles that hide the front-end capture control on Configure.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_settings_styles( $hook_suffix ) {
		$is_alpaca_settings_page     = false !== strpos( $hook_suffix, 'alpaca-settings' );
		$is_discussion_settings_page = 'options-discussion.php' === $hook_suffix;

		if ( ! $is_alpaca_settings_page && ! $is_discussion_settings_page ) {
			return;
		}

		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$style_file       = $plugin_directory . '/assets/css/settings.css';
		$style_version    = file_exists( $style_file ) ? (string) filemtime( $style_file ) : '0.1.0';

		wp_enqueue_style(
			'alpaca-takeover-settings',
			plugin_dir_url( $plugin_file ) . 'assets/css/settings.css',
			array(),
			$style_version
		);
	}

	/**
	 * Deny the standard REST API routes for WordPress posts and pages.
	 *
	 * @param mixed           $result  The response to return, or null to continue dispatching.
	 * @param WP_REST_Server  $server  The REST server instance.
	 * @param WP_REST_Request $request The REST request.
	 * @return mixed The response or an error for blocked routes.
	 */
	public function block_core_content_rest_routes( $result, $server, $request ) {
		unset( $server );

		if ( null !== $result || ! preg_match( '#^/wp/v2/(?:posts|pages)(?:/|$)#', $request->get_route() ) ) {
			return $result;
		}

		return new WP_Error(
			'alpaca_takeover_content_disabled',
			esc_html__( 'Posts and pages are disabled on this site.', 'alpaca-takeover' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Map built-in Posts and Pages capabilities to an ungrantable capability.
	 *
	 * @param array  $args      Post type registration arguments.
	 * @param string $post_type Post type key.
	 * @return array Modified or original registration arguments.
	 */
	public function lock_core_content_types( $args, $post_type ) {
		if ( ! in_array( $post_type, array( 'post', 'page' ), true ) ) {
			return $args;
		}

		$capability_names = array(
			'edit_post',
			'read_post',
			'delete_post',
			'edit_posts',
			'edit_others_posts',
			'publish_posts',
			'read_private_posts',
			'delete_posts',
			'delete_private_posts',
			'delete_published_posts',
			'delete_others_posts',
			'edit_private_posts',
			'edit_published_posts',
			'create_posts',
		);

		$args['capabilities'] = array_fill_keys( $capability_names, 'do_not_allow' );
		$args['map_meta_cap'] = true;

		return $args;
	}

	/**
	 * Remove the selected WordPress admin menu entries.
	 *
	 * @return void
	 */
	public function remove_admin_menus() {
		if ( is_network_admin() ) {
			return;
		}

		$menu_slugs = array(
			'index.php',
			'edit.php',
			'edit-tags.php?taxonomy=category',
			'edit-tags.php?taxonomy=post_tag',
			'edit.php?post_type=page',
			'upload.php',
			'edit-comments.php',
			'themes.php',
			'tools.php',
		);

		$submenu_items = array(
			'edit.php'            => array(
				'edit-tags.php?taxonomy=category',
				'edit-tags.php?taxonomy=post_tag',
			),
			'themes.php'          => array(
				'themes.php',
				'site-editor.php',
				'widgets.php',
				'nav-menus.php',
				'theme-install.php',
				'theme-editor.php',
				'customize.php',
			),
			'tools.php'           => array(
				'import.php',
				'theme-editor.php',
			),
			'options-general.php' => array(
				'options-writing.php',
				'options-reading.php',
				'options-media.php',
				'options-permalink.php',
				'options-privacy.php',
			),
		);

		foreach ( $submenu_items as $parent_slug => $submenu_slugs ) {
			foreach ( $submenu_slugs as $submenu_slug ) {
				remove_submenu_page( $parent_slug, $submenu_slug );
			}
		}

		foreach ( $menu_slugs as $menu_slug ) {
			remove_menu_page( $menu_slug );
		}

		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
		add_submenu_page(
			'options-general.php',
			__( 'Avatars', 'alpaca-takeover' ),
			__( 'Avatars', 'alpaca-takeover' ),
			'manage_options',
			'options-discussion.php'
		);
	}

	/**
	 * Register the Project Board menu for users with the basic read capability.
	 *
	 * @return void
	 */
	public function allow_board_read_access() {
		if ( is_network_admin() || ! self::is_alpaca_active() || ! function_exists( 'alpaistr_project_board_page' ) ) {
			return;
		}

		remove_submenu_page( 'project-board', 'project-board' );
		remove_menu_page( 'project-board' );

		add_menu_page(
			__( 'Project Board', 'alpaca-takeover' ),
			__( 'Project Board', 'alpaca-takeover' ),
			'read',
			'project-board',
			'alpaistr_project_board_page',
			'dashicons-schedule'
		);

		add_submenu_page(
			'project-board',
			__( 'Project Board', 'alpaca-takeover' ),
			__( 'Project Board', 'alpaca-takeover' ),
			'read',
			'project-board',
			'alpaistr_project_board_page',
			0
		);
	}

	/**
	 * Rename the Discussion screen title to Avatars.
	 *
	 * @param WP_Screen $screen The current admin screen.
	 * @return void
	 */
	public function filter_discussion_title( $screen ) {
		if ( 'options-discussion' !== $screen->id ) {
			return;
		}

		add_filter( 'gettext', array( $this, 'replace_discussion_title' ), 10, 3 );
	}

	/**
	 * Replace the core Discussion screen title.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text to translate.
	 * @param string $domain      Text domain.
	 * @return string The replacement or original translation.
	 */
	public function replace_discussion_title( $translation, $text, $domain ) {
		unset( $domain );

		if ( 'Discussion Settings' !== $text ) {
			return $translation;
		}

		return __( 'Avatars', 'alpaca-takeover' );
	}

	/**
	 * Enable custom menu ordering outside Network Admin.
	 *
	 * @param bool $custom_menu_order Whether custom menu ordering is enabled.
	 * @return bool Whether custom menu ordering is enabled.
	 */
	public function enable_custom_menu_order( $custom_menu_order ) {
		if ( is_network_admin() ) {
			return $custom_menu_order;
		}

		return true;
	}

	/**
	 * Move Project Board to the first position in the admin menu.
	 *
	 * @param array $menu_order The current admin menu order.
	 * @return array The updated admin menu order.
	 */
	public function move_project_board_to_top( $menu_order ) {
		if ( is_network_admin() || ! is_array( $menu_order ) ) {
			return $menu_order;
		}

		$board_position = array_search( 'project-board', $menu_order, true );

		if ( false === $board_position ) {
			return $menu_order;
		}

		array_splice( $menu_order, $board_position, 1 );
		array_unshift( $menu_order, 'project-board' );

		return $menu_order;
	}

	/**
	 * Replace the WordPress admin-bar logo with the Alpaca logo and board link.
	 *
	 * @param WP_Admin_Bar $admin_bar The admin bar instance.
	 * @return void
	 */
	public function replace_wordpress_logo( $admin_bar ) {
		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$logo_url         = plugin_dir_url( $plugin_file ) . 'assets/images/alpaca.svg';
		$logo_title       = '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Alpaca Project Board', 'alpaca-takeover' ) . '" class="alpaca-takeover-admin-bar-logo" />';

		$admin_bar->remove_node( 'wp-logo' );
		$admin_bar->add_node(
			array(
				'id'    => 'wp-logo',
				'title' => $logo_title,
				'href'  => admin_url( 'admin.php?page=project-board' ),
				'meta'  => array(
					'class' => 'alpaca-takeover-logo-node',
				),
			)
		);
	}

	/**
	 * Enqueue styles for the custom admin-bar logo.
	 *
	 * @return void
	 */
	public function enqueue_admin_bar_styles() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$style_file       = $plugin_directory . '/assets/css/admin-bar.css';
		$style_version    = file_exists( $style_file ) ? (string) filemtime( $style_file ) : '0.1.0';

		wp_enqueue_style(
			'alpaca-takeover-admin-bar',
			plugin_dir_url( $plugin_file ) . 'assets/css/admin-bar.css',
			array(),
			$style_version
		);
	}

	/**
	 * Enqueue the Alpaca admin visual theme.
	 *
	 * @return void
	 */
	public function enqueue_admin_styles() {
		if ( is_network_admin() ) {
			return;
		}

		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$style_file       = $plugin_directory . '/assets/css/admin.css';
		$style_version    = file_exists( $style_file ) ? (string) filemtime( $style_file ) : '0.1.0';

		wp_enqueue_style(
			'alpaca-takeover-font',
			'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'alpaca-takeover-admin',
			plugin_dir_url( $plugin_file ) . 'assets/css/admin.css',
			array( 'alpaca-takeover-font' ),
			$style_version
		);
	}

	/**
	 * Enqueue the accessible administration navigation disclosure.
	 *
	 * @return void
	 */
	public function enqueue_admin_navigation_script() {
		if ( is_network_admin() ) {
			return;
		}

		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$script_file      = $plugin_directory . '/assets/js/admin-nav.js';
		$script_version   = file_exists( $script_file ) ? (string) filemtime( $script_file ) : '0.1.0';

		wp_enqueue_script(
			'alpaca-takeover-admin-navigation',
			plugin_dir_url( $plugin_file ) . 'assets/js/admin-nav.js',
			array( 'wp-i18n' ),
			$script_version,
			true
		);
	}

	/**
	 * Remove toolbar links for the frontend and conventional content.
	 *
	 * @param WP_Admin_Bar $admin_bar The admin bar instance.
	 * @return void
	 */
	public function remove_admin_bar_content_links( $admin_bar ) {
		$node_ids = array( 'comments', 'new-content', 'view-site', 'site-name' );

		foreach ( $node_ids as $node_id ) {
			$admin_bar->remove_node( $node_id );
		}
	}

	/**
	 * Deny direct access to the removed admin screens.
	 *
	 * @param WP_Screen $screen The current admin screen.
	 * @return void
	 */
	public function block_admin_screens( $screen ) {
		if ( is_network_admin() ) {
			return;
		}

		if ( 'dashboard' === $screen->id && self::is_alpaca_active() && current_user_can( 'read' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=project-board' ) );
			exit;
		}

		$blocked_screen_ids = array(
			'dashboard',
			'tools',
			'import',
			'upload',
			'media',
			'attachment',
			'edit-comments',
			'comment',
			'themes',
			'theme-install',
			'theme-editor',
			'customize',
			'widgets',
			'nav-menus',
			'site-editor',
			'options-writing',
			'options-reading',
			'options-media',
			'options-permalink',
			'options-privacy',
		);

		$blocked_post_types = array( 'post', 'page', 'attachment' );
		$blocked_taxonomies = array( 'category', 'post_tag' );

		if (
			in_array( $screen->id, $blocked_screen_ids, true ) ||
			in_array( $screen->post_type, $blocked_post_types, true ) ||
			in_array( $screen->taxonomy, $blocked_taxonomies, true )
		) {
			wp_die(
				esc_html__( 'This WordPress admin area is disabled.', 'alpaca-takeover' ),
				esc_html__( 'Access denied', 'alpaca-takeover' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Check whether Alpaca Issue Tracker is loaded.
	 *
	 * @return bool Whether the Alpaca plugin is active for this request.
	 */
	private static function is_alpaca_active() {
		return defined( 'ALPAISTR_VERSION' ) && class_exists( '\\AlpacaIssueTracker\\AlpacaIssueTracker', false );
	}
}
