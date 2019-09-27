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
		return rest_ensure_response(['nearPoint' => null]);
	}

}
