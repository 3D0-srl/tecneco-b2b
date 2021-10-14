<?php
require('modules/b2b/controllers/front/ApiController.php');
require('modules/b2b/classes/BackOrder.class.php');
ini_set('serialize_precision',5);
class OrderController extends ApiController{
	private $codice_cliente = 0;
	private $codice_listino_cliente = 0;
	
	function getUrlImage($id,$sku){
		return $this->url."/img/".$id."/md/".$sku.".png";
	}
	
	function display(){
		if( !_var('escape') ){
			parent::display();
		}
		$id_user = $this->user['id'];
		$action = $this->getAction();
		switch($action){
			case 'backorder':
				require('modules/b2b/classes/Tools.class.php');
				require('modules/b2b/classes/ToolsNew.class.php');
				
				$this->getBackOrder();
				break;
			case 'print_mail':
				//$cart = Cart::prepareQuery()->where("status",'in_attesa')->getOne();
				$cart = Cart::withId(12014);

				$this->sendMailOrder2($cart);
				debugga('ok');
				exit;
			case 'test_pdf':
				
				$cart = Cart::prepareQuery()->where('number','1618415906')->getOne();
				
				$this->buildPDF2($cart->id);
				debugga('ok');
				exit;
			case 'get_num_items':
				if( !$id_user ){
					$id_user = 866;
				}
				$user = User::withId($id_user);
				$_SESSION['userdata'] =  $user;

				
				$cart = Cart::getCurrent();
				$database = _obj('Database');
				$tot = $database->select('sum(quantity) as num',"cartRow","cart={$cart->id}");

				
				
			
				//$data = new stdClass;
				
				$data = [
					'tot' => $tot[0]['num'],
					'tot_back' => BackOrder::getTotalItems($user)
				];
				$this->success($data);
				break;
			/*
			restituisce la lista degli ordini
			*/
			case 'get_orders':
				
				if( !$id_user ){
					$id_user = 866;
				}
				$condition = '';
				$limit = 10;
				$page = _var('page');
				if( !$page ) $page = 1;
				if( $page ){
					
					$offset = ($page-1)*$limit;
					
					$condition = "limit {$limit} offset {$offset}";
				}
				
				
				$database = _obj('Database');
				$list = $database->select('id,aggiunto_a,number,evacuationDate as date,status,total,total_tax,total_without_tax','cart',"user={$id_user} AND status <> 'active'  AND status <> 'deleted' AND status <> 'canceled' order by evacuationDate DESC {$condition}");

				

				$tot = $database->select('count(*) as cont',"cart","user={$id_user} AND status <> 'active'  AND status <> 'deleted' AND status <> 'canceled'");
				$tot = $tot[0]['cont'];

				
				$tot_pages = ((int)($tot/$limit))+1;
				
				$stati = CartStatus::prepareQuery()->get();
				foreach($stati as $v){
					$status[$v->label] = strtoupper($v->get('name'));

					$status_color[$v->label] = $v->color;
				}
				$numero_ordine = [];
				if(okArray($list)){
					
					foreach($list as $k => $v){
						$numero_ordine[$v['id']] = $v['number'];
						$list[$k]['status'] = $status[$v['status']];
						$list[$k]['status_color'] = $status_color[$v['status']];
					}
					foreach($list as $k => $v){
						if( $v['aggiunto_a'] ){
							$list[$k]['number'] = $numero_ordine[$v['aggiunto_a']]."/1";
						}
						
					}
				}
				

				$data = array(
					'orders' => $list,
					'tot_pages' => $tot_pages,
					'current_page' => $page
				);
				//debugga($data);exit;
				$this->success($data);
		
				break;
			/*case 'get_provinces':
				$database = _obj('Database');
				$list = $database->select('sigla as id,nome as name','provincia','1=1 order by nome');
				$this->success($list);
				break;
			case 'get_countries':
				$database = _obj('Database');
				$list = Country::getAll();
				$toreturn = array();
				foreach($list as $v){
					$toreturn[] =array(
						'id' => $v->id,
						'name' => $v->get('name')
					);
				}
				$this->success($toreturn);
				break;
			case 'get_addresses':
				if( !$id_user ){
					$id_user = 866;
				}
				$database = _obj('Database');
				$list = $database->select('id,name,city,province,address,phone,cellular,email','address',"id_user={$id_user}");
				$this->success($list);
				break;*/
			/*

			chiude l'ordine
			riceve in input un array del tipo
			array(
				'note' => 'Note ordine',
				'id_address' => 'id indirizzo'
			);
			*/
			case 'close_cart':
				$input = json_decode(file_get_contents('php://input'), true);
				$note = $input['note'];
				$id_address = $input['id_address'];
				$aggiunto_a = $input['aggiunto_a']?$input['aggiunto_a']:null;

				//verifico se pu� aggiungere
				
                $user = User::withId($id_user);
				$_SESSION['userdata'] =  $user;

				$database = _obj('Database');
				$cart = Cart::getCurrent();
				//controllo quantit�

				$select = $database->select('r.id as id_order,p.sku,r.quantity,i.quantity as giacenza',"(product as p join cartRow as r on r.product=p.id) join product_inventory as i on i.id_product=r.product","r.cart={$cart->id}");
				$errors = [];
				foreach($select as $v){
					if( $v['quantity'] > $v['giacenza'] ){
						$errors[] = [ "La quantit� ordinata per l'articolo {$v['sku']} � maggiore di quella presente in   magazzino {$v['giacenza']}"
						];
					}
				}
				if( count($errors) > 0 ){
					$this->error($errors[0]);
				}
				
				if( $aggiunto_a ){
					if( !$this->checkStatus()){
						$aggiunto_a = null;
					}
				}

				$data = array(
					'name' => $user->name,
					'surname' => $user->surname,
					'company' => $user->company,
					'email' => $user->email,
					'province' => $user->province,
					'country' => $user->country,
					'city' => $user->city,
					'postalCode' => $user->postalCode,
					'email' => $user->email,
					'vatNUmber' => $user->vatNUmber,
					'aggiunto_a' => $aggiunto_a
				);
				
				
				if( $id_address ){
					$address = Address::withId($id_address);
					if( is_object($address) ){
						$data['shippingCity'] = $address->city;
						$data['shippingProvince'] = $address->province;
						$data['shippingCountry'] = $address->country;
						$data['shippingAddress'] = $address->address;
						$data['shippingPostalCode'] = $address->postalCode;
						$data['shippingPhone'] = $address->phone;
						$data['shippingCellular'] = $address->cellular;
						$data['shippingEmail'] = $address->email;
						$data['codice_destinazione'] = $address->label;
					}
				}else{

					$data['shippingCity'] = $data['city'];
					$data['shippingProvince'] = $data['province'];
					$data['shippingCountry'] = $data['country'];
					$data['shippingAddress'] = $data['address'];
					$data['shippingPostalCode'] = $data['postalCode'];
					$data['shippingPhone'] = $data['phone'];
					$data['shippingCellular'] = $data['cellular'];
					$data['shippingEmail'] = $data['email'];

				}
				$data['note'] = $note;
				$data['paymentMethod'] = 'BONIFICO';
				$data['shippingMethod'] = 1;
				$data['evacuationDate'] = date('Y-m-d H:i');

				//debugga($cart);exit;
				$tot = $database->select('sum(quantity) as num, sum(quantity*price_without_tax) as tot_without_vat,sum(quantity*price) as tot',"cartRow","cart={$cart->id}");
				$data['num_products'] = $tot[0]['num'];
				$data['total'] = $tot[0]['tot'];
				$data['total_without_tax'] = $tot[0]['tot_without_vat'];
				$data['total_tax'] =$data['total'] - $data['total_without_tax'];
				
				$min_tot = Marion::getConfig('b2b_limit_order_general','min_tot');
				$min_qnt = Marion::getConfig('b2b_limit_order_general','min_qnt');


				
				$cliente = $database->select('*','b2b_cliente',"id_user={$user->id}");
				if( okArray($cliente) ){
					$cliente = $cliente[0];

					
					if( (float)$cliente['min_order'] > 0 ){
						$min_tot = $cliente['min_order'];
					}

					if( (int)$cliente['min_qnt_order'] > 0 ){
						$min_qnt = $cliente['min_qnt_order'];
					}

					
					
				}
				
				if( !$aggiunto_a ){
					if( $data['total_without_tax'] < $min_tot ){
						$this->error("Il totale del tuo ordine � inferiore al limite minimo consentito: �".Eshop::formatMoney($min_tot)." (iva esclusa)");
					}
					if( $data['num_products'] < $min_qnt ){
						$this->error("Il numero di articoli ordinati � inferiore al limite minimo consentito: ".$min_qnt);
					}
				}

				
				$cart->set($data)->save();

				


				
		
				//$result = $cart->close();
				$cart->changeStatus('in_attesa');
				$backOrders =$database->select('*','back_orders',"user_id={$id_user} AND cart_id<>{$cart->id}");
				
				if( okArray($backOrders) ){
					$back_toupdate = [];
					$back_deleted = [];
					$rows = $database->select('product,quantity',"cartRow","cart={$cart->id}");
					$_backOrders = [];
					foreach($backOrders as $v){
						$_backOrders[$v['product_id']] = $v;
					}
					foreach($rows as $v){
						if( array_key_exists($v['product'],$_backOrders) ){
							$riga = $_backOrders[$v['product']];
							$riga['qnt'] = $riga['qnt'] - $v['quantity'];
							if( $riga['qnt'] > 0 ){
								$back_toupdate[] = $riga;
							}else{
								$back_deleted[] = $riga['id'];
							}
							
						}
					
					}
					if( okArray($back_toupdate)){
						foreach($back_toupdate as $v){
							$database->update('back_orders',"id={$v['id']} AND user_id={$id_user}",[
								'qnt' => $v['qnt']
							]);
						}
					}

					if( okArray($back_deleted)){
						foreach($back_deleted as $v){
							$database->delete('back_orders',"id={$v} AND user_id={$id_user}");
						}
					}

				}
				

				foreach($rows as $v){

				}

				//debugga($data);exit;
				
				
                $this->sendMailOrder($cart);
				$this->success(array('id_cart' => $cart->id));
				break;
			/*
			svuota il carrello	
			*/
			case 'cart_empty':
				if( !$id_user ){
					$id_user = 866;
				}
				$user = User::withId($id_user);
				
				$_SESSION['userdata'] =  $user;
				$cart = Cart::getCurrent();
				$database = _obj('Database');
				$database->delete('cartRow',"cart={$cart->id} AND user={$id_user}");
				$this->success(true);
				break;
			/*
			mostra il carrello corrente
			*/
			case 'print':
			case 'cart':
				
				require('modules/b2b/classes/Tools.class.php');
				require('modules/b2b/classes/ToolsNew.class.php');
				
				$id_cart = _var('id_cart');
				
				/*$user = User::withId($id_user);
				
				$_SESSION['userdata'] =  $user;
				
				if( $id_cart ){
					$cart = Cart::withId($id_cart);
					if( $cart->user != $id_user ){
						$this->error("Cart not exists");
					}
				}else{
					$cart = Cart::getCurrent();
				}
				
				
				
				$database = _obj('Database');
				$select = $database->select('p.id as id_product,p.sku,cp.quantita_imballo_multiplo,cp.profilt,cp.fornito_nishiboru,cp.filtro_antibatterico,cp.img_1,cp.id_tipologia,c.custom1,c.quantity,c.timestamp,c.id as id_order','(cartRow as c join product as p on p.id=c.product) join catalogo_prodotto as cp on cp.sku=p.sku',"cart={$cart->id} order by sku");
				$tipologie = array();
				$tipologia_select = $database->select('*','catalogo_tipologia_prodotto');
				foreach($tipologia_select as $v){
					$tipologie[$v['id']] = $v['nome'];
				}
				

				$now = time();
				$dati = array();
				$qnt_omaggio = 0;
				foreach($select as $v){
					
					if( $now > strtotime('+ 10 hours',strtotime($v['timestamp'])) ){
						//debugga('qua');exit;
						$prezzo = Tools::buildPrice($id_user,$v['id_product'],$v['quantity']);
						$data_order = $this->builDataOrder($cart,$v['id_product'],$prezzo,$v['quantity']);

						$id_order = $v['id_order'];
						$database->update('cartRow',"id={$id_order}",$data_order);
						//ricalcolo il prezzo
					}else{
						$prezzo = unserialize($v['custom1']);
					}

					
					
					$qnt_input = $v['quantity'];
					$qnt_omaggio += $prezzo['quantita_omaggio'];
					$row = array(
						'id_product' => $v['id_product'],
						'sku' => $v['sku'],
						'id_tipologia' => $v['id_tipologia'],
						'descrizione' => $tipologie[$v['id_tipologia']]?$tipologie[$v['id_tipologia']]:'',
						'url_image' => $v['img_1']?$this->getUrlImage($v['img_1'],$v['sku']):'',
						'prezzo_base' => $prezzo['prezzo_base'],
						'prezzo' => $prezzo['prezzo_senza_iva'],
						'prezzo_con_iva' => $prezzo['prezzo'],
						'listino_italia' => $prezzo['prezzo_italia'],
						'quantita_omaggio' => $prezzo['quantita_omaggio'],
						'campagna_tipo' => $prezzo['campagna_tipo'],
						'sconti' => is_array($prezzo['sconti_array'])?array_values($prezzo['sconti_array']):array(),
						'qnt_input' => $qnt_input,
						'totale' => $qnt_input?($qnt_input*$prezzo['prezzo_senza_iva']):null,
						'quantita_imballo_multiplo' => $v['quantita_imballo_multiplo'],
						'profilt' => $v['profilt']?1:0,
						'nishiboru' => $v['fornito_nishiboru']?1:0,
						'adplus' => (!$v['antibatterico'] && $v['filtro_antibatterico'])?1:0

					);
					$dati[] = $row;
				}
				$tot = $database->select('sum(quantity) as num, sum(quantity*price_without_tax) as tot_without_vat,sum(quantity*price) as tot, sum(quantity) as tot_pieces',"cartRow","cart={$cart->id}");
				
                if ($id_cart) {
                    $data = array(
                        'rows' => $dati,
                        'num_products' => $tot[0]['num'],
                        'total_without_vat' => $tot[0]['tot_without_vat'],
                        'total' => $tot[0]['tot'],
                        'total_pieces' => $tot[0]['tot_pieces'],
                        'number' => $cart->number,
                        'date' => $cart->evacuationDate,
						'city'=>$cart->shippingCity,
						'province'=>$cart->shippingProvince?$cart->shippingProvince:$cart->province,
						'country'=>$cart->shippingCountry?$cart->shippingCountry:$cart->country,
						'address'=>$cart->shippingAddress?$cart->shippingAddress:$cart->address,
						'postalCode'=>$cart->shippingPostalCode?$cart->shippingPostalCode:$cart->postalCode,
                        'notes'=>$cart->note,
						'qnt_omaggio' => $qnt_omaggio,
						'total_vat' => $tot[0]['tot']-$tot[0]['tot_without_vat'],
                    );
                }else{
                    $data = array(
                        'rows' => $dati,
                        'num_products' => $tot[0]['num'],
						'qnt_omaggio' => $qnt_omaggio,
                        'total_without_vat' => $tot[0]['tot_without_vat'],
						'total_vat' => $tot[0]['tot']-$tot[0]['tot_without_vat'],
                        'total' => $tot[0]['tot'],
                        'total_pieces' => $tot[0]['tot_pieces']
                    );
                }*/

				$info = $this->getCart($id_cart);
				
				$cart = $info['cart'];
				$data = $info['data'];

				
				if( $action == 'print' ){
					$pdf = $this->buildPDF($info);
					
					$this->success(base64_encode($pdf));
					
				}
				
				$this->success($data);
				break;
			/*

			rimuove un prodotto dal carrello corrente
			
			riceve in input un array di id di prodotti (come per l'aggiunta al carrello)
			
			restutuisce il seguente array
			$data = array(
					
					'num_items' => 34, //numero totale di articoli
					'tot_items' => 300.00 //totale del carrello iva inclusa,
					'tot_items_without_vat' => 250.00 //totale del carrello iva esclusa,
				);

			*/
			case 'remove_from_cart':
				$input = json_decode(file_get_contents('php://input'), true);
				$list = $input['products'];
				
				
				$user = User::withId($id_user);
				$_SESSION['userdata'] =  $user;
				$cart = Cart::getCurrent();
				
				//$errors = array();
				foreach($list as $ind => $v){
					$row = Order::prepareQuery()
						->where('cart',$cart->id)
						->where('product',$v['id_product'])
						->getOne();
					//debugga($row);exit;
					
					if( is_object($row) ){
						$row->delete();
					}
				}
				$database = _obj('Database');
				$tot = $database->select('sum(quantity) as num, sum(quantity*price_without_tax) as tot_without_vat,sum(quantity*price) as tot, sum(quantity) as tot_pieces',"cartRow","cart={$cart->id}");
				
				

				$data = array(
					//'errors' => $errors,
					'num_items' => $tot[0]['num'],
					'tot_items' => $tot[0]['tot'],
					'tot_items_without_vat' => $tot[0]['tot_without_vat'],
					'total_pieces' => $tot[0]['tot_pieces'],
				);
				
				$this->success($data);
				break;
			/*
			aggiunge un prodotto dal carrello corrente
			restutuisce il seguente array
			$data = array(
					
					'num_items' => 34, //numero totale di articoli
					'tot_items' => 300.00 //totale del carrello iva inclusa,
					'tot_items_without_vat' => 250.00 //totale del carrello iva esclusa,
				);

			*/
			case 'add_to_cart':
				require('modules/b2b/classes/Tools.class.php');
				require('modules/b2b/classes/ToolsNew.class.php');
				//$id_product = _var('id_product');
				$database = _obj('Database');
				$input = json_decode(file_get_contents('php://input'), true);
				$list = $input['products'];
				$fromBackOrder = _var('fromBackOrder');
				
				
				$prodotti_aggiunti = []; 

				$user = User::withId($id_user);

				
				$_SESSION['userdata'] =  $user;
				$cart = Cart::getCurrent();
				
				$errors = array();
				foreach($list as $ind => $v){
					$row = Order::prepareQuery()
						->where('cart',$cart->id)
						->where('product',$v['id_product'])
						->getOne();
					//debugga($row);exit;
					
					if( !is_object($row) ){
						$row = Order::create();
					}
					$back_id = null;
					if( $user->backorder ){
						$back_id = null;
						if( $v['backOrder'] ){
							$back_id = BackOrder::add($v['id_product'],$v['backOrder']);
						}
						
					}
					if( $v['quantity'] > 0 ){
						
						
						

						//controllo se l'utente è un utente backorder e se l'aggiunta avviene dalla tab backorder
						if( $user->backorder && $fromBackOrder){

							//quantita da aggiungere a quella presente nel carrello
							$qnt_back_order_da_aggiungere = $v['quantity'];
							
							
							//aggiungo la quantità già presente nel carrello
							if($row->id){
								$v['quantity'] += $row->quantity;
							}
							$check = Tools::checkQuantity($v['id_product'],$v['quantity']);
							//se il controllo non va a buon fine imposto la qnt a quella massima per il prodotto 
							//e imposto il controllo come positivo
							if( !$check['success']){
								$check['success'] = true;
								//aggiorno la quantità da aggiungere che sarà diminuita rispetto a prima
								$qnt_back_order_da_aggiungere = $v['quantity']-$check['max_qnt'];
								$v['quantity'] = $check['max_qnt'];

								
							}
							
						}else{
							$check = Tools::checkQuantity($v['id_product'],$v['quantity']);
						}

						
					
						if( $check['success'] ){

							//if( $this->user['id'] == 2356 || $this->user['id'] == 1457 ){
							$prezzo = ToolsNew::buildPrice($id_user,$v['id_product'],$v['quantity'],$this->user['no_promo']);
							/*}else{
								$prezzo = Tools::buildPrice($id_user,$v['id_product'],$v['quantity'],$this->user['no_promo']);
							}*/
							
							//$prezzo_serialized = serialize($prezzo);
							//debugga($prezzo_serialized);exit;

							$data_order = $this->builDataOrder($cart,$v['id_product'],$prezzo,$v['quantity']);
							$row->set($data_order);

							$row->save();

							if( $fromBackOrder ){
								//rimuovo dal backorder la quantità aggiunta
								$old_back_order = BackOrder::getRow($id_user,$v['id_product']);
								
								if( $old_back_order['qnt'] <= $qnt_back_order_da_aggiungere ){
									$database->delete('back_orders',"product_id={$v['id_product']} AND user_id={$id_user}");
								}else{
									
									$database->update('back_orders',
													"product_id={$v['id_product']} AND user_id={$id_user}",
													[
														'qnt' => $old_back_order['qnt']-$qnt_back_order_da_aggiungere
													]
									);
								}
							}

							

							$aggiunto = [
								'id_product' => $v['id_product'],
								'id_order' => $row->id
							];

							if( $user->backorder && $back_id){
								$aggiunto['id_back_order'] = $back_id;
							}

							$prodotti_aggiunti[] = $aggiunto;
							
						}else{
							$error = array(
								'index' => $ind,
								'id_product' => $v['id_product'],
								'error' => utf8_encode($check['error'])
							);
							if( $user->backorder && $back_id){
								$error['id_back_order'] = $back_id;
							}
							$errors[] = $error;
						}
					}else{
						if( $user->backorder && $back_id ){
							$aggiunto = [
								'id_product' => $v['id_product'],
								'id_back_order' => $back_id
							];

							$prodotti_aggiunti[] = $aggiunto;
						}
					}
				}
				$database = _obj('Database');
				$tot = $database->select('sum(quantity) as num, sum(quantity*price_without_tax) as tot_without_vat,sum(quantity*price) as tot, sum(quantity) as tot_pieces',"cartRow","cart={$cart->id}");
				
				

				$data = array(
					'errors' => $errors,
					'num_items' => $tot[0]['num'],
					'tot_items' => $tot[0]['tot'],
					'tot_items_without_vat' => $tot[0]['tot_without_vat'],
					'total_pieces' => $tot[0]['tot_pieces'],
					'num_back_order_items' => BackOrder::getTotalItems($user),
					'orders' => $prodotti_aggiunti
				);
				
				$this->success($data);
				break;
			case 'update_back_order':
				$user = User::withId($id_user);
				$_SESSION['userdata'] = $user;
				require_once('modules/b2b/classes/BackOrder.class.php');
				$input = json_decode(file_get_contents('php://input'), true);
				$database = _obj('Database');
				$back = BackOrder::add($input['id_product'],$input['qnt']);
				$this->success(
					[
						'id' =>	$back,
						'total' => BackOrder::getTotalItems($user)
					]
				);
				break;
			case 'delete_back_order':
				$database = _obj('Database');
				$input = json_decode(file_get_contents('php://input'), true);
				$ids = $input['ids'];
				foreach($ids as $id){
					$database->delete('back_orders',"id={$id} AND user_id={$id_user}");
				}
				$this->success(1);
				break;
			case 'check_riassortimento':
				
				$this->success(BackOrder::getNuoviProdotti($id_user));
				break;
			case 'get_price':

				
				
				$id_product = _var('id_product'); //parametro input
				$qnt = _var('qnt'); //parametro input
				$escape_check = _var('escape_check');
				//$sku = 2879;
				if( !$id_user ){
					//$id_user = 376;
				}
				//$qnt = 50;

				if( $this->user['backorder'] ){
					require_once('modules/b2b/classes/BackOrder.class.php');
					$back = BackOrder::getRow($id_user,$id_product);
					
					
				}
				
				require('modules/b2b/classes/ToolsNew.class.php');
				if( !$escape_check ){
					$check = ToolsNew::checkQuantity($id_product,$qnt);
				}else{
					$check['success'] = true;
					$check['max_qnt'] = $qnt;
				}
				
				
				if( $check['success'] ){
					$prezzo = ToolsNew::buildPrice($id_user,$id_product,$qnt,$this->user['no_promo']);
				}else{

					$error = $check['error'];
					$qnt = $check['max_qnt'];
					$prezzo = ToolsNew::buildPrice($id_user,$id_product,$qnt,$this->user['no_promo']);
				}
				 $response = [];
				
				
				
				
				$response['prezzo'] = array(
					'prezzo' => $prezzo['prezzo_senza_iva'],
					'totale' => $prezzo['prezzo_senza_iva']*$qnt,
					'sconti' => is_array($prezzo['sconti_array'])?$prezzo['sconti_array']:array(),

				);
				$response['qnt'] = 
					[
					'max_qnt' => $check['max_qnt'],
					'back_order' => $check['back_order']
				];
				if( isset($back) && $back){
					
					
					
					$qnt_back = (int)$back['qnt'];
					$response['qnt']['back_order'] = $qnt_back;
						
					
				}
				if( $prezzo['quantita_omaggio'] > 0 ){
					$multiplo = (int)($qnt/$prezzo['quantita_totale']);
					$response['prezzo']['quantita_omaggio'] = $prezzo['quantita_omaggio']*$multiplo;
				}
				if( !isset($error) ){
					$this->success($response);
				}else{
					$this->error(utf8_encode($error),$response);
				}
			break;
			case 'status':
				
				/*$user = User::withId($id_user);
				
				$database = _obj('Database');
				$check = $database->select('id,number,status','cart',"user={$id_user} AND aggiunto_a is NULL ORDER BY evacuationDate DESC");
				if( okArray($check) ){
					$check = $check[0];
					if( in_array($check['status'],['active','spedito','canceled','deleted'] )){
						$this->error();
					}else{
						$tot = $database->select('count(*) as tot',"cart","aggiunto_a = {$check['id']}");
						
						if( $tot[0]['tot'] == 0 ){
							$this->success($check);
						}else{
							$this->error();
						}
					}
					
				}else{
					$this->error();
				}*/
				if( $check = $this->checkStatus()){
					$this->success($check);
				}else{
					$this->error();
				}
				break;
		}

		

		
	}
	


	
	function checkStatus(){
			$id_user = $this->user['id'];

			$user = User::withId($id_user);
				
			$database = _obj('Database');
			$check = $database->select('id,number,status','cart',"user={$id_user} AND aggiunto_a is NULL ORDER BY evacuationDate DESC");
			if( okArray($check) ){
				$check = $check[0];
				if( in_array($check['status'],['active','sent','spedito','canceled','deleted'] )){
					return false;
				}else{
					$tot = $database->select('count(*) as tot',"cart","aggiunto_a = {$check['id']}");
					
					if( $tot[0]['tot'] == 0 ){
						return $check;
					}else{
						return false;
					}
				}
				
			}else{
				return false;
			}

	}



