<?php

require_once(PB_Park__PLUGIN_DIR . 'rest-controllers/class.pbpark-rest-misc-controller.php');
require_once(PB_Park__PLUGIN_DIR . 'rest-controllers/class.pbpark-rest-park-controller.php');
require_once(PB_Park__PLUGIN_DIR . 'rest-controllers/class.pbpark-rest-post-controller.php');
require_once(PB_Park__PLUGIN_DIR . 'rest-controllers/class.pbpark-rest-term-controller.php');

class PB_Park_REST_API {

	public static function init() {
		(new PB_Park_REST_Misc_Controller())->register_routes();
		(new PB_Park_REST_Park_Controller())->register_routes();
		(new PB_Park_REST_Post_Controller())->register_routes();
		(new PB_Park_REST_Term_Controller())->register_routes();
	}

}