<?php
/**
 * Adds featured-image support to Alpaca issues.
 *
 * @package AlpacaTakeover
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds featured-image upload and display support to the Project Board.
 */
final class Alpaca_Takeover_Featured_Image {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'alpaca-takeover/v1';

	/**
	 * Register featured-image hooks.
	 *
	 * @return void
	 */
	public static function boot() {
		$featured_image = new self();

		add_action( 'init', array( $featured_image, 'add_thumbnail_support' ), 20 );
		add_action( 'rest_api_init', array( $featured_image, 'register_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $featured_image, 'enqueue_assets' ) );
		add_filter( 'rest_request_after_callbacks', array( $featured_image, 'add_featured_image_data' ), 10, 3 );
	}

	/**
	 * Prevent creating instances outside the static bootstrap.
	 */
	private function __construct() {}

	/**
	 * Enable WordPress featured-image support for Alpaca issues.
	 *
	 * @return void
	 */
	public function add_thumbnail_support() {
		if ( post_type_exists( 'alpaca_issue' ) ) {
			add_post_type_support( 'alpaca_issue', 'thumbnail' );
		}
	}

	/**
	 * Register the featured-image endpoint.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/featured-images',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_board_featured_images' ),
				'permission_callback' => array( $this, 'can_read_featured_images' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/issues/(?P<id>\d+)/featured-image',
			array(
				'methods'             => array( WP_REST_Server::CREATABLE, WP_REST_Server::DELETABLE ),
				'callback'            => array( $this, 'update_featured_image' ),
				'permission_callback' => array( $this, 'can_update_featured_image' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => static function ( $issue_id ) {
							return is_numeric( $issue_id ) && 0 < (int) $issue_id;
						},
					),
				),
			)
		);
	}

	/**
	 * Check whether the current user can view issue featured images.
	 *
	 * @return bool Permission result.
	 */
	public function can_read_featured_images() {
		return current_user_can( 'read' );
	}

	/**
	 * Return featured images for issues on the board.
	 *
	 * @return WP_REST_Response|WP_Error Featured images or an error.
	 */
	public function get_board_featured_images() {
		if ( ! function_exists( 'alpaistr_get_board_data' ) ) {
			return new WP_Error(
				'alpaca_takeover_board_data_unavailable',
				esc_html__( 'Board data is unavailable.', 'alpaca-takeover' ),
				array( 'status' => 503 )
			);
		}

		$featured_images = array();
		$board_data      = alpaistr_get_board_data();

		foreach ( $board_data as $status ) {
			if ( empty( $status['issues'] ) || ! is_array( $status['issues'] ) ) {
				continue;
			}

			foreach ( $status['issues'] as $issue ) {
				$issue_id = isset( $issue['id'] ) ? (int) $issue['id'] : 0;

				if ( $issue_id <= 0 ) {
					continue;
				}

				$featured_image = $this->get_featured_image_data( $issue_id );

				if ( null !== $featured_image ) {
					$featured_images[ $issue_id ] = $featured_image;
				}
			}
		}

		return rest_ensure_response(
			array(
				'images' => $featured_images,
			)
		);
	}

	/**
	 * Check whether the current user can update an issue's featured image.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error Permission result.
	 */
	public function can_update_featured_image( $request ) {
		if ( ! class_exists( '\AlpacaIssueTracker\Helpers' ) ) {
			return new WP_Error(
				'alpaca_takeover_issue_permissions_unavailable',
				esc_html__( 'Issue permissions are unavailable.', 'alpaca-takeover' ),
				array( 'status' => 503 )
			);
		}

		return \AlpacaIssueTracker\Helpers::validate_rest_nonce_permission(
			$request,
			'update_issue',
			array( 'post_id' => (int) $request['id'] )
		);
	}

	/**
	 * Set or remove an issue's featured image.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error Updated featured image or an error.
	 */
	public function update_featured_image( $request ) {
		$issue_id = (int) $request['id'];
		$issue    = get_post( $issue_id );

		if ( ! $issue || 'alpaca_issue' !== $issue->post_type ) {
			return new WP_Error(
				'alpaca_takeover_issue_not_found',
				esc_html__( 'Issue not found.', 'alpaca-takeover' ),
				array( 'status' => 404 )
			);
		}

		if ( WP_REST_Server::DELETABLE === $request->get_method() ) {
			delete_post_thumbnail( $issue_id );
		} else {
			$attachment_id = $this->get_attachment_id_from_request( $request, $issue_id );

			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}

			$uploaded_files = $request->get_file_params();
			if (
				! set_post_thumbnail( $issue_id, $attachment_id ) &&
				(int) get_post_thumbnail_id( $issue_id ) !== $attachment_id
			) {
				if ( isset( $uploaded_files['file'] ) ) {
					wp_delete_attachment( $attachment_id, true );
				}

				return new WP_Error(
					'alpaca_takeover_featured_image_update_failed',
					esc_html__( 'The featured image could not be saved. Please try again.', 'alpaca-takeover' ),
					array( 'status' => 500 )
				);
			}
		}

		if ( function_exists( 'alpaistr_clear_board_cache' ) ) {
			alpaistr_clear_board_cache();
		}

		return rest_ensure_response(
			array(
				'featured_image' => $this->get_featured_image_data( $issue_id ),
			)
		);
	}

	/**
	 * Find or create an image attachment from the REST request.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param int             $issue_id Issue post ID.
	 * @return int|WP_Error Attachment ID or an error.
	 */
	private function get_attachment_id_from_request( $request, $issue_id ) {
		$files = $request->get_file_params();

		if ( isset( $files['file'] ) ) {
			$file = $files['file'];

			if ( ! empty( $file['error'] ) ) {
				return new WP_Error(
					'alpaca_takeover_featured_image_upload_failed',
					esc_html__( 'The image could not be uploaded. Please try again.', 'alpaca-takeover' ),
					array( 'status' => 400 )
				);
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$attachment_id = media_handle_sideload( $file, $issue_id );

			if ( is_wp_error( $attachment_id ) ) {
				return new WP_Error(
					'alpaca_takeover_featured_image_upload_failed',
					esc_html__( 'The image could not be uploaded. Please choose a supported image and try again.', 'alpaca-takeover' ),
					array( 'status' => 400 )
				);
			}

			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				wp_delete_attachment( $attachment_id, true );

				return new WP_Error(
					'alpaca_takeover_featured_image_not_image',
					esc_html__( 'Choose an image file to use as the featured image.', 'alpaca-takeover' ),
					array( 'status' => 400 )
				);
			}

			return (int) $attachment_id;
		}

		$params        = $request->get_json_params();
		$attachment_id = isset( $params['attachmentId'] ) ? absint( $params['attachmentId'] ) : 0;
		$attachment    = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'alpaca_takeover_featured_image_invalid',
				esc_html__( 'Choose a valid image from the media library.', 'alpaca-takeover' ),
				array( 'status' => 400 )
			);
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error(
				'alpaca_takeover_featured_image_forbidden',
				esc_html__( 'You are not allowed to use this image.', 'alpaca-takeover' ),
				array( 'status' => 403 )
			);
		}

