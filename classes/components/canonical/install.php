<?php
	/**
	 * Установщик модуля
	 */

	/**
	 * @var array $INFO реестр модуля
	 */
	$INFO = [
		'name' => 'canonical',
		'config' => '1',
		'default_method' => 'get',
		'default_method_admin' => 'lists'
	];


	/**
	 * @var array $COMPONENTS файлы модуля
	 */
	$COMPONENTS = [
		'./classes/components/canonical/admin.php',
		'./classes/components/canonical/class.php',
		'./classes/components/canonical/customMacros.php',
		'./classes/components/canonical/events.php',
		'./classes/components/canonical/handlers.php',
		'./classes/components/canonical/i18n.php',
		'./classes/components/canonical/includes.php',
		'./classes/components/canonical/install.php',
		'./classes/components/canonical/lang.php',
		'./classes/components/canonical/macros.php',
		'./classes/components/canonical/permissions.php',

		'./classes/components/canonical/manifest/install.xml',
		'./classes/components/canonical/manifest/actions/CreateTable.php',
	];



	/**
	 * Создадим таблицу для канонических адресов
	 *
	 */
	$oConnection = ConnectionPool::getInstance()->getConnection();

	$sQuery = <<<SQL
	DROP TABLE IF EXISTS cms3_canonical_urls;
SQL;
	$oConnection->queryResult($sQuery);
	$oConnection->commitTransaction();

	$sQuery = <<<SQL
	CREATE TABLE cms3_canonical_urls(
	id int(11) NOT NULL AUTO_INCREMENT,
	l1 varchar(64) DEFAULT NULL,
	l2 text,
	obj_id int(10) unsigned NOT NULL,
	page_id int(10) unsigned NOT NULL,
	domain_id int(10) unsigned NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY page_id (page_id),
	FOREIGN KEY (page_id)
    	REFERENCES cms3_hierarchy (id)
    	ON DELETE CASCADE
	);
SQL;
	$oConnection->queryResult($sQuery);
	$oConnection->commitTransaction();

	// TODO: Заполнить таблицу адресами

	// TODO: Заполнить карту сайта