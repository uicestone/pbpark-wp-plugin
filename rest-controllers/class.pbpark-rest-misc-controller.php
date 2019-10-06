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

		register_rest_route( $this->namespace, '/ranking/(?P<park>.+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_ranking' ),
			)
		) );

		register_rest_route( $this->namespace, '/point/(?P<id>.+)', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'set_point_location' ),
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

		$body = $request->get_json_params();

		error_log("User {$user->id} location: " . json_encode($request->get_json_params()));
		$near_point = null;
		$points = array_map(function($point_post) use($user, $body){
			$point = get_point($point_post, true, $user);
			if ($point->latitude && $point->longitude) {
				$point->distance = haversine_great_circle_distance($point->latitude, $point->longitude, $body['latitude'], $body['longitude']);
			} else {
				$point->distance = null;
			}
			return $point;
		}, get_posts(['post_type'=>'point', 'posts_per_page'=>-1]));

		usort($points, function($a, $b){
			if ($b->distance === null) return -1;
			if ($a->distance === null) return 1;
			return $a->distance < $b->distance ? -1 : 1;
		});

		if ($points[0]->distance !== null && $points[0]->distance < 15) {
			$near_point = $points[0];
		}

		if ($request->get_param('mockNearPoint') && !$near_point) { //  near a point
			$point_post = get_posts('post_type=point&order=asc')[0]; // mock near point
			$near_point = get_point($point_post->ID, true, $user);
		}

		$points_distance = array_map(function($point){
			return ['name'=>$point->name, 'distance'=>$point->distance];
		}, $points);

		return rest_ensure_response(['nearPoint' => $near_point, 'points' => $points_distance]);
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
		if (in_array($point, array_column($quiz_data, 'point')) && !in_array('administrator', $user->roles)) {
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

	/**
	 * Get ranking list and user position of a park
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_ranking( $request ) {

		if (is_wp_error($me = get_user_by_openid())) {
			return rest_ensure_response($me);
		}

		$park = $request->get_param('park');

		$my_duration = (int) get_user_meta($me->id, 'quiz_duration_' . $park, true);
		$my_correct = (int) get_user_meta($me->id, 'quiz_correct_' . $park, true);

		$users_more_correct = count(get_users([
			'meta_query' => [
				'correct_clause' => [
					'key' => 'quiz_correct_' . $park,
					'type' => 'numeric',
					'compare' => '>',
					'value' => $my_correct
				]
			],
			'limit' => -1
		]));

		$users_same_correct_less_duration = count(get_users([
			'meta_query' => [
				'relation' => 'AND',
				'correct_clause' => [
					'key' => 'quiz_correct_' . $park,
					'value' => $my_correct
				],
				'duration_clause' => [
					'key' => 'quiz_duration_' . $park,
					'type' => 'numeric',
					'compare' => '<',
					'value' => $my_duration
				]
			],
			'limit' => -1
		]));

		$ranking_users = get_users([
			'meta_query' => [
				'relation' => 'AND',
				'duration_clause' => [
					'key' => 'quiz_duration_' . $park,
					'type' => 'numeric'
				],
				'correct_clause' => [
					'key' => 'quiz_correct_' . $park,
					'type' => 'numeric'
				]
			],
			'orderby' => array(
				'correct_clause' => 'DESC',
				'duration_clause' => 'ASC',
			),
		]);

		$tops = array_map(function(WP_User $user) use ($park) {
			return [
				'id' => $user->ID,
				'name' => $user->display_name,
				'avatarUrl' => get_user_meta($user->ID, 'avatar_url', true),
				'duration' => (int) get_user_meta($user->ID, 'quiz_duration_' . $park, true),
				'correct' => (int) get_user_meta($user->ID, 'quiz_correct_' . $park, true)
			];
		}, $ranking_users);

		$myRanking = [
			'id' => $me->id,
			'name' => $me->name,
			'avatarUrl' => get_user_meta($me->id, 'avatar_url', true),
			'duration' => $my_duration,
			'correct' => $my_correct,
			'ranking' => $users_more_correct + $users_same_correct_less_duration + 1
		];

		return rest_ensure_response(compact('tops', 'myRanking'));
	}

	/**
	 * Set GPS location of a point
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function set_point_location( $request ) {
		$point_id = $request->get_param('id');
		$body = $request->get_json_params();
		update_field('latitude', $body['latitude'], $point_id);
		update_field('longitude', $body['longitude'], $point_id);
		return rest_ensure_response(get_point($point_id));
	}
}
