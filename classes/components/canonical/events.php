<?php {

	// Маршрутизация запроса по адресу в URL
	new umiEventListener('routing', 'canonical', 'onRouting');

	// Добавление канонического адреса в общую таблицу маршрутизации при СОЗДАНИИ/ИЗМЕНЕНИИ страницы объекта каталога
	// все эти события завязаны на одно событие - обновление карты сайта
	new umiEventListener('before_update_sitemap', 'canonical', 'onUpdateSiteMap');

	// TODO: для карты сайта
	//

	// TODO: возможность редактирования списка канонических адресов через панель администратора
	// TODO: возможность сгенерировать канонические адреса для объектов каталога через панель администратора
}