	function builDataOrder($cart,$id_product,$prezzo,$qnt){
		$sku = '';
		if( $id_product ){
			$prodotto = Product::withId($id_product);
			if( is_object($prodotto) ){
				$sku = $prodotto->sku;
			}
		}
		return array(
			'price_without_tax' => $prezzo['prezzo_senza_iva'],
			'price' => $prezzo['prezzo'],
			'taxPrice' =>$prezzo['prezzo'] -$prezzo['prezzo_senza_iva'],
			'weight' => 1,
			'cart' => $cart->id,
			'user' => $cart->user,
			'product' => $id_product,
			'quantity' => $qnt,
			'custom1' => serialize($prezzo),
			'timestamp' => date('Y-m-d H:i'),
			'sku' => $sku
			);
	}


    function sendMailOrder($cart){	
		$info = $this->getCart($cart->id);
		$pdf = $this->buildPDF($info);

		$generale = Marion::getConfig('generale');
		
        $this->setVar('cart', $cart);
		$this->setVar('data', $info['data']);
        //preparo l'html
		ob_start();
		$this->output('mail/close_cart.htm');
		$html = ob_get_contents();
        ob_end_clean();

		$database = _obj('Database');
		
		
		
		
        $subject = "[{$cart->number}] Nuovo ordine su ecommere.tecneco.com";
        
		$mail = _obj('Mail');
        $mail->setHtml($html);
		$mail->setSubject($subject);
		//pi� destinatari

		$to = [
				//$cart->email,
				'sales@tecneco.com',
				'gianni@tecneco.com',
				//'ciro.napolitano87@gmail.com'
		];

		$agente = $database->select('a.*','b2b_agente as a join b2b_cliente as c on c.codice_agente=a.codice_gestionale',"c.id_user={$cart->user}");

		if( okArray($agente) ){
			$to[] = $agente[0]['email'];
		}

		error_log(json_encode($to),3,_MARION_MODULE_DIR_."b2b/log/mail.log");

		//$mail->setToFromArray($to);
		
		$files = [
			[
				'data' => $pdf,
				'type' => 'application/pdf',
				'name' => 'Riepilogo ordine'
			]
		];
		$mail->addDataFiles($files);
		
		/*$mittente = $cart->email;
		if( !$mittente ){	
			
		}
		$mittente = $generale['mail'];
		$mail->setFrom($mittente);
        $res = $mail->send();*/
		
		/*INVIO AL CLIENTE*/
		if( $cart->email ){
			$mail->setFrom($generale['mail']);
			$mail->setToFromArray([$cart->email]);
			$res = $mail->send();
		}
		
		/*INVIO ALL'ADMIN*/
		$mail->_to = '';
		$mail->setToFromArray($to);
		//$to = "$utente[nome] $utente[cognome] <$utente[email]>"
		if(!$cart->email ){
			$mail->setFrom($generale['mail']);
		}else{
			$mail->setFrom($cart->email);
		}
		
        $res = $mail->send();
		return $res;
	}

