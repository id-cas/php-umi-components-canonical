<?php

use UmiCms\Service;


class canonical extends def_module {

	private $oConnection;

	/**
	 * Конструктор
	 */
	public function __construct() {
		parent::__construct();

//		$oDomain = cmsController::getInstance()->getCurrentDomain();
//		$sDomain = $oDomain->getHost();
//		if(!in_array(base64_encode($sDomain), ['ZnJhdWthdGlhLnJ1'])){
//			throw new Exception('Invalid domain');
//		}

		if (Service::Request()->isAdmin()) {
			$this->includeAdminClasses();
		}

		$this->includeCommonClasses();

		$this->oConnection = ConnectionPool::getInstance()->getConnection();
	}

	/**
	 * Подключает классы функционала административной панели
	 * @return $this
	 */
	public function includeAdminClasses() {
		$this->__loadLib('admin.php');
		$this->__implement('CanonicalAdmin');

		$this->loadAdminExtension();

		$this->__loadLib('customAdmin.php');
		$this->__implement('canonicalCustomAdmin', true);

		return $this;
	}


	/**
	 * Подключает общие классы функционала
	 * @return $this
	 */
	public function includeCommonClasses() {
		/**
		 * @var canonical $this
		 */
		$this->__loadLib("macros.php");
		$this->__implement("CanonicalMacros");

		$this->loadSiteExtension();

		$this->__loadLib('handlers.php');
		$this->__implement('CanonicalHandlers');

		$this->__loadLib("customMacros.php");
		$this->__implement("CanonicalCustomMacros", true);

		$this->loadCommonExtension();
		$this->loadTemplateCustoms();
	}




	public function getCanonicalByPath($sNonCanonicalPath, $iDomainId) {
		$oUmiHierarchy = umiHierarchy::getInstance();

		// Получим идентификатор страницы по неканоническому адресу
		if(!$iPageId = $oUmiHierarchy->getIdByPath($sNonCanonicalPath)){
			return false;
		}


		return $this->getCanonicalPathByPageId($iPageId, $iDomainId);
	}


	public function getCanonicalPathByPageId($iPageId, $iDomainId) {
		// Получим канонический адрес по идентификатору страницы
		$sQuery = <<<SQL
SELECT CONCAT('/', cu.l1, '/', cu.l2, '/') AS can_url
FROM cms3_canonical_urls cu
WHERE 1=1
AND cu.page_id = {$iPageId}
AND cu.domain_id = {$iDomainId}
LIMIT 1
SQL;
		$oResult = $this->oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		// Если канонический адрес не найден
		if ($oResult->length() == 0) {
			return false;
		}

		if(!($row = $oResult->fetch())){
			return false;
		}

		return $row['can_url'];
	}


	public function getPageHierarchyLevel($iPageId){
		// Запрос в БД
		$sQuery = <<<SQL
		 SELECT level FROM `cms3_hierarchy_relations` WHERE child_id='{$iPageId}' LIMIT 1;
SQL;
		$oResult = $this->oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		// Если канонический адрес для НАЙДЕН
		if ($oResult->length() > 0) {
			$aRow = $oResult->fetch();
			return intval($aRow['level']);
		}

		return false;
	}


	private function concatLevels($sL1, $sL2){
		return "/{$sL1}/{$sL2}/";
	}



	private function insertCanonicalPath($sL1, $sL2, $iObjId, $iPageId, $iDomainId){
		$sQuery = <<<SQL
		INSERT INTO cms3_canonical_urls (`l1`, `l2`, `obj_id`, `page_id`, `domain_id`) VALUES('{$sL1}', '{$sL2}', {$iObjId}, {$iPageId}, {$iDomainId});
SQL;
		$this->oConnection->queryResult($sQuery);
	}


	/**
	 * Проверяет уникальность канонического адерса
	 *
	 * @param $sL1
	 * @param $sL2
	 * @return bool
	 */
	private function isUniqPath($sL1, $sL2, $iDomainId){
		// Запрос в БД
		$sQuery = <<<SQL
		SELECT l1, l2 FROM cms3_canonical_urls WHERE l1='{$sL1}' AND l2='{$sL2}' AND domain_id={$iDomainId} LIMIT 1;
SQL;
		$oResult = $this->oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		// Если канонический адрес для НАЙДЕН
		if ($oResult->length() === 0) {
			return true;
		}

		return false;
	}


	public function isCanonicalUrl($sUrl, $iDomainId){
		$sUrl = ltrim($sUrl, '/');
		$sUrl = rtrim($sUrl, '/');

		$aUrl = explode('/', $sUrl);

		if(!(is_array($aUrl) && count($aUrl) > 1)){
			return false;
		}

		// Уровень №1
		$aUrlLevel1 = $aUrl[0];

		// Уровень №2
		array_shift($aUrl);
		$aUrlLevel2 = implode('/', $aUrl);

		return !($this->isUniqPath($aUrlLevel1, $aUrlLevel2, $iDomainId));
	}


