<?php
class ProductPromoController extends AdminModuleController{
    public $_auth = 'cms';
	public $_twig = true;

	function ajax(){
        $action = $this->getAction();
        switch ($action) {
            case 'salva':
                $this->salva();
                break;
			case 'rimuovi':
                $id = $this->getID();
				$promo = ProductPromo::prepareQuery()->where('codice_gestionale',$id)->getOne();
				$product = Product::prepareQuery()->where('sku',$id)->getOne();
				if( is_object($promo) ){
					
					$promo->delete();
				}
				
				echo json_encode(array('success'=>1,'id' => $product->id));
                break;
        }
    }
	function setMedia(){
		parent::setMedia();
        // Penso che non gli piaccia il js di inputmask
        //Input mask -> $this->registerJS("../modules/b2b/js/inputmask/bundle.jquery.js?v=1",'end'); // per richiamare JS
        
		$this->registerJS("../plugins/inputmask/dist/jquery.inputmask.bundle.js?v=3",'end'); // per richiamare JS
		//$this->registerJS("../modules/b2b/js/inputmask/bundle.jquery.js?v=1",'end'); // per richiamare JS
        $this->registerJS("../modules/b2b/js/script.js?v=1",'end'); // per richiamare JS
		//$this->registerCSS("../modules/b2b/css/style.css", 'end'); // per richiamare CSS
	}

    function displayList(){
        $this->setMenu('b2b_tecneco_products_promos');
        $action = $this->getAction();
		
        switch ($action) {
            case 'list':
                $dataform = $this->prepareData();
                $this->setVar('dataform', $dataform);
                $this->setVar('active', _var('letter'));
                $this->output('product-promo/list.htm');
                break;
        }
    }

    function salva(){
        $this->setMenu('b2b_tecneco_products_promos');
        //sto sottomettendo il form
            $id = _var('id');
            $dati = _var('formdata');
            $dati['id'] = null;
            $dati['codice_gestionale'] = _var('sku');
            $array = $this->checkDataForm('b2b_product_promo',$dati); //controllo i dati
            //debugga($array);
            if( $array[0] == 'ok'){
                $obj =  ProductPromo::create(); //creo un nuovo oggetto
                $obj->set($array)->save();
                echo json_encode(array('success'=>1));
            }
            else{
                echo json_encode( array($array[1]));
            }
    }

    function prepareData(){
        $this->setMenu('b2b_tecneco_products_promos');
        $letter = _var('letter');
        if (!$letter) {
            $letter = 'a';
        }
        if(!(($letter>='a'&&$letter<='z')||($letter>='A'&&$letter<='Z')||($letter>='0'&&$letter<='9'))){
            $letter = 'a';
        }
        $letter = strtoupper($letter);
        if( $success ){
            $this->displayMessage('Dati salvati con successo','success');
        }
        $database = _obj('Database');
       
        $listProduct = $database->select('product.id,product.sku, productLocale.name ','product join productLocale on product.id=productLocale.product',"product.sku LIKE '{$letter}%' order by sku");

		//if( isCiro()){
		$where = '';
		foreach($listProduct as $b){
			$where .= "'{$b['sku']}',";
		}
		$where = preg_replace('/\,$/','',$where);
		$listPromo = ProductPromo::prepareQuery()->whereExpression("codice_gestionale IN ({$where})")->get();
		
		//}

        $this->setVar('listPromo',$listPromo);
        $this->setVar('listProduct',$listProduct);
        foreach($listProduct as $product){
            $index = $product['id'];
            $form = $this->getDataForm('b2b_product_promo');
            $form['promoted'] = false;
            foreach($listPromo as $promo){
                if ($promo->codice_gestionale == $product['sku']) {
					
                    $temp = $promo->prepareForm2();
                    $form = $this->getDataForm('b2b_product_promo', $temp);
                    $form['promoted'] = true;
                }
            }
			$desc = $form['description'];
			foreach($desc['locales'] as $lo => $v){
				$desc['locales'][$lo]['etichetta'] = '';
			}

			$pulsante = $form['pulsante_testo'];
			foreach($pulsante['locales'] as $lo => $v){
				$pulsante['locales'][$lo]['etichetta'] = '';
			}
			$form['pulsante_testo'] = $pulsante;
            $form['index'] = $index;
			$dataform[$index] = $form;
        }

		//debugga($dataform);exit;
        return $dataform;
    }
}

?>