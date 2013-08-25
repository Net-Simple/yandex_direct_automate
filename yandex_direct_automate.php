<?php
///////////////////////////////////////////////////////////////////////
/// Project: �������������� ���������� �������� Yandex.direct       ///
/// Name: ����� � ������� ������ �������                            ///
/// Version: 1.1.1                                                  ///
/// Author: ��������� ��������� �������������                       ///
/// License: GNU General Public License                   			///
/// Url: http://cloud-automate.ru                                   ///
/// Email: info@cloud-automate.ru                                   ///
/// Requirements: PHP >= 5.2.0                                      ///
/// Charset: WINDOWS-1251                                           ///
/// Last modified: 23.08.2013 13:57                                 ///
///////////////////////////////////////////////////////////////////////
class Yandex_direct_automate // ����� ��������������� ���������� ��������
                             // Yandex.direct
{
	private $CONFIG = array (); // ���������������� ������
	private $ERRORS = array (); // ������ ������
	private $REPORTS = array (); // ������ �������
	private $LOG = array (); // ������ �������
	private $http = true;
	private $path = '';
	private $updateprices_max = 1000;
	private $updateprices_call_max = 3000;
	
	// ����������� ������//
	function __construct() {
		GLOBAL $YANDEX_DIRECT_AUTOMATE;
		//������ �� oAuth
		$YANDEX_DIRECT_AUTOMATE["oauth"] = 'https://oauth.yandex.ru/token';
		
		//������ �� API
		$YANDEX_DIRECT_AUTOMATE["api"] = 'https://soap.direct.yandex.ru/json-api/v4/';
		
		//������������� ���� � ����
		$YANDEX_DIRECT_AUTOMATE['log'] = '/yandex_direct_automate_log.txt';
		
		$this->CONFIG = $YANDEX_DIRECT_AUTOMATE;
		$this->http = ($_SERVER ['DOCUMENT_ROOT']) ? true : false;
		$this->path = (! $this->path) ? dirname ( __FILE__ ) : $this->path;				
		
		if (! function_exists ( 'curl_init' ))
			$this->ERRORS [] = '� ��� �� �������� ��������� cUrl';
	} // \function
	  // \����������� ������//
	  
	// �����//
	function report() {
		$REPORTS = $this->REPORTS;
		$ERRORS = $this->ERRORS;
		$reports = "Yandex_direct_automate <br>\r\n�����: <br>\r\n";
		$errors = (count ( $ERRORS ) > 0) ? "������: <br>\r\n" : "";
		
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
		
		// �����������
		if (isset ( $this->CONFIG ['log_summary'] ) && $this->CONFIG ['log_summary']) {
			if (file_exists ( $this->path . $this->CONFIG ['log'] ) && DATE ( 'd' ) != DATE ( 'd', filectime ( $this->path . $this->CONFIG ['log'] ) ))
				@unlink ( $this->path . $this->CONFIG ['log'] );
			
			if (! file_exists ( $this->path . $this->CONFIG ['log'] ))
				$content = "���� � ����� ��������\t����� ��������\t�������� ��������\t����� ����������\t��������� ����������\t����� �����\t��������� �����\t���� �� ���� �� ���������\t���� 1-�� ��������������\t���� � ��������������\t���� 1-�� �����\t���� � ��������\t���� �� ���� ����� ���������\r\n";
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
	  // \�����//
	  
	// �������������� ������� � ��������� ������//
	function automate() {
		$PHRASES = array ();
		
		// ��������� oAuth ������//
		
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
				$this->ERRORS [] = '�� ������� �������� ��������������� oAuth �����!';
		
		// \��������� oAuth ������//
		
		// �������� ������ ��������//
		$PARAMS = array (
				$this->CONFIG ['login'] 
		);
		$GetCampaignsList = $this->send ( 'GetCampaignsList', $PARAMS );
		
		for($i = 0; $i < count ( $GetCampaignsList->data ); $i ++) {
			if ($GetCampaignsList->data [$i]->StatusShow == 'Yes') {
				if (isset ( $GetCampaignsList->data [$i]->Name ))
					$GetCampaignsList->data [$i]->Name = str_replace ( '"', '', iconv ( 'UTF-8', 'WINDOWS-1251', $GetCampaignsList->data [$i]->Name ) );
					
					// �������� ������ ���� ��������//
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
					  
				// \�������� ������ ���� ��������//
			} // \if
		} // \for
		  // \�������� ������ ��������//
		  
		// ������������� ������//
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
				
				if ($PARAMS ['price_start'] == - 1) 				// price_start=-1 ������
				                                    // ���������.
				{
					if ($PHRASES [$i] ['PremiumMax'] <= 1)
						$price = $PHRASES [$i] ['PremiumMax'];
					elseif ($PHRASES [$i] ['PremiumMin'] / $PHRASES [$i] ['Max'] >= 2.5)
						$price = $PHRASES [$i] ['Max'];
					else
						$price = $PHRASES [$i] ['PremiumMin'];
				} elseif ($PARAMS ['price_start'] == 5) 				// price_start=3 �����������
				                                        // ���� �������������� ���
				                                        // ��������.
				{
					$price = $PHRASES [$i] ['Min'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['Max'] - $PHRASES [$i] ['Min']) * $PARAMS ['price_percent'] / 100;
					$price1 = $PHRASES [$i] ['PremiumMin'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100;
						if ( (($price1-$price)/$price) * 100 <= $PARAMS ['price_difference_percent'])
							$price = $price1;
				} elseif ($PARAMS ['price_start'] == 4) 				// price_start=3 �����������
				                                        // ���� �������������� ���
				                                        // ��������.
				{
					$price = $PHRASES [$i] ['PremiumMin'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100;
					if ($price > $PARAMS ['price_max'])
						$price = $PHRASES [$i] ['Min'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['Max'] - $PHRASES [$i] ['Min']) * $PARAMS ['price_percent'] / 100;
				} elseif ($PARAMS ['price_start'] == 3) 				// price_start=3 �����������
				                                        // ���� �������������� ���
				                                        // ��������.
				{
					$price = min ( $PHRASES [$i] ['PremiumMin'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100, $PHRASES [$i] ['Min'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['Max'] - $PHRASES [$i] ['Min']) * $PARAMS ['price_percent'] / 100 );
				} elseif ($PARAMS ['price_start'] == 2) 				// price_start=2
				                                        // ��������������
				                                        // ��� 1 ����� ��������.
				{
					$price = $PHRASES [$i] ['PremiumMin'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100;
					
					if ($PHRASES [$i] ['Max'] > 0 && $price > $PHRASES [$i] ['Max'])
						if ((($price - $PHRASES [$i] ['Max']) * 100) / $PHRASES [$i] ['Max'] >= $PARAMS ['price_difference_percent'])
							$price = $PHRASES [$i] ['Max'] + $PARAMS ['price_add'];
				} elseif ($PARAMS ['price_start'] == 1) 				// price_start=1
				                                        // ��������������
				{
					$price = $PHRASES [$i] ['PremiumMin'] + $PARAMS ['price_add'] + ($PHRASES [$i] ['PremiumMax'] - $PHRASES [$i] ['PremiumMin']) * $PARAMS ['price_percent'] / 100;
					if ($price < $PHRASES [$i] ['PremiumMin'])
						$price = $PHRASES [$i] ['PremiumMin'];
				} else 				// price_start=0 ��������
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
				
				$this->REPORTS [] = "��������: {$PHRASES[$i]['CampaignID']}, ����������: {$PHRASES[$i]['BannerID']} - {$PHRASES[$i]['Title']}, �����: {$PHRASES[$i]['PhraseID']} - {$PHRASES[$i]['Phrase']}, ���� �� ���� = {$price}";
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
				$this->ERRORS [] = "UpdatePrices ������: {$UpdatePrices->error_str}";
			} // \if
		} // \for
			  // \������������� ������//
	} // \function
	  // \�������������� ������� � ��������� ������//
	  
	// ������� ������//
	function send($method, $PARAMS) 	// ������� ������
	{
		$RETURN = array ();
		if (isset ( $method ) && isset ( $PARAMS )) {
			
			// ������������ �������
			$request = array ();
						
			$request ['token'] = $this->CONFIG ['token'];
			$request ['application_id'] = $this->CONFIG ['client_id'];
			$request ['login'] = $this->CONFIG ['login'];			
			$request ['locale'] = 'ru';
			$request ['method'] = $method;
			$request ['param'] = $PARAMS;
			
			// �������������� � JSON-������
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
				$this->ERRORS [] = "�� ������� ������� �����: {$this->CONFIG['api']}. � ��� ��������� allow_url_fopen=0 � php.ini ��� ��������� ���������� php_openssl";
			$RETURN = json_decode ( $result );
		}
		if (isset ( $RETURN->error_str )) {
			$RETURN->error_str = str_replace ( '"', '', iconv ( 'UTF-8', 'WINDOWS-1251', $RETURN->error_str ) );
			$this->ERRORS [] = "������ {$method}: " . $RETURN->error_str;
		}
		return $RETURN; // ���������� �����
	} // \function
	  // \������� ������//
	  
	// ��������������//
	function translit($string) {
		$len = strlen ( $string );
		$result = "";
		for($i = 0; $i < $len; $i ++) {
			$letter = substr ( $string, $i, 1 );
			switch ($letter) {
				case '�' :
					{
						$result .= 'a';
						break;
					}
				case '�' :
					{
						$result .= 'A';
						break;
					}
				case '�' :
					{
						$result .= 'b';
						break;
					}
				case '�' :
					{
						$result .= 'B';
						break;
					}
				case '�' :
					{
						$result .= 'v';
						break;
					}
				case '�' :
					{
						$result .= 'V';
						break;
					}
				case '�' :
					{
						$result .= 'g';
						break;
					}
				case '�' :
					{
						$result .= 'G';
						break;
					}
				case '�' :
					{
						$result .= 'd';
						break;
					}
				case '�' :
					{
						$result .= 'D';
						break;
					}
				case '�' :
					{
						$result .= 'e';
						break;
					}
				case '�' :
					{
						$result .= 'E';
						break;
					}
				case '�' :
					{
						$result .= 'e';
						break;
					}
				case '�' :
					{
						$result .= 'E';
						break;
					}
				case '�' :
					{
						$result .= 'zh';
						break;
					}
				case '�' :
					{
						$result .= 'ZH';
						break;
					}
				case '�' :
					{
						$result .= 'z';
						break;
					}
				case '�' :
					{
						$result .= 'Z';
						break;
					}
				case '�' :
					{
						$result .= 'i';
						break;
					}
				case '�' :
					{
						$result .= 'I';
						break;
					}
				case '�' :
					{
						$result .= 'I';
						break;
					}
				case '�' :
					{
						$result .= 'i';
						break;
					}
				case '�' :
					{
						$result .= 'k';
						break;
					}
				case '�' :
					{
						$result .= 'K';
						break;
					}
				case '�' :
					{
						$result .= 'l';
						break;
					}
				case '�' :
					{
						$result .= 'L';
						break;
					}
				case '�' :
					{
						$result .= 'm';
						break;
					}
				case '�' :
					{
						$result .= 'M';
						break;
					}
				case '�' :
					{
						$result .= 'n';
						break;
					}
				case '�' :
					{
						$result .= 'N';
						break;
					}
				case '�' :
					{
						$result .= 'o';
						break;
					}
				case '�' :
					{
						$result .= 'O';
						break;
					}
				case '�' :
					{
						$result .= 'p';
						break;
					}
				case '�' :
					{
						$result .= 'P';
						break;
					}
				case '�' :
					{
						$result .= 'r';
						break;
					}
				case '�' :
					{
						$result .= 'R';
						break;
					}
				case '�' :
					{
						$result .= 's';
						break;
					}
				case '�' :
					{
						$result .= 'S';
						break;
					}
				case '�' :
					{
						$result .= 't';
						break;
					}
				case '�' :
					{
						$result .= 'T';
						break;
					}
				case '�' :
					{
						$result .= 'u';
						break;
					}
				case '�' :
					{
						$result .= 'U';
						break;
					}
				case '�' :
					{
						$result .= 'f';
						break;
					}
				case '�' :
					{
						$result .= 'F';
						break;
					}
				case '�' :
					{
						$result .= 'h';
						break;
					}
				case '�' :
					{
						$result .= 'H';
						break;
					}
				case '�' :
					{
						$result .= 'ts';
						break;
					}
				case '�' :
					{
						$result .= 'TS';
						break;
					}
				case '�' :
					{
						$result .= 'ch';
						break;
					}
				case '�' :
					{
						$result .= 'CH';
						break;
					}
				case '�' :
					{
						$result .= 'sh';
						break;
					}
				case '�' :
					{
						$result .= 'SH';
						break;
					}
				case '�' :
					{
						$result .= 'sch';
						break;
					}
				case '�' :
					{
						$result .= 'SCH';
						break;
					}
				case '�' :
				case '�' :
					{
						$result .= '"';
						break;
					}
				case '�' :
					{
						$result .= 'y';
						break;
					}
				case '�' :
					{
						$result .= 'Y';
						break;
					}
				case '�' :
				case '�' :
					{
						$result .= "'";
						break;
					}
				case '�' :
					{
						$result .= 'e';
						break;
					}
				case '�' :
					{
						$result .= 'E';
						break;
					}
				case '�' :
					{
						$result .= 'yu';
						break;
					}
				case '�' :
					{
						$result .= 'YU';
						break;
					}
				case '�' :
					{
						$result .= 'ya';
						break;
					}
				case '�' :
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
		  // \��������������//
} // \class

?>