	private function getUniqLevel2Path($iPageId, $sMethod, $iDomainId){
		// TODO: Сделать нормальную генерацию на основании свойств (рекурсивно)
		$oConfig = mainConfiguration::getInstance();
		$sSeparator = $oConfig->get('seo', 'alt-name-separator');

		$oUmiHierarchy = umiHierarchy::getInstance();
		$oPage = $oUmiHierarchy->getElement($iPageId);
		if(!($oPage instanceof umiHierarchyElement)){
			return implode($sSeparator, [$sMethod, time()]);
		}

		$oObject = $oPage->getObject($iPageId);
		if(!($oObject instanceof umiObject)){
			return implode($sSeparator, [$sMethod, $iPageId, time()]);
		}
		$iObjId = $oObject->getId();

		/** Генерируем путь на основании имении и идентификатора объекта */
		$sObjAlt = $oUmiHierarchy->convertAltName($oObject->getName());
		$sL2Path = implode($sSeparator, [$sObjAlt, $iObjId]);


		// Если ункальности не хватило - добавляем метку времени
		if(!($this->isUniqPath($sMethod, $sL2Path, $iDomainId))){
			return implode($sSeparator, [$sL2Path, time()]);
		}

		return $sL2Path;
	}


	/**
	 * Возвращает домен для каонической записи
	 *
	 * @param $iCanonicalId
	 * @return bool
	 */
	private function getDomain($iCanonicalId){
		// Запрос в БД
		$sQuery = <<<SQL
		SELECT domain_id FROM cms3_canonical_urls WHERE id={$iCanonicalId} LIMIT 1;
SQL;
		$oResult = $this->oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		// Если такая запись не найдена
		if ($oResult->length() === 0) {
			return false;
		}

		$aRow = $oResult->fetch();

		return $aRow['domain_id'];
	}


	public function createCanonicalPath($iPageId, $sMethod, $iDomainId){
		// Проверим, возможно для объекта с $iPageId существует виртуальная копия, а значит канонический
		// адрес должен быть идентичным
		$oUmiHierarchy = umiHierarchy::getInstance();
		$oPage = $oUmiHierarchy->getElement($iPageId);


		if(!($oPage instanceof umiHierarchyElement)){
			return false;
		}

		$oObject = $oPage->getObject($iPageId);
		if(!($oObject instanceof umiObject)){
			return false;
		}
		$iObjId = $oObject->getId();


		// Запрос в БД
		$sQuery = <<<SQL
		SELECT l1, l2 FROM cms3_canonical_urls WHERE obj_id={$iObjId} AND domain_id={$iDomainId} LIMIT 1;
SQL;
		$oResult = $this->oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		// Если канонический адрес для виртуальной копии НАЙДЕН
		if ($oResult->length() != 0) {
			$row = $oResult->fetch();

			$this->insertCanonicalPath($row['l1'], $row['l2'], $iObjId, $iPageId, $iDomainId);
			return $this->concatLevels($row['l1'], $row['l2']);
		}



		// Генерируем новый уникальный канонический адрес
		$sPathLevel1 = ($sMethod === 'category') ? 'categories' : 'product';
		$sPathLevel2 = $this->getUniqLevel2Path($iPageId, $sMethod, $iDomainId);

		$this->insertCanonicalPath($sPathLevel1, $sPathLevel2, $iObjId, $iPageId, $iDomainId);
		return $this->concatLevels($sPathLevel1, $sPathLevel2);
	}


	public function modifyCanonicalPath($iCanonicalId, $sL1, $sL2){
		$sL1 = ltrim($sL1, '/');
		$sL1 = rtrim($sL1, '/');

		// Первый уровень всегда единичной длины
		$aL1 = explode('/', $sL1);
		if(count($aL1) > 1){
			return false;
		}


		$sL2 = ltrim($sL2, '/');
		$sL2 = rtrim($sL2, '/');


		// Узнаем домен канонической записи
		if(!($iDomainId = $this->getDomain($iCanonicalId))){
			// throw new publicAdminException(getLabel('error-canonical-not-found'));
			return false;
		}


		// Проверим уникальность
		if(!$this->isUniqPath($sL1, $sL2, $iDomainId)){
			// throw new publicAdminException(getLabel('error-canonical-not-uniq'));
			return false;
		}

		// Запрос в БД
		$sQuery = <<<SQL
		UPDATE cms3_canonical_urls SET l1 = '{$sL1}', l2 = '{$sL2}' WHERE id={$iCanonicalId};
SQL;
		$this->oConnection->queryResult($sQuery);
		return true;
	}

	/**
	 * Возвращает канонический адрес страницы
	 *
	 * @param $iPageId
	 * @return bool|null|string
	 */
	public function getCanonicalLink($iPageId){
		$oHierarchy = umiHierarchy::getInstance();
		$oPage = $oHierarchy->getElement($iPageId);

		if(!($oPage instanceof umiHierarchyElement)){
			return false;
		}


		/** Функционал модуля "Канонические адреса" */
		// Если страница находится в списке канонических адресов (для разделов и товаров каталога)
		$oDomain = Service::DomainDetector()->detect();
		$sCanonicalLink = $this->getCanonicalPathByPageId($iPageId, $oDomain->getId());
		if($sCanonicalLink !== false){
			return $oDomain->getProtocol() . '://' . $oDomain->getHost() . $sCanonicalLink;
		}

		/** Базовый функционал */
		// Если текущий $iPageId является идентификатором исходного объекта
		$oOriginalPage = $oPage;
		if(!$oPage->isOriginal()){
			$oOriginalPage = $oHierarchy->getOriginalPage($oPage->getObjectId());
		}

		// Если оказалось, что мы видим виртуальную копию страницы
		$sOriginalPageLink = null;
		if ($oOriginalPage instanceof iUmiHierarchyElement) {
			$bOldForceAbsolutePath = $oHierarchy->forceAbsolutePath();
			$sOriginalPageLink = $oHierarchy->getPathById($oOriginalPage->getId());
			$oHierarchy->forceAbsolutePath($bOldForceAbsolutePath);
		}

		return $sOriginalPageLink;
	}
};
