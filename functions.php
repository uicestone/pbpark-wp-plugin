<?php

/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversine_great_circle_distance(
	$latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
{
	// convert from degrees to radians
	$latFrom = deg2rad($latitudeFrom);
	$lonFrom = deg2rad($longitudeFrom);
	$latTo = deg2rad($latitudeTo);
	$lonTo = deg2rad($longitudeTo);

	$latDelta = $latTo - $latFrom;
	$lonDelta = $lonTo - $lonFrom;

	$angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
			cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
	return $angle * $earthRadius;
}

/**
 * GCJ02 转换为 WGS84
 * @param lng
 * @param lat
 * @returns {*[]}
 */
function gcj02_to_wgs84($lng, $lat) {
	$a = 6378245.0;
	$ee = 0.00669342162296594323;
	$dlat = transformlat($lng - 105.0, $lat - 35.0);
	$dlng = transformlng($lng - 105.0, $lat - 35.0);
	$radlat = $lat / 180.0 * pi();
	$magic = sin($radlat);
	$magic = 1 - $ee * $magic * $magic;
	$sqrtmagic = sqrt($magic);
	$dlat = ($dlat * 180.0) / (($a * (1 - $ee)) / ($magic * $sqrtmagic) * pi());
	$dlng = ($dlng * 180.0) / ($a / $sqrtmagic * cos($radlat) * pi());
	$mglat = $lat + $dlat;
	$mglng = $lng + $dlng;
	return [$lng * 2 - $mglng, $lat * 2 - $mglat];
};

function transformlat($lng, $lat) {
	$ret = -100.0 + 2.0 * $lng + 3.0 * $lat + 0.2 * $lat * $lat + 0.1 * $lng * $lat + 0.2 * sqrt(abs($lng));
	$ret += (20.0 * sin(6.0 * $lng * pi()) + 20.0 * sin(2.0 * $lng * pi())) * 2.0 / 3.0;
	$ret += (20.0 * sin($lat * pi()) + 40.0 * sin($lat / 3.0 * pi())) * 2.0 / 3.0;
	$ret += (160.0 * sin($lat / 12.0 * pi()) + 320 * sin($lat * pi() / 30.0)) * 2.0 / 3.0;
	return $ret;
};

function transformlng($lng, $lat) {
	$ret = 300.0 + $lng + 2.0 * $lat + 0.1 * $lng * $lng + 0.1 * $lng * $lat + 0.1 * sqrt(abs($lng));
	$ret += (20.0 * sin(6.0 * $lng * pi()) + 20.0 * sin(2.0 * $lng * pi())) * 2.0 / 3.0;
	$ret += (20.0 * sin($lng * pi()) + 40.0 * sin($lng / 3.0 * pi())) * 2.0 / 3.0;
	$ret += (150.0 * sin($lng / 12.0 * pi()) + 300.0 * sin($lng / 30.0 * pi())) * 2.0 / 3.0;
	return $ret;
};

function generate_weapp_qrcode($type, $id) {
	$wx = new WeixinAPI(true);

	$key = "qrcode-{$type}-{$id}";
	$query = "?{$type}={$id}";

	return $wx->app_create_qr_code($key, '/pages/index/index' . $query, 1280);
}

function get_code($code_id, $coupon_id = null) {
	if (!$coupon_id) {
		$coupon_id = get_post_meta($code_id, 'coupon', true);
	}

	$code_post = get_post($code_id);
	$coupon_post = get_post($coupon_id);

	$code = array(
		'id' => $code_id,
		'codeString' => $code_post->post_name,
		'couponId' => $coupon_id,
		// 'expires_at' => '',
		'coupon' => get_coupon($coupon_id),
		'customerNickname' => get_post_meta($code_id, 'customer_nickname', true)
	);

	$used = get_field('used', $code_id);

	if ($used) {
		$used_shop_post = get_post(get_post_meta($code_id, 'used_shop', true));
		$scanned_manager_user = get_field('scanned_manager', $code_id);
		$code = array_merge($code, array(
			'used' => !!$used,
			'usedShop' => array(
				'id' => $used_shop_post->ID,
				'name' => get_the_title($used_shop_post)
			),
			'usedTime' => get_field('used_time', $code_id),
			'managerName' => $scanned_manager_user->display_name
		));
	}

	return (object) $code;
}

function get_coupon($coupon_id) {
	$coupon_post = get_post($coupon_id);

	$coupon = array(
		'id' => $coupon_id,
		'desc' => get_field('desc', $coupon_id),
		'shops' => array_map(function($shop_post) {
			return array(
				'id' => $shop_post->ID,
				'name' => get_the_title($shop_post->ID),
				'address' => get_field('address', $shop_post->ID),
				'phone' => get_field('phone', $shop_post->ID),
			);
		}, get_field('shops', $coupon_id) ?: array()),
		'allShops' => !!get_field('all_shops', $coupon_id),
		'thumbnailUrl' => get_the_post_thumbnail_url($coupon_id),
		'content' => wpautop($coupon_post->post_content),
		'validFrom' => get_field('valid_from', $coupon_id),
		'validTill' => get_field('valid_till', $coupon_id),
	);

	return (object) $coupon;
}