	 function sendMailOrder2($cart){	
		$info = $this->getCart($cart->id);
		$pdf = $this->buildPDF($info);
		
		$generale = Marion::getConfig('generale');
		//debugga($info['data']['rows']);exit;
        $this->setVar('cart', $cart);
		$this->setVar('data', $info['data']);
        //preparo l'html
		ob_start();
		$this->output('mail/close_cart.htm');
		$html = ob_get_contents();
        ob_end_clean();
		if( !_var('send') ){
			//debugga($html);exit;
		}
		
        
		
        $subject = "[{$cart->number}] Nuovo ordine su ecommere.tecneco.com";
        
		$mail = _obj('Mail');
        $mail->setHtml($html);
		$mail->setSubject($subject);
		//pi� destinatari
		
		
		$files = [
			[
				'data' => $pdf,
				'type' => 'application/pdf',
				'name' => 'Proposta ordine'
			]
		];
		$mail->addDataFiles($files);
		

		$mail->setFrom($generale['mail']);
		$mail->setToFromArray([$cart->email]);
		
		
        $res = $mail->send();
		
		$mail->_to = '';
		$mail->setToFromArray([
				'ciro.napolitano87@gmail.com',
				'supporto@3d0.it'
		]);
		$mail->setFrom($cart->email);
		
        $res = $mail->send();
		debugga($mail);exit;
		
		return $res;
	}
	

