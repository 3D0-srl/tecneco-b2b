<?php
class Tools{

	public static function checkQuantity($id_product,$qnt=1){
		$database = _obj('Database');
		$data = $database->select('*','product_inventory',"id_product={$id_product}");
		$data = $data[0];
		if( $data['quantity'] < $qnt ){
			$response = array(
				'success' => false,
				'error' => 'Quantità massima '.$data['quantity'],
				'max_qnt' => $data['quantity']
			);
		}else{
			$response = array(
				'success' => true,
				'max_qnt' => $data['quantity']
			);
		}

		return $response;
	}

	public static function buildPrice($id_user,$id_or_sku,$quantita=1,$nopromo=false){
		
		$database = _obj('Database');
		$data = $database->select('*','b2b_cliente',"id_user={$id_user}");
		
		if( (int)$id_or_sku ){
			$product = $database->select('p.*,t.percentage as tax','(product as p join product_shop_values as s on s.id_product=p.id) join tax as t on t.id=s.id_tax',"p.id={$id_or_sku}");
		}else{
			$product = $database->select('p.*,t.percentage as tax','(product as p join product_shop_values as s on s.id_product=p.id) join tax as t on t.id=s.id_tax',"p.sku='{$id_or_sku}'");
		}
		
		if( okArray($product) ){
			$product = $product[0];
			$prezzo = $database->select('*','price',"product={$product['id']} AND label = 'default'");
			if( okArray($prezzo) ){
				$prezzo = $prezzo[0]['value'];
			}
			//debugga($prezzo);exit;
		}else{
			return false;
		}

		
		
		
		$data = $data[0];
		if( !okArray($data) ) return false;
		$codice_cliente = $data['codice_gestionale'];
		$codice_listino_cliente = $data['codice_listino_cliente'];
		$codice_listino = $data['codice_listino'];

		$codice_articolo = $product['sku'];
		$codice_listino_articolo = $product['codice_listino_articolo'];
	
		$iva = $product['tax']/100;
		
		
		$data_user = array(
			'codice_listino' =>  $data['codice_listino'],
			'codice_listino_cliente' =>  $data['codice_listino_cliente'],
			'codice_cliente' =>  $data['codice_gestionale'],
			'sconto1' => $data['sconto1'],
			'sconto2' => $data['sconto2'],

		);
		$data_product = array(
			'codice_articolo' =>  $product['sku'],
			'codice_listino_articolo' =>  $product['codice_listino_articolo'],
			'iva' =>  $iva,
			'prezzo' => $prezzo,

		);
		
		return self::getPrice(
			$data_user,
			$data_product,
			$quantita,
			$nopromo
		);
		
	}


