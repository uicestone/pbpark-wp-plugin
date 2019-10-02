<?php

WP_CLI::add_command('pbpark', 'PB_Park_CLI');

/**
 * 党建地图CLI工具
 */
class PB_Park_CLI extends WP_CLI_Command {

	/**
	 * Download all cpc history reviews.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pbpark test
	 *
	 */
	public function test() {
		WP_CLI::line('Test.');
	}

	/**
	 * Find latitude and longitude of stores by address.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pbpark find_stores_lat_long [--all]
	 *
	 */
	public function find_points_lat_long($args, $assoc_args) {

		$shops = get_posts(array('post_type' => 'shop', 'posts_per_page' => -1));

		foreach ($shops as $shop) {
			$address = get_post_meta($shop->ID, 'address', true);

			if (!$address) {
				WP_CLI::line( 'No address found for ' . $shop->ID . ' ' . $shop->post_title);
				continue;
			}

			$latitude = get_post_meta($shop->ID, 'latitude', true);
			$longitude = get_post_meta($shop->ID, 'longitude', true);

			if ($latitude && $longitude && !$assoc_args['all']) {
				continue;
			}

			$result_string = file_get_contents('http://restapi.amap.com/v3/geocode/geo?key=' . constant('AMAP_KEY') . '&address=' . urlencode($address));
			$result = json_decode($result_string);

			if (count($result->geocodes) > 1) {
				WP_CLI::line( 'Multiple results found for ' . $shop->ID . ' ' . $shop->post_title);
				continue;
			}

			if (count($result->geocodes) === 0) {
				WP_CLI::line( 'No results found for ' . $shop->ID . ' ' . $shop->post_title);
				continue;
			}

			$location = $result->geocodes[0]->location;

			$latitude = explode(',', $location)[1];
			$longitude = explode(',', $location)[0];

			$wgs84_result = gcj02_to_wgs84($longitude, $latitude);
			$longitude = $wgs84_result[0];
			$latitude = $wgs84_result[1];

			update_post_meta($shop->ID, 'latitude', $latitude);
			update_post_meta($shop->ID, 'longitude', $longitude);

			WP_CLI::line( 'Location saved ' . $latitude . ',' . $longitude . ' ' . $shop->ID . ' ' . $shop->post_title . '.');
		}
	}

	/**
	 * Import questions
	 */
	public function import_questions() {
		global $wpdb;
		$questions = $wpdb->get_results("SELECT * FROM `questions`");
		$points_questions = [];
		foreach ($questions as $question) {
			$existing_questions = get_posts(['title' => $question->title, 'post_type'=>'question']);

			if ($existing_questions) {
				$question_post_id = $existing_questions[0]->ID;
			} else {
				$question_post_id = wp_insert_post(array(
					'post_title' => $question->title,
					'post_type' => 'question',
					'post_status' => 'publish'
				));
			}
			$options = preg_split('/(\r?\n)+/', $question->options);
			update_field('options', implode("\r\n", $options), $question_post_id);
			update_field('true_option', $question->true_option, $question_post_id);
			if (empty($points_questions[$question->point])) {
				$points_questions[$question->point] = [];
			}
			WP_CLI::line('已导入题目：' . mb_substr($question->title, 0, 20) . '，点位：' . $question->point . '，题目ID：' . $question_post_id);
			array_push($points_questions[$question->point], $question_post_id);
		}
		foreach ($points_questions as $point => $question_ids) {
			$point_post = get_page_by_path($point, OBJECT, 'point');
			update_field('questions', $question_ids, $point_post->ID);
			WP_CLI::line('点位：' . $point_post->post_name . '设置了' . count($question_ids) . '道题目');
		}
	}

}
