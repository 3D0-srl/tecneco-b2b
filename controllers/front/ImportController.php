<?php
ini_set('memory_limit','1024M');
ini_set('max_execution_time', 8000);
class ImportController extends FrontendController{
	public $_twig = true;
	public $path_sincro = 'modules/b2b/ftp/files/';
	public $path_fatture = 'modules/b2b/ftp/fatture/';
	public $_IMPORT_CLIENTI = false;
	public $_IMPORT_ARTICOLI = false;
	public $_IMPORT_LISTINI = false;
	public $_IMPORT_ORDINI = false;
	public $_IMPORT_FATTURE = false;


	public $configurazione_b2b = array();
	public $tracciati = array();



	private $taxes = [];
	private $dbOld = null;


	private $get_old_credetials = false;
	

	private $job;
	

	function display(){
		$this->getDbOld();
		$database = _obj('Database');

		if( $type = _var('type') ){

		}else{
			if( _var('cron') ){
				$check = $database->select('*','b2b_import_job',"eseguito = 0 order by timestamp limit 1");
				
				if( okArray($check) ){
					if( $check[0]['stato'] != 'IN CODA' ){
						exit;
					}
					$this->job = $check[0];
					$type = $this->job['type'];
				}else{
					exit;
				}
			}else{
				exit;
			}
		}
		
		if( $type ){
			$name = "_IMPORT_".strtoupper($type);
			$this->$name = true;
		}
		

		if( Marion::getConfig('b2b','aggiornamento') ){ 
			debugga('sincronizzazione in corso');
			exit;
		}

		
		
		$this->loadConfig();
		
		$this->start();
		

		$this->process();

		
		
		$this->end();
		
		debugga('finito');
	}

	function start(){
		//if( !isCiro()){
			Marion::setConfig('b2b','aggiornamento',1);
		//}
		if( $this->job ){
			$database = _obj('Database');
			$date = date('Y-m-d H:i');
			$database->update('b2b_import_job',"id={$this->job['id']}",array('stato' => 'IN PROCESSAZIONE','startDate'=> $date));
		}
	}

	function end(){
		Marion::setConfig('b2b','aggiornamento',0);

		if( $this->job ){
			$database = _obj('Database');
			$date = date('Y-m-d H:i');
			$database->update('b2b_import_job',"id={$this->job['id']}",array('stato' => 'TERMINATO','eseguito'=> 1,'endDate'=> $date));
		}
	}

	function process(){
		
		mb_internal_encoding('utf-8'); // @important 

		if( $this->_IMPORT_CLIENTI ){
			$this->import_clienti(); // da completare
		}
		if( $this->_IMPORT_ARTICOLI ){
			$this->import_articoli(); //ok
		}
		if( $this->_IMPORT_LISTINI ){
			$this->import_listini(); //ok
		}
		if( $this->_IMPORT_ORDINI ){
			$this->import_ordini(); // da fare
		}
		if( $this->_IMPORT_FATTURE ){
			$this->import_fatture(); // da fare
		}
	}

	function import_fatture(){
	
		//if( !isValerio()) exit;
		
		$database = _obj('Database');
		$dir = $this->path_fatture;
		
		
		$list = scandir($dir);

		
		
		$old = $database->select('path','b2b_fattura',"1=1");
		foreach( $old as $v ){
			$fatture_vecchie[] = $v['path'];
		}
		//unset($fatture_vecchie);
		//debugga($fatture_vecchie);
		foreach($list as $v){
			
				
			if( $v == '.' || $v == '..' || $v == '.ftpquota') continue;
			
			$ext = explode('.',$v);
			$file = "{$dir}/".$v;

			if( in_array($file,$fatture_vecchie) ) continue;
			$pathinfo = pathinfo($file);
			
			
			if( is_file($file) && $v != '.ftpquota'){
				$riga = array(
					'nome' => preg_replace('/-([0-9A-Za-z]+)/','',$pathinfo['filename']),
					'path' => "{$dir}/".$v,
					'ext' => $pathinfo['extension'],
					'data' => strftime("%Y-%m-%d %H:%M",filemtime($file)),
					'codice_cliente' => preg_replace('/([0-9a-zA-Z]+)-/','',$pathinfo['filename']),
				);
				$tmp3 = explode('_',$riga['codice_cliente']);
				$riga['codice_cliente'] = $tmp3[1];
				$tmp = preg_replace('/([a-z\.]+)/','',strtolower($riga['nome']));
				$tmp2 = explode('_',$tmp);
				$riga['numero'] = $tmp2[1];
				$riga['anno'] = $tmp2[0];
				
				$documenti[] = $riga;
			}
			
		}
		
		
		if( okArray($documenti) ){

			foreach($documenti as $v){
				if( preg_match('/[a-zA-Z\-_]/',$v['numero']) ){
					continue;
				}

				
				
				$utente = $database->select('c.*,u.company','b2b_cliente as c join user as u on u.id=c.id_user',"codice_gestionale='{$v['codice_cliente']}'");

				debugga($utente);exit;
				if( okArray($utente) ){
					
					$email = $utente[0]['email_fattura'];
					
					$dati['subject'] = "Fattura n.".$v['numero']." dal sito ecommerce.tecneco.com";
					$dati['ragsoc'] = $utente[0]['ragsoc'];
					$dati['fattura'] = $v['nome'];
					$dati['numero'] = $v['numero'];
					$dati['attachment']['isfile'] = 1;
					$dati['attachment']['info_file']['file'] = $v['path'];
					
					if( $email ){
						$res = manda_mail($email, 'amministrazione@tecneco.com', $dati['subject'], 'mail_fattura.htm', 'html_only_message', $dati);
						
						//exit;
						if( $res ){
							$v['inviata'] = 't';
						}else{
							$v['inviata'] = 'f';
						}
						$v['inviata'] = 't';
						$database->insert('fatture_b2b',$v);
						//debugga($database->lastquery);exit;
						sleep(1);
					}
				}
				//debugga($database->lastquery);exit;
			}
		}

		debugga('finito');

		
	}

