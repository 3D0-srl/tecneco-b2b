<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: multipart/form-data; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, GET, POST, FILES");
header("Allow: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require('modules/b2b/controllers/front/ApiController.php');
require('modules/b2b/spreadsheet-reader/php-excel-reader/excel_reader2.php');
require('modules/b2b/spreadsheet-reader/SpreadsheetReader.php');

class Test2Controller extends ApiController{
	 
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
		 $this->url = $this->getUrl();

		//parent::display();
		$id_user = $this->user['id']; //prendo l'id dell'utente dal JWT
		$action = $this->getAction();
		//$id_user = 376;
		switch( $action ){
            case 'excel':
				$data = json_decode(file_get_contents("php://input"),true);
				$path = "modules/b2b/xls/".$data['name'];
				
				file_put_contents($path,base64_decode($data['content']));
				$ext = explode('.',$data['name']);
				switch(strtolower($ext[1])){
					case 'csv':
						list($list_sku,$qnt) = $this->readCSV($path);
						break;
					case 'xls':
						list($list_sku,$qnt) = $this->readXLS($path);
                        break;/*
					case 'xlsx':
						list($list_sku,$qnt) = $this->readXLSX($path);
						break;*/
					default:

						break;
				}
				
				
				debugga($qnt);exit;
                
				//list($list_sku,$qnt) = $this->parseExcel();

				$list_sku = array('A100','A200');
				$qnt = array(
					'A1000' => 2, //id_prodotto => qnt	
					'A200' => 10 //id_prodotto => qnt	
				);
				$list = $this->getProductsInList($id_user,$list_sku,$qnt);


				$data = array();
				$data['list'] = $list;
				$data['errors'] = $errors;
				$this->success($data);
				break;
			case 'get_info_product':
				$id_product = _var('id_product');  //parametro input
				$id_product=2866;
				$this->getProductInfo($id_product);
				break;
			case 'search_product':
				$sku = _var('sku');  //parametro input
				

				/* ESEMPIO */
				//$sku = 'AR100';
				

				$data = $this->getProductsBySku($id_user,$sku);
				$this->success($data);
				break;
			case 'search_auto':
				$id_auto = _var('id_auto');
				
					/* ESEMPIO */
				$id_auto = 405;
				
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
    
	function readCSV($file){
		$row = 1;
		$sku = array();
		$qnt = array();
		if (($handle = fopen($file, "r")) !== FALSE) {
		  while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
			if( $row == 1 ){ 
				 $row++;
				 continue;
			}
			$sku[] = trim($data[0]);
			$qnt[trim($data[0])] = trim($data[1]);
			
		  }
		  fclose($handle);
		}

		return array($sku,$qnt);
	}
    
	function readXLS($file){
        $data = new Spreadsheet_Excel_Reader($file);
        debugga($data);
        $Reader = new SpreadsheetReader($file);
        debugga($Reader);
        exit;

        foreach ($Reader as $Row)
        {
            print_r($Row);
        }
        exit;
		$row = 1;
		$sku = array();
		$qnt = array();
            $Reader = new SpreadsheetReader($file);
            $Sheets = $Reader -> Sheets();
        
            foreach ($Sheets as $Index => $Name)
            {
                echo 'Sheet #'.$Index.': '.$Name;
        
                $Reader -> ChangeSheet($Index);
        
                foreach ($Reader as $Row)
                {
                    print_r($Row);
                }
            }
            fclose($handle);
            exit;


		return array($sku,$qnt);
	}


	function getInfoProduct($id_product){

	}
	

	function getMarche(){
		$database = _obj('Database');
		$list = $database->select('id,nome','catalogo_marca');
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

		$list = $database->select('distinct distinct modello','catalogo_auto',"gruppo={$id_gruppo}");
		$data = array();
		foreach($list as $v){
			$data[] = $v['modello'];
		}
		$this->success($data);
	}

	function getMotori($id_gruppo,$id_modello){
		$id_auto = (int)$id_gruppo;
		$database = _obj('Database');

		$list = $database->select('id as id_auto,allestimento','catalogo_auto',"gruppo={$id_auto} AND modello='{$id_modello}'");
		$this->success($list);
	}

	function getProductsInList($id_user,$list_sku,$qnt){
		//devi creare kla condizione a partire da $list_sku e metterla nella queru
		$database = _obj('Database');
		$list = $database->select('p.id,p.sku,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia','product as p join catalogo_prodotto as c on c.sku=p.sku',"p.sku IN (listaa...)");
			

		return $this->getProductList($id_user,$list,$qnt);

	}

	function getProductsBySku($id_user,$sku){
		
		$database = _obj('Database');
		$list = $database->select('p.id,p.sku,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia','product as p join catalogo_prodotto as c on c.sku=p.sku',"p.sku LIKE '%{$sku}%'");
			
		return $this->getProductList($id_user,$list);

	}



	function getProductsByAuto($id_user,$id_auto){
		
		$database = _obj('Database');
		$list = $database->select('p.id,p.sku,c.quantita_imballo_multiplo,c.profilt,c.fornito_nishiboru,c.filtro_antibatterico,c.img_1,c.id_tipologia','(product as p join catalogo_prodotto as c on c.sku=p.sku) join catalogo_auto_prodotto as ap on ap.id_prodotto=c.id',"ap.id_auto={$id_auto}");
	
		return $this->getProductList($id_user,$list);

	}

	function getProductInfo($id_product){
		
		$database = _obj('Database');
		$list = $database->select('c.*','product as p join catalogo_prodotto as c on c.sku=p.sku',"p.id={$id_product}");
		$data = $list[0];
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
		}

		//debugga($data);exit;
		
		return $data;

	}



	function getProductList($id_user,$list,$qnt_sku=array()){
		require('modules/b2b/classes/Tools.class.php');
		$user = User::withId($id_user);
		$_SESSION['userdata'] =  $user;
		$cart = Cart::getCurrent();
		$database = _obj('Database');
		if( okArray($qnt_sku) ){
			$old_data = $database->select('product,quantity','cartRow',"cart={$cart->id}");
			foreach($old_data as $v){
				$qnt_old[$v['product']] = $v['quantity'];
			}
		}
		
		$data = array();
		$tipologie = array();
		$tipologia_select = $database->select('*','catalogo_tipologia_prodotto');
		foreach($tipologia_select as $v){
			$tipologie[$v['id']] = $v['nome'];
		}

		
		

		foreach($list as $k =>  $v){
			if( okArray($qnt_sku) ){
				$qnt = $qnt_sku[$v['sku']]?$qnt_sku[$v['sku']]:1; //prendere quella presente nel carrello per l'articolo
				$qnt_input =  $qnt_sku[$v['sku']];
			}else{
				$qnt = $qnt_old[$v['id']]?$qnt_old[$v['id']]:1; //prendere quella presente nel carrello per l'articolo
				$qnt_input =  $qnt_old[$v['id']];

			}
			
			$prezzo = Tools::buildPrice($id_user,$v['id'],$qnt);
			//debugga($prezzo);exit;
			$row = array(
				'id_product' => $v['id'],
				'sku' => $v['sku'],
				'id_tipologia' => $v['id_tipologia'],
				'descrizione' => $tipologie[$v['id_tipologia']]?$tipologie[$v['id_tipologia']]:'',
				'url_image' => $v['img_1']?$this->getUrlImage($v['img_1'],$v['sku']):'',
				'prezzo' => $prezzo['prezzo_senza_iva'],
				'listino_italia' => $prezzo['prezzo_italia'],
				'sconti' => is_array($prezzo['sconti_array'])?array_values($prezzo['sconti_array']):array(),
				'qnt_input' => $qnt_input,
				'totale' => $qnt_input?($qnt_input*$prezzo['prezzo_senza_iva']):null,
				'quantita_imballo_multiplo' => $v['quantita_imballo_multiplo'],
				'profilt' => $v['profilt']?1:0,
				'nishiboru' => $v['fornito_nishiboru']?1:0,
				'adplus' => (!$v['antibatterico'] && $v['filtro_antibatterico'])?1:0

			);
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


			/* DA RENDERE DINAMICO */
			if( $k == 1 ){
				$row['promo'] = 1;
				$row['testo_promo'] = 'Questo è un test';
			}else{
				$row['promo'] = 0;
				$row['testo_promo'] = '';
			}
			$data[] = $row;
		}
		return $data;
	}



	function getUrlImage($id,$sku){
		return $this->url."/img/".$id."/md/".$sku.".png";
	}

}