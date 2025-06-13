<?php
	$bookerauth = Db::getInstance()->execute(
		"CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."booker_auth`(
		`id_auth` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`id_booker` int(10) unsigned NOT NULL,		
		`date_from` DATETIME NOT NULL,		
		`date_to` DATETIME NOT NULL,
		`active` tinyint(1) unsigned NOT NULL DEFAULT '1',
		`date_add` DATETIME NOT NULL,
		`date_upd` DATETIME NOT NULL,
		PRIMARY KEY (`id_auth`),
		INDEX `idx_booker` (`id_booker`),
		INDEX `idx_date_from` (`date_from`),
		INDEX `idx_date_to` (`date_to`),
		INDEX `idx_active` (`active`)
		) ENGINE=InnoDB DEFAULT CHARSET=UTF8;"
	);	
?>