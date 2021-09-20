<?php
class MotorplanController extends FrontendController{
	


	function display(){
		


		header('Content-Type: application/xml');
		
		$database = _obj('Database');


		$user = _var('user');
		$password = _var('password');
		$brand = _var('brand');
		$codart = _var('code');
		$qty = _var('qty');
		$stores = _var('stores');
		$extId = _var('extId');


		if( !$qty ){
			$qty = 1;
		}
		/*

		$user = '07259671217';
		$codart = 'OL 304';
		$qty = 10;


		$password = encryptIt('0401002441');
		*/

		//controllo credenziali
		$password = decryptIt2($password);


		
		$res = User::login($user, $password);

		//$array_qnt = array(10,20,30);


		if( $token ){
			header('Location: https://ecommerce.tecneco.com/home/'.$codart."/".$qty);
			//header('Location: https://ecommerce.tecneco.com/home/'.$codart."/".$qty);	
		}
		//autenticazione riuscita
		if( okArray($res) ){
			$_SESSION['userdata'] = $res;
			

			$codart2 = strtolower($codart);
			$prodotto = $database->select('b.codice_listino_articolo,b.codice,codice_gestionale,b.quantita as quantita_gestionale,p.img,m.descrizione as modello,p.codart,p.tipo,p.info_prodotto_auto','(prodotti_b2b as b join prodotti as p on upper(p.codart)=upper(b.codice_sito)) left outer join modello_filtro as m on coalesce(p.idmodello,1) = m.codice',"b.eliminato='f' AND (lower(codice_gestionale) = '{$codart2}' OR lower(codice_sito) = '{$codart2}')");
			if( okArray($prodotto) ){
				$prodotto = $prodotto[0];
				
				$array_qnt = $this->quantita_offerte($prodotto);

				if( !okArray($array_qnt) ){
					$array_qnt[] = 1;
				}
				$dati = get_prezzo_utente_dati($prodotto,$qty);
				
				
				if( $dati['iva'] ) $dati['iva'] = $dati['iva']*100;

				$dati['codice_gestionale'] = $prodotto['codice_gestionale'];
				$dati['quantita_gestionale'] = $prodotto['quantita_gestionale'];
				$dati['tipo'] = $prodotto['tipo'];
			
				if( okArray($array_qnt) ){
					foreach($array_qnt as $v){
						$tmp = get_prezzo_utente_dati($prodotto,$v);
						
						$unita_omaggio = (int)($v/$tmp['quantita_totale']);
						$dati['offerte'][] = array(
							'price' => $tmp['prezzo_senza_iva'],
							'qty' => $v,
							'omg' => $tmp['quantita_omaggio']*$unita_omaggio

						);
						

					}
				}
			
			}else{
				$errore = 3;
			}


		}else{
			$errore = 1;
		}


		if( $errore ){
			$template->errore = $errore;

		}else{
			$template->token = creazione_token();
			$template->dati = $dati;
		}

		$template->output('richiesta.xml');





		function quantita_offerte($prodotto){
			$database = get_object('DataBase');
			$codice_cliente = $_SESSION['userdata']['codice_gestionale'];
			$codice_listino_cliente = $_SESSION['userdata']['codice_listino_cliente'];
			$codice_listino = $_SESSION['userdata']['codice_listino'];
			$codice_articolo = $prodotto['codice_gestionale'];
			$codice_listino_articolo = $prodotto['codice_listino_articolo'];
			$prezzo = $prodotto['prezzo'];

			
			$iva =  $_SESSION['userdata']['codiceiva']/100;
			if( !$iva ){
				$iva = $prodotto['iva']/100;
			}
			
			$tabella_regole = array(
				'1' => array('',''),
				'2' => array('','codice_listino_articolo'),
				'3' => array('','codice_articolo'),
				'4' => array('codice_listino',''),
				'5' => array('codice_listino','codice_listino_articolo',true),
				'6' => array('codice_listino','codice_articolo',true),
				'7' => array('codice_listino_cliente',''),
				'8' => array('codice_listino_cliente','codice_listino_articolo',true),
				'9' => array('codice_listino_cliente','codice_articolo',true),
				'10' => array('codice_cliente',''),
				'11' => array('codice_cliente','codice_listino_articolo',true),
				'12' => array('codice_cliente','codice_articolo',true),
			);

			$now = date('Y-m-d');
			//$where .= "datainizio <= '{$now}' AND quantita <= {$quantita} AND (";
			$where .= "datainizio <= '{$now}' AND (";
			
			foreach($tabella_regole as $tipologia => $regola){
				if( $tipologia == 1 ){
					$where .= "tipologia = 1 OR ";
				}else{
					
					$$regola[0] = trim($$regola[0]);
					$$regola[1] = trim($$regola[1]);
					if( $regola[2] ){
						if( $$regola[0] && $$regola[1] ){
							
							$where .= "(tipologia = {$tipologia} AND cliente='{$$regola[0]}' AND articolo='{$$regola[1]}') OR ";
						}
						
					}else{
						
						if(  $$regola[0] ){
							$where .= "(tipologia = {$tipologia} AND cliente='{$$regola[0]}') OR ";

						}elseif( $$regola[1] ){
							$where .= "(tipologia = {$tipologia} AND  articolo='{$$regola[1]}') OR ";

						}
					}

				}
			}
			
			$where = preg_replace('/OR $/',')',$where);
			
			$listini = $database->select('distinct quantita','listini',"{$where} order by quantita ASC");
			$listini2 = $database->select('distinct su_quantita_totale','listini',"{$where} AND su_quantita_totale IS NOT NULL AND su_quantita_totale > 0 order by su_quantita_totale ASC");
			
			$toreturn = array();
			if( okArray($listini) ){
				foreach($listini as $v){
					if( $v['quantita'] != 1 ){
						$toreturn[] = $v['quantita'];
					}
				}
				
			}
			if( okArray($listini2) ){
				foreach($listini2 as $v){
					if( $v['su_quantita_totale'] != 1 ){
						$toreturn[] = $v['su_quantita_totale'];
					}
				}
			}

			return array_values($toreturn);



		}


	}
}


?>