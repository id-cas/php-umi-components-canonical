<?php

use UmiCms\Service;

/** Класс обработчиков событий */
class CanonicalHandlers {

	/** @var canonical $module */
	public $module;

	// Уровни адреса для объектов и товаров каталога
	private $sPathLevel1 = null;
	private $sPathLevel2 = null;

	/**
	 * Маршрутизация по каноническим адресам
	 */
	public function onRouting(iUmiEventPoint $oEvent){

		// Инициализация cmsController-а
		if(!$oEvent->getParam('router') instanceof cmsController) {
			return;
		}
		$oCmsController = $oEvent->getParam('router');
		$iDomainId = $oCmsController->getCurrentDomain()->getId();


		// Состояние системы перед analyzePath в cmsController
		if ($oEvent->getMode() == 'before') {

			// Проверим валидность запрашиваемого URL
			$oRequest = Service::Request();

			$sRequestUri = $oRequest->uri();
			if ($sRequestUri && preg_match('/(^[^\?]*\/&)/', $sRequestUri)) {
				return;
			}

			$sPath = $oRequest->getPath();
			if ($sPath && preg_match('/(^[^\?]*\/&)/', $sPath)) {
				return;
			}

			/** Все неканонические адреса разделов и товаров редиректим на канонические (если текущий и канонический адреса отличаются) */
			if($sCanonicalPath = $this->module->getCanonicalByPath($sPath, $iDomainId)){
				if($sCanonicalPath !== $sPath && !$this->module->isCanonicalUrl($sPath, $iDomainId)){
					$buffer = Service::Response()
						->getCurrentBuffer();
					$buffer->status('301 Moved Permanently');
					$buffer->redirect($sCanonicalPath);

					return;
				}
			}



			$aPathParts = explode('/', $sPath);
			if(count($aPathParts) < 2) {
				return;
			}

			// Уровень №1
			$sPathLevel1 = $aPathParts[0];

			// Уровень №2
			array_shift($aPathParts);
			$sPathLevel2 = implode('/', $aPathParts);

			// Проверим, что это адреса для каталога
			if($sPathLevel1 !== 'categories' && $sPathLevel1 !== 'product'){
				return;
			}


			/** Кэширование запроса поиска канонического адреса */
			$cacheLifetime = 2592000; // 20-ть дней
			$cacheFrontend = Service::CacheFrontend();
			$cacheKey = $sPathLevel1. '-'. $sPathLevel2;

			$aCanonicalId = $cacheFrontend->loadData($cacheKey);

			if(!$aCanonicalId){
				/** Работа с таблицей канонических адресов */
				// Проверим, что текущий URL есть в таблице канонических URL для каталога и если так - найдем page_id
				// запрашиваемой страницы
				$oConnection = ConnectionPool::getInstance()->getConnection();

				$sQuery = <<<SQL
SELECT cu.page_id AS id
FROM cms3_canonical_urls cu
WHERE cu.l1 = '{$sPathLevel1}'
AND cu.l2 = '{$sPathLevel2}'
AND cu.domain_id = {$iDomainId}
ORDER BY cu.page_id ASC
SQL;

				$oResult = $oConnection->queryResult($sQuery);
				$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

				// Если канонический адрес не найден
				if ($oResult->length() == 0) {
					return;
				}

				// Получим список идентификаторов страниц, соответсвующих каноническому адресу
				$aCanonicalId = [];
				while ($aRow = $oResult->fetch()) {
					$aCanonicalId[] = (int) $aRow['id'];
				}

				// Закэшируем результат
				$cacheFrontend->saveData(
					$cacheKey,
					$aCanonicalId,
					$cacheLifetime
				);
			}

			// Произвольный вариант страницы (из набора вирутуальных копий, если существуют)
			$iElementId = $aCanonicalId[0];

			$oElement = umiHierarchy::getInstance()->getElement($iElementId);
			if(!($oElement instanceof umiHierarchyElement)){
				return;
			}

			if(!$oElement->getIsActive()){
				return;
			}

			if($oElement->getIsDeleted()){
				return;
			}


			// Для страницы существует набор виртуальных копий: постараемся показать максимально реливантный вариант
			// основнываясь на поведении пользователя
			if(count($aCanonicalId) > 1){

				// Последняя посещенная страница
				$iLastVisitedPageId = isset($_SESSION['can_last_page']) ? intval($_SESSION['can_last_page']) : 0;

				if ($iLastVisitedPageId && !in_array($iLastVisitedPageId, $aCanonicalId)) {

					// Получим список дочерних страниц Последней посещенной страницы
					$aLastPageChildren = array_map(function ($n) {
						return (int) $n;
					}, umiHierarchy::getInstance()->getChildrenList($iLastVisitedPageId));

					// Дочерние элементы найдены
					if (!empty($aLastPageChildren) && is_array($aLastPageChildren) && count($aLastPageChildren) > 0) {

						// Проверим, что среди найденных канонических идентификаторах есть страница дочерняя для
						// последней страницы, просмотренной пользователем
						foreach ($aCanonicalId as $iCanonicalId) {
							if (in_array($iCanonicalId, $aLastPageChildren)) {
								$iElementId = $iCanonicalId;
								break;
							}
						}

					}
				}
			}

			// Установим идентификатор канонической страницы для текущей сессии
			$_SESSION['can_real_page_id'] = $iElementId;


			// Выберем не каноническую страницу для отображения по каноническому адресу
			$oCmsController->setCurrentElementId($iElementId);

			// Глобально части пути
			$this->sPathLevel1 = $sPathLevel1;
			$this->sPathLevel2 = $sPathLevel2;
		}



		if ($oEvent->getMode() == 'after') {
			/** МОДУЛЬ + МЕТОД */

			if(is_null($this->sPathLevel1) || is_null($this->sPathLevel2)){
				return;
			}

			$oCmsController->setCurrentModule('catalog');

			if($this->sPathLevel1 == 'categories'){
				$oCmsController->setCurrentMethod('category');
			}

			if($this->sPathLevel1 == 'product'){
				$oCmsController->setCurrentMethod('object');
			}
		}

	}