	function buildPDF2($id_or_data=null){

		
		if( okArray($id_or_data) ){
			$info = $id_or_data;
		}else{
			$info = $this->getCart($id_or_data);
		}

		
		$cart = $info['cart'];
		$data = $info['data'];

				
				
		$this->setVar('cart',$cart);
		$this->setVar('data',$data);
		
		
		
		$database = _obj('Database');
		$promo_select = $database->select('*','b2b_product_promo',"1=1");
		foreach($promo_select as $v){
			$associazione_promo[$v['codice_gestionale']] = $v;
		}
		$righe = $data['rows'];
		foreach($righe as $k => $v){
			
			if( $promo = $associazione_promo[$v['sku']] ){
				
				$righe[$k]['promo'] = 1;
				$righe[$k]['date_from'] = $promo['date_from'];
				$righe[$k]['date_to'] = $promo['date_to'];
			}
		}
		$this->reistraFunzioniTemplate();

		$this->setVar('ordini',$righe);
	
		
		$this->setVar('baseurl',"http://".$_SERVER['SERVER_NAME']."/modules/b2b/images/");

		
		ob_start();
		$this->output('footer_pdf.htm');
		$footer = ob_get_contents();
		ob_end_clean();


		ob_start();
		$this->output('print_order.htm');
		$html = ob_get_contents();
		ob_end_clean();

		$pdf = _obj('PDF2');
		
		$pdf->setHtml($html);

		$pdf->setFooterHtml($footer);
		
		$pdf->output(wkhtmltopdf::MODE_DOWNLOAD,'order.pdf');
		exit;
		return $data;
	}


