<?php

use UmiCms\Service;

/** Класс функционала административной панели */
class CanonicalAdmin {

	use baseModuleAdmin;

	/** @var umiRedirects $module */
	public $module;

	/**
	 * Возвращает список редиректов
	 * @return bool
	 * @throws Exception
	 */
	public function lists() {
		$this->setDataType('list');
		$this->setActionType('view');

		if ($this->module->ifNotJsonMode()) {
			$this->setDirectCallError();
			$this->doData();
			return true;
		}


		/** Список канонических адресов */
		$result = [];

		$oConnection = ConnectionPool::getInstance()->getConnection();


		// Узнаем иерархические типы данных для каталога
		$sQuery  = " SELECT id";
		$sQuery .= " FROM cms3_hierarchy_types";
		$sQuery .= " WHERE 1 = 1";
		$sQuery .= " AND name = 'catalog'";

		// Выполним запрос
		$oResult = $oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		$aHierarchyTypes = [];
		while($aRow = $oResult->fetch()){
			$aHierarchyTypes[] = $aRow['id'];
		}
		$sHierarchyTypes = implode(',', $aHierarchyTypes);

		$aDomainId = getRequest('domain_id');
		if(isset($aDomainId) && is_array($aDomainId)){
			$iDomainId = $aDomainId[0];
		}
		else {
			$iDomainId = cmsController::getInstance()->getCurrentDomain()->getId();
		}


		// Здесь
		// o.name - название страницы
		$sQuery  = " SELECT SQL_CALC_FOUND_ROWS cu.id, cu.l1 AS level_1, cu.l2 AS level_2, cu.page_id, cu.obj_id, o.name AS page_name, parent.name AS parent_name";
		$sQuery .= " FROM cms3_canonical_urls cu, cms3_objects o, cms3_hierarchy h,";
		$sQuery .= " (SELECT h2.id, o2.name FROM cms3_objects o2, cms3_hierarchy h2 WHERE o2.id = h2.obj_id AND h2.type_id IN ($sHierarchyTypes) AND h2.domain_id = {$iDomainId}) AS parent";
		$sQuery .= " WHERE 1 = 1";
		$sQuery .= " AND o.id = cu.obj_id";
		$sQuery .= " AND h.id = cu.page_id";
		$sQuery .= " AND h.domain_id = {$iDomainId}";
		$sQuery .= " AND cu.domain_id = {$iDomainId}";
		$sQuery .= " AND h.rel = parent.id";
		$sQuery .= " AND h.type_id IN ($sHierarchyTypes)";

		// Фильтр
		$aFieldsFilter = getRequest('fields_filter');
		if(is_array($aFieldsFilter)){
			foreach($aFieldsFilter as $sField => $aData){
				$sOperator = key($aData);
				$sValue = $aData[$sOperator];

				if($sField === 'parent'){
					$sQuery .= " AND LOWER(parent.name) {$sOperator} LOWER('{$sValue}%')";
				}

				if($sField === 'item'){
					$sQuery .= " AND LOWER(o.name) {$sOperator} LOWER('{$sValue}%')";
				}

				if($sField === 'level_1'){
					$sQuery .= " AND LOWER(cu.l1) {$sOperator} LOWER('{$sValue}%')";
				}

				if($sField === 'level_2'){
					$sQuery .= " AND LOWER(cu.l2) {$sOperator} LOWER('{$sValue}%')";
				}
			}
		}

		// Сортировка
		$aFieldsFilter = getRequest('order_filter');
		if(is_array($aFieldsFilter)){
			foreach($aFieldsFilter as $sField => $sDir){
				$sDir = strtoupper($sDir);

				switch($sField){
					case 'parent':
						$sQuery .= " ORDER BY parent.name {$sDir}";
						break;
					case 'item':
						$sQuery .= " ORDER BY o.name {$sDir}";
						break;
					case 'level_1':
						$sQuery .= " ORDER BY level_1 {$sDir}";
						break;
					case 'level_2':
						$sQuery .= " ORDER BY level_2 {$sDir}";
						break;
					default:
						$sQuery .= " ORDER BY level_1 ASC, level_2 ASC";
						break;
				}
			}
		}
		else {
			$sQuery .= " ORDER BY level_1 ASC, level_2 ASC";
		}


		// Лимит для отображения
		$limit = (int) getRequest('per_page_limit');
		$limit = ($limit === 0) ? 25 : $limit;
		$currentPage = (int) getRequest('p');
		$offset = $currentPage * $limit;

		$sQuery .= " LIMIT {$offset}, {$limit}";


		// Выполним запрос
		$oResult = $oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		// Получим список канонических URL
		$aData = [];
		while($aRow = $oResult->fetch()){
			$aData[] = [
				'id' => $aRow['id'],
				'level_1' => $aRow['level_1'],
				'level_2' => $aRow['level_2'],
				'page_id' => $aRow['page_id'],
				'obj_id' => $aRow['obj_id'],
			];
		}

		// Общее кол-во записей
		$sQuery = <<<SQL
SELECT FOUND_ROWS() AS total;
SQL;
		$oResult = $oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		$aRow = $oResult->fetch();
		$total = $aRow['total'];



		// Получим список канонических страниц с названием позиции и родительской категорией
		$oUmiHierarcy = umiHierarchy::getInstance();
		$oUmiObjectsCollection = umiObjectsCollection::getInstance();
		foreach($aData as $aRow){
			$oObject =$oUmiObjectsCollection->getObject($aRow['obj_id']);
			if(!($oObject instanceof umiObject)){
				$aRow['total'] = $aRow['total'] - 1;
				continue;
			}
			$sItemPath = $this->module->getCanonicalLink($aRow['page_id']);


			$oPage = $oUmiHierarcy->getElement($aRow['page_id']);
			if(!($oPage instanceof umiHierarchyElement)){
				$aRow['total'] = $aRow['total'] - 1;
				continue;
			}
			$iParentId = $oPage->getParentId();
			$sParentPath = $this->module->getCanonicalLink($iParentId);

			$oParentPage = $oUmiHierarcy->getElement($iParentId);
			if(!($oParentPage instanceof umiHierarchyElement)){
				$aRow['total'] = $aRow['total'] - 1;
				continue;
			}
			$oParentObject = $oParentPage->getObject();



			$result['data'][] = [
				'id' => $aRow['id'],
				'level_1' => $aRow['level_1'],
				'level_2' => $aRow['level_2'],
				'item' => '<a href="'. $sItemPath. '" target="_blank">'. $oObject->getName(). '</a>',
				'parent' => '<a href="'. $sParentPath. '" target="_blank">'. $oParentObject->getName(). '</a>',
			];
		}


		$result['data']['offset'] = $offset;
		$result['data']['per_page_limit'] = $limit;
		$result['data']['total'] = $total;

		Service::Response()
			->printJson($result);
	}