	function import_clienti(){

		$this->scrivi_log("Inzio importazione clienti",'import','clienti');
		
		$database = _obj('Database');
		$clienti = $this->leggi_tracciato('clienti');
		
		//IMPORTAZIONE DELLE DESTINAZIONI
		$destinazioni = $this->leggi_tracciato('destinazioni');
		
		
		$credenziali = [];
		/*if( $this->get_old_credetials ){
			$credenziali_vecchie = $this->dbOld->select('username,password,codice,codice_gestionale,deleted',"utenti","duplicato='f'");
			foreach($credenziali_vecchie as $v){
				$credenziali[$v['codice_gestionale']] = $v;
			}
		}*/

		

		

		
		
		$classifiche_cliente = $this->leggi_tracciato('classifiche_clienti');
		foreach($classifiche_cliente as $t => $o){
			$dati_clienti_aggiuntivi[trim($o['CodiceChiave'])] = trim($o['Descrizione']);
		}
		
		$old_clienti = $database->select('codice_gestionale,id_user','b2b_cliente',"1=1");
		foreach($old_clienti as $v){
			$old[$v['codice_gestionale']] = $v['id_user'];
			$cod_user[$v['codice_gestionale']][] = $v['id_user'];
		}

		
	
		
		
		$countries = Country::getAll();
		$countries_code = [];
		foreach($countries as $country){
			if( $name_country = $country->get() ) {
				$countries_code[strtoupper($name_country)] = $country->id;
			}
		}
		
		
		foreach($clienti as $v){
			
			if( $v['ragsoc'] ){
				
				$nazione = strtoupper($dati_clienti_aggiuntivi[$v['codice_nazione']]);
				//debugga($nazione);
				$_country = ($countries_code[$nazione])?$countries_code[$nazione]:'';
				if( !$_country && $v['provincia'] ){
					$_country = 'IT';
				}
				//ebugga('qua');exit;
				
				$toinsert_user = array(
					'email' => $v['email'],
					'username' => $v['cf_piva'],
					'password' =>password_hash(trim($v['codice_gestionale']), PASSWORD_DEFAULT),
					'company' => $v['ragsoc'],
					'city' => $v['citta'],
					'address' => $v['indirizzo'],
					'province' => $v['provincia'],
					'vatNumber' => $v['cf_piva'],
					'postalCode' => $v['cap'],
					'country' => $_country
				);

				//modifica per i clienti estero
				if( $toinsert_user['username'] == '00000000000'){
					$toinsert_user['username'] = $v['codice_gestionale'];
				}	

				
				$toinsert_cliente = array(
					
					'codice_gestionale' => $v['codice_gestionale'],
					'codice_gestionale_int' => (int)$v['codice_gestionale'],
					'codice_listino_cliente' => $v['codice_listino_cliente'],
					'codice_listino_cliente2' => $v['codice_listino_cliente2'],
					'codice_listino' => $v['codice_listino'],
					'sconto1' => $v['sconto1'],
					'sconto2' => $v['sconto2'],
					'duplicato' => 0,
					'email_fattura' => $v['email_fattura'],
					'gruppo' => $v['gruppo'],
					//'lat' => $lat,
					//'lng' => $lng,
					'asso' => $v['asso'],
					'info1' => $v['info1'],
					'info2' => $v['info2'],
					'info3' => $v['info3'],
					'codice_agente' => (int)$v['codice_agente'],
					'condizioni_pagamento' => $v['condizioni_pagamento'],
					'condizioni_trasporto' => $v['condizioni_trasporto'],
					'budget_anno' => $v['budget_anno'],


				);

				
				//INSERIMENTO
				if( !$old[$v['codice_gestionale']] ){
					//debugga('inserimento');exit;

					
					
					$toinsert_user['active'] = 1;
					$toinsert_user['deleted'] = 0;
					$id_user = $database->insert('user',$toinsert_user);
					$toinsert_cliente['id_user'] = $id_user;
					
					$database->insert('b2b_cliente',$toinsert_cliente);
					$this->scrivi_log("Inserito ".$v['codice_gestionale'],'import','clienti');
				}else{
					//debugga('update');exit;
					//unset($toinsert_user['username']);
					//unset($toinsert_user['password']);

					/*if( $this->get_old_credetials ){
						$old_credenziali = $credenziali[$v['codice_gestionale']];
						if( okArray($old_credenziali) ){
							$toinsert_user['deleted'] = ($old_credenziali['deleted'] == 't')?1:0;
							$toinsert_user['codice_old_b2b'] = $old_credenziali['codice'];
							$toinsert_user['username'] = $old_credenziali['username'];
							$toinsert_user['password'] = password_hash($old_credenziali['password'], PASSWORD_DEFAULT);

							
						}


					}else{
						unset($toinsert_user['username']);
					    unset($toinsert_user['password']);
					}*/
					unset($toinsert_user['username']);
					unset($toinsert_user['password']);
					unset($toinsert_cliente['duplicato']);
					//debugga($toinsert_cliente);exit;
					
					$id_users = $cod_user[$v['codice_gestionale']];
					if( okArray($id_users) ){
						foreach($id_users as $id_user){
							$database->update('user',"id={$id_user}",$toinsert_user);	
							$this->scrivi_log("Aggiornato {$id_user} relativo al codice cliente ".$v['codice_gestionale'],'import','clienti');
							
						}
						$database->update('b2b_cliente',"codice_gestionale='{$v['codice_gestionale']}'",$toinsert_cliente);
						//$this->scrivi_log("Aggiornato ".$v['codice_gestionale'],'import','clienti');
					}


					/*$id_user = $old[$v['codice_gestionale']];
					$database->update('user',"id={$id_user}",$toinsert_user);
					$database->update('b2b_cliente',"codice_gestionale='{$v['codice_gestionale']}'",$toinsert_cliente);
					
					$this->scrivi_log("Aggiornato ".$v['codice_gestionale'],'import','clienti');*/

				}
				
			}
			
		}

		
		
		
		
		
		$select = $database->select('id_user,codice_gestionale','b2b_cliente');
		foreach($select as $v){
			$map_cli[$v['codice_gestionale']][] = $v['id_user'];
		}

		if( isCiro()){
			//debugga($map_cli);exit;
		}

		
		if( okArray($destinazioni) && count($destinazioni) > 5 ){
			$database->execute("TRUNCATE TABLE  address");
		}
		$this->scrivi_log("Inizio importazione indirizzi");
		foreach($destinazioni as $k => $v){
			if( $k >= 1  ){

				$_clienti = $map_cli[$v['codice_cliente']];
				
				foreach( $_clienti as $_v ){
					$data = array(
						'id_user' => $_v,
						'name' => $v['descrizione'],
						'surname' => $v['descrizione2'],
						'label' => $v['codice_destinazione'],
						'city' => $v['citta'],
						'address' => $v['indirizzo'],
						'province' => $v['provincia'],
						'phone' => $v['telefono1'],
						'cellular' => $v['telefono2'],
						'email' => $v['email'],
						'country' => 'IT'
					);
					$database->insert('address',$data);
					
					$this->scrivi_log("Inserito indirizzo ".$v['codice_cliente']." - {$_v}",'import','clienti');
					//$this->scrivi_log($database->error,'import','indirizzi');
				}
				
			}
		}
		$this->scrivi_log("Fine importazione clienti",'import','clienti');
		

	}
	