	public static function getPrice(
		$data_user = array(),
		$data_product = array(),
		$quantita = 1,
		$nopromo=false
		){
		
		
		$database = _obj('Database');
		//creazione delle condizione
		
		//$prezzo = $data_product['prezzo'];
		$iva = $data_product['iva'];
		$listini = self::getListini(
			$data_user['codice_cliente'],
			$data_user['codice_listino'],
			$data_user['codice_listino_cliente'],
			$data_product['codice_articolo'],
			$data_product['codice_listino_articolo'],
			$nopromo
			);
		
		
		if( okArray($listini) ){
			foreach( $listini as $l){
				$listini_tmp[$l['tipologia']][] = $l;
			}
		}
		if( !function_exists('cmp_listini') ){
			function cmp_listini($a, $b) {
				if ($a['priorita'] == $b['priorita']) {
					return 0;
				}
				return ($a['priorita'] > $b['priorita']) ? -1 : 1;
			}
		}

		foreach($listini_tmp as $tipologia =>$values){
			uasort($values, 'cmp_listini');
			$listini_tmp[$tipologia] = $values;
		}
		
		
		

		unset($listini);
		//scansiono i gruppi di listini (listini raggruppati per categoria listino)
		foreach($listini_tmp as $k => $v){
			
			
			$candidato = null;
			//scansiono i listini appartenenti alla stessa categoria. Prendo il prezzo del primo listino (quindi con priorità più alta) che sia diverso dalla campagna 900
			foreach($v as $v1){
				
				if( $v1['campagna'] == '900'){
					$campagna_900 = $v1;
					continue;
				}
				
				if( $v1['quantita'] > $quantita ){
					
					continue;
				}else{
					
						
					if( !$candidato ){
						$candidato = $v1;
					}else{
						if( $candidato['priorita'] == $v1['priorita'] && $candidato['quantita'] < $v1['quantita']){
							
							$candidato = $v1;
						}
					}
					
				}
				
			}
			if($candidato ) {
				$listini[$k] = $candidato;
			}
			
			
		}
			
		
		

		$prezzo =0;
		$sconto = array();
		uasort($listini, 'cmp_listini');
		$listini = array_values($listini);
		
		
		
			
		foreach($listini as $k => $v){
			if( in_array($v['campagna'],array('095','100','200')) ){
				$mod_prezzi[$v['campagna']]['dati'] = $v;
				$mod_prezzi[$v['campagna']]['indice'] = $k;
			}
		}
		
		$min = 0;
		foreach($mod_prezzi as $v){
			if( !$min ){
				$min = $v['dati']['prezzo'];
				$indice = $v['indice'];
			}else{
				if( $v['dati']['prezzo'] < $min ){
					$min = $v['dati']['prezzo'];
					$indice = $v['indice'];
				}
			}
		}
		if( $indice > 0 ){
			$_tmp = $listini[0];
			$listini[0] = $listini[$indice];
			$listini[$indice] = $_tmp;
		}
		//debugga($listini);exit;
		
		$quantita_omaggio = 0;
		$quantita_totale = 0;
		
		foreach($listini as $iter => $v){
			
			if( $v['sconto1'] && $v['sconto1'] != -1){ 
				$sconto[1] = $v['sconto1'];
			}elseif( $v['sconto2'] == -1 ){
				unset($sconto[1]); 
			}

			if( $v['sconto2'] && $v['sconto2'] != -1){ 
				$sconto[2] = $v['sconto2'];
			}elseif( $v['sconto2'] == -1 ){
				unset($sconto[2]); 
			}


			if( $v['sconto3'] && $v['sconto3'] != -1){ 
				$sconto[3] = $v['sconto3'];
			}elseif( $v['sconto3'] == -1 ){
				unset($sconto[3]); 
			}


			if( $v['sconto4'] && $v['sconto4'] != -1){ 
				$sconto[4] = $v['sconto4'];
			}elseif( $v['sconto4'] == -1 ){
				unset($sconto[4]); 
			}


			if( $v['sconto5'] && $v['sconto5'] != -1){ 
				$sconto[5] = $v['sconto5'];
			}elseif( $v['sconto5'] == -1 ){
				unset($sconto[5]); 
			}

			//se trovo il prezzo lo memorizzo
			if( $v['prezzo'] && $v['prezzo'] != 9999999999.99 && $v['prezzo'] != 9999999999.9999 && $v['prezzo'] != -1){ 
				if( $v['cliente'] == 'ITALIA' || strtoupper($v['cliente']) == 'RHIAG' ){
					$prezzo_italia = $v['prezzo'];
				}
				$prezzo = $v['prezzo'];
			}

			if( $v['quantita_omaggio'] ){
				$quantita_omaggio = $v['quantita_omaggio'];
				$quantita_totale = $v['su_quantita_totale'];
			}
			
			//verifico se è una campagna			
			if( trim($v['campagna']) && !okArray($sconto_campagna) ){
				$campagna = trim($v['campagna']);
				//verifico se è una campagna di sconto
				if( !$prezzo ){ 
					$campagna_tipo = 'sconto';
					$sconto_campagna = $sconto;
				}else{
					$campagna_tipo = 'prezzo';
					break;
				}
			}else{
				if( $prezzo ){ 
					if( okArray($sconto_campagna) ){
						$sconto = $sconto_campagna;
					}
					break;
				}
			}
		}

		

		$sconto_aggiuntivo = 0;
		if( $campagna == 200 ){
			//per la campagna 200 verifico se l'utente ha degli sconti
			if( (float)$data_user['sconto1'] ){

				$sconto[1] = ((float)$data_user['sconto1'])/10;
			}
			if( (float)$data_user['sconto2'] ){
				$sconto[2] = ((float)$data_user['sconto2'])/10;
			}


			if( okArray($campagna_900) ){
				if( (float)$data_user['sconto2'] ){
					unset($sconto[2]); //modifica del 2021-06-03
					//$sconto[2]  = '';
				}
				if( $campagna_900['sconto1'] ){
					$sconto[1] = ((float)$campagna_900['sconto1'] );
				}
				if( $campagna_900['sconto2'] ){
					$sconto[2] = ((float)$campagna_900['sconto2'] );
				}
				if( $campagna_900['sconto3'] ){
					$sconto[3] = ((float)$campagna_900['sconto3'] );
				}
				if( $campagna_900['sconto4'] ){
					$sconto[4] = ((float)$campagna_900['sconto4'] );
				}
				if( $campagna_900['sconto5'] ){
					$sconto[5] = ((float)$campagna_900['sconto5'] );
				}
			}

			foreach($sconto as $v){
				if( $v ){
					$sconto_aggiuntivo = 1;
				}
			}
			
			
		}


		

		if( !$prezzo_italia ){
			foreach($listini as $iter2 => $v){
				if( $iter2 > $iter ){
					if( $v['cliente'] == 'ITALIA' || strtoupper($v['cliente']) == 'RHIAG' ){
						$prezzo_italia = $v['prezzo'];
						break;
					}
				}
			}
			
		}
		
		

		$prezzo_finale = $prezzo;
		for($i = 1; $i<=5; $i++){
			if( array_key_exists( $i ,$sconto )){
				$scontovalore = $sconto[$i]/100;
				if( $scontovalore ){
					$prezzo_finale = $prezzo_finale - $prezzo_finale*$scontovalore;
				}
			}
		}
		
		
		
		
		$prezzo_data = array(
			'prezzo_base' => $prezzo,
			'prezzo_italia' => $prezzo_italia,
			'iva' => $iva,
			'prezzo'  => $prezzo_finale+$prezzo_finale*$iva,
			'prezzo_senza_iva'  => $prezzo_finale,
			//'prezzo' => $prezzo,
			'campagna' => $campagna,
			'campagna_tipo' => $campagna_tipo,
			'sconto_utente' => $sconto_aggiuntivo,
			'quantita_omaggio' => $quantita_omaggio,
			'quantita_totale' => $quantita_totale
		);
		
		

		
		
		if( okArray($sconto) ){
			$prezzo_data['sconti_array'] = $sconto;
			foreach($sconto as $k => $v){
				$prezzo_data["sconto{$k}"] = $v;
			}
		}

		
		for($ind=1;$ind<=5;$ind++){
			if(!isset($prezzo_data['sconto'.$ind])){
				$prezzo_data['sconto'.$ind]='';
			}
		}

		
		return $prezzo_data;
		
	}


