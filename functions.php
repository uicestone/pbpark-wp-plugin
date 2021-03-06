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

function generate_weapp_qrcode($point_slug) {
	$wx = new WeixinAPI(true);

	$key = "qrcode-point-{$point_slug}";
	$query = "?point={$point_slug}";

	return $wx->app_create_qr_code($key, '/pages/park/index' . $query, 1280);
}

function get_point($point_id, $with_questions = false, $user = null) {

	if (is_a($point_id, 'WP_Post')) {
		$point_post = $point_id;
	} else {
		$point_post = get_post($point_id);
	}

	$thumbnail_url = get_the_post_thumbnail_url($point_post->ID, 'full');

	if (defined('CDN_URL') && $thumbnail_url) {
		$thumbnail_url = preg_replace('/' . preg_quote(site_url(), '/') . '\//', constant('CDN_URL'), $thumbnail_url);
	}

	$point = array(
		'id' => $point_post->ID,
		'name' => $point_post->post_title,
		'slug' => $point_post->post_name,
		// 'content' => preg_replace('/(\r?\n){2,}/', "\n", $point_post->post_content),
		'content' => wpautop($point_post->post_content),
		'thumbnail_url' => $thumbnail_url,
		'latitude' => get_field('latitude', $point_post->ID),
		'longitude' => get_field('longitude', $point_post->ID)
	);

	if ($user) {
		// get user score of this point
		$park_id = get_post_meta($point_post->ID, 'park', true);
		$park_post = get_post($park_id);
		$park_slug = $park_post->post_name;
		$quiz_data = json_decode(get_user_meta($user->id, 'quiz_data_' . $park_slug, true));
		if ($quiz_data) {
			$points_quiz_result_match = array_filter($quiz_data, function($point_quiz_data) use($point) {
				return $point_quiz_data->point === $point['slug'];
			});
			if ($point_quiz_result = array_pop($points_quiz_result_match)) {
				$point['duration'] = $point_quiz_result->duration;
				$point['correct'] = $point_quiz_result->correct;
			}
		}
	}

	if ($with_questions) {
		$point['questions'] = array_map('get_question', get_field('questions', $point_post->ID));
	}

	return (object) $point;
}

function get_question($question_id) {
	$question_post = get_post($question_id);

	$optionsAreImages = get_field('options_are_images', $question_id);

	if ($optionsAreImages) {
		$options = array_map('wp_get_attachment_url', explode(',', get_field('image_options', $question_id)));
	} else {
		$options = explode("\r\n", get_field('options', $question_id));
	}

	$question = array(
		'id' => $question_id,
		'title' => $question_post->post_title,
		'optionsAreImages' => !!$optionsAreImages,
		'options' => $options,
		'trueOption' => get_field('true_option', $question_id) - 1
	);

	return (object) $question;
}