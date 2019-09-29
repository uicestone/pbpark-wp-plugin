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

		register_rest_route( $this->namespace, '/quiz-result', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'save_quiz_result' ),
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

		if (is_wp_error($user = get_user_by_openid())) {
			return rest_ensure_response($user);
		}

		error_log("User {$user->id} location: " . json_encode($request->get_json_params()));
		$near_point = null;
		if ($request->get_param('mockNearPoint')) { //  near a point
			$point_post = get_posts('post_type=point&order=asc')[0]; // mock near point
			$near_point = get_point($point_post->ID, true);
		}
		return rest_ensure_response(['nearPoint' => $near_point]);
	}

	/**
	 * Saves result of a quiz at a point
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function save_quiz_result( $request ) {
		if (is_wp_error($user = get_user_by_openid())) {
			return rest_ensure_response($user);
		}
		$body = $request->get_json_params();
		$park = $body['park'];
		$point = $body['point'];
		$duration = $body['duration'];
		$correct = $body['correct'];
		$quiz_data = json_decode(get_user_meta($user->id, 'quiz_data_' . $park, true));
		if (!$quiz_data) {
			$quiz_data = [];
		}
		if (in_array($point, array_column($quiz_data, 'point'))) {
			return rest_ensure_response(new WP_Error('duplicate_result', '同一个点只能提交一次答案', array('status' => 409)));
		}
		array_push($quiz_data, (object) compact('point', 'duration', 'correct'));
		update_user_meta($user->id, 'quiz_data_' . $park, json_encode($quiz_data));
		$duration_total = array_sum(array_column($quiz_data, 'duration'));
		$correct_total = array_sum(array_column($quiz_data, 'correct'));
		update_user_meta($user->id, 'quiz_duration_' . $park, $duration_total);
		update_user_meta($user->id, 'quiz_correct_' . $park, $correct_total);

		return rest_ensure_response([
			'data' => $quiz_data,
			'totalCorrect' => $correct_total,
			'totalDuration' => $duration_total
		]);
	}
}
