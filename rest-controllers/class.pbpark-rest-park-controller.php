<?php

class PB_Park_REST_Park_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1/pbpark';
		$this->rest_base = 'park';
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_parks' ),
			)
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>.+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_park' ),
			)
		) );

	}

	/**
	 * Get a list of parks
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_parks( $request ) {

		$parameters = array('post_type' => 'park', 'posts_per_page' => -1);

		$near = $request->get_param('near');
		$near_lat_long = array();

		if ($near) {
			$near_lat_long = explode(',', $near);
		}

		$posts = get_posts($parameters);

		$parks = array_map(function (WP_Post $park_post) use($near_lat_long) {

			$park = array(
				'id' => $park_post->ID,
				'name' => get_the_title($park_post->ID),
				'address' => get_field('address', $park_post->ID),
				'phone' => get_field('phone', $park_post->ID)
			);

			if ($near_lat_long) {
				$park_latitude = get_post_meta($park_post->ID, 'latitude', true);
				$park_longitude = get_post_meta($park_post->ID, 'longitude', true);
				$park['distance'] = round(haversine_great_circle_distance($near_lat_long[0], $near_lat_long[1], $park_latitude, $park_longitude) / 1000, 1);
			}

			return (object) $park;

		}, $posts);

		if ($near_lat_long) {
			usort($parks, function($a, $b) {
				return $a->distance - $b->distance;
			});
		}

		return rest_ensure_response($parks);
	}

	/**
	 * Get a park
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_park( $request ) {

		if (is_wp_error($user = get_user_by_openid())) {
			$user = null;
		}

		$id = $request->get_param('id');

		$near = $request->get_param('near');
		$near_lat_long = array();

		if ($near) {
			$near_lat_long = explode(',', $near);
		}

		if (is_numeric($id)) {
			$post = get_post($id);
		} else {
			$post = get_page_by_path($id, 'OBJECT', 'park');
		}

		if (!$post) {
			return rest_ensure_response(new WP_Error('park_not_found', '公园不存在', array('status' => 404)));
		}

		$park = array(
			'id' => $post->ID,
			'slug' => $post->post_name,
			'name' => get_the_title($post->ID),
			// 'content' => preg_replace('/(\r?\n){2,}/', "\n", $post->post_content),
			'content' => wpautop($post->post_content),
			'address' => get_field('address', $post->ID),
			'phone' => get_field('phone', $post->ID),
			'points' => array_map(function($point_id) use($user){
				return get_point($point_id, false, $user);
			}, get_field('points', $post->ID))
		);

		if ($near_lat_long) {
			$park_latitude = get_post_meta($post->ID, 'latitude', true);
			$park_longitude = get_post_meta($post->ID, 'longitude', true);
			$park['distance'] = round(haversine_great_circle_distance($near_lat_long[0], $near_lat_long[1], $park_latitude, $park_longitude) / 1000, 1);
		}

		return rest_ensure_response($park);
	}

}