	function import_articoli(){
		$this->scrivi_log("inizio importazione ARTICOLI ".date('Y-m-d H:i'),'import','ARTICOLI');
		
		
		$this->getTaxes();
		
		
		
		//lettura degli articoli
		$articoli = $this->leggi_tracciato('articoli');
		
		$database = _obj('Database');
		if( $articoli ){
			//lettura traduzione articoli
			
			//prendo i codici articoli vecchi
			$codici_clienti_old = array();
			$articoli_old = $database->select('id,sku','product',"deleted=0");
			if( okArray($articoli_old) ){
				foreach($articoli_old as $v){
					if( $v['sku'] ){
						$codici_articoli_old[$v['sku']] =$v['id'];
					}
				}
			}
			
			foreach($articoli as $k => $v){
				$v['iva'] = $this->getTax($v['iva']);
				if( trim($v['codice_gestionale']) == '#OP#REP' ) continue;
				if( !array_key_exists($v['codice_gestionale'],$codici_articoli_old) ) {
					
					
					$dati = $v;
					
					$this->insertProduct($dati);
					//$database->insert('product',$dati);
					$this->scrivi_log("inserisco ll'articolo ".$v['codice_gestionale'],'import','ARTICOLI');
					
				
				}else{
					$dati = $v;
					$this->updateProduct($codici_articoli_old[$v['codice_gestionale']],$dati);
					unset($codici_articoli_old[$v['codice_gestionale']]);
					
					$this->scrivi_log("aggiorno la quantita dell'articolo ".$v['codice_gestionale'],'import','ARTICOLI');
				}

			}

			//setto gli articoli non presenti nel file come eliminati
			if(okArray($codici_articoli_old) ){
				foreach($codici_articoli_old as $k => $v){
					//$database->update('prodotti_b2b',"codice_gestionale='{$k}'",array('eliminato'=>'t'));
				}
			}
		}
		//debugga(count($codici_articoli_old));exit;
		$this->scrivi_log("fine importazione ARTICOLI ".date('Y-m-d H:i'),'import','ARTICOLI');

	}

