<?php

class PB_Park_REST_100d_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1/pbpark';
		$this->rest_base = '100d';
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_100ds' ),
			)
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>.+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_100d' ),
			)
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>.+)', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'save_100d_answer' ),
			)
		) );
	}

	/**
	 * Get a list of 100 days
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_100ds( $request ) {
		$target = strtotime('2021-07-01T00:00:00+0800');
		$daysLeft = (int)ceil(($target - time()) / 86400);
		$latestDay = 100 - $daysLeft + 1;
		$items = array_map(function (WP_Post $post) use($latestDay) {
			$day = (int)get_field('day',$post->ID);
			$item = (object)[
				'id'=>$post->ID,
				'title'=>$post->post_title,
				'day'=>(int)get_field('day',$post->ID),
				'type'=>get_field('type',$post->ID),
				'available'=>$day<=$latestDay
			];
			return $item;
		}, get_posts(['post_type'=>'100d','order'=>'asc','posts_per_page'=>-1]));

		return rest_ensure_response($items);

	}

	/**
	 * Get a day of 100 days
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_100d( $request ) {

		$post = get_post($request->get_param('id'));

		$item = (object)[
			'id'=>$post->ID,
			'title'=>$post->post_title,
			'day'=>(int)get_field('day',$post->ID),
			'type'=>get_field('type',$post->ID),
			'requirement'=>get_field('requirement',$post->ID),
		];

		if ($item->type === 'qa') {
			$question_ids = get_field('questions', $post->ID);
			$questions = array_map(function($id) {
				return get_question($id);
			}, $question_ids);
			$item->questions = $questions;
		}

		return rest_ensure_response($item);

	}

	/**
	 * Submit a answer of a day of 100 day
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function save_100d_answer( $request ) {

		$post = get_post($request->get_param('id'));
		$openid = $request->get_param('openid');
		$user = get_user_by_openid($openid);

		if (!$user) {
			return rest_ensure_response(new WP_Error('user_not_found', '微信用户不存在', array('status' => 401)));
		}

		if (!$post) {
			return rest_ensure_response(new WP_Error('day_not_found', '打卡天不存在', array('status' => 404)));
		}

		$day = (object)[
			'id'=>$post->ID,
			'title'=>$post->post_title,
			'day'=>(int)get_field('day',$post->ID),
			'type'=>get_field('type',$post->ID),
			'requirement'=>get_field('requirement',$post->ID),
		];

		$openid_label = strtoupper(substr($openid,-6));

		$answer_data = $request->get_json_params();

		$user = get_users(['meta_key'=>'openid', 'meta_value'=>$openid])[0];

		$answer_id = wp_insert_post(array(
			'post_title' => "{$openid_label}的打卡：第{$day->day}天",
			'post_type' => '100a',
			'post_status' => 'publish',
			'post_author' => $user->ID
		));

		update_field('day', $day->day, $answer_id);
		update_field('type', $day->type, $answer_id);
		update_field('answer', $answer_data['answer'], $answer_id);

		$answered_days = json_decode(get_user_meta($user->ID, 'answered_days', true) ?: '[]');
		array_push($answered_days,(int)$day->day);
		$answered_days = array_unique($answered_days);

		update_user_meta($user->id, 'answered_days', json_encode($answered_days));

		return rest_ensure_response([
			'id'=>$answer_id,
			'day'=>$day->day,
			'type'=>$day->type,
			'answer'=>$answer_data
		]);

	}

}
