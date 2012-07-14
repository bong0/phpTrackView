<?php

$tables = array(
	DB_TABLE_TRACKS => array(
		'mysql' =>
			'CREATE TABLE IF NOT EXISTS `' . DB_TABLE_TRACKS . '` (
			  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			  `title` varchar(255) NOT NULL,
			  `filename` text NOT NULL,
			  `created` datetime NOT NULL,
			  `modified` datetime NOT NULL,
			  `deleted` tinyint(1) NOT NULL DEFAULT \'0\',
			  PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8',
		'sqlite' => 
			'CREATE TABLE IF NOT EXISTS "' . DB_TABLE_TRACKS .  '" (
			  "id" INTEGER PRIMARY KEY ,
			  "title" varchar(255) NOT NULL,
			  "filename" text NOT NULL,
			  "created" datetime NOT NULL,
			  "modified" datetime NOT NULL,
			  "deleted" tinyint(1) NOT NULL DEFAULT \'0\'
			)',
	),
);

foreach ($tables as $table) {
	if (empty($table[DB_TYPE])) continue;
	$db->exec($table[DB_TYPE]);
}