	function buildPDF($id_or_data=null){

		
		if( okArray($id_or_data) ){
			$info = $id_or_data;
		}else{
			$info = $this->getCart($id_or_data);
		}

		
		$cart = $info['cart'];
		$data = $info['data'];

				
				
		$this->setVar('cart',$cart);
		$this->setVar('data',$data);
		$database = _obj('Database');
		$promo_select = $database->select('*','b2b_product_promo',"1=1");
		foreach($promo_select as $v){
			$associazione_promo[$v['codice_gestionale']] = $v;
		}
		$righe = $data['rows'];
		foreach($righe as $k => $v){
			
			if( $promo = $associazione_promo[$v['sku']] ){
				
				$righe[$k]['promo'] = 1;
				$righe[$k]['date_from'] = $promo['date_from'];
				$righe[$k]['date_to'] = $promo['date_to'];
			}
		}
		$this->reistraFunzioniTemplate();

		$this->setVar('ordini',$righe);
	
		
		$this->setVar('baseurl',"http://".$_SERVER['SERVER_NAME']."/modules/b2b/images/");
		
		$this->reistraFunzioniTemplate();
		ob_start();
		$this->output('footer_pdf.htm');
		$footer = ob_get_contents();
		ob_end_clean();


		ob_start();
		$this->output('print_order.htm');
		$html = ob_get_contents();
		ob_end_clean();

		$pdf = _obj('PDF2');
		$pdf->setHtml($html);
		$pdf->setFooterHtml($footer);
		
		$data = $pdf->output(wkhtmltopdf::MODE_STRING);
		
		return $data;
	}


