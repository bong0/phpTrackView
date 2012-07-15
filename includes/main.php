<?php

function get_execution_time() {
    static $start = null;
	return $start === null ? $start = microtime(true) : microtime(true) - $start;
}
get_execution_time();

/*
	main include file
*/

require_once 'includes/config.php';

require_once 'includes/lib/model.php';
require_once 'includes/models/track.model.php';
require_once 'includes/controllers/home.controller.php';
require_once 'includes/controllers/track.controller.php';
require_once 'includes/controllers/upload.controller.php';

require_once 'includes/helpers.php';
require_once 'includes/security.php';
require_once 'includes/lib/bcmath.php';
require_once 'includes/lib/database.php';
require_once 'includes/lib/database_bootstrap.php';
require_once 'includes/lib/dist2points.php';
require_once 'includes/lib/parser.php';
require_once 'includes/lib/upload.php';