		return $attachment_id;
	}

	/**
	 * Add featured-image data to Alpaca board and issue responses.
	 *
	 * @param mixed           $response The response to the request.
	 * @param array           $handler  The matched route handler.
	 * @param WP_REST_Request $request  REST request.
	 * @return mixed The original response, with featured-image data when applicable.
	 */
	public function add_featured_image_data( $response, $handler, $request ) {
		unset( $handler );

		if ( ! $response instanceof WP_REST_Response || 200 !== $response->get_status() ) {
			return $response;
		}

		$route = $request->get_route();
		$data  = $response->get_data();

		if ( '/alpaca/v1/board' === $route && is_array( $data ) ) {
			foreach ( $data as $status_index => $status ) {
				if ( empty( $status['issues'] ) || ! is_array( $status['issues'] ) ) {
					continue;
				}

				foreach ( $status['issues'] as $issue_index => $issue ) {
					$issue_id = isset( $issue['id'] ) ? (int) $issue['id'] : 0;

					if ( $issue_id > 0 ) {
						$data[ $status_index ]['issues'][ $issue_index ]['meta']['featured_image'] = $this->get_featured_image_data( $issue_id );
					}
				}
			}
		} elseif (
			preg_match( '#^/alpaca/v1/get/(?P<id>\d+)$#', $route, $matches ) &&
			is_array( $data ) &&
			isset( $data['meta'] ) &&
			is_array( $data['meta'] )
		) {
			$data['meta']['featured_image'] = $this->get_featured_image_data( (int) $matches['id'] );
		} else {
			return $response;
		}

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Return the featured image details for an issue.
	 *
	 * @param int $issue_id Issue post ID.
	 * @return array|null Featured image data, or null when unset.
	 */
	private function get_featured_image_data( $issue_id ) {
		$attachment_id = (int) get_post_thumbnail_id( $issue_id );

		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$image_url = wp_get_attachment_image_url( $attachment_id, 'large' );

		if ( ! $image_url ) {
			return null;
		}

		return array(
			'id'  => $attachment_id,
			'url' => esc_url_raw( $image_url ),
			'alt' => sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
		);
	}

	/**
	 * Enqueue the featured-image control on the Project Board.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( is_network_admin() || 'toplevel_page_project-board' !== $hook_suffix ) {
			return;
		}

		$plugin_directory = dirname( __DIR__ );
		$plugin_file      = $plugin_directory . '/alpaca-takeover.php';
		$script_file      = $plugin_directory . '/assets/js/featured-image.js';
		$style_file       = $plugin_directory . '/assets/css/featured-image.css';

		wp_enqueue_style(
			'alpaca-takeover-featured-image',
			plugin_dir_url( $plugin_file ) . 'assets/css/featured-image.css',
			array( 'alpaca-takeover-admin' ),
			file_exists( $style_file ) ? (string) filemtime( $style_file ) : '0.1.0'
		);

		wp_enqueue_script(
			'alpaca-takeover-featured-image',
			plugin_dir_url( $plugin_file ) . 'assets/js/featured-image.js',
			array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			file_exists( $script_file ) ? (string) filemtime( $script_file ) : '0.1.0',
			true
		);

		wp_localize_script(
			'alpaca-takeover-featured-image',
			'alpacaTakeoverFeaturedImage',
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
