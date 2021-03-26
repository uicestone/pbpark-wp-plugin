<?php

class PB_Park_Admin {

	public static function init() {
		self::register_post_types();
		self::manage_admin_columns();
	}


	protected static function register_post_types() {

		// register_taxonomy_for_object_type('category', 'attachment');
		// register_taxonomy_for_object_type('post_tag', 'attachment');

		// add_post_type_support('attachment', '');

		register_post_type('park', array(
			'label' => '公园',
			'labels' => array(
				'all_items' => '所有公园',
				'add_new' => '添加公园',
				'add_new_item' => '新公园',
				'not_found' => '未找到公园'
			),
			'public' => true,
			'supports' => array('title', 'editor'),
			'menu_icon' => 'dashicons-store',
			'has_archive' => true
		));

		add_filter( 'acf/update_value/name=points', function($value, $post_id, $field ) {
			foreach ( $value as $point_id ) {
				update_post_meta($point_id, 'park', $post_id);
			}
			return $value;
		}, 10, 3 );

		register_post_type('point', array(
			'label' => '答题点',
			'labels' => array(
				'all_items' => '所有答题点',
				'add_new' => '添加答题点',
				'add_new_item' => '新答题点',
				'not_found' => '未找到答题点'
			),
			'public' => true,
			'supports' => array('title', 'editor', 'thumbnail'),
			'menu_icon' => 'dashicons-megaphone',
			'has_archive' => true
		));

		add_action( 'pre_get_posts', function ( $query ) {
			if ( $query->get('post_type') !== 'point' ) {
				return;
			}

			$orderby = $query->get('orderby');
			$order = $query->get('order');

			if (!$orderby && !$order) {
				$query->set( 'order', 'asc' );
				$query->set( 'orderby', 'post_name' );
			}

		}, 1);

		add_action( 'add_meta_boxes', function($post_type, $post) {
			if ($post_type !== 'point') return;
			add_meta_box(
				'qr-code',
				__( '专用小程序二维码' ),
				function() use ($post){
					?>
					<img src="<?=generate_weapp_qrcode($post->post_name)?>" style="width:100%">
					<?php
				},
				null,
				'side',
				'default'
			);
		}, 10, 2 );

		register_post_type('question', array(
			'label' => '题目',
			'labels' => array(
				'all_items' => '所有题目',
				'add_new' => '添加题目',
				'add_new_item' => '新题目',
				'not_found' => '未找到题目'
			),
			'public' => true,
			'supports' => array('title'),
			'menu_icon' => 'dashicons-admin-page'
		));

		register_post_type('100d', array(
			'label' => '100天',
			'labels' => array(
				'all_items' => '所有天',
				'add_new' => '添加一天',
				'add_new_item' => '新天',
				'not_found' => '未找到天'
			),
			'public' => true,
			'supports' => array('title'),
			'menu_icon' => 'dashicons-buddicons-community'
		));

		register_post_type('100a', array(
			'label' => '100天打卡',
			'labels' => array(
				'all_items' => '所有打卡',
				'add_new' => '添加打卡',
				'add_new_item' => '新打卡',
				'not_found' => '未找到打卡'
			),
			'public' => true,
			'supports' => array('title'),
			'menu_icon' => 'dashicons-editor-spellcheck'
		));
	}

