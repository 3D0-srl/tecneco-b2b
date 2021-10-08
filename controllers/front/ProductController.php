<?php
require('modules/b2b/controllers/front/ApiController.php');
class ProductController extends ApiController{
	 
	 private $url = '';

	 public function getUrl()
	 {
		return sprintf(
			"%s://%s",
			isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
			$_SERVER['SERVER_NAME']
		);
	 }


	function display(){
		$database = _obj('Database');
		$this->url = $this->getUrl();
		if( !_var('escape') ){
			parent::display();
		}
		$id_user = $this->user['id']; //prendo l'id dell'utente dal JWT


		$action = $this->getAction();
		//$id_user = 376;
		switch( $action ){
			 case 'excel':
				 
				
				require('modules/b2b/spreadsheet-reader/php-excel-reader/excel_reader2.php');
				require('modules/b2b/spreadsheet-reader/SpreadsheetReader.php');
				$data = json_decode(file_get_contents("php://input"),true);
				
				$path = sys_get_temp_dir()."/".$data['name'];
				
				file_put_contents($path,base64_decode($data['content']));
				$ext = explode('.',$data['name']);
				switch(strtolower($ext[1])){
					case 'csv':
						list($list_sku,$qnt) = $this->readCSV($path);
						break;
					case 'xls':
						list($list_sku,$qnt) = $this->readXLS($path);
                        break;
					case 'xlsx':
						list($list_sku,$qnt) = $this->readXLSX($path);
						break;
					default:

						break;
				}
				


				$dataIncoming = $this->getProductsInList($id_user,$list_sku,$qnt);
				$list = $dataIncoming['list'];


				$data = array();
				$data['data'] = $list;
                $data['errors'] = $errors;
                $data['list_errors'] = $dataIncoming['errors'];
				$this->success($data);
				
				break;
			case 'autocomplete':
				$text = _var('text');  //parametro input

				$sostituito = $database->select('*','catalogo_prodotto',"sku_stat = '{$text}' AND sostituito_da IS NOT NULL");
			
				if( okArray($sostituito) ){
					$data = [
						'sostituito_da' => $sostituito[0]['sostituito_da']
					];
					$this->error($data);
				}
                $data = $database->select('id, sku','product',"sku LIKE '%{$text}%'");
				
				$this->success($data);
				break;
			case 'get_info_product':
				$id_product = _var('id_product');  //parametro input
                $data = $this->getProductInfo($id_product);
				$this->success($data);
				break;
			case 'search_product':
				$sku = _var('sku');  //parametro input
				

				/* ESEMPIO */
				//$sku = 'AR100';
				

				$data = $this->getProductsBySku($id_user,$sku);
				$this->success($data);
				break;
			case 'search_concorrente':
				$sku = _var('sku');  //parametro input
				

				/* ESEMPIO */
				//$sku = 'AR100';
				

				$data = $this->getProductsByConcorrente($id_user,$sku);
				$this->success($data);
				break;
			case 'search_auto':
				$id_auto = _var('id_auto');
				
					/* ESEMPIO */
				//$id_auto = 405;
				
				$data = $this->getProductsByAuto($id_user,$id_auto);
				$this->success($data);
				break;
			case 'marche': //costruttore
				$this->getMarche();
				break;
			case 'gruppi': //serie
				$id_marca = _var('id_marca'); //parametro input
				$this->getGruppi($id_marca);
				break;
			case 'modelli': // modello
				$id_gruppo = _var('id_gruppo');  //parametro input
				$this->getModelli($id_gruppo);
				break;
			case 'motori':
				$id_modello = _var('id_modello');  //parametro input
				$id_gruppo = _var('id_gruppo');  //parametro input
				$this->getMotori($id_gruppo,$id_modello);
				break;
		}
	}

	function getInfoProduct($id_product){

	}
	

	function getMarche(){
		$database = _obj('Database');
		$list = $database->select('id,nome','catalogo_marca','1=1 order by nome');
		$this->success($list);
	}

	function getGruppi($id_marca){
		$id_marca = (int)$id_marca;
		$database = _obj('Database');

		$list = $database->select('id,nome','catalogo_gruppo_auto',"id_catalogo_marca={$id_marca}");
		$this->success($list);
	}

	function getModelli($id_gruppo){
		$id_gruppo = (int)$id_gruppo;
		$database = _obj('Database');

		$list = $database->select('distinct distinct modello','catalogo_auto',"gruppo={$id_gruppo} order by modello ASC");
		$data = array();
		foreach($list as $v){
			$data[] = $v['modello'];
		}
		$this->success($data);
	}