	function import_listini(){

		$this->scrivi_log("inizio importazione LISTINI ".date('Y-m-d H:i'),'import','LISTINI');
		//lettura delle condizioni di listino
		$listini = $this->leggi_tracciato('condizioni_listini');
		//debugga($listini);exit;
		$database = _obj('Database');
		
		if($listini ){
			//elimino i listini cecchi
			$database->delete('b2b_listini','1=1');
			$query_header = "INSERT INTO b2b_listini(";

			$colonne = $database->select('COLUMN_NAME','INFORMATION_SCHEMA.COLUMNS',"TABLE_SCHEMA = 'catalogo_db' AND TABLE_NAME   = 'b2b_listini'");
			
			foreach($colonne as $v){
				if($v['COLUMN_NAME'] != 'id'){
					$campi_tabella[] = $v['COLUMN_NAME'];
					$query_header .= "{$v['COLUMN_NAME']},";
				}
			}
			$query_header = preg_replace('/\,$/',') VALUES ',$query_header);
			
			$query_template = "('%s','%s',%s,%s,%s,%s,%s,%s,'%s','%s',%s,%s,%s,'%s',%s,%s),";
			
			
			$tmp = array();
			
			foreach($listini as $k => $v){
				if( $k >= 2  ){
					
					$nuova_riga = array();
					foreach($campi_tabella as $c){
						$nuova_riga[$c] = $v[$c];
					}
					if( !$nuova_riga['quantita'] ){
						$nuova_riga['quantita'] = 1;
					}

					
					$tmp[] = array_values($nuova_riga);
					
					if( count($tmp) == 100 ){
						
						$new_query = $query_header;
						foreach($tmp as $t){
							
							if( !trim($t[0])){
								continue;
							}
							$new_query .= vsprintf($query_template,$t);
						}
						
						
						$new_query = preg_replace('/\,$/','',$new_query);
						$database->execute($new_query);
						

						$this->scrivi_log($database->conn->error,'import','LISTINI');
						//debugga($database->error);
						//debugga($new_query);exit;
						//$error = pg_last_error($database->dblink);
						if( $error ){
							//debugga($error);
						}
						//sleep(1);
						$tmp = array();
					}
				}
			}
			if( count($tmp) > 0 ){
				
				$new_query = $query_header;
				foreach($tmp as $t){
					if( !trim($t[0])){
						continue;
						//debugga($t);
					}
					$new_query .= vsprintf($query_template,$t);
				}
				$new_query = preg_replace('/\,$/','',$new_query);
				$database->execute($new_query);
				$this->scrivi_log($database->conn->error,'import','LISTINI');
				//$error = pg_last_error($database->dblink);
				if( $error ){
					debugga($error);
				}
				$tmp = array();
			}
		
		}
		$this->scrivi_log("fine importazione LISTINI ".date('Y-m-d H:i'),'import','LISTINI');

	}