	public static function getListini(
		$codice_cliente,
		$codice_listino,
		$codice_listino_cliente,
		$codice_articolo,
		$codice_listino_articolo,
		$nopromo=false	
	){
		
		$database = _obj('Database');
		//creazione delle condizione
	
		$appoggio = array(
			'codice_cliente' => $codice_cliente,
			'codice_listino' => $codice_listino,
			'codice_listino_cliente' => $codice_listino_cliente,
			'codice_articolo' => $codice_articolo,
			'codice_listino_articolo' => $codice_listino_articolo,
		);

		
		
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
		if( $nopromo ){
			$where .= "campagna <> '095' AND datainizio <= '{$now}' AND (";
		}else{
			$where .= "datainizio <= '{$now}' AND (";
		}
		
		
		
		//SOLO PER LA CAMPAGNA 095
		if(strlen(trim($codice_listino_cliente)) > 2 ){
			$codice_listino_cliente_campagna_095 = substr($codice_listino_cliente, -2);
			
		}
		
		foreach($tabella_regole as $tipologia => $regola){
			$nome_campo1 = trim($regola[0]);
			$nome_campo2 = trim($regola[1]);
			$val1 = $appoggio[$nome_campo1]; 
			$val2 =  $appoggio[$nome_campo2];
		

			if( $tipologia == 1 ){
				$where .= "tipologia = 1 OR ";
			}else{
				
				
				
				if( $regola[2] ){
					
				
					
					if( $val1  && $val2 ){
						
						$where .= "(tipologia = {$tipologia} AND cliente='{$val1}' AND articolo='{$val2}') OR ";
					}
					
				}else{
					
					if( $val1  ){
						$where .= "(tipologia = {$tipologia} AND cliente='{$val1}') OR ";

					}elseif($val2 ){
						$where .= "(tipologia = {$tipologia} AND  articolo='{$val2}') OR ";

					}
				}
				if( $codice_listino_cliente_campagna_095 ){
					if( $tipologia == '7' || $tipologia == '8' || $tipologia == '9'){
							
						if( $regola[2] ){
							if( $val1  && $val2  ){
								
								$where .= "(tipologia = {$tipologia} AND cliente='{$codice_listino_cliente_campagna_095}' AND articolo='{$val2}' AND campagna = '095') OR ";
							}
							
						}else{
							
							if( $val1  ){
								$where .= "(tipologia = {$tipologia} AND cliente='{$codice_listino_cliente_campagna_095}' AND campagna = '095') OR ";

							}
						}
					}
				}

			}
		}
		
		
		$where = preg_replace('/OR $/',')',$where);
		
		$listini = $database->select('*','b2b_listini',"{$where} order by quantita asc, priorita desc");
		if( isCiro()){
			//debugga($listini);exit;
		}
		return $listini;

	}
}

?>