	function getMotori($id_gruppo,$id_modello){
		$id_auto = (int)$id_gruppo;
		$database = _obj('Database');

		$list = $database->select('id as id_auto,allestimento','catalogo_auto',"gruppo={$id_auto} AND modello='{$id_modello}' order by allestimento");
		$this->success($list);
	}

	/*function getProductsInList($id_user,$list_sku,$qnt){
		//devi creare kla condizione a partire da $list_sku e metterla nella queru
		$database = _obj('Database');
		$where = '';
		foreach($list_sku as $v){
			$where = "'{$v}',";
		}
		$where = preg_replace('/\,$/','',$where);
		$list = $database->select('p.id,p.sku,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia','product as p join catalogo_prodotto as c on c.sku_stat=p.sku',"p.sku IN ({$where})");
			

		return $this->getProductList($id_user,$list,$qnt);

	}*/

	function getProductsInList($id_user,$list_sku,$qnt){
        $database = _obj('Database');
        $list = array();
        $errors = array();
        $i = 0;
		

		$id_user = $this->user['id'];
		$user = User::withId($id_user);


		//if( $this->user['id'] == 2356 || $this->user['id'] == 1457 ){
			if( okArray($qnt) ){
				
				$_SESSION['userdata'] =  $user;
				$cart = Cart::getCurrent();

				
				$old_data = $database->select('quantity,c.sku_stat,c.sku','cartRow as r join (product as p join catalogo_prodotto as c on c.sku_stat=p.sku) on p.id=r.product',"cart={$cart->id}");
				
				
				foreach($old_data as $v){
					if( array_key_exists($v['sku'],$qnt) ){
						$qnt[$v['sku']] += $v['quantity'];
					}else{
						if( array_key_exists($v['sku_stat'],$qnt) ){
							$qnt[$v['sku_stat']] += $v['quantity'];
						}
					}
					
				}
			}
		//}
		

        foreach ($list_sku as $product) {
			if( trim($product) ){
				if(is_numeric($qnt[$product])){
					$product_trim = preg_replace('/\s/','',$product);
					$productQuery = $database->select('p.id,p.sku,c.rank,i.quantity as qnt_dispo,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia,c.watermark,c.sostituito_da','(product as p left outer join product_inventory as i on i.id_product=p.id) join catalogo_prodotto as c on c.sku_stat=p.sku',"p.sku = '{$product}' OR c.sku = '{$product}' OR p.sku = '{$product_trim}' OR c.sku = '{$product_trim}'");
					if($productQuery){
						foreach ($productQuery as $single_product) {

							/** AGGIUNTA CODICE SOSTITUTIVO */
							if( $single_product['sostituito_da'] ){
								$error['product_id']=$single_product['sku'];
								$error['message']="NEW_CODE";
								$error['new_code']=$single_product['sostituito_da'];
								
								$new_sku = $single_product['sostituito_da'];
								$productQuery_new = $database->select('p.id,p.sku,c.rank,i.quantity as qnt_dispo,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia,c.watermark','(product as p left outer join product_inventory as i on i.id_product=p.id) join catalogo_prodotto as c on c.sku_stat=p.sku',"p.sku = '{$new_sku}' OR c.sku = '{$new_sku}'");
								if( okarray($productQuery_new) ){

									$single_product = $productQuery_new[0];

									if( $product != $single_product['sku'] ){
										//$product =  $single_product['sku'];
										$qnt[$single_product['sku']] = $qnt[$product];
									}
									$error2 = null;
									if( $single_product['qnt_dispo'] > 0 ){

										if( $single_product['qnt_dispo'] < $qnt[$product] ){
										
											 $error2 = [
												'product_id' => $new_sku,
												'message' => 'OUT_OF_STOCK',
												'qnt_order' => $qnt[$product],
												'qnt_dispo' => $single_product['qnt_dispo']

											];
											$qnt[$product] =$single_product['qnt_dispo'];
											if( $product != $single_product['sku'] ){
												$qnt[$single_product['sku']] = $single_product['qnt_dispo'];
											}
										}
										array_push($list, $single_product);
									}else{
										 $error2 = [
												'product_id' => $new_sku,
												'message' => 'UNAVAILABLE',
												'qnt_order' => $qnt[$product]

											];
									}
								}
								
								array_push($errors, $error);
								if( $error2 ){
									array_push($errors, $error2);
								}
								continue;

							}




							//if( $user->username == 'cicciobello' ){
							if( $product != $single_product['sku'] ){
								//$product =  $single_product['sku'];
								$qnt[$single_product['sku']] = $qnt[$product];
							}
								//debugga($single_product);exit;
							//}

							if( $single_product['qnt_dispo'] > 0 ){

								if( $single_product['qnt_dispo'] < $qnt[$product] ){
								
									 $error = [
										'product_id' => $product,
										'message' => 'OUT_OF_STOCK',
										'qnt_order' => $qnt[$product],
										'qnt_dispo' => $single_product['qnt_dispo']

									];
									$qnt[$product] =$single_product['qnt_dispo'];
									if( $product != $single_product['sku'] ){
										$qnt[$single_product['sku']] = $single_product['qnt_dispo'];
									}
									array_push($errors, $error);
								}
								array_push($list, $single_product);

							}else{
								 $error = [
										'product_id' => $product,
										'message' => 'UNAVAILABLE',
										'qnt_order' => $qnt[$product],
								 ];
								 array_push($errors, $error);
							}
							
						}
					}
					else{
						$error = [];
						$error2 = null;
						//controllo se � stato sostituito
						$select = $database->select('*','catalogo_prodotto',"sku_stat='{$product}' OR sku='{$product}'");
						if( okArray($select) && $select[0]['sostituito_da']){
							$error['product_id']=$product;
							$error['message']="NEW_CODE";
							$error['new_code']=$select[0]['sostituito_da'];
							
							$new_sku = $select[0]['sostituito_da'];
							$productQuery_new = $database->select('p.id,p.sku,c.rank,i.quantity as qnt_dispo,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia,c.watermark','(product as p left outer join product_inventory as i on i.id_product=p.id) join catalogo_prodotto as c on c.sku_stat=p.sku',"p.sku = '{$new_sku}' OR c.sku = '{$new_sku}'");
							if( okarray($productQuery_new) ){

								$single_product = $productQuery_new[0];

								if( $product != $single_product['sku'] ){
									//$product =  $single_product['sku'];
									$qnt[$single_product['sku']] = $qnt[$product];
								}
								if( $single_product['qnt_dispo'] > 0 ){

									if( $single_product['qnt_dispo'] < $qnt[$product] ){
									
										 $error2 = [
											'product_id' => $new_sku,
											'message' => 'OUT_OF_STOCK',
											'qnt_order' => $qnt[$product],
											'qnt_dispo' => $single_product['qnt_dispo']

										];
										$qnt[$product] =$single_product['qnt_dispo'];
										if( $product != $single_product['sku'] ){
											$qnt[$single_product['sku']] = $single_product['qnt_dispo'];
										}
									}
									array_push($list, $single_product);
								}
							}
						}else{

							$error['product_id']=$product;
							$error['message']="INVALID_CODE";
						}
						array_push($errors, $error);
						if( $error2 ){
							array_push($errors, $error2);
						}
					}
				}else{
					$error['product_id']=$product;
					$error['message']="INVALID_INT";
					array_push($errors, $error);
				}
			}
        }
		
        $toReturn['errors'] = $errors;
        $toReturn['list'] = $this->getProductList($id_user,$list,$qnt);
		return $toReturn;

	}