	/**
	 * Обновление карты сайта для конкретного элемента
	 * режим: before
	 * параметры события before_update_sitemap:
	 *  (int) id - идентификатор страницы
	 *  (int) domainId - идентификатор домена
	 *  (int) langId - идентификатор языка
	 *  (string) updateTime - время последнего обновления страницы
	 *  (int) level - максимальный уровень вложенности относительно страницы
	 * параметры-ссылки:
	 *  (string) link - url адрес страницы
	 *  (float) pagePriority - приоритет просмотра страницы поисковым роботом
	 *  (bool) robots_deny - восстанавливаемая страница
	 */
	public function onUpdateSiteMap(iUmiEventPoint $oEvent){
		if ($oEvent->getMode() !== 'before') {
			return;
		}


		// Получим идентификатор текущей обновляемой страницы
		if(!($iPageId = $oEvent->getParam('id'))){
			return;
		}

		// Удостоверимся, что текущая страница - это раздел или объект каталога
		$oUmiHierarchy = umiHierarchy::getInstance();
		$oPage = $oUmiHierarchy->getElement($iPageId);

		if(!($oPage instanceof umiHierarchyElement)){
			return;
		}

		$sModule = $oPage->getModule();
		if($sModule !== 'catalog'){
			return;
		}

		$sMethod = $oPage->getMethod();
		if($sMethod !== 'category' && $sMethod !== 'object'){
			return;
		}


		// Если это корень каталога - тоже можно пропустить
		if($sMethod === 'category' && !$this->module->getPageHierarchyLevel($iPageId)){
			return;
		}


		// Определим идентификатор домена
		$iDomainId = $oEvent->getParam('domainId');


		// Проверим есть ли такая страница в таблице канонических адресов - заберем оттуда существующий канонический адрес
		// Если страницы там не оказалось - создадим канонический адрес
		if(!($sCanonicalPath = $this->module->getCanonicalPathByPageId($iPageId, $iDomainId))){
			$sCanonicalPath = $this->module->createCanonicalPath($iPageId, $sMethod, $iDomainId);
		}

		// Произошла какая-то ошибка и канонический адрес не удалось создать
		if(!$sCanonicalPath){
			return;
		}



		/**
		 * Новые данные для карты сайта (проверим есть ли данные в карте сайта и обновим в ней данные на канонические)
		 */
		// Проверим, если для объекта уже существует запись в карте сайте (и это не текущий обновляемый объект) - прекратим добавление в карту сайта.
		// Это позволит избежать дублей в карте сайта для виртуальных копий.
		$oObject = $oPage->getObject();
		if(!($oObject instanceof umiObject)){
			return;
		}
		$iObjId = $oObject->getId();


		$oConnection = ConnectionPool::getInstance()->getConnection();

		$sQuery = <<<SQL
SELECT h.id
FROM cms3_hierarchy h, cms_sitemap cs
WHERE 1 = 1
AND h.id = cs.id
AND h.obj_id = {$iObjId}
AND h.id != {$iPageId}
AND h.domain_id != {$iDomainId}
AND cs.domain_id != {$iDomainId}
AND h.is_deleted = 0
AND h.is_active = 1
SQL;
		$oResult = $oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		// Уже есть подобная запись в карте сайта
		if ($oResult->length() > 0) {
			// // Прерываение обработки события не сработало, т.к. нет обработчика в методе вызвавшем это событие, поэтому
			// // будем делать смежным способом
			// throw new coreBreakEventsException("Sitemap already contain link for object with page_id: {$iPageId} (obj_id: {$iObjId})");

			// Добавим новые данные для карты сайта на следующем шаге, а на этому удалим все дубли
			$aDublesPageId = [];
			while($aRow = $oResult->fetch()){
				$aDublesPageId[] = $aRow['id'];
			}
			$sDublesPageId = implode(',', $aDublesPageId);

			$sQuery = <<<SQL
DELETE
FROM cms_sitemap
WHERE id in ({$sDublesPageId})
SQL;
			$oResult = $oConnection->queryResult($sQuery);
		}


		// Актуализированные данные для вставки в карту сайта
		$domainsCollection = Service::DomainCollection();
		$oDomain = $domainsCollection->getDomain($iDomainId);
		$sHost = $oDomain->getHost();

		$oConfig = mainConfiguration::getInstance();
		$sServeProtocol = $oConfig->get('system', 'server-protocol');

		$sLink =& $oEvent->getRef('link');
		$sLink = "{$sServeProtocol}://{$sHost}{$sCanonicalPath}";

		$fPagePriority =& $oEvent->getRef('pagePriority');
		$fPagePriority = ($sMethod === 'object') ? 0.3 : 0.5;

		$oEvent->setParam('level', 2);
		$oEvent->setParam('domainId', $iDomainId);


	}
}