	function getBackOrder(){
		$id_user = $this->user['id'];
		if( !$id_user ) $id_user = 2356;	
		$user = User::withId($id_user);
		
		$_SESSION['userdata'] =  $user;
		$database = _obj('Database');

		$cart = Cart::getCurrent();
		$currentOrders = $database->select('product,quantity','cartRow',"cart={$cart->id}");
		foreach($currentOrders as $v){
			$carrello[$v['product']] = $v['quantity'];
		}

		
		$select = $database->select(
		'c.id,i.quantity as qnt_dispo,p.id as id_product,p.sku,cp.quantita_imballo_multiplo,cp.profilt,cp.fornito_nishiboru,cp.filtro_antibatterico,cp.img_1,cp.id_tipologia,c.qnt,cp.rank,c.timestamp,c.id as id_order',
		'(back_orders as c join (product as p left outer join product_inventory as i on i.id_product=p.id) on p.id=c.product_id) join catalogo_prodotto as cp on cp.sku_stat=p.sku',
		"user_id={$id_user} order by sku");



		
		$tipologie = array();
		$tipologia_select = $database->select('*','catalogo_tipologia_prodotto');
		foreach($tipologia_select as $v){
			$tipologie[$v['id']] = $v['nome'];
		}
		$ranks = [];
		$rank_select = $database->select('*','catalogo_rank');
		foreach($rank_select as $v){
			$ranks[$v['id']] = $v['nome'];
        }
		foreach($select as $v){
			
			$prezzo = ToolsNew::buildPrice($id_user,$v['id_product'],$v['qnt'],$this->user['no_promo']);
			//debugga($prezzo);exit;

			$qnt_input = $v['qnt'];
			if( $prezzo['quantita_omaggio'] ){
				$multiplo_omaggio = (int)($qnt_input / $prezzo['quantita_totale']);
				$quantita_omaggio =  $multiplo_omaggio*$prezzo['quantita_omaggio'];
				$prezzo['quantita_omaggio'] = $quantita_omaggio;

			}else{
				$prezzo['quantita_omaggio'] = 0;
			}
			$qnt_omaggio += $prezzo['quantita_omaggio'];
			$row = array(
				'rank' => $ranks[$v['rank']],
				'id_order' => (int)$v['id'],
				'id_product' => $v['id_product'],
				'sku' => $v['sku'],
				'qnt_dispo' => (int)$v['qnt_dispo']-$carrello[$v['id_product']],
				'id_tipologia' => $v['id_tipologia'],
				'descrizione' => $tipologie[$v['id_tipologia']]?$tipologie[$v['id_tipologia']]:'',
				'url_image' => $v['img_1']?$this->getUrlImage($v['img_1'],$v['sku']):'',
				'prezzo_base' => $prezzo['prezzo_base'],
				'prezzo' => $prezzo['prezzo_senza_iva'],
				'prezzo_con_iva' => $prezzo['prezzo'],
				'listino_italia' => $prezzo['prezzo_italia'],
				'quantita_omaggio' => $prezzo['quantita_omaggio'],
				'campagna_tipo' => $prezzo['campagna_tipo'],
				
				'sconti' => is_array($prezzo['sconti_array'])?array_values($prezzo['sconti_array']):array(),
				'qnt_input' => (int)$qnt_input,
				'qnt_input_old' => (int)$qnt_input,
				'totale' => $qnt_input?($qnt_input*$prezzo['prezzo_senza_iva']):null,
				'quantita_imballo_multiplo' => $v['quantita_imballo_multiplo'],
				'profilt' => $v['profilt']?1:0,
				'nishiboru' => $v['fornito_nishiboru']?1:0,
				'adplus' => (!$v['antibatterico'] && $v['filtro_antibatterico'])?1:0

			);

			if( $row['campagna_tipo'] == 'prezzo' ){
				$row['netto_campagna'] = $prezzo['prezzo_base'];
			}else{
				$row['netto_campagna'] = '';
			}
			$dati[] = $row;
		}

		$data = [
			'rows' => $dati
		];
		//debugga($data);exit;
		$this->success($data);
	}