	protected static function manage_admin_columns() {

		add_filter('manage_park_posts_columns', function ($columns) {
			$columns['phone'] = '电话';
			$columns['address'] = '地址';
			return $columns;
		});

		add_action('manage_park_posts_custom_column', function ($column_name) {
			global $post;
			switch ($column_name) {
				case 'phone' :
					echo get_post_meta($post->ID, 'phone', true);
					break;
				case 'address' :
					echo get_post_meta($post->ID, 'address', true);
					break;
				default;
			}
		});

		add_filter('manage_point_posts_columns', function ($columns) {
			$columns['desc'] = '描述';
			$columns['park'] = '所属公园';
			$columns['location'] = '坐标';
			return $columns;
		});

		add_action('manage_point_posts_custom_column', function ($column_name) {
			global $post;
			switch ($column_name) {
				case 'desc' :
					echo mb_substr($post->post_content, 0, 40) . '…';
					break;
				case 'park':
					$park_id = get_post_meta($post->ID, 'park', true);
					echo '<a href="' . admin_url('post.php?post=' . $park_id . '&action=edit') . '">' . get_post($park_id)->post_title . '</a>';
					break;
				case 'location' :
					echo get_field('latitude', $post->ID);
					echo '<br>';
					echo get_field('longitude', $post->ID);
					break;
				default;
			}
		});

		add_filter('manage_question_posts_columns', function ($columns) {
			unset($columns['date']);
			return $columns;
		});

		add_action('manage_question_posts_custom_column', function ($column_name) {
			global $post;
			switch ($column_name) {
				default;
			}
		});

		add_filter('manage_100d_posts_columns', function ($columns) {
			$columns['type'] = '类型';
			$columns['requirement'] = '要求';
			unset($columns['date']);
			$columns['date'] = '日期';
			return $columns;
		});

		add_action('manage_100d_posts_custom_column', function ($column_name) {
			global $post;
			switch ($column_name) {
				case 'type' :
					echo get_field('type', $post->ID);
					break;
				case 'requirement' :
					echo get_field('requirement', $post->ID);
					break;
				default;
			}
		});

		add_filter('manage_100a_posts_columns', function ($columns) {
			$columns['type'] = '类型';
			$columns['user'] = '昵称';
			$columns['organization'] = '支部';
			$columns['answer'] = '内容';
			unset($columns['date']);
			$columns['date'] = '日期';
			return $columns;
		});

		add_action('manage_100a_posts_custom_column', function ($column_name) {
			global $post;
			$type = get_field('type', $post->ID);
			$answer = get_field('answer', $post->ID);
			switch ($column_name) {
				case 'type' :
					echo $type;
					break;
				case 'answer' :
					if ($type === 'text') {
						echo $answer;
					} elseif ($type === 'photo') {
						echo '<a href="' . $answer . '" target="_blank"><img width="100px" src="' . $answer . '" /></a>';
					} elseif ($type === 'video') {
						echo '<a href="#" onClick="MyWindow=window.open(\'' . addslashes($answer) . '\',\'MyWindow\',\'width=1280,height=720\'); return false">查看</a>';
					}
					break;
				case 'user' :
					$user = get_user_by('ID', $post->post_author);
					echo $user->display_name;
					break;
				case 'organization' :
					echo get_user_meta($post->post_author, 'organization', true);
					break;
				default;
			}
		});

		add_filter( 'manage_users_columns', function ( $column ) {
			unset($column['email']);
			unset($column['posts']);
			$column['registered'] = '注册时间';
			return $column;
		} );


		add_filter( 'manage_users_custom_column', function ( $val, $column_name, $user_id ) {
			switch($column_name) {
			  case 'registered':
				  $user = get_userdata($user_id);
				  return get_date_from_gmt($user->user_registered);
			  default:
			}
		}, 10, 3 );

		add_filter('manage_users_sortable_columns', function($sortable_columns){
			$sortable_columns['reg_time'] = 'reg_time';
			return $sortable_columns;
		});

		add_action('pre_user_query', function($query){
			if(!isset($_REQUEST['orderby']) || $_REQUEST['orderby']=='reg_time' ){
				if( !in_array($_REQUEST['order'],array('asc','desc')) ){
					$_REQUEST['order'] = 'desc';
				}
				$query->query_orderby = "ORDER BY user_registered ".$_REQUEST['order']."";
			}
		});

		/**
		 * Convert values of ACF core date time pickers from Y-m-d H:i:s to timestamp
		 * @param  string $value   unmodified value
		 * @param  int    $post_id post ID
		 * @param  object $field   field object
		 * @return string          modified value
		 */
		function acf_save_as_timestamp( $value, $post_id, $field  ) {
			if( $value ) {
				$value = strtotime( $value ) - get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
			}

			return $value;
		}

		add_filter( 'acf/update_value/type=date_time_picker', 'acf_save_as_timestamp', 10, 3 );

		/**
		 * Convert values of ACF core date time pickers from timestamp to Y-m-d H:i:s
		 * @param  string $value   unmodified value
		 * @param  int    $post_id post ID
		 * @param  object $field   field object
		 * @return string          modified value
		 */
		function acf_load_as_timestamp( $value, $post_id, $field  ) {
			if( $value ) {
				$value = date( 'Y-m-d H:i:s', (int)$value + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			}

			return $value;
		}

		add_filter( 'acf/load_value/type=date_time_picker', 'acf_load_as_timestamp', 10, 3 );

		add_filter ('sanitize_user', function ($username, $raw_username, $strict) {
			$username = wp_strip_all_tags( $raw_username );
			$username = remove_accents( $username );
			// Kill octets
			$username = preg_replace( '|%([a-fA-F0-9][a-fA-F0-9])|', '', $username );
			$username = preg_replace( '/&.+?;/', '', $username ); // Kill entities

			// 网上很多教程都是直接将$strict赋值false，
			// 这样会绕过字符串检查，留下隐患
			if ($strict) {
				$username = preg_replace ('|[^a-z\p{Han}0-9 _.\-@]|iu', '', $username);
			}

			$username = trim( $username );
			// Consolidate contiguous whitespace
			$username = preg_replace( '|\s+|', ' ', $username );

			return $username;
		}, 10, 3);

		add_action('restrict_manage_posts', function() {

			global $current_screen;

			if ($current_screen->post_type == '100a') {
				?>
				第<input type="number" min="1" max="100" name="nthday" value="<?=$_GET['nthday']?>" style="height:30px;width:45px">天
			  	<select name="organization" style="width:130px">
					<option value="" <?php if (!$_GET['organization']){ ?> selected<?php } ?>>全部支部</option>
					<?php foreach (json_decode(get_option('malu_organizations')) as $org_group): ?>
		  			<?php 	foreach ($org_group->units as $unit): ?>
					<option value="<?=$unit?>" <?php if ($_GET['organization'] === $unit){ ?> selected<?php } ?>><?=$unit?></option>
					<?php 	endforeach; ?>
					<?php endforeach; ?>
				</select>
				<?php
			}
		});

		add_filter('parse_query', function ($query) {
			if (is_admin() && $query->query['post_type'] === '100a') {
				$qv = &$query->query_vars;
				$qv['meta_query'] = array();
				if ($_GET['nthday']) {
					$qv['meta_query'][] = array(
						'key' => 'day',
						'value' => $_GET['nthday']
					);
				}
				if ($_GET['organization']) {
					$qv['meta_query'][] = array(
						'key' => 'organization',
						'value' => $_GET['organization']
					);
				}
			}
		});

	}

}