	function import_ordini(){
		
		$this->scrivi_log("inizio importazione ORDINI ".date('Y-m-d H:i'),'import','ORDINI');
		//lettura delle condizioni di listino
		$ordini = $this->leggi_tracciato('ordini');
		$database = _obj('Database');
		if( okArray($ordini) ){
			
			foreach($ordini as $v){
				$progressivo = (int)$v['codice_carrello'];
				
				unset($v['codice_carrello']);
				$carrello = $database->select('*','cart',"progressivo={$progressivo}");
				//$carrello = $database->select('*','cart','id=19693');
				
				if( okArray($carrello) ){
					
					$carrello = $carrello[0];
					//aggiornamento stato ordine
					$stato = $v['stato'];
					
					if( $stato ){
						switch($stato){
							case 'ATT':
								$stato = 'in_attesa';
								break;
							case 'PRO':
								$stato = 'elaborazione';
								break;
							case 'SPE':
								$stato = 'sent';
								break;
							default:
								$stato = 'canceled';
								break;
						}
						//se lo stato è lo stesso o è minore dello stato attuale non aggiorno nulla
						if( $stato == $carrello['status']) continue;
						if( $carrello['status'] == 'sent' && in_array($stato,['in_attesa','elaborazione']) ) continue;
						if( $carrello['status'] == 'elaborazione' && in_array($stato,['in_attesa']) ) continue;

						$v['stato'] = $stato;
						if( $v['data_ddt'] == '0000-00-00'){
							unset($v['data_ddt']);
						}
						if( $v['data_fattura'] == '0000-00-00'){
							unset($v['data_fattura']);
						}
						//$v['status'] = $stato;
						unset($v['stato']);

						$check = $database->select('*','cartChangeStatus',"cartId={$carrello['id']} AND status='{$stato}'");
					
						if( !okArray($check) ) {
							
							
							if( $database->update('cart',"id={$carrello['id']}",$v) ){
								$v['status'] = $stato;
								$database->update('cart',"aggiunto_a={$carrello['id']}",$v);
								
								$this->scrivi_log("aggiornato lo stato del carrello {$carrello['id']} ".date('Y-m-d H:i'),'import','ORDINI');
								$cart = Cart::withId($carrello['id']);
								$cartStatus = CartStatus::withLabel($stato);
								

								ob_start();
								$this->setVar('cart',$cart);
								$this->setVar('status',$cartStatus);
								$this->output('mail/cambio_stato.htm');
								$html = ob_get_contents();
								ob_end_clean();

								


								$cart->changeStatus($stato,null,$html);
								
							}
							

							
						}
		
	

					}
				}
			}
		}

		$this->scrivi_log("fine importazione ORDINI ".date('Y-m-d H:i'),'import','ORDINI');

	}


	function getTaxes(){
		$database = _obj('Database');
		$list = $database->select('*','tax',"1=1");
		foreach($list as $v){
			$this->taxes[$v['percentage']] = $v['id'];
		}
	}

	function getTax($iva){
		if( !$iva ) return $iva;
		
		if( $this->taxes[$iva] ) return $this->taxes[$iva];

		
		$tax =Tax::create()->set(
			[
				'percentage' => $iva
			]
		)->setData(
			[
				'name' => $iva
			],'it'
		)->save();

		
		$this->taxes[$iva] = $tax->id;
		return $tax->id;
	}

	function updateProduct($id_product,$dati=array()){

		
		$database = _obj('Database');
		$database->update('product_inventory',
			"id_product = {$id_product}",
			array(
				'quantity' => $dati['quantita'],
			)
		);

		$id_product = $database->update('product',
			"id = {$id_product}",
			array(
				'ean' => $dati['codice_ean'],
				'codice_listino_articolo' => $dati['codice_listino_articolo']
			)
		);

	

		$database->update('price',
			"product = {$id_product} AND label = 'default'",
			array(
				'value' => $dati['prezzo'],
			)
		);

		$database->update('product_shop_values',"id_product={$id_product}",
			array(
				'id_tax' => $dati['iva'],
			)
		);
	}
	function insertProduct($dati=array()){
		$database = _obj('Database');
		$id_product = $database->insert('product',
			array(
				'sku' => $dati['codice_gestionale'],
				'type' => 1,
				'ean' => $dati['codice_ean'],
				'visibility' => 1,
				'codice_listino_articolo' => $dati['codice_listino_articolo']
			)
		);
		
		$database->insert('productLocale',
			array(
				'product' => $id_product,
				'locale' => 'it',
				'name' => $dati['descrizione']
			)
		);
		$database->insert('product_inventory',
			array(
				'id_product' => $id_product,
				'quantity' => $dati['quantita'],
				'id_inventory' => 1
			)
		);

		$database->insert('product_shop_values',
			array(
				'id_product' => $id_product,
				'id_tax' => $dati['iva'],
				'min_order' => 1,
				'max_order' => 0,
				'parent_price' => 0,
				'id_shop' => 1
			)
		);

		$database->insert('price',
			array(
				'product' => $id_product,
				'label' => 'default',
				'quantity' => 1,
				'value' => $dati['prezzo'],
				'userCategory' => 0,
				'type' => 'price',
				'id_shop' => 1
			)
		);
		
		return $id_product;
		
	}



