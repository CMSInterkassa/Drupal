﻿Модуль разработан в компании GateOn предназначен для CMS Drupal 8.3.x + Commerce 2
Сайт разработчикa: www.gateon.net
E-mail: www@smartbyte.pro
Версия: 1.0

Автоматическая установка:

*Установка предполагает ,что у вас уже установлен Commerce 2 ,

1.В админ панели Модули->Установить новый модуль
2.Выберите архив модуля и нажмите "Установить"
(Если автоматически модуль установился, тогда переходите к следующему пункту,
если нет, тогда воспользуйтесь ручной установкой)

Ручная установка:

1.Зайдите в  файловый менеджер на вашем хостинге или через FTP в директорию /modules/
2.Копируем сюда папку модуля (commerce_payment_interkassa) и в списке модулей отмечаем модуль Commerce Interkassa и нажимаем "Установить"
3.Модуль установлен, можно переходить к настройке

Настройка:
1.Прежде всего в настройках кассы на сайте Интеркассы в разделе "Интерфейс" разрешено ли переопределение в запросе. Если нет, тогда измените где нужно ,чтобы все ползунки были зеленые.
2.Во вкладке безопасность желательно включить проверку цифровой подписи для улучшения безпопасности. Эту страницу не закрывайте, нам понадобится тестовый и секретный ключ.
3.Переходим в Commerce->Конфигурация->Payment Gateway->Add Payment Gateway . 

Идентификатор кассы, секретный ключ и тестовый ключ возьмите с вашей кассы на сайте Интеркассы.

*Можете включить тестовый режим для тестирования работоспособности модуля на тестовой платежной системе.

ОБЯЗАТЕЛЬНО ОЗНАКОМИТСЯ:
!!! Для тестирования платежей в кассе используйте тестовую валюту XTS (тестовая платежная система), для это необходимо включить в кассе этот пункт, и далее при перенаправлении на сайт "Интеркассы" выбрать ее в качестве оплаты, далее система сгенерирует тестовую страничку со всеми видами ответа от сервера, это поможет вам настроить все необходимые параметры и убедиться в работоспособности вашей системы.