<?php
///////////////////////////////////////////////////////////////////////
/// Project: Автоматическое управление ставками Yandex.direct       ///
/// Name: Класс с логикой работы системы                            ///
/// Version: 1.1.1                                                  ///
/// Author: Кононенко Станислав Александрович                       ///
/// License: GNU General Public License                   			///
/// Url: http://cloud-automate.ru                                   ///
/// Email: info@cloud-automate.ru                                   ///
/// Requirements: PHP >= 5.2.0                                      ///
/// Charset: WINDOWS-1251                                           ///
/// Last modified: 23.08.2013 13:57                                 ///
///////////////////////////////////////////////////////////////////////
class Yandex_direct_automate // Класс автоматического управления ставками
                             // Yandex.direct
{
	private $CONFIG = array (); // Конфигурационный массив
	private $ERRORS = array (); // Массив ошибок
	private $REPORTS = array (); // Массив отчетов
	private $LOG = array (); // Массив отчетов
	private $http = true;
	private $path = '';
	private $updateprices_max = 1000;
	private $updateprices_call_max = 3000;
	
	// Конструктор класса//
	function __construct() {
		GLOBAL $YANDEX_DIRECT_AUTOMATE;
		//Ссылка на oAuth
		$YANDEX_DIRECT_AUTOMATE["oauth"] = 'https://oauth.yandex.ru/token';
		
		//Ссылка на API
		$YANDEX_DIRECT_AUTOMATE["api"] = 'https://soap.direct.yandex.ru/json-api/v4/';
		
		//Относительный путь к логу
		$YANDEX_DIRECT_AUTOMATE['log'] = '/yandex_direct_automate_log.txt';
		
		$this->CONFIG = $YANDEX_DIRECT_AUTOMATE;
		$this->http = ($_SERVER ['DOCUMENT_ROOT']) ? true : false;
		$this->path = (! $this->path) ? dirname ( __FILE__ ) : $this->path;				
		
		if (! function_exists ( 'curl_init' ))
			$this->ERRORS [] = 'У вас не включена поддержка cUrl';
	} // \function
	  // \Конструктор класса//
	  
	// Отчет//
	function report() {
		$REPORTS = $this->REPORTS;
		$ERRORS = $this->ERRORS;
		$reports = "Yandex_direct_automate <br>\r\nОтчет: <br>\r\n";
		$errors = (count ( $ERRORS ) > 0) ? "Ошибки: <br>\r\n" : "";
		
		for($i = 0; $i < count ( $REPORTS ); $i ++) {
			$reports .= ($i + 1) . ". " . $REPORTS [$i] . "<br>\r\n";
		} // for
		
		for($i = 0; $i < count ( $ERRORS ); $i ++) {
			$errors .= ($i + 1) . ". " . $ERRORS [$i] . "<br>\r\n";
		} // for
		
		if (! $this->http) {
			$reports = $this->translit ( str_replace ( '<br>', '', $reports ) );
			$errors = $this->translit ( str_replace ( '<br>', '', $errors ) );
		} 
		else
			header("Content-Type: text/html; charset=windows-1251");
		
		echo $reports;
		echo $errors;
		
		// Логирование
		if (isset ( $this->CONFIG ['log_summary'] ) && $this->CONFIG ['log_summary']) {
			if (file_exists ( $this->path . $this->CONFIG ['log'] ) && DATE ( 'd' ) != DATE ( 'd', filectime ( $this->path . $this->CONFIG ['log'] ) ))
				@unlink ( $this->path . $this->CONFIG ['log'] );
			
			if (! file_exists ( $this->path . $this->CONFIG ['log'] ))
				$content = "Дата и время операции\tНомер кампании\tНазвание кампании\tНомер объявления\tЗаголовок объявления\tНомер фразы\tЗаголовок фразы\tцена за клик до изменения\tцена 1-го спецразмещения\tвход в спецразмещение\tцена 1-го места\tвход в гарантию\tцена за клик после изменения\r\n";
			else
				$content = '';
			for($i = 0; $i < count ( $this->LOG ); $i ++) {
				$content .= implode ( "\t", $this->LOG [$i] ) . "\r\n";
			} // \for
			$handle = fopen ( $this->path . $this->CONFIG ['log'], "a" );
			fwrite ( $handle, $content );
			fclose ( $handle );
		} else
			file_put_contents ( $this->path . $this->CONFIG ['log'], str_replace ( '<br>', '', $reports . $errors ) );
	} // \function
	  // \Отчет//
	  
	// Автоматический рассчет и установка ставок//
	function automate() {
		$PHRASES = array ();
		
		// Получения oAuth токена//
		
			$ch = curl_init ();
			
			$data = array (
					'grant_type' => 'password',
					'username' => $this->CONFIG ['login'],
					'password' => $this->CONFIG ['password'],
					'client_id' => $this->CONFIG ['client_id'],
					'client_secret' => $this->CONFIG ['client_secret'] 
			);
			
			curl_setopt ( $ch, CURLOPT_URL, 'https://oauth.yandex.ru/token' );
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_POST, 1 );
			curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
			
			$result = curl_exec ( $ch );
			
			curl_close ( $ch );
			$OAUTH = json_decode ( $result );
			if (isset ( $OAUTH->access_token ))
				$this->CONFIG ['token'] = $OAUTH->access_token;
			else
				$this->ERRORS [] = 'Не удалось получить авторизационный oAuth токен!';
		
		// \Получения oAuth токена//
		
		// Получаем список компаний//
		$PARAMS = array (
				$this->CONFIG ['login'] 
		);
		$GetCampaignsList = $this->send ( 'GetCampaignsList', $PARAMS );
		
		for($i = 0; $i < count ( $GetCampaignsList->data ); $i ++) {
			if ($GetCampaignsList->data [$i]->StatusShow == 'Yes') {
				if (isset ( $GetCampaignsList->data [$i]->Name ))
					$GetCampaignsList->data [$i]->Name = str_replace ( '"', '', iconv ( 'UTF-8', 'WINDOWS-1251', $GetCampaignsList->data [$i]->Name ) );
					
					// Получаем список фраз компании//
				$PARAMS = array (
						'CampaignIDS' => array (
								$GetCampaignsList->data [$i]->CampaignID 
						),
						'Filter' => array (
								'StatusShow' => array (
										'Yes' 
								) 
						),
						'GetPhrases' => 'WithPrices' 
				);
				
				$GetBanners = $this->send ( 'GetBanners', $PARAMS );
				
				for($j = 0; $j < count ( $GetBanners->data ); $j ++) {
					// echo
					// $GetBanners->data[$j]->BannerID.'='.count($GetBanners->data[$j]->Phrases).'<br>';
					for($k = 0; $k < count ( $GetBanners->data [$j]->Phrases ); $k ++) {
						// echo
						// 'PhraseID='.$GetBanners->data[$j]->Phrases[$k]->PhraseID.',
						// Min='.$GetBanners->data[$j]->Phrases[$k]->Min.',
						// Max='.$GetBanners->data[$j]->Phrases[$k]->Max.',
						// PremiumMin='.$GetBanners->data[$j]->Phrases[$k]->PremiumMin.',
						// PremiumMax='.$GetBanners->data[$j]->Phrases[$k]->PremiumMax.'<br>';
						if ($GetBanners->data [$j]->Phrases [$k]->LowCTR != 'Yes') {
							
							if (isset ( $GetBanners->data [$j]->Phrases [$k]->Phrase ))
								$GetBanners->data [$j]->Phrases [$k]->Phrase = str_replace ( '"', '', iconv ( 'UTF-8', 'WINDOWS-1251', $GetBanners->data [$j]->Phrases [$k]->Phrase ) );
							
							if (isset ( $GetBanners->data [$j]->Title ))
								$Title = str_replace ( '"', '', iconv ( 'UTF-8', 'WINDOWS-1251', $GetBanners->data [$j]->Title ) );
							else
								$Title = '';
							$PHRASES [] = array (
									'CampaignID' => $GetCampaignsList->data [$i]->CampaignID,
									'CampaignName' => $GetCampaignsList->data [$i]->Name,
									'BannerID' => $GetBanners->data [$j]->BannerID,
									'PhraseID' => $GetBanners->data [$j]->Phrases [$k]->PhraseID,
									'Phrase' => $GetBanners->data [$j]->Phrases [$k]->Phrase,
									'Title' => $Title,
									'Price' => isset ( $GetBanners->data [$j]->Phrases [$k]->Price ) ? $GetBanners->data [$j]->Phrases [$k]->Price : 0,
									'Min' => isset ( $GetBanners->data [$j]->Phrases [$k]->Min ) ? $GetBanners->data [$j]->Phrases [$k]->Min : 0,
									'Max' => isset ( $GetBanners->data [$j]->Phrases [$k]->Max ) ? $GetBanners->data [$j]->Phrases [$k]->Max : 0,
									'PremiumMin' => isset ( $GetBanners->data [$j]->Phrases [$k]->PremiumMin ) ? $GetBanners->data [$j]->Phrases [$k]->PremiumMin : 0,
									'PremiumMax' => isset ( $GetBanners->data [$j]->Phrases [$k]->PremiumMax ) ? $GetBanners->data [$j]->Phrases [$k]->PremiumMax : 0 
							); // \array
						} // \if
					} // \for
				} // \for
					  
				// \Получаем список фраз компании//
			} // \if
		} // \for
		  // \Получаем список компаний//
		  
		// Устанавливаем ставки//
		$count = 0;
		$counter = 0;
		$PARAM = array ();
		for($i = 0; $i < count ( $PHRASES ); $i ++) {
			$price = 0;
			
			if (isset ( $this->CONFIG ['params'] [$PHRASES [$i] ['CampaignID']] ))
				$PARAMS = $this->CONFIG ['params'] [$PHRASES [$i] ['CampaignID']];
			else
				$PARAMS = $this->CONFIG ['params'];
			if (isset ( $PARAMS ['manual'] ) && $PARAMS ['manual']) {
			} else {
				
				if ($PARAMS ['price_start'] == - 1) 				// price_start=-1 Особая
				                                    // стратегия.
				{
					if ($PHRASES [$i] ['PremiumMax'] <= 1)
						$price = $PHRASES [$i] ['PremiumMax'];
					elseif ($PHRASES [$i] ['PremiumMin'] / $PHRASES [$i] ['Max'] >= 2.5)
						$price = $PHRASES [$i] ['Max'];
					else
						$price = $PHRASES [$i] ['PremiumMin'];
				} elseif ($PARAMS ['price_start'] == 5) 				// price_start=3 Минимальная
				                                        // цена спецразмещение или
				                                        // гарантия.
				{
					$price = $PHRASES [$i] ['Min'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['Max'] - $PHRASES [$i] ['Min']) * $PARAMS ['price_percent'] / 100;
					$price1 = $PHRASES [$i] ['PremiumMin'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100;
						if ( (($price1-$price)/$price) * 100 <= $PARAMS ['price_difference_percent'])
							$price = $price1;
				} elseif ($PARAMS ['price_start'] == 4) 				// price_start=3 Минимальная
				                                        // цена спецразмещение или
				                                        // гарантия.
				{
					$price = $PHRASES [$i] ['PremiumMin'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100;
					if ($price > $PARAMS ['price_max'])
						$price = $PHRASES [$i] ['Min'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['Max'] - $PHRASES [$i] ['Min']) * $PARAMS ['price_percent'] / 100;
				} elseif ($PARAMS ['price_start'] == 3) 				// price_start=3 Минимальная
				                                        // цена спецразмещение или
				                                        // гарантия.
				{
					$price = min ( $PHRASES [$i] ['PremiumMin'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100, $PHRASES [$i] ['Min'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['Max'] - $PHRASES [$i] ['Min']) * $PARAMS ['price_percent'] / 100 );
				} elseif ($PARAMS ['price_start'] == 2) 				// price_start=2
				                                        // Спецразмещение
				                                        // или 1 место гарантия.
				{
					$price = $PHRASES [$i] ['PremiumMin'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100;
					
					if ($PHRASES [$i] ['Max'] > 0 && $price > $PHRASES [$i] ['Max'])
						if ((($price - $PHRASES [$i] ['Max']) * 100) / $PHRASES [$i] ['Max'] >= $PARAMS ['price_difference_percent'])
							$price = $PHRASES [$i] ['Max'] + $PARAMS ['price_add'];
				} elseif ($PARAMS ['price_start'] == 1) 				// price_start=1
				                                        // Спецразмещение
				{
					$price = $PHRASES [$i] ['PremiumMin'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100;
					if ($price < $PHRASES [$i] ['PremiumMin'])
						$price = $PHRASES [$i] ['PremiumMin'];
				} else 				// price_start=0 Гарантия
				{
					$price = $PHRASES [$i] ['Min'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['Max'] - $PHRASES [$i] ['Min']) * $PARAMS ['price_percent'] / 100;
					if ($price < $PHRASES [$i] ['Min'])
						$price = $PHRASES [$i] ['Min'];
				} // if
				
				if ($PARAMS ['price_max'] > 0)
					if ($price > $PARAMS ['price_max'])
						$price = $PARAMS ['price_max'];
				
				$price = round ( $price, 2 );
				
				$PARAM [$count] [] = array (
						'PhraseID' => $PHRASES [$i] ['PhraseID'],
						'BannerID' => $PHRASES [$i] ['BannerID'],
						'CampaignID' => $PHRASES [$i] ['CampaignID'],
						'Price' => $price 
				);
				
				$this->REPORTS [] = "Кампания: {$PHRASES[$i]['CampaignID']}, объявление: {$PHRASES[$i]['BannerID']} - {$PHRASES[$i]['Title']}, фраза: {$PHRASES[$i]['PhraseID']} - {$PHRASES[$i]['Phrase']}, цена за клик = {$price}";
				$this->LOG [$counter] [] = DATE ( 'Y-m-d H:i:s' );
				$this->LOG [$counter] [] = "{$PHRASES[$i]['CampaignID']}";
				$this->LOG [$counter] [] = "{$PHRASES[$i]['CampaignName']}";
				$this->LOG [$counter] [] = "{$PHRASES[$i]['BannerID']}";
				$this->LOG [$counter] [] = "{$PHRASES[$i]['Title']}";
				$this->LOG [$counter] [] = "{$PHRASES[$i]['PhraseID']}";
				$this->LOG [$counter] [] = "{$PHRASES[$i]['Phrase']}";
				$this->LOG [$counter] [] = str_replace ( '.', ',', $PHRASES [$i] ['Price'] );
				$this->LOG [$counter] [] = str_replace ( '.', ',', $PHRASES [$i] ['PremiumMax'] );
				$this->LOG [$counter] [] = str_replace ( '.', ',', $PHRASES [$i] ['PremiumMin'] );
				$this->LOG [$counter] [] = str_replace ( '.', ',', $PHRASES [$i] ['Max'] );
				$this->LOG [$counter] [] = str_replace ( '.', ',', $PHRASES [$i] ['Min'] );
				$this->LOG [$counter] [] = str_replace ( '.', ',', $price );
				if ($counter > 0 && fmod ( $counter + 1, $this->updateprices_max ) == 0) {
					$count ++;
				} // \if
				$counter ++;
			} // \if manual
		} // \for
		
		for($i = 0; $i < count ( $PARAM ); $i ++) {
			$UpdatePrices = $this->send ( 'UpdatePrices', $PARAM [$i] );
			
			if (isset ( $UpdatePrices->error_str )) {
				$UpdatePrices->error_str = str_replace ( '"', '', iconv ( 'UTF-8', 'WINDOWS-1251', $UpdatePrices->error_str ) );
				$this->ERRORS [] = "UpdatePrices ошибка: {$UpdatePrices->error_str}";
			} // \if
		} // \for
			  // \Устанавливаем ставки//
	} // \function
	  // \Автоматический рассчет и установка ставок//
	  
	// Посылка данных//
	function send($method, $PARAMS) 	// Посылка данных
	{
		$RETURN = array ();
		if (isset ( $method ) && isset ( $PARAMS )) {
			
			// формирование запроса
			$request = array ();
						
			$request ['token'] = $this->CONFIG ['token'];
			$request ['application_id'] = $this->CONFIG ['client_id'];
			$request ['login'] = $this->CONFIG ['login'];			
			$request ['locale'] = 'ru';
			$request ['method'] = $method;
			$request ['param'] = $PARAMS;
			
			// преобразование в JSON-формат
			$request = json_encode ( $request );
			

				$ch = curl_init ();
				curl_setopt ( $ch, CURLOPT_URL, $this->CONFIG ['api'] );
				curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, false );
				curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt ( $ch, CURLOPT_POST, 1 );
				curl_setopt ( $ch, CURLOPT_POSTFIELDS, $request );
				$result = curl_exec ( $ch );
				curl_close ( $ch );

			
			if (! $result)
				$this->ERRORS [] = "Не удается открыть адрес: {$this->CONFIG['api']}. У вас отключено allow_url_fopen=0 в php.ini или отключено расширение php_openssl";
			$RETURN = json_decode ( $result );
		}
		if (isset ( $RETURN->error_str )) {
			$RETURN->error_str = str_replace ( '"', '', iconv ( 'UTF-8', 'WINDOWS-1251', $RETURN->error_str ) );
			$this->ERRORS [] = "Запрос {$method}: " . $RETURN->error_str;
		}
		return $RETURN; // Возвращаем ответ
	} // \function
	  // \Посылка данных//
	  
	// Транслитерация//
	function translit($string) {
		$len = strlen ( $string );
		$result = "";
		for($i = 0; $i < $len; $i ++) {
			$letter = substr ( $string, $i, 1 );
			switch ($letter) {
				case 'а' :
					{
						$result .= 'a';
						break;
					}
				case 'А' :
					{
						$result .= 'A';
						break;
					}
				case 'б' :
					{
						$result .= 'b';
						break;
					}
				case 'Б' :
					{
						$result .= 'B';
						break;
					}
				case 'в' :
					{
						$result .= 'v';
						break;
					}
				case 'В' :
					{
						$result .= 'V';
						break;
					}
				case 'г' :
					{
						$result .= 'g';
						break;
					}
				case 'Г' :
					{
						$result .= 'G';
						break;
					}
				case 'д' :
					{
						$result .= 'd';
						break;
					}
				case 'Д' :
					{
						$result .= 'D';
						break;
					}
				case 'е' :
					{
						$result .= 'e';
						break;
					}
				case 'Е' :
					{
						$result .= 'E';
						break;
					}
				case 'ё' :
					{
						$result .= 'e';
						break;
					}
				case 'Ё' :
					{
						$result .= 'E';
						break;
					}
				case 'ж' :
					{
						$result .= 'zh';
						break;
					}
				case 'Ж' :
					{
						$result .= 'ZH';
						break;
					}
				case 'з' :
					{
						$result .= 'z';
						break;
					}
				case 'З' :
					{
						$result .= 'Z';
						break;
					}
				case 'и' :
					{
						$result .= 'i';
						break;
					}
				case 'И' :
					{
						$result .= 'I';
						break;
					}
				case 'й' :
					{
						$result .= 'I';
						break;
					}
				case 'Й' :
					{
						$result .= 'i';
						break;
					}
				case 'к' :
					{
						$result .= 'k';
						break;
					}
				case 'К' :
					{
						$result .= 'K';
						break;
					}
				case 'л' :
					{
						$result .= 'l';
						break;
					}
				case 'Л' :
					{
						$result .= 'L';
						break;
					}
				case 'м' :
					{
						$result .= 'm';
						break;
					}
				case 'М' :
					{
						$result .= 'M';
						break;
					}
				case 'н' :
					{
						$result .= 'n';
						break;
					}
				case 'Н' :
					{
						$result .= 'N';
						break;
					}
				case 'о' :
					{
						$result .= 'o';
						break;
					}
				case 'О' :
					{
						$result .= 'O';
						break;
					}
				case 'п' :
					{
						$result .= 'p';
						break;
					}
				case 'П' :
					{
						$result .= 'P';
						break;
					}
				case 'р' :
					{
						$result .= 'r';
						break;
					}
				case 'Р' :
					{
						$result .= 'R';
						break;
					}
				case 'с' :
					{
						$result .= 's';
						break;
					}
				case 'С' :
					{
						$result .= 'S';
						break;
					}
				case 'т' :
					{
						$result .= 't';
						break;
					}
				case 'Т' :
					{
						$result .= 'T';
						break;
					}
				case 'у' :
					{
						$result .= 'u';
						break;
					}
				case 'У' :
					{
						$result .= 'U';
						break;
					}
				case 'ф' :
					{
						$result .= 'f';
						break;
					}
				case 'Ф' :
					{
						$result .= 'F';
						break;
					}
				case 'х' :
					{
						$result .= 'h';
						break;
					}
				case 'Х' :
					{
						$result .= 'H';
						break;
					}
				case 'ц' :
					{
						$result .= 'ts';
						break;
					}
				case 'Ц' :
					{
						$result .= 'TS';
						break;
					}
				case 'ч' :
					{
						$result .= 'ch';
						break;
					}
				case 'Ч' :
					{
						$result .= 'CH';
						break;
					}
				case 'ш' :
					{
						$result .= 'sh';
						break;
					}
				case 'Ш' :
					{
						$result .= 'SH';
						break;
					}
				case 'щ' :
					{
						$result .= 'sch';
						break;
					}
				case 'Щ' :
					{
						$result .= 'SCH';
						break;
					}
				case 'ъ' :
				case 'Ъ' :
					{
						$result .= '"';
						break;
					}
				case 'ы' :
					{
						$result .= 'y';
						break;
					}
				case 'Ы' :
					{
						$result .= 'Y';
						break;
					}
				case 'ь' :
				case 'Ь' :
					{
						$result .= "'";
						break;
					}
				case 'э' :
					{
						$result .= 'e';
						break;
					}
				case 'Э' :
					{
						$result .= 'E';
						break;
					}
				case 'ю' :
					{
						$result .= 'yu';
						break;
					}
				case 'Ю' :
					{
						$result .= 'YU';
						break;
					}
				case 'я' :
					{
						$result .= 'ya';
						break;
					}
				case 'Я' :
					{
						$result .= 'YA';
						break;
					}
				default :
					{
						$result .= $letter;
						break;
					}
			} // switch
		} // for
		
		return $result;
	} // \function
		  // \Транслитерация//
} // \class

?>