	/**
	 * Возвращает данные для создания формы редактирования канонического адерса.
	 * Если передан $_REQUEST['param1'] = do,
	 * то сохраняет изменения канонического адерса и производит перенаправление.
	 * Адрес перенаправление зависит от режима кнопки "Сохранить".
	 * @throws publicAdminException
	 */
	public function edit() {
		$iCanonicalId = (string) getRequest('param0');

		$this->setHeaderLabel('header-canonical-edit');


		$oConnection = ConnectionPool::getInstance()->getConnection();

		$sQuery  = <<<SQL
SELECT l1, l2, page_id, obj_id
FROM cms3_canonical_urls
WHERE 1=1
AND id = {$iCanonicalId}
SQL;
		$oResult = $oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		if($oResult->length() === 0){
			throw new publicAdminException(getLabel('error-canonical-not-found'));
		}

		$aRow = $oResult->fetch();
		$iObjId = (int) $aRow['obj_id'];
		$iPageId = (int) $aRow['page_id'];

		$formData = [
			'level_1' => $aRow['l1'],
			'level_2' => $aRow['l2'],
		];


		// Режим сохранения
		if ($this->isSaveMode('param1')) {
			$aData = (array) getRequest('data');

			$sNewLevel1 = $aData[$iCanonicalId]['level_1'];
			$sNewLevel2 = $aData[$iCanonicalId]['level_2'];

			if($this->module->modifyCanonicalPath($iCanonicalId, $sNewLevel1, $sNewLevel2)){
				$oUmiHierarchy = umiHierarchy::getInstance();
				if($oElement = $oUmiHierarchy->getElement($iPageId)){
					$updater = Service::SiteMapUpdater();
					$updater->update($oElement);
				}
			}


			$this->chooseRedirect();
		}

		$formData['field_name_prefix'] = 'data[' . $iCanonicalId . ']';
//		$formData[$iCanonicalId] = $iCanonicalId;

		$this->setDataType('form');
		$this->setActionType('modify');
		$this->setData($formData);
		$this->doData();
	}

//	/** Удаляет редиректы */
//	public function del() {
//		$redirects = getRequest('element');
//
//		if (!is_array($redirects)) {
//			$redirects = [$redirects];
//		}
//
//		$umiRedirectsCollection = umiRedirectsCollection::getInstance();
//		$idName = $umiRedirectsCollection->getMap()->get('ID_FIELD_NAME');
//
//		$redirectIds = [];
//
//		foreach ($redirects as $redirect) {
//			$redirectIds[] = $redirect[$idName];
//		}
//
//		$result = [];
//
//		try {
//			$umiRedirectsCollection->delete(
//				[
//					$idName => $redirectIds
//				]
//			);
//			$result['data']['success'] = true;
//		} catch (Exception $e) {
//			$result['data']['error'] = $e->getMessage();
//		}
//
//		$this->setDataType('list');
//		$this->setActionType('view');
//		$this->setData($result);
//		$this->doData();
//	}