	function getCart($id_cart=null){
		
		$id_user = $this->user['id'];		
		$user = User::withId($id_user);
		
		$_SESSION['userdata'] =  $user;
		
		if( $id_cart ){
			$cart = Cart::withId($id_cart);
			if( $cart->user != $id_user ){
				//$this->error("Cart not exists");
			}
		}else{
			$cart = Cart::getCurrent();
		}
		
		
		
		$database = _obj('Database');
		if( $id_cart ){
			$select = $database->select('p.id as id_product,p.sku,cp.quantita_imballo_multiplo,cp.profilt,cp.fornito_nishiboru,cp.filtro_antibatterico,cp.img_1,cp.id_tipologia,c.custom1,c.quantity,cp.rank,c.timestamp,c.id as id_order','(cartRow as c join product as p on p.id=c.product) join catalogo_prodotto as cp on cp.sku_stat=p.sku',"cart={$cart->id} order by sku");
		}else{
			$select = $database->select('i.quantity as qnt_dispo,p.id as id_product,p.sku,cp.quantita_imballo_multiplo,cp.profilt,cp.fornito_nishiboru,cp.filtro_antibatterico,cp.img_1,cp.id_tipologia,c.custom1,c.quantity,cp.rank,c.timestamp,c.id as id_order','(cartRow as c join (product as p left outer join product_inventory as i on i.id_product=p.id) on p.id=c.product) join catalogo_prodotto as cp on cp.sku_stat=p.sku',"cart={$cart->id} order by sku");
		}
		//debugga($database->lastquery);exit;
		$tipologie = array();
		$tipologia_select = $database->select('*','catalogo_tipologia_prodotto');
		foreach($tipologia_select as $v){
			$tipologie[$v['id']] = $v['nome'];
		}
		$ranks = [];
		$rank_select = $database->select('*','catalogo_rank');
		foreach($rank_select as $v){
			$ranks[$v['id']] = $v['nome'];
        }
		

		$now = time();
		$dati = array();
		$qnt_omaggio = 0;
		foreach($select as $v){
			
			if( !$id_cart && ($now > strtotime('+ 10 hours',strtotime($v['timestamp']))) ){
				//debugga('qua');exit;
				//if( $this->user['id'] == 2356 || $this->user['id'] == 1457 ){
					$prezzo = ToolsNew::buildPrice($id_user,$v['id_product'],$v['quantity'],$this->user['no_promo']);
				/*}else{
					$prezzo = Tools::buildPrice($id_user,$v['id_product'],$v['quantity'],$this->user['no_promo']);
				}*/
				
				$data_order = $this->builDataOrder($cart,$v['id_product'],$prezzo,$v['quantity']);

				$id_order = $v['id_order'];
				$database->update('cartRow',"id={$id_order}",$data_order);
				//ricalcolo il prezzo
			}else{
				$prezzo = unserialize($v['custom1']);
				if( $this->user['id'] == 2356 || $this->user['id'] == 1457 ){
					//debugga($prezzo);

				}
			}
			
			
			
			$qnt_input = $v['quantity'];
			if( $prezzo['quantita_omaggio'] ){
				$multiplo_omaggio = (int)($qnt_input / $prezzo['quantita_totale']);
				$quantita_omaggio =  $multiplo_omaggio*$prezzo['quantita_omaggio'];
				$prezzo['quantita_omaggio'] = $quantita_omaggio;

			}else{
				$prezzo['quantita_omaggio'] = 0;
			}
			$qnt_omaggio += $prezzo['quantita_omaggio'];
			$row = array(
				'rank' => $ranks[$v['rank']],
				'id_order' => $v['id'],
				'id_product' => $v['id_product'],
				'sku' => $v['sku'],
				'qnt_dispo' => $v['qnt_dispo'],
				'id_tipologia' => $v['id_tipologia'],
				'descrizione' => $tipologie[$v['id_tipologia']]?$tipologie[$v['id_tipologia']]:'',
				'url_image' => $v['img_1']?$this->getUrlImage($v['img_1'],$v['sku']):'',
				'prezzo_base' => $prezzo['prezzo_base'],
				'prezzo' => $prezzo['prezzo_senza_iva'],
				'prezzo_con_iva' => $prezzo['prezzo'],
				'listino_italia' => $prezzo['prezzo_italia'],
				'quantita_omaggio' => $prezzo['quantita_omaggio'],
				'campagna_tipo' => $prezzo['campagna_tipo'],
				
				'sconti' => is_array($prezzo['sconti_array'])?array_values($prezzo['sconti_array']):array(),
				'qnt_input' => $qnt_input,
				'qnt_input_old' => $qnt_input,
				'totale' => $qnt_input?($qnt_input*$prezzo['prezzo_senza_iva']):null,
				'quantita_imballo_multiplo' => $v['quantita_imballo_multiplo'],
				'profilt' => $v['profilt']?1:0,
				'nishiboru' => $v['fornito_nishiboru']?1:0,
				'adplus' => (!$v['antibatterico'] && $v['filtro_antibatterico'])?1:0

			);

			if( $row['campagna_tipo'] == 'prezzo' ){
				$row['netto_campagna'] = $prezzo['prezzo_base'];
			}else{
				$row['netto_campagna'] = '';
			}
			$dati[] = $row;
		}
		$tot = $database->select('sum(quantity) as num, sum(quantity*price_without_tax) as tot_without_vat,sum(quantity*price) as tot, sum(quantity) as tot_pieces',"cartRow","cart={$cart->id}");
		
		if ($id_cart) {
			if( $cart->aggiunto_a ){
				$old_order = $database->select('*','cart',"id={$cart->aggiunto_a}");
				if( okArray($old_order) ){
					$number = $old_order[0]['number']."/1";
				}else{

					$number = $cart->number;
				}

			}
			$data = array(
				'rows' => $dati,
				'num_products' => $tot[0]['num'],
				'total_without_vat' => $tot[0]['tot_without_vat'],
				'total' => $tot[0]['tot'],
				'total_pieces' => $tot[0]['tot_pieces'],
				'number' => $number,
				'date' => $cart->evacuationDate,
				'city'=>$cart->shippingCity,
				'province'=>$cart->shippingProvince?$cart->shippingProvince:$cart->province,
				'country'=>$cart->shippingCountry?$cart->shippingCountry:$cart->country,
				'address'=>$cart->shippingAddress?$cart->shippingAddress:$cart->address,
				'postalCode'=>$cart->shippingPostalCode?$cart->shippingPostalCode:$cart->postalCode,
				'notes'=>$cart->note,
				'qnt_omaggio' => $qnt_omaggio,
				'total_vat' => $tot[0]['tot']-$tot[0]['tot_without_vat'],
				'total_pieces_with_omaggi'=> $qnt_omaggio+$tot[0]['tot_pieces']
			);
		}else{
			$data = array(
				'rows' => $dati,
				'num_products' => $tot[0]['num'],
				'qnt_omaggio' => $qnt_omaggio,
				'total_without_vat' => $tot[0]['tot_without_vat'],
				'total_vat' => $tot[0]['tot']-$tot[0]['tot_without_vat'],
				'total' => $tot[0]['tot'],
				'total_pieces' => $tot[0]['tot_pieces'],
				'total_pieces_with_omaggi'=> $qnt_omaggio+$tot[0]['tot_pieces']
			);
		}
	
		return array(
			'cart' => $cart,
			'data' => $data
		);
	
	}



	function reistraFunzioniTemplate(){
		

		/*
		function controllo_data_promo($datainizio,$datafine){
		
		$now = strtotime(date('Y-m-d'));
		if( $datainizio && $datafine ){
			$datainizio = strtotime($datainizio);
			$datafine = strtotime($datafine);
			if( $datainizio > $datafine ){
				return false;
			}else{
				if( $now >= $datainizio && $now <= $datafine ){
					return true;
				}else{
					return false;
				}
			}
		}else{
			return false;
		}
	}

		*/

		$this->addTemplateFunction(new \Twig\TwigFunction('controllo_data_promo', function ($datainizio,$datafine) {
			$now = strtotime(date('Y-m-d'));
			if( $datainizio && $datafine ){
				$datainizio = strtotime($datainizio);
				$datafine = strtotime($datafine);
				if( $datainizio > $datafine ){
					return false;
				}else{
					if( $now >= $datainizio && $now <= $datafine ){
						return true;
					}else{
						return false;
					}
				}
			}else{
				return false;
			}
		}));

	}


	



}
?>