	function leggi_tracciato($tipo){
		$tracciato =  $this->tracciati[$tipo];
		
		//prendo il file
		$filepath_b2b = $this->configurazione_b2b['path_import_'.$tipo];
		
		$file = $filepath_b2b;
		if( !file_exists($filepath_b2b) ){
			
			$this->scrivi_log('file non presente ','import',strtoupper($tipo));
			return false;
		}
		$numero_linee = count(file($file));
		$f = fopen($file, 'r');
		$first_line = fgets($f);
		fclose($f);
		
		if( $numero_linee <= 1){ 
			if( $numero_linee == 0 || ($numero_linee == 1 && preg_match('/#/',$first_line)) ){
				$this->scrivi_log('esco in quanto il file ha '.$numero_linee." righe",'import',strtoupper($tipo));
				$this->end();
				exit;
				return false;
			}
		}
		
		if( file_exists($file) ){
			
			$file = fopen($file, "r");
			
			$i = 0;
			while(!feof($file)){
				$line = fgets($file);
				
				foreach($tracciato as $k => $v){
					
					$start = $v[0];
					$length = $v[1];
					$function = $v[2];
					$valore = substr($line,$start,$length);
					
					if( $function && function_exists($function) ){
						$valore =$function($valore);
					}
					if( $function && method_exists($this,$function) ){
						$valore =$this->$function($valore);
					}
					$dati[$i][$k] = $valore;
				}
				
				$i++;
				
			}
			fclose($file);
		}else{
			$this->scrivi_log("il file di importazione di tipo {$tipo} non è presente");
			exit;
		}

		//pulisco i dati
		switch($tipo){
			case 'clienti':
				foreach($dati as $k => $v){
					if( !$v['codice_gestionale'] ){
						unset($dati[$k]);
					}
				}
				break;
			case 'classifiche_clienti':
				foreach($dati as $k => $v){
					if( !$v['IDClassifica'] ){
						unset($dati[$k]);
					}
				}
				break;
			case 'legami_clienti_classifiche':
				foreach($dati as $k => $v){
					if( !$v['CodiceCliente'] ){
						unset($dati[$k]);
					}
				}
				break;
			case 'articoli':
				foreach($dati as $k => $v){
					if( !$v['codice_gestionale'] ){
						unset($dati[$k]);
						continue;
					}
					$dati[$k]['quantita'] = (int)$v['quantita'];
				}
				$filename = date('Y-m-d_H_i').".txt";
				$file_destination = $this->path_sincro."GIACENZE/".$filename;
				rename($filepath_b2b, $file_destination);
				break;
			case 'ordini':
				

				$filename = date('Y-m-d_H_i').".txt";
				$file_destination = $this->path_sincro."ORDINI_IMPORT/".$filename;
				if( file_exists($filepath_b2b) ){
					$res = rename($filepath_b2b, $file_destination);
				}
				break;
				

		}
		return $dati;
	}