	function getProductsByConcorrente($id_user,$sku){
		$database = _obj('Database');
		//$database->select('p.id,p.sku,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia','product as p join catalogo_prodotto as c on c.sku=p.sku',"p.sku LIKE '%{$sku}%'");
		
		$list = $database->select('cp.id_prodotto',"catalogo_codice_concorrente as co join catalogo_concorrente_prodotto as cp on cp.id_codice_concorrente=co.id","codice_filtro LIKE '{$sku}%' AND id_prodotto is not null");
		//debugga($database->lastquery);exit;
		$where = '';
		foreach($list as $v){
			$where .= "{$v['id_prodotto']},";
		}
		$where = preg_replace('/\,$/','',$where);
		
		//2354,1861,211,66,1574,1222
		//debugga($where);exit;
		
		$list = $database->select('p.id,p.sku,c.rank,i.quantity as qnt_dispo,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia,c.watermark','(product as p left outer join product_inventory as i on i.id_product=p.id) join catalogo_prodotto as c on c.sku_stat=p.sku',"c.id IN ({$where}) order by p.sku");
		
	
		return $this->getProductList($id_user,$list);
	}

	function getProductsBySku($id_user,$sku){
		if( preg_match('/^CK/',$sku) ){
			$product_alias = preg_replace('/^CK/','CKA',$sku);
			$product_alias_trim = preg_replace('/\s/','',$product_alias);
		}
		$database = _obj('Database');
		$product_trim = preg_replace('/\s/','',$sku);

		$sostituito = $database->select('*','catalogo_prodotto',"sku_stat = '{$product_trim}' AND sostituito_da IS NOT NULL");

			
			
		if( okArray($sostituito) ){
			$data = [
				'sostituito_da' => $sostituito[0]['sostituito_da']
			];
			$this->error($data);
		}

		if( $product_alias ){
			$list = $database->select('p.id,p.sku,c.rank,i.quantity as qnt_dispo,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia,c.watermark','(product as p left outer join product_inventory as i on i.id_product=p.id) join catalogo_prodotto as c on c.sku_stat=p.sku',"p.sku LIKE '%{$sku}%' OR c.sku LIKE '%{$sku}%' OR p.sku LIKE '%{$product_trim}%' OR c.sku LIKE '%{$product_trim}%' OR p.sku LIKE '%{$product_alias}%' OR p.sku LIKE '%{$product_alias_trim}%' OR c.sku LIKE '%{$product_alias}%' OR c.sku LIKE '%{$product_alias_trim}%' order by p.sku");
		}else{
			$list = $database->select('p.id,p.sku,c.rank,i.quantity as qnt_dispo,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia,c.watermark','(product as p left outer join product_inventory as i on i.id_product=p.id) join catalogo_prodotto as c on c.sku_stat=p.sku',"p.sku LIKE '%{$sku}%' OR c.sku LIKE '%{$sku}%' OR p.sku LIKE '%{$product_trim}%' OR c.sku LIKE '%{$product_trim}%' order by p.sku");
		}
	
		//if( !okArray($list) ){
			
		//}
		

		
		return $this->getProductList($id_user,$list);

	}