	/**
	 * Сохраняет изменения поля редиректа
	 * @throws Exception
	 */
	public function saveValue() {
		$iCanonicalId = (string) getRequest('param0');
		$sFieldKey = (string) getRequest('field');
		$sFieldValue = (string) getRequest('value');

		// Проверим, что пытаемся изменить только URL
		if(!($sFieldKey === 'level_1' || $sFieldKey === 'level_2')){
			return false;
		}


		/** Обновим URL, если возможно */
		$oConnection = ConnectionPool::getInstance()->getConnection();

		$sQuery  = <<<SQL
SELECT l1, l2, page_id, obj_id
FROM cms3_canonical_urls
WHERE 1=1
AND id = {$iCanonicalId}
SQL;
		$oResult = $oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		if($oResult->length() === 0){
			throw new publicAdminException(getLabel('error-canonical-not-found'));
		}

		$aRow = $oResult->fetch();
		$iObjId = (int) $aRow['obj_id'];
		$iPageId = (int) $aRow['page_id'];
		$sLevel1 = (string) $aRow['l1'];
		$sLevel2 = (string) $aRow['l2'];

		if($sFieldKey === 'level_1') {
			$sNewLevel1 = $sFieldValue;
			$sNewLevel2 = $sLevel2;
		}
		else {
			$sNewLevel1 = $sLevel1;
			$sNewLevel2 = $sFieldValue;
		}

		if($this->module->modifyCanonicalPath($iCanonicalId, $sNewLevel1, $sNewLevel2)){
			$oUmiHierarchy = umiHierarchy::getInstance();
			if($oElement = $oUmiHierarchy->getElement($iPageId)){
				$updater = Service::SiteMapUpdater();
				$updater->update($oElement);

				$result['data']['success'] = true;
				return;
			}
		}

		$result['data']['success'] = false;


		Service::Response()
			->printJson($result);
	}

	/**
	 * Возвращает настройки модуля "Редиректы".
	 * Если передано ключевое слово "do" в $_REQUEST['param0'],
	 * то сохраняет переданные настройки.
	 */
	public function config() {
//		$config = mainConfiguration::getInstance();
//
		$params = [
//			'config' => [
//				'boolean:allow-redirects-watch' => null
//			]
		];
//
//		if ($this->isSaveMode()) {
//			$params = $this->expectParams($params);
//			$config->set('seo', 'watch-redirects-history', $params['config']['boolean:allow-redirects-watch']);
//			$config->save();
//			$this->chooseRedirect();
//		}
//
//		$params['config']['boolean:allow-redirects-watch'] = $config->get('seo', 'watch-redirects-history');
//
		$this->setConfigResult($params);
	}

	/** Удаляет все редиректы в системе */
	public function removeAllRedirects() {
		Service::Redirects()->deleteAll();
	}

	/**
	 * Возвращает настройки для формирования табличного контрола
	 * @param string $param контрольный параметр
	 * @return array
	 */
	public function getDatasetConfiguration($param = '') {
		return [
			'methods' => [
				[
					'title' => getLabel('smc-load'),
					'forload' => true,
					'module' => 'canonical',
					'type' => 'load',
					'name' => 'lists'
				],
				[
					'title' => getLabel('js-permissions-edit'),
					'module' => 'canonical',
					'type' => 'edit',
					'name' => 'edit'
				],
				[
					'title' => getLabel('js-confirm-unrecoverable-yes'),
					'module' => 'umiRedirects',
					'type' => 'saveField',
					'name' => 'saveValue'
				],
			],
			'default' => 'level_1[400px]|level_2[400px]|page[400px]|parent[400px]',
			'fields' => [
				[
					'name' => 'level_1',
					'title' => getLabel('label-field-level-1'),
					'type' => 'string',
				],
				[
					'name' => 'level_2',
					'title' => getLabel('label-field-level-2'),
					'type' => 'string',
				],
				[
					'name' => 'item',
					'title' => getLabel('label-field-item-name'),
					'type' => 'string',
				],
				[
					'name' => 'parent',
					'title' => getLabel('label-field-parent-name'),
					'type' => 'string',
				],
			]
		];
	}

	/** Возвращает конфиг модуля в формате JSON для табличного контрола */
	public function flushDataConfig() {
		$this->module->printJson($this->getDatasetConfiguration());
	}

	/**
	 * Убирает лишние слэши из редиректа
	 * @param string $redirect источник или назначение редиректа
	 * @return string
	 */
	private function trim($redirect) {
		if ($redirect === '/') {
			return $redirect;
		}
		return trim($redirect, '/');
	}

}
