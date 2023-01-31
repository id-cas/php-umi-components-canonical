<?php
	/**
	 * Группы прав на функционал модуля
	 */
	$permissions = [
		/** Права на управление редиректами */
		'manage' => [
			'lists',
			'edit',
			'getdatasetconfiguration',
			'savevalue',
			'flushdataconfig'
		],
		/** Права на работу с настройками */
		'config' => [
			'config'
		],
		/** Права на удаление редиректов */
		'delete' => [
			'del',
			'removeallredirects'
		]
	];
?>