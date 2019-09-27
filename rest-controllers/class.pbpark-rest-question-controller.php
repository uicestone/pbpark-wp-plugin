<?php

class PB_Park_REST_Question_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1/pbpark';
		$this->rest_base = 'code';
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_codes' ),
			), array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'post_code' ),
			), array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'patch_code' ),
			), array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_code' ),
			)
		) );

	}

	/**
	 * Get a list of codes
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_codes( $request ) {

		$openid = $request->get_param('openid');
		$shop_id = $request->get_param('shopId');

		if (!$openid) {
			return rest_ensure_response(new WP_Error('missing_openid', 'Missing openid.', array('status' => 400)));
		}

		$manager = get_users(array('meta_key' => 'openid', 'meta_value' => $openid))[0];

		$parameters = array('post_type' => 'code', 'posts_per_page' => -1, 'post_status' => 'any');

		if ($manager && $shop_id) {
			$manage_shop_post = get_field('shop', 'user_' . $manager->ID);
			if (!$manage_shop_post) {
				return rest_ensure_response(new WP_Error('manage_shop_missing', '没有绑定门店', array('status' => 403)));
			}
			if ($manage_shop_post->ID != $shop_id) {
				return rest_ensure_response(new WP_Error('manage_shop_mismatch', '绑定门店错误', array('status' => 403)));
			}
			$parameters['meta_query'] = array(
				array('key' => 'used_shop', 'value' => $manage_shop_post->ID)
			);
			$parameters['posts_per_page'] = 50;
		} else {
			$parameters['meta_query'] = array(
				array('key' => 'openid', 'value' => $openid)
			);
		}

		$posts = get_posts($parameters);

		$codes = array_map(function (WP_Post $post) {
			return get_code($post->ID);
		}, $posts);

		return rest_ensure_response($codes);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function post_code( $request ) {

		$body = $request->get_json_params();

		// validate user
		if (!array_key_exists('openid', $body)) {
			return rest_ensure_response(new WP_Error('openid_missing', 'Missing openid.', array('status' => 400)));
		}

		$openid = $body['openid'];
		$customer_nickname = $body['customerNickname'];

		if (!array_key_exists('couponIds', $body) || !is_array($body['couponIds'])) {
			return rest_ensure_response(new WP_Error('coupon_missing', 'Missing coupon.', array('status' => 400)));
		}

		$codes = array();

		foreach ($body['couponIds'] as $coupon_id) {

			// TODO validate coupon

			$code_string = crc32(sha1($openid . ',' . $coupon_id));

			$code_post_exists = get_page_by_path($code_string, 'OBJECT', 'code');

			if ($code_post_exists && !constant('WP_DEBUG')) {
				$code_exists = get_code($code_post_exists->ID);
				$code_exists->claimed = true;
				$codes[] = $code_exists;
				continue;
			}

			$code_id = wp_insert_post(array(
				'post_type' => 'code',
				'post_status' => 'private',
				'post_title' => $code_string
			));

			add_post_meta($code_id, 'coupon', $coupon_id);
			add_post_meta($code_id, 'openid', $openid);
			add_post_meta($code_id, 'customer_nickname', $customer_nickname);

			$code = get_code($code_id, $coupon_id);

			$codes[] = $code;
		}

		return rest_ensure_response($codes);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function patch_code( $request ) {

		$body = $request->get_json_params();

		$code_string = $body['codeString'];

		$code_post = get_page_by_path($body['codeString'], 'OBJECT', 'code');

		if (!$code_post) {
			return rest_ensure_response(new WP_Error('code_not_found', '券码不存在', array('status' => 404)));
		}

		$openid = $body['openid'];

		if (!$openid) {
			return rest_ensure_response(new WP_Error('openid_missing', 'Missing openid.', array('status' => 401)));
		}

		$user = get_users(array('meta_key' => 'openid', 'meta_value' => $openid))[0];

		if (!$user) {
			return rest_ensure_response(new WP_Error('scan_not_allowed', 'User is not allowed to scan.', array('status' => 403)));
		}

		$shop_post = get_field('shop', 'user_' . $user->ID);

		if (!$shop_post) {
			return rest_ensure_response(new WP_Error('manage_shop_missing', '您尚未绑定核销门店', array('status' => 403)));
		}

		$used = get_field('used', $code_post);

		if (!$used) {
			update_field('used', 1, $code_post->ID);
			update_field('used_shop', $shop_post->ID, $code_post->ID);
			update_field('used_time', time(), $code_post->ID);
			update_field('scanned_manager', $user, $code_post->ID);
		}

		$code = get_code($code_post->ID);

		$code->wasUsed = !!$used;

		return rest_ensure_response($code);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function delete_code( $request ) {

	}

}