	function loadConfig(){
		$this->configurazione_b2b = array(
			"path_import_clienti" => "{$this->path_sincro}wclibas_000000.txt",
			"path_import_classifiche_clienti" => "{$this->path_sincro}/wtabccl_000000.txt",
			"path_import_articoli" => "{$this->path_sincro}wartbas_000000.txt",
			"path_import_classifiche_articoli" => "{$this->path_sincro}wtabcar_064316.txt",
			"path_import_legami_clienti_classifiche" => "{$this->path_sincro}wclicla_064303.txt",
			"path_import_legami_articoli_classifiche" => "{$this->path_sincro}wartcla_064309.txt",
			"path_import_condizioni_listini" => "{$this->path_sincro}wclilis_000000.txt",
			"path_import_traduzione_articoli" => "{$this->path_sincro}wartlin_000000.txt",
			"path_import_destinazioni" => "{$this->path_sincro}wclides_000000.txt",
			"path_import_ordini" => "{$this->path_sincro}w400upd_000000.txt",
			"path_import_lingue" => "../b2b/import/Lingue.txt",
			"path_log_import" => _MARION_MODULE_DIR_."b2b/log/",
			"path_log_export" => _MARION_MODULE_DIR_."b2b/log/",
		);


		$this->tracciati = array(
			'ordini' => array(
				'codice_carrello' => array(0,10,'trim'), 
				'anno_ordine_as400' => array(10,4,'trim'), 
				'numero_ordine_as400' => array(14,6,'trim'), 
				'stato' => array(20,3,'trim'), 
				'data_ddt' => array(23,8,'format_data'), 
				'num_ddt' => array(31,7,'trim'), 
				'data_fattura' => array(38,8,'format_data'), 
				'numero_fattura' => array(46,8,'trim'), 
			),
			
			'lingue' => array(
				'codice_lingua' => array(0,1,'trim'), //Codice
				'codice_lingua_formattato' => array(0,8,'trim'), //Codice
				'nome' => array(8,8,'trim'), //Codice
				'campo1' => array(16,8,'trim'), //Codice
				'campo2' => array(24,8,'trim'), //Codice
				'campo3' => array(32,8,'trim'), //Codice
				'campo4' => array(40,8,'trim'), //Codice
				'campo5' => array(48,8,'trim'), //Codice
				'campo6' => array(56,8,'trim'), //Codice
			),
			'clienti' => array(
				'codice_gestionale' => array(0,20,'trim'), //Codice
				'ragsoc' => array(20,50,'trim'), 
				'cap' => array(120,5,'trim'), //Cap
				'indirizzo' => array(125,50,'trim'), //Indirizzo
				'citta' => array(175,50,'trim'), //Localita
				'provincia' => array(225,2,'trim'), //Provincia
				'cf_piva' => array(357,11,'trim'), //PartitaIva
				'cf' => array(368,16,'trim'), //CodiceFiscale
				//'CodiceModalitaPagamento' => array(459,3),
				//'CodiceModalitaSpedizione' => array(462,3),
				'codiceiva' => array(384,4),
				'codice_listino' => array(586,6,'trim'), //CodiceListino
				//'Abi' => array(422,5),
				//'Cab' => array(427,5),
				//'ContoCorrente' => array(432,13),
				'radiomobile' => array(267,20,'trim'), //Cellulare
				//'CodiceGiornoConsegna' => array(584,1),
				//'Fido' => array(445,10),
				//'DescrizioneSupplementare' => array(70,50),
				'codice_listino_cliente' => array(592,4),
				'codice_listino_cliente2' => array(766,2),
				//'FlagBloccatoDaERP' => array(623,1),
				//'MotivazioneBloccoDaERP' => array(624,40),
				'telefono' => array(227,20,'trim'), //Telefono1
				//'Telefono2' => array(247,20),
				'fax' => array(287,20,'trim'), //Fax
				'email' => array(307,50,'trim'), //Email
				//'PartitaIvaComunitaria' => array(665,15),
				'codice_lingua' => array(680,2),
				'sconto1' => array(567,3,'trim'),
				'sconto2' => array(570,3,'trim'),
				'codice_agente' => array(573,3,'trim'),
				'asso' => array(576,2,'trim'),
				'gruppo' => array(890,40,'format_gruppo'),
				'email_fattura' => array(683,70,'trim'),
				'codice_agente2' => array(754,2,'trim'),
				'codice_nazione' => array(760,2,'trim'),
				'info1' => array(770,40,'trim'),
				'info2' => array(810,40,'trim'),
				'info3' => array(850,40,'trim'),
				'condizioni_pagamento' => array(930,40,'trim'),
				'condizioni_trasporto' => array(970,40,'trim'),
				'budget_anno' => array(1010,13,'format_budget'),
			),
			'classifiche_clienti' => array(
				'IDClassifica' =>  array(0,2),
				'CodiceChiave' => array(2,10),
				'Descrizione' => array(12,40),
				'FlagVisibile' => array(74,1),
			),
			'classifiche_articoli' => array(
				'IDClassifica' =>  array(0,2,'trim'),
				'CodiceChiave' => array(2,10,'trim'),
				'Descrizione' => array(12,40,'trim'),
				'FlagVisibile' => array(74,1,'trim'),
			),
			'legami_articoli_classifiche' => array(
				'CodiceArticolo' =>  array(0,20,'trim'),
				'IDClassifica' => array(20,2,'trim'),
				'CodiceChiave' => array(22,2,'trim'),
			),
			'legami_clienti_classifiche' => array(
				'CodiceCliente' =>  array(0,20),
				'IDClassifica' => array(20,2),
				'CodiceChiave' => array(22,2),
			),
			'articoli' => array(
				'codice_gestionale' => array(0,20,'trim'), //Codice
				'descrizione' => array(20,100,'trim'), //Descrizione
				'prezzo' => array(120,10,'format_prezzo5'), //Prezzo
				'iva' => array(130,2,'intval'), //Iva
				//'ImpostaContrassegno' => array(132,10),
				//'ImpostaFabbricazione' => array(142,10),
				'codice_ean' => array(152,13,'trim'), //CodiceEan
				//'DescrizioneSupplementare' => array(70,50),
				'codice_listino_articolo' => array(209,4,'trim'), //CodiceListinoArticolo
				'quantita' => array(238,10,'format_prezzo'), //QuantitaDisponibile
				//'Costo' => array(213,10),
				//'FattoreConfezionamento' => array(165,3),
			),
			'traduzione_articoli' => array(
				'codice_gestionale' => array(0,20,'trim'), //Codice
				'codice_lingua' => array(20,5,'trim'), //codice lingua
				'descrizione' => array(25,50,'trim'), //Descrizione
			),
			'condizioni_listini' => array(
				'sconto1' => array(40,5,'format_prezzo'), //ValoreSconto1
				'sconto2' => array(45,5,'format_prezzo'), //ValoreSconto2
				'sconto3' => array(50,5,'format_prezzo'), //ValoreSconto3
				'sconto4' => array(55,5,'format_prezzo'), //ValoreSconto4
				'sconto5' => array(60,5,'format_prezzo'), //ValoreSconto5
				'prezzo' => array(65,15,'format_prezzo5'), //Prezzo
				'datainizio' => array(80,8,'format_data'), //DataInizio
				'tipo' => array(96,1,'trim'),	//Tipo
				'articolo' => array(20,20,'trim'), //CodiceChiaveArticolo
				'cliente' => array(0,20,'trim'), //CodiceChiaveCliente
				'tipologia' => array(99,3,'intval'), //Tipologia Listino
				'priorita' => array(102,2,'intval'), //Priorità listino
				'quantita' => array(104,10,'intval'), //Quantità applicazione (a partire da)
				'campagna' => array(120,10,'trim'), //Quantità applicazione (a partire da)
				'quantita_omaggio' => array(130,7,'intval'), 
				'su_quantita_totale' => array(137,7,'intval')
			),

			'destinazioni' => array(
				'codice_cliente' => array(0,20,'trim'), //Codice
				'codice_destinazione' => array(20,20,'trim'), //codice lingua
				'descrizione' => array(40,40,'trim'), //Descrizione
				'descrizione2' => array(80,40,'trim'), //Descrizione
				'cap' => array(120,5,'trim'), 
				'indirizzo' => array(125,40,'trim'), 
				'citta' => array(165,40,'trim'), 
				'provincia' => array(205,2,'trim'), 
				'telefono1' => array(207,20,'trim'), 
				'telefono2' => array(227,20,'trim'), 
				'email' => array(247,70,'trim'), 
			),

			
		);
	}

