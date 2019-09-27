<?php

class PB_Park_REST_Park_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1/pbpark';
		$this->rest_base = 'shop';
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_shops' ),
			)
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>.+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_shop' ),
			)
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/manager', array(
			array(
				'methods' => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'register_shop_manager' ),
			)
		) );
	}

	/**
	 * Get a list of shops
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_shops( $request ) {

		$parameters = array('post_type' => 'shop', 'posts_per_page' => -1);

		$near = $request->get_param('near');
		$near_lat_long = array();

		if ($near) {
			$near_lat_long = explode(',', $near);
		}

		$posts = get_posts($parameters);

		$shops = array_map(function (WP_Post $shop_post) use($near_lat_long) {

			$valid_coupons = array_map(function(WP_Post $coupon_post) {
				return array(
					'id' => $coupon_post->ID,
					'desc' => get_field('desc', $coupon_post->ID),
					'all_shops' => !!get_field('all_shops', $coupon_post->ID),
					'thumbnailUrl' => get_the_post_thumbnail_url($coupon_post->ID),
					'content' => wpautop($coupon_post->post_content),
					'validFrom' => get_field('valid_from', $coupon_post->ID),
					'validTill' => get_field('valid_till', $coupon_post->ID),
				);
			}, get_posts(array(
				'post_type' => 'coupon',
				'posts_per_page' => -1,
				'meta_query' => array(
					'relation' => 'OR',
					'shops' => array(
						'key' => 'shops',
						'value' => '"' . $shop_post->ID . '"',
						'compare' => 'LIKE'
					),
					'all_shops' => array(
						'key' => 'all_shops',
						'value' => '1'
					),
				)
			)));

			$shop = array(
				'id' => $shop_post->ID,
				'name' => get_the_title($shop_post->ID),
				'address' => get_field('address', $shop_post->ID),
				'phone' => get_field('phone', $shop_post->ID),
				'validCoupons' => $valid_coupons
			);

			if ($near_lat_long) {
				$shop_latitude = get_post_meta($shop_post->ID, 'latitude', true);
				$shop_longitude = get_post_meta($shop_post->ID, 'longitude', true);
				$shop['distance'] = round(haversine_great_circle_distance($near_lat_long[0], $near_lat_long[1], $shop_latitude, $shop_longitude) / 1000, 1);
			}

			return (object) $shop;

		}, $posts);

		if ($near_lat_long) {
			usort($shops, function($a, $b) {
				return $a->distance - $b->distance;
			});
		}

		return rest_ensure_response($shops);
	}

	/**
	 * Get a shop
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_shop( $request ) {

		$id = $request->get_param('id');

		$near = $request->get_param('near');
		$near_lat_long = array();

		if ($near) {
			$near_lat_long = explode(',', $near);
		}

		$post = get_post($id);

		if (!$post) {
			return rest_ensure_response(new WP_Error('shop_not_found', '门店不存在', array('status' => 404)));
		}

		$valid_coupons = array_map(function(WP_Post $coupon_post) {
			return array(
				'id' => $coupon_post->ID,
				'desc' => get_field('desc', $coupon_post->ID),
				'all_shops' => !!get_field('all_shops', $coupon_post->ID),
				'thumbnailUrl' => get_the_post_thumbnail_url($coupon_post->ID),
				'content' => wpautop($coupon_post->post_content),
				'validFrom' => get_field('valid_from', $coupon_post->ID),
				'validTill' => get_field('valid_till', $coupon_post->ID),
			);
		}, get_posts(array(
			'post_type' => 'coupon',
			'posts_per_page' => -1,
			'meta_query' => array(
				'relation' => 'OR',
				'shops' => array(
					'key' => 'shops',
					'value' => '"' . $post->ID . '"',
					'compare' => 'LIKE'
				),
				'all_shops' => array(
					'key' => 'all_shops',
					'value' => '1'
				),
			)
		)));

		$shop = array(
			'id' => $post->ID,
			'name' => get_the_title($post->ID),
			'address' => get_field('address', $post->ID),
			'phone' => get_field('phone', $post->ID),
			'validCoupons' => $valid_coupons
		);

		if ($near_lat_long) {
			$shop_latitude = get_post_meta($post->ID, 'latitude', true);
			$shop_longitude = get_post_meta($post->ID, 'longitude', true);
			$shop['distance'] = round(haversine_great_circle_distance($near_lat_long[0], $near_lat_long[1], $shop_latitude, $shop_longitude) / 1000, 1);
		}

		return rest_ensure_response($shop);
	}

	/**
	 * Register shop manager
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function register_shop_manager( $request ) {
		$body = $request->get_json_params();

		$openid = $body['openid'];
		$shop_id = $body['shopId'];
		$nickname = $body['nickname'];
		$display_name = $body['displayName'];

		if (!$openid || !$shop_id || !$nickname) {
			return rest_ensure_response(new WP_Error('wrong_argument', '参数错误', array('status' => 404)));
		}

		// check if user exists
		$user_object = get_users(array('meta_key' => 'openid', 'meta_value' => $openid))[0];

		if (!$user_object) {
			$user_id = wp_insert_user(array(
				'user_login' => 'manager-' . substr($openid, -4),
				'user_pass' => $openid,
				'display_name' => $display_name,
				'first_name' => $display_name,
				'nickname' => $nickname,
				'role' => 'manager'
			));

			if (is_wp_error($user_id)) {
				return rest_ensure_response($user_id);
			}

			add_user_meta($user_id, 'openid', $openid);
			update_field('shop', $shop_id, 'user_' . $user_id);
			$user_object = get_user_by('ID', $user_id);
		}

		$user = array(
			'id' => $user_object->ID,
			'name' => $user_object->display_name,
			'roles' => $user_object->roles
		);

		$manage_shop_post_id = get_user_meta($user_object->ID, 'shop', true);

		if ($manage_shop_post_id) {
			$manage_shop_post = get_post($manage_shop_post_id);
			$user['manageShop'] = (object) array(
				'id' => $manage_shop_post->ID,
				'name' => $manage_shop_post->post_title
			);
		}

		return rest_ensure_response($user);	}

}
