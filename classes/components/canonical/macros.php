<?php
/**
 * Класс макросов, то есть методов, доступных в шаблоне
 */
class CanonicalMacros {
	/**
	 * @var banners $module
	 */
	public $module;

	public function getCanonicalPathById($iPageId, $bAbsolutePath = true, $iDomainId = 0) {
		$oConnection = ConnectionPool::getInstance()->getConnection();

		if(!$iDomainId){
			$iDomainId = cmsController::getInstance()->getCurrentDomain()->getId();
		}

		$sQuery = <<<SQL
SELECT l1, l2
FROM cms3_canonical_urls
WHERE 1=1
AND page_id = '{$iPageId}'
AND domain_id = '{$iDomainId}'
LIMIT 1
SQL;
		$oResult = $oConnection->queryResult($sQuery);
		$oResult->setFetchType(IQueryResult::FETCH_ASSOC);

		// Если канонический адрес не найден
		if ($oResult->length() == 0) {
			return false;
		}

		// Получим список идентификаторов страниц, соответсвующих каноническому адресу
		$aRow = $oResult->fetch();

		$sPath = "/{$aRow['l1']}/{$aRow['l2']}/";

		if($bAbsolutePath === true){
			$oCmsController = cmsController::getInstance();
			$oDomain = $oCmsController->getCurrentDomain();
			$sHost = $oDomain->getHost();

			$oConfig = mainConfiguration::getInstance();
			$sServeProtocol = $oConfig->get('system', 'server-protocol');

			$sPath = "{$sServeProtocol}://{$sHost}{$sPath}";
		}

		return $sPath;
	}
}