	function getProductsByAuto($id_user,$id_auto){
		
		$database = _obj('Database');
		$list = $database->select('c.id as id_prodotto,p.id,p.sku,c.rank,i.quantity as qnt_dispo,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia,c.watermark','((product as p left outer join product_inventory as i on i.id_product=p.id) join catalogo_prodotto as c on c.sku_stat=p.sku) join catalogo_auto_prodotto as ap on ap.id_prodotto=c.id',"ap.id_auto={$id_auto}  order by p.sku");
		

		//$list = $database->select('p.id,p.sku,c.rank,i.quantity as qnt_dispo,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia,c.watermark','((product as p left outer join product_inventory as i on i.id_product=p.id) join catalogo_prodotto as c on c.sku_stat=p.sku) join catalogo_auto_prodotto as ap on ap.id_prodotto=c.id',"ap.id_auto={$id_auto}  order by p.sku");
		$user = User::withId($id_user);
		if( okArray($list) ){
			
			foreach($list as $k => $v){
				$applicazioni = $database->select('*','catalogo_auto_prodotto',"id_prodotto={$v['id_prodotto']} AND id_auto={$id_auto} order by ordine");

				
				foreach($applicazioni as $a){
					if( $a['descrizione'] ){
						$list[$k]['applicazioni'][] = $a['descrizione'];
					}
				}
			}
			
		}


		return $this->getProductList($id_user,$list);

	}

