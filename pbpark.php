<?php
/*
Plugin Name: 马陆党建地图
Description: 提供马陆党建地图后台管理和RESTful API数据接口
Version: 1.0.0
Author: Uice Lu
Author URI: https://cecilia.uice.lu
License: GPLv2 or later
Text Domain: pbpark
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'PB_Park_VERSION', '0.1.0' );
define( 'PB_Park__MINIMUM_WP_VERSION', '4.8' );
define( 'PB_Park__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, array( 'PB_Park', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'PB_Park', 'plugin_deactivation' ) );

require_once( PB_Park__PLUGIN_DIR . 'class.pbpark.php' );
require_once( PB_Park__PLUGIN_DIR . 'class.pbpark-rest-api.php' );
require_once( PB_Park__PLUGIN_DIR . 'functions.php' );

add_action( 'rest_api_init', array( 'PB_Park_REST_API', 'init' ) );

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once( PB_Park__PLUGIN_DIR . 'class.pbpark-admin.php' );
	add_action( 'init', array( 'PB_Park_Admin', 'init' ) );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( PB_Park__PLUGIN_DIR . 'class.pbpark-cli.php' );
}
