<?php

class PB_Park_REST_Misc_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1/pbpark';
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/location', array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'update_user_location' ),
			)
		) );

	}

	/**
	 * Get a list of banners
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function update_user_location( $request ) {
		$openid = getallheaders()['openid'];
		error_log('User location: ' . json_encode($request->get_json_params()));
		$near_point = null;
		if ($request->get_param('mockNearPoint')) { //  near a point
			$point_post = get_posts('post_type=point&order=asc')[0]; // mock near point
			$near_point = get_point($point_post->ID, true);
		}
		return rest_ensure_response(['nearPoint' => $near_point]);
	}

}
