<?php
require('modules/b2b/controllers/front/ApiController.php');
class Order2Controller extends ApiController{
	private $codice_cliente = 0;
	private $codice_listino_cliente = 0;

	
	function display(){
		//parent::display();
		$id_user = $this->user['id'];
		$action = $this->getAction();
		switch($action){
			case 'hostory':

			case 'cart':
				$id_user = 376;

				$user = User::withId($id_user);
				$_SESSION['userdata'] =  $user;

				$cart = Cart::getCurrent();
				
				$database = _obj('Database');
				$select = $database->select('*','cartRow',"cart={$cart->id}");
				//debugga($select);exit;
				foreach($select as $v){
					
					$prezzo = unserialize($v['custom1']);
					debugga($v);
					$row = array(
						'id_product' => $v['product'],
						//'sku' => $v['sku'],
						//'id_tipologia' => $v['id_tipologia'],
						//'descrizione' => $tipologie[$v['id_tipologia']]?$tipologie[$v['id_tipologia']]:'',
						//'url_image' => $v['img_1']?$this->getUrlImage($v['img_1'],$v['sku']):'',
						'prezzo' => $prezzo['prezzo_senza_iva'],
						'listino_italia' => $prezzo['prezzo_italia'],
						'sconti' => is_array($prezzo['sconti_array'])?$prezzo['sconti_array']:array(),
						'qnt_input' => $v['quantity'],
						'totale' => $v['quantity']*$v['price_without_tax'],
						//'quantita_imballo_multiplo' => $v['quantita_imballo_multiplo'],
						//'profilt' => $v['profilt']?1:0,
						//'nishiboru' => $v['fornito_nishiboru']?1:0,
						//'adplus' => (!$v['antibatterico'] && $v['filtro_antibatterico'])?1:0

					);
					debugga($row);
					
				}
				break;

			case 'add_to_cart':
				require('modules/b2b/classes/Tools.class.php');
				//$id_product = _var('id_product');
				
				$input = json_decode(file_get_contents('php://input'), true);
				$list = $input['list'];
				$list = array(
					array(
						'id_product' => 2879,
						'quantity' => 20
					),
				);

				$id_user = 376;
				


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

					$check = Tools::checkQuantity($v['id_product'],$v['quantity']);
					if( $check['success'] ){
						$prezzo = Tools::buildPrice($id_user,$v['id_product'],$v['quantity']);
						
						$row->set(
							array(
							'price_without_tax' => $prezzo['prezzo_senza_iva'],
							'price' => $prezzo['prezzo'],
							'taxPrice' =>$prezzo['prezzo'] -$prezzo['prezzo_senza_iva'],
							'weight' => 1,
							'cart' => $cart->id,
							'user' => $id_user,
							'product' => $v['id_product'],
							'quantity' => $v['quantity'],
							'custom1' => serialize($prezzo)
							)

						);

						$row->save();
					}else{
						$errors[] = array(
							'index' => $ind,
							'id_product' => $v['id_product'],
							'error' => utf8_encode($check['error'])
						);
					}
				}
				$database = _obj('Database');
				$tot = $database->select('count(quantity) as num, sum(quantity*price_without_tax) as tot',"cartRow","cart={$cart->id}");
				
				

				$data = array(
					'errors' => $errors,
					'num_items' => $tot[0]['num'],
					'tot_items' => $tot[0]['tot'],
				);
				
				$this->success($data);
				break;
			case 'get_price':
				
				$id_product = _var('id_product'); //parametro input
				$qnt = _var('quantity'); //parametro input
				//$sku = 2879;
				//$id_user = 376;
				$qnt = 50;
				require('modules/b2b/classes/Tools.class.php');
				$check = Tools::checkQuantity($sku,$qnt);
				
				if( $check['success'] ){
					$prezzo = Tools::buildPrice($id_user,$sku,$qnt);
				}else{
					$qnt = $check['max_qnt'];
					$prezzo = Tools::buildPrice($id_user,$sku,$qnt);
				}

				$response = $check;
				//$response['prezzo'] = $prezzo;
				$response['prezzo'] = array(
					'prezzo' => $prezzo['prezzo_senza_iva'],
					'totale' => $prezzo['prezzo_senza_iva']*$qnt,
					'sconti' => is_array($prezzo['sconti_array'])?$prezzo['sconti_array']:array(),

				);

				$this->success($response);
			break;
		}

		

		
	}

	function getOrders(){
		
		
		$user = Marion::getUser();
		$carrelli = Cart::prepareQuery()
				->where('user',$user->id)
				->where('status','active','<>')
				->orderBy('evacuationDate','DESC')
				->get();
		$stati = CartStatus::prepareQuery()->get();
		foreach($stati as $v){
			$status[$v->label] = "<span class='label' style='background:".$v->color."'>".strtoupper($v->get('name'))."</span>";
		}
		
		if(okArray($carrelli)){

			foreach($carrelli as $v){
				$v->status = $status[$v->status];
			}
			//preparo il pager
			$params = PagerConfig::withLabel('order')->getParams();
			//inserisco i dati per cui voglio il pager
			$params['itemData'] = $carrelli;
			
			//creo il pager
			require_once 'Pager.php';
			$pager = &Pager::factory($params);
		
			$data = $pager->getPageData();

			//prendo i link del pager
			$links = $pager->getLinks();
			$this->setVar('carrelli',$data);
			$this->setVar('links',$links);
			
		}
		$this->output('orders.htm');
	}





	



}
?>