<?php

class PB_Park_REST_Term_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1/pbpark';
		$this->rest_base = 'terms';
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<name>.+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_term' ),
			)
		) );
	}

	/**
	 * Get single term
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_term( $request ) {

		$term_name = $request->get_param('name');

		$term = get_term_by('slug', $term_name, $request->get_param('taxonomy') ?: 'category');

		$image = get_field('image', $term);

		if (defined('CDN_URL')) {
			$cdn_url = constant('CDN_URL');
			if ($image) {
				$image = preg_replace('/' . preg_quote(site_url(), '/') . '\//', $cdn_url, $image);
			}
		}

		$term->image = $image;

		return rest_ensure_response($term);

	}

}
