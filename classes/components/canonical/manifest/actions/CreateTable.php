<?php

	namespace UmiCms\Manifest\Canonical;


	/** Команда создания таблицы канонических URL-ов в БД */
	class CreateTableAction extends \Action {

		/** @inheritdoc */
		public function __construct($name, array $params = []) {
			parent::__construct($name, $params);
			$this->oConnection = ConnectionPool::getInstance()->getConnection();
		}

		/** @inheritdoc */
		public function execute() {
			$sQuery = <<<SQL
DROP TABLE IF EXISTS cms3_canonical_urls;
SQL;
			$this->oConnection->queryResult($sQuery);
			$this->oConnection->commitTransaction();


//			$sQuery = <<<SQL
//CREATE TABLE cms3_canonical_urls(
//id int(11) NOT NULL AUTO_INCREMENT,
//l1 varchar(64) DEFAULT NULL,
//l2 text,
//obj_id int(10) unsigned NOT NULL,
//page_id int(10) unsigned NOT NULL,
//PRIMARY KEY (id),
//UNIQUE KEY page_id (page_id),
//KEY fk_canonical_urls_page_id (page_id)
//);
//SQL;
//			$this->oConnection->queryResult($sQuery);
//			$this->oConnection->commitTransaction();

			return $this;
		}

		/** @inheritdoc */
		public function rollback() {
			return $this;
		}


	}