	function scrivi_log($testo,$tipo='import',$file_name=NULL){
		
		if( $tipo == 'import'){
			$path = $this->configurazione_b2b['path_log_import'];
		}else{
			$path = $this->configurazione_b2b['path_log_export'];
		}
		if($path){
			$data_corrente = date('Y-m-d');
			if( $file_name ){
				if( !file_exists($path."/".$file_name) ) mkdir($path."/".$file_name);
				$file = $path."/".$file_name."/".$data_corrente.".txt";
			}else{
				$file = $path.$data_corrente.".txt";
			}
			$date = "[".date('Y-m-d H:i')."] ";						   
			error_log($date.$testo."\n", 3, $file);
		}


	}

	function format_data($valore){
		if( $valore ){
			$anno = substr($valore,0,4);
			$mese = substr($valore,4,2);
			$giorno = substr($valore,6,2);
			return $anno."-".$mese."-".$giorno;
		}
		return $valore;
	}



	function format_prezzo($valore){
		if( $valore ){
			
			$valore = (float)$valore;
			if( $valore == 99999){
				$valore = -1;
			}else{
				$valore = $valore/100;
			}
			return $valore;
		}
		return $valore;
	}

	function format_gruppo($valore){
		$valore = trim($valore);
		if( $valore ){
			$valore = strtoupper($valore);
			if( strlen($valore) == 1 ){
				$valore = 'CLASSE '.$valore;
			}
		}
		return $valore;
	}

	function format_budget($valore){
		if( $valore ){
			
			$valore = (float)$valore;
			
			$valore = $valore/100;
			
			return $valore;
		}
		return $valore;
	}

	function format_prezzo5($valore){
		if( $valore ){
			
			$valore = (float)$valore;
			if( $valore == 999999999999999){
				$valore = -1;
			}else{
				$valore = $valore/100000;
			}
			return $valore;
		}
		return $valore;
	}

	function getDbOld(){
		
		require(_MARION_ROOT_DIR_.'backend/database.class.php');
		$d3['dbhost'] = "89.40.227.99";
		$d3['dbname'] = "tecneco";
		$d3['dbuser'] = "tecneco";
		$d3['dbpassword'] = "vwKxHL3U";
		

		return $this->dbOld = new Database($d3);
		
	}



}

?>