<?php

class PB_Park_REST_Post_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'v1/pbpark';
		$this->rest_base = 'posts';
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_posts' ),
			)
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>.+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_post' ),
			)
		) );
	}

	/**
	 * Get a list of posts
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_posts( $request ) {

		$parameter_mappings = array (
			'category' => 'category_name',
			'page' => 'paged',
			'limit' => 'posts_per_page',
			'order' => 'order',
			'orderby' => 'orderby'
		);

		$parameters = array();

		foreach ($parameter_mappings as $param => $mapped_param) {
			if ($request->get_param($param) != null) {
				$parameters[$mapped_param] = $request->get_param($param);
			}
		}

		$posts = get_posts($parameters);

		$parameters_all = $parameters;
		$parameters_all['posts_per_page'] = -1;
		unset($parameters_all['paged']);
		$posts_all = get_posts($parameters_all);
		$posts_all_count = count($posts_all);
		header('X-WP-TotalPages: ' . ceil($posts_all_count / $parameters['posts_per_page']));


		$items = array_map(function (WP_Post $post) {
			$item = self::get_post_data($post);
			return $item;
		}, $posts);

		return rest_ensure_response($items);

	}

	/**
	 * Get single post detail
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public static function get_post( $request ) {

		$id = $request->get_param('id');

		if (is_numeric($id)) {
			$post = get_post($id);
		} else {
			$post = get_page_by_path($id);
		}

		$item = self::get_post_data($post, true);

		return rest_ensure_response($item);

	}

	private static function get_post_data($post, $show_content = false) {
		$author = get_user_by('ID', $post->post_author);
		$categories = get_the_category($post->ID);

		if ($show_content) {
			$content = do_shortcode(wptexturize(wpautop($post->post_content)));
		}
		$posterUrl = get_the_post_thumbnail_url($post->ID) ?: null;

		if (defined('CDN_URL')) {
			$cdn_url = constant('CDN_URL');
			if ($posterUrl) {
				$posterUrl = preg_replace('/' . preg_quote(site_url(), '/') . '\//', $cdn_url, $posterUrl);
			}
			if (isset($content)) {
				$content = preg_replace('/src="' . preg_quote(site_url(), '/') . '\/(.*?)\.(jpg|png|gif|mp3|mp4)"/', 'src="' . $cdn_url . '$1.$2"', $content);
			}
		}

		if (defined('CDN_URL_QPIC')) {
			$cdn_url_qpic = constant('CDN_URL_QPIC');
			if (isset($content)) {
				$content = preg_replace('/https?:\/\/mmbiz.qpic.cn\//', $cdn_url_qpic, $content);
			}
		}

		$item = array(
			'id' => $post->ID,
			'title' => get_the_title($post->ID),
			'excerpt' => get_the_excerpt($post->ID),
			'status' => $post->post_status,
			'slug' => $post->post_name,
			'posterUrl' => $posterUrl,
			'categories' => $categories,
			'author' => (object) array(
				'id' => $author->ID,
				'name' => $author->display_name,
				'roles' => $author->roles
			),
			'createdAt' => $post->post_date,
			'updatedAt' => $post->post_modified
		);

		if (isset($content)) {
			$item['content'] = $content;
		}

		if (in_array('党课精选', array_column($categories, 'cat_name'))) {
			$item['videoListLayout'] = get_field('layout', $post->ID);
			if ($show_content) {
				$item['videos'] = array_map(function($post_id){
					$post = get_post($post_id);
					return static::get_post_data($post, true);
				}, get_field('videos', $post->ID));
			}
		}

		if (in_array('党课视频图文链接', array_column($categories, 'cat_name'))) {
			$item['link'] = get_field('link', $post->ID);
		}

		return (object) $item;
	}

}