	function getProductInfo($id_product){
		
		$database = _obj('Database');
		$list = $database->select('c.*','product as p join catalogo_prodotto as c on c.sku_stat=p.sku',"p.id={$id_product}");

		
		


		$data = $list[0];
		$remote_data = json_decode(file_get_contents('https://tecneco.com/index.php?ctrl=CatalogOnline&mod=catalogoOnline&action=get_scheda_filtro&filtro='.$data['id'].'&json_b2b=1'),true);
		

		$_gruppi = $remote_data['gruppi']['gruppo'];
		$_concorrenti = $remote_data['concorrenti'];
		
		$gruppi = [];
		foreach($_gruppi as $k=>$values){
			
			$valori = [];
			
			foreach($values as $k1 =>$_modelli){
				$modelli = [];
				foreach($_modelli as $mod){
					$modelli[] = $mod['modello'];
				}
				$valori[] = [
						'key' => $k1,
						'values' => $modelli
					];
			}
			$gruppi[] = [
				'key' => $k,
				'values' => $valori
			];
		}

		$dati_scheda = $remote_data['scheda'];
		$dati_scheda['qrcode'] = "https://www.tecneco.com/qrcode/{$dati_scheda['imgcodart']}.png?v=1";
		if( $dati_scheda['report_tecnici'] ){
			$dati_scheda['report_tecnico_link'] = "https://catalogo.tecneco.com/index.php?action=download&ctrl=Media&id={$dati_scheda['report_tecnici']}&type=attachment";
		}
		
		unset($dati_scheda['etichetta_tecneco']);


		$concorrenti = [];
		foreach($_concorrenti as $k => $v){
			$valori = [];
			foreach($v as $v1){
				$valori[] = $v1['codice_filtro_concorrente'];
			}
			$concorrenti[] = [
				'key' => $k,
				'values' => $valori
			];
		}
		$toreturn = [
				'applicazioni' => $gruppi,
				'concorrenti' => $concorrenti,
				'scheda' => $dati_scheda
		];
	
		//degga($remote_data['gruppi']);exit;
		/*debugga($remote_data);exit;
		if( okArray($data) ){
			if( $data['img_1'] ){
				$data['image_url1'] = $this->getUrlImage($data['img_1'],$data['sku']);
			}
			if( $data['img_2'] ){
				$data['image_url2'] = $this->getUrlImage($data['img_2'],$data['sku']);
			}

			$tipologia_select = $database->select('*','catalogo_tipologia_prodotto',"id={$data['id_tipologia']}");
			if( okArray($tipologia_select) ){
				$data['nome_tipologia'] = $tipologia_select[0]['nome'];
			}
		}*/

		//debugga($data);exit;
		
		return $toreturn;

	}



