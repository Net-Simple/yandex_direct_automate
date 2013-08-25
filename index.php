<?php
///////////////////////////////////////////////////////////////////////
/// Project: Автоматическое управление ставками Yandex.direct       ///
/// Name: Файл для запуска программы yandex_direct_automate         ///
/// Version: 1.1                                                    ///
/// Author: Кононенко Станислав Александрович                       ///
/// License: GNU General Public License                   			///
/// Url: http://cloud-automate.ru                                   ///
/// Email: info@cloud-automate.ru                                   ///
/// Requirements: PHP >= 5.2.0                                      ///
/// Charset: WINDOWS-1251                                           ///
/// Last modified: 21.08.2013 19:00                                 ///
///////////////////////////////////////////////////////////////////////

//Загрузка конфигурационного файла
include_once(dirname(__FILE__)."/config.php");

//Загрузка класса автоматического управления ставками Yandex.direct
include_once(dirname(__FILE__)."/yandex_direct_automate.php"); 

//Создание экземпляра класса
$Yandex_direct_automate=new Yandex_direct_automate();

//Установка ставок direct.yandex.ru
$RETURN=$Yandex_direct_automate->automate();

//Отчет о выполнении.
$Yandex_direct_automate->report();

?>