<?php
///////////////////////////////////////////////////////////////////////
/// Project: �������������� ���������� �������� Yandex.direct       ///
/// Name: ���� ��� ������� ��������� yandex_direct_automate         ///
/// Version: 1.1                                                    ///
/// Author: ��������� ��������� �������������                       ///
/// License: GNU General Public License                   			///
/// Url: http://cloud-automate.ru                                   ///
/// Email: info@cloud-automate.ru                                   ///
/// Requirements: PHP >= 5.2.0                                      ///
/// Charset: WINDOWS-1251                                           ///
/// Last modified: 21.08.2013 19:00                                 ///
///////////////////////////////////////////////////////////////////////

//�������� ����������������� �����
include_once(dirname(__FILE__)."/config.php");

//�������� ������ ��������������� ���������� �������� Yandex.direct
include_once(dirname(__FILE__)."/yandex_direct_automate.php"); 

//�������� ���������� ������
$Yandex_direct_automate=new Yandex_direct_automate();

//��������� ������ direct.yandex.ru
$RETURN=$Yandex_direct_automate->automate();

//����� � ����������.
$Yandex_direct_automate->report();

?>