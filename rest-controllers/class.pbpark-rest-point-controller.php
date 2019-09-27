<?php

class PB_Park_REST_Point_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1/pbpark';
		$this->rest_base = 'coupon';
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_coupons' ),
			), array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'post_coupon' ),
			), array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_coupon' ),
			)
		) );

	}

	/**
	 * Get a list of coupons
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_coupons( $request ) {

		$parameters = array('post_type' => 'coupon', 'posts_per_page' => -1);

		$posts = get_posts($parameters);

		$coupons = array_map(function (WP_Post $post) {
			return get_coupon($post->ID);
		}, $posts);

		return rest_ensure_response($coupons);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function post_coupon( $request ) {

		$body = $request->get_body_params();

		$coupon_id = wp_insert_post(array(
			'post_type' => 'coupon',
			'post_status' => 'publish'
		));

		foreach ($body as $key => $value) {
			add_post_meta($coupon_id, $key, $value);
		}

		return rest_ensure_response(get_coupon($coupon_id));
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function delete_coupon( $request ) {

	}

}
