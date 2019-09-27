<?php

WP_CLI::add_command('pbpark', 'PB_Park_CLI');

/**
 * Filter spam comments.
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

}
