<?php

date_default_timezone_set('UTC');

ini_set('display_errors', '1');
error_reporting(E_ALL);

/* =========== Database Configuration ========== */

define('DB_HOST',   'localhost');
define('DB_USER',   'phpTrackView');
define('DB_PASS',   'phpTrackView');
define('DB_NAME',   'phpTrackView');
define('DB_PREFIX', 'ptv_');

define('DB_TABLE_TRACKS',	DB_PREFIX . 'tracks');


/* =========== Website Configuration ========== */

$defaultTitle = 'phpTrackView';
$defaultFooter = date('Y') . ' phpTrackView 1.0';

/* =========== Directory Configuration ========== */
define('DATA_DIR', 'files');
