<?php
ini_set('memory_limit','1024M');
ini_set('max_execution_time', 8000);
class OldController extends FrontendController{

	


	function display(){
		$this->getDbOld();
		
		switch($this->getAction()){
			case 'clienti':
				exit;
				$this->getClienti();
				exit;
			case 'estero':
				exit;
				$this->estero();
				exit;
			
			case 'reset':
				exit;
				$this->resetPassword();
				exit;
			case 'promo':
				exit;
				$this->getPromo();
				exit;
			case 'ordini':
				exit;
				$this->getOrdini();
				exit;
		}
	}


	function estero(){

		//0402001514
		$database = _obj('Database');
		$list = $database->select('id,username,password,c.codice_gestionale','user as u join b2b_cliente as c on c.id_user=u.id',"username='00000000000'");
		
		foreach($list as $v){
			$database->update('user',"id={$v['id']}",
				array(
				'username'=>$v['codice_gestionale'],
				'password'=>password_hash($v['codice_gestionale'], PASSWORD_DEFAULT) ));
			
		}
		debugga('finito');
		exit;
	}


	public function resetPassword(){
		$utenti =  $this->dbOld->select('username,password','utenti',"1=1");
		foreach($utenti as $v){
			$old[$v['username']] = $v['password'];
		}
		$database = _obj('Database');
		$list = $database->select('username,password,id','user','1=1');
		error_log("inizio\n", 3, _MARION_MODULE_DIR_.'b2b/log/password.txt');
		foreach($list as $v){
			if( $old[$v['username']]  ){
				$vecchia = $old[$v['username']];
				
				$database->update('user',"id={$v['id']}",array('password'=>password_hash($vecchia, PASSWORD_DEFAULT) ));

				error_log($database->lastquery."\n", 3, _MARION_MODULE_DIR_.'b2b/log/password.txt');
			}
		}

		error_log("fine\n", 3, _MARION_MODULE_DIR_.'b2b/log/password.txt');

		debugga($old);exit;
	}

	function getDbOld(){
		
		require(_MARION_ROOT_DIR_.'backend/database.class.php');
		$d3['dbhost'] = "89.40.227.99";
		$d3['dbname'] = "tecneco";
		$d3['dbuser'] = "tecneco";
		$d3['dbpassword'] = "vwKxHL3U";
		

		return $this->dbOld = new Database($d3);
		
	}

	function getClienti(){
		$duplicati =  $this->dbOld->select('*','utenti',"duplicato='t'");
		
		$database = _obj('Database');

		$clienti = $database->select('codice_old_b2b,id,codice_gestionale','user as u join b2b_cliente as b on b.id_user=u.id',"duplicato=1");


		foreach($clienti as $v){
			$da_escludere[] = $v['codice_old_b2b'];
			$map_cliente[$v['codice_old_b2b']] = $v['id'];
		}

		

		$province =  $this->dbOld->select('*','province',"locale='it'");
		foreach($province as $v){
			$cod_prov[$v['codice']] = $v['sigla'];
		}


		foreach($duplicati as $v){

			if( !in_array($v['codice'],$da_escludere) ){
		
				
				$toinsert_user = array(
					'email' => $v['email'],
					'username' => $v['cf_piva'],
					'password' =>password_hash(trim($v['codice_gestionale']), PASSWORD_DEFAULT),
					'company' => $v['ragsoc'],
					'city' => $v['citta'],
					'province' => $cod_prov[$v['provincia']],
					'vatNumber' => $v['cf_piva'],
					'postalCode' => $v['cap'],
					'country' => 'IT',
					'codice_old_b2b' => $v['codice']
				);
				$toinsert_cliente = array(
					
					'codice_gestionale' => $v['codice_gestionale'],
					'codice_gestionale_int' => (int)$v['codice_gestionale'],
					'codice_listino_cliente' => $v['codice_listino_cliente'],
					'codice_listino' => $v['codice_listino'],
					'sconto1' => $v['sconto1'],
					'sconto2' => $v['sconto2'],
					'duplicato' => 0,
					'email_fattura' => $v['email_fattura'],
					'gruppo' => $v['gruppo'],
					'info1' => $v['info1'],
					'info2' => $v['info2'],
					'info3' => $v['info3'],
					'codice_agente' => (int)$v['codice_agente'],
					'duplicato' => 1,
					'deleted' => ($v['deleted'] == 't')?1:0,
					'active' => 1

				);
				$id_user = $database->insert('user',$toinsert_user);
				debugga($database->error);
				$toinsert_cliente['id_user'] = $id_user;
				debugga($toinsert_cliente);
				$database->insert('b2b_cliente',$toinsert_cliente);
				debugga($database->error);
				debugga($toinsert_user);
			}else{

				$id_user = $map_cliente[$v['codice']];

				$toinsert_user['deleted'] = ($v['deleted'] == 't')?1:0;
				$toinsert_user['username'] = $v['username'];
				$toinsert_user['active'] = 1;
				$toinsert_user['deleted'] = 0;
				$toinsert_user['password'] = password_hash($v['password'], PASSWORD_DEFAULT);
				
				$database->update('user',"id={$id_user}",$toinsert_user);
				debugga($database->lastquery);
				debugga($database->error);
				
			}

			
		}
	}


	function getPromo(){
		require_once(_MARION_MODULE_DIR_."b2b/classes/ProductPromo.class.php");
		
		$database = _obj('Database');
		$database->delete('b2b_product_promo_lang',"1=1");
		$database->delete('b2b_product_promo','1=1');
		
		$prodotti = $this->dbOld->select('*','prodotti_b2b',"promo='t'");
		
		foreach($prodotti as $v){
			
			$obj =  ProductPromo::create();
			$dati = [
				'codice_gestionale' => $v['codice_gestionale'],
				'date_from' => $v['datainizio_promo'],
				'date_to' => $v['datafine_promo'],

					
			];
			$obj->set($dati);
			$obj->setData(
				[
					'description' => $v['testo_promo']
				],'it'
			);
			
			$obj->save();
		}

		debugga('fnito');exit;

	}


