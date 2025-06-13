<?php
	$booker = Db::getInstance()->execute(
		"CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."booker`(
		`id_booker` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`name` VARCHAR(255) NOT NULL,
		`active` tinyint(1) unsigned NOT NULL DEFAULT '1',		
		`google_account` VARCHAR(255) DEFAULT NULL,
		`date_add` DATETIME NOT NULL,
		`date_upd` DATETIME NOT NULL,
		PRIMARY KEY (`id_booker`),
		INDEX `idx_active` (`active`)
		) ENGINE=InnoDB DEFAULT CHARSET=UTF8;"
	);
	
	$booker_lang = Db::getInstance()->execute(
		"CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."booker_lang`(
		`id_booker` int(10) unsigned NOT NULL,
		`id_lang` int(10) unsigned NOT NULL,		
		`description` TEXT DEFAULT NULL,
		PRIMARY KEY (`id_booker`, `id_lang`),
		INDEX `idx_booker` (`id_booker`),
		INDEX `idx_lang` (`id_lang`)
		) ENGINE=InnoDB DEFAULT CHARSET=UTF8;"
	);	
?>