	function getProductList($id_user,$list,$qnt_sku=array()){
		require('modules/b2b/classes/BackOrder.class.php');
		$user = User::withId($id_user);
		
		if( $user->backorder ){
			$backorders = BackOrder::getAll($user->id);
		}

		


		require('modules/b2b/classes/Tools.class.php');
		require('modules/b2b/classes/ToolsNew.class.php');
		$user = User::withId($id_user);
		$_SESSION['userdata'] =  $user;
		$cart = Cart::getCurrent();
		$database = _obj('Database');
		$qnt_old = [];
		$id_order = [];
		if( !okArray($qnt_sku) ){
			$old_data = $database->select('id,product,quantity','cartRow',"cart={$cart->id}");
			foreach($old_data as $v){
				$qnt_old[$v['product']] = $v['quantity'];
				$id_order[$v['product']] = $v['id'];
			}
		}

		
		
		$data = array();
		$tipologie = array();
		$ranks = array();
		$tipologia_select = $database->select('*','catalogo_tipologia_prodotto');
		foreach($tipologia_select as $v){
			$tipologie[$v['id']] = $v['nome'];
        }
		$rank_select = $database->select('*','catalogo_rank');
		foreach($rank_select as $v){
			$rank[$v['id']] = $v['nome'];
        }
        
		$now_date = date('Y-m-d'); 
        $promos = $database->select('p.id, p.codice_gestionale, p.date_from, p.date_to, pl.description,pl.pulsante_testo','b2b_product_promo as p join b2b_product_promo_lang as pl on p.id=pl.id_product_promo',"date_from <= '{$now_date}' AND date_to >= '{$now_date}' ");
		
		foreach($list as $k =>  $v){
			if( okArray($qnt_sku) ){
				$qnt = $qnt_sku[$v['sku']]?$qnt_sku[$v['sku']]:1; //prendere quella presente nel carrello per l'articolo
				$qnt_input =  $qnt_sku[$v['sku']];
			}else{
				$qnt = $qnt_old[$v['id']]?$qnt_old[$v['id']]:1; //prendere quella presente nel carrello per l'articolo
				$qnt_input =  $qnt_old[$v['id']];

			}

			//if( $this->user['id'] == 2356 || $this->user['id'] == 1457 ){
				$prezzo = ToolsNew::buildPrice($id_user,$v['id'],$qnt,$this->user['no_promo']);
			/*}else{
				$prezzo = Tools::buildPrice($id_user,$v['id'],$qnt,$this->user['no_promo']);
			}*/
			
			
			$row = array(
				'id_product' => $v['id'],
				'sku' => $v['sku'],
				'qnt_dispo' => $v['qnt_dispo'],
				'rank' =>$rank[$v['rank']]?$rank[$v['rank']]:'',
				'id_tipologia' => $v['id_tipologia'],
				'descrizione' => $tipologie[$v['id_tipologia']]?$tipologie[$v['id_tipologia']]:'',
				'url_image' => $v['img_1']?$this->getUrlImage($v['img_1'],$v['sku'],$v['watermark']):'',
				'prezzo' => $prezzo['prezzo_senza_iva'],
				'listino_italia' => $prezzo['prezzo_italia'],
				'campagna_tipo' => $prezzo['campagna_tipo'],
				'campagna' => $prezzo['campagna'],
				'sconti' => is_array($prezzo['sconti_array'])?array_values($prezzo['sconti_array']):array(),
				'qnt_input' => $qnt_input,
				'qnt_input_old' => $qnt_input,
				'id_order' => $id_order[$v['id']],
				'totale' => $qnt_input?($qnt_input*$prezzo['prezzo_senza_iva']):null,
				'quantita_imballo_multiplo' => $v['quantita_imballo_multiplo'],
				'profilt' => $v['profilt']?1:0,
				'nishiboru' => $v['fornito_nishiboru']?1:0,
				'adplus' => (!$v['antibatterico'] && $v['filtro_antibatterico'])?1:0,
				'applicazioni' => $v['applicazioni']

			);
			
			if( isset($backorders) && isset($backorders[$v['id']]) ){

				$row['qnt_backorder_original'] = $backorders[$v['id']]['qnt'];
				$row['qnt_backorder'] = $backorders[$v['id']]['qnt'];// - $qnt_input;
				$row['qnt_backorder_old'] = $backorders[$v['id']]['qnt'] - $qnt_input;
				$row['id_backorder'] = $backorders[$v['id']]['id'];
				
				
			}
			if( $prezzo['quantita_omaggio'] ){
		
				$multiplo_omaggio = (int)($qnt / $prezzo['quantita_totale']);
				$quantita_omaggio =  $multiplo_omaggio*$prezzo['quantita_omaggio'];
				$row['quantita_omaggio'] = $quantita_omaggio;

			}else{
				$row['quantita_omaggio'] = 0;
			}

			if( $prezzo['campagna_tipo'] == 'prezzo' ){
				$row['netto_campagna'] = $prezzo['prezzo_base'];
			}else{
				$row['netto_campagna'] = '';
			}
			$row['totale'] = $row['prezzo'] * $qnt;
            $row['promo'] = 0;
            $row['testo_promo'] = '';
			$row['pulsante_promo'] = 'PROMO';
            
			
			foreach($promos as $promo){
                if($promo['codice_gestionale'] == $row['sku']){
                    $row['promo'] = 1;
					//$descr = htmlentities($promo['description'], ENT_QUOTES|"ENT_HTML401", "UTF-8", true);
					//$row['testo_promo'] =$descr;
					//$row['testo_promo'] = preg_replace('/�/','</br>�',$promo['description']);
                    $row['testo_promo'] = nl2br($promo['description']);
					if( trim($promo['pulsante_testo']) ){
						$row['pulsante_promo'] = strtoupper($promo['pulsante_testo']);
					}
                    $row['promo_date_from'] = $promo['date_from'];
                    $row['promo_date_to'] = $promo['date_to'];
					//debugga($promo);exit;
                }
            }
            
			$data[] = $row;
		}
		return $data;
	}



	function getUrlImage($id,$sku,$watermark){
		if( $watermark ){
			return $this->url."/img/".$id."/md-".$watermark."/".$sku.".png";
		}else{
			return $this->url."/img/".$id."/md/".$sku.".png";
		}
		
	}

	 function readCSV($file){
        $row = 1;
        $sku = array();
        $qnt = array();
        if (($handle = fopen($file, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, ";")) !== false) {
                if ($row == 1) {
                    $row++;
                    continue;
                }
                $sku[] = trim($data[0]);
                $qnt[trim($data[0])] += trim($data[1]);
            }
            fclose($handle);
        }
        return array(array_unique($sku),$qnt);
    }
    
    function readXLS($file){
        $sku = array();
        $qnt = array();
        $Reader = new SpreadsheetReader($file);
        $flag = 0;
        foreach ($Reader as $Row) {
            if ($flag) {
                $sku[] = trim($Row[0]);
                $qnt[trim($Row[0])] += trim($Row[1]);
            } else {
                $flag = 1;
            }
        }
		return array(array_unique($sku),$qnt);
    }

}