	function getOrdini(){


		$database = _obj('Database');
		$clienti = $database->select('codice_old_b2b,id','user',"1=1");
		$prodotti = $database->select('id,sku','product',"1=1");
		$carts = $database->select('id,number','cart',"1=1");
		foreach($carts as $v){
			$importati[$v['number']] = $v['id'];
		}

		



		foreach($prodotti as $p){
			$map_prod[$p['sku']] = $p['id'];
		}


		foreach($clienti as $v){	
			$map_cliente[$v['codice_old_b2b']] = $v['id'];
		}
		$province =  $this->dbOld->select('*','province',"locale='it'");
		foreach($province as $v){
			$cod_prov[$v['codice']] = $v['sigla'];
		}

		$mappatura = [
			'codice' => 'number',
			'ragsoc' => 'company',
			'indirizzo' => 'address',
			'citta' => 'city',
			'provincia' => 'province',
			'cap' => 'cap',
			'cf_piva' => 'vatNumber',
			'indirizzo_sp' => 'shippingAddress',
			'citta_sp' => 'shippingCity',
			'provincia_sp' => 'shippingProvince',
			'cap_sp' => 'shippingPostalCode',
			'data' => 'evacuationDate',
			'totale_senza_iva' => 'total_without_tax',
			'iva' => 'total_tax',
			'totale' => 'total',
			'progressivo' => 'progressivo',
			'codice_destinazione' => 'codice_destinazione',
			'codice_gestionale' => 'codice_cliente',
			'utente' => 'user',
			'note' => 'note',
			'numero_ordine_as400' => 'numero_ordine_as400',
			'anno_ordine_as400' => 'anno_ordine_as400',
			'num_ddt' => 'num_ddt',
			'data_fattura' => 'data_fattura',
			'numero_fattura' => 'numero_fattura',
			
		];
		//$carrelli = $this->dbOld->select('*','carrelli',"1=1 order by codice ASC");
		$data_limite = '2021-03-01';
		//$carrelli = $this->dbOld->select('*','carrelli',"data < '{$data_limite}' order by codice ASC");
		$carrelli = $this->dbOld->select('*','carrelli',"1=1 order by codice ASC");
		//debugga($carrelli[0]);exit;
		/*
		cicciobello
            [password] => 0401002441

		*/
		$_num = 0;
		foreach($carrelli as $k => $v){
			if( array_key_exists($v['codice'],$importati)) continue;
			//debugga($v);exit;
			$_num++;
			if( $_num > 1000 ){
				break;
			}
			$cart = Cart::create();
			$cart->esportato = 1;
			foreach($mappatura as $k1 => $v1){
				if( $v1 == 'province' || $v1 == 'shippingProvince'){
					$cart->$v1 = $cod_prov[$v[$k1]];
				}elseif($v1 == 'user'){
					$cart->$v1 = $map_cliente[$v[$k1]];
				}else{
					$cart->$v1 = $v[$k1];
				}
				
			}
			$status = '';
			switch($v['stato']){
				case 1:
					$status = 'in_attesa';
					break;
				case 2:
					$status = 'in_attesa';
					break;
				case 3:
					$status = 'elaborazione';
					break;
				case 4:
					$status = 'sent';
					break;
				case 5:
					$status = 'canceled';
					break;
			}
			$cart->aggiunto_a = $importati[$v['aggiunto_a']]?$importati[$v['aggiunto_a']]:0;
			$cart->status = $status;
			$cart->esportato = ($v['esportato']=='t')?1:0;
			$cart->old = 1;
			$cart->save();
			$importati[$cart->number] = $cart->id;
			
			$ordini = $this->dbOld->select('*','ordini',"carrello={$v['codice']}");
			$id_cart = $cart->id;
			foreach($ordini as $o){
				$sconti = unserialize($o['sconti_array']);
				$order = Order::create();
				$order->cart = $id_cart;
				$order->user = $cart->user;
				$order->sku = $o['codice_gestionale'];
				$order->price = $o['prezzo'];
				$order->price_without_tax = $o['prezzo_senza_iva'];
				$order->taxPrice = $o['prezzo']-$o['prezzo_senza_iva'];
				$order->quantity = $o['quantita'];
				$order->weight = 0;
				$order->product = $map_prod[$o['codice_gestionale']]?$map_prod[$o['codice_gestionale']]:0;
				$custom1 = [
                    'prezzo_base' => $o['prezzo_base'],
                    'prezzo_italia' => $o['prezzo_italia'],
                    'iva' => $o['iva'],
                    'prezzo' => $o['prezzo'],
                    'prezzo_senza_iva' => $o['prezzo_senza_iva'],
                    'campagna' => $o['campagna'],
                    'campagna_tipo' => $o['campagna_tipo'],
                    'sconto_utente' => ($o['sconto_utente']=='t')?1:0,
                    'quantita_omaggio' => $o['quantita_omaggio'],
                    'quantita_totale' => $o['quantita_totale'],
                    'sconti_array' => $sconti,
                    'sconto3' => $o['sconto1'],
                    'sconto1' => $o['sconto2'],
                    'sconto2' => $o['sconto3'],
                    'sconto4' => $o['sconto4'],
                    'sconto5' => $o['sconto5']
                ];
				$order->custom1 = serialize($custom1);
				$order->save();

				//debugga($order);exit;
				
			}
			
			
		}
		debugga('finito');exit;
		debugga($carrelli);exit;
	}
}

?>