<?php
class NopromoController extends ModuleController{
	public $_auth = 'cms';
    public $_twig = true; //per i successivi no
	

	/*function display(){
		$this->setMenu('no_promo');
		
		$database = _obj('Database');

		if( $this->isSubmitted()){
			$dati = $this->getFormdata();
			$array = $this->checkDataForm('b2b_no_promo',$dati);
			
			if( $array[0] == 'ok'){
				$lista = array_map('trim',explode("\n",$array['lista']));

				$unique = array_unique($lista);
				if( count($unique) != count($lista) ){
					$array[0] = 'nak';
					$array[1] = 'Alcuni codici cliente sono duplicati';
				}
			}


			if( $array[0] == 'ok' ){
				unset($array[0]);
				foreach($array as $k => $v){
					Marion::setConfig('b2b_no_promo',$k,$v);
				}
				$this->displayMessage('Dati salvati con successo!');
			}else{
				$this->errors[] = $array[1];
			}
		}else{
			$dati = Marion::getConfig('b2b_no_promo');

		}
		
	

		$dataform = $this->getDataForm('b2b_no_promo',$dati);
		$dataform['lista']['other']['rows'] = 20;
		$dataform['lista']['placeholder'] = 'Es. 040100010';

		$this->setVar('dataform',$dataform);
		$this->output('no_promo/setting.htm');
		
		
		

	}*/

	public function display(){
		$this->setMenu('no_promo');
		
		$database = _obj('Database');

		$action =  _var('action');
		$codice = trim(_var('codice'));
		$utenti_no_promo = Marion::getConfig('b2b_no_promo');
		$utenti_no_promo = array_map('trim', array_unique(explode("\n",$utenti_no_promo['lista'])));
		foreach($utenti_no_promo as $v){
			
			if($action == 'delete'){
				if(  $codice != $v ){
					$text .= "{$v}\n";
					$where .= "'{$v}',";
				}
			}else{
				$text .= "{$v}\n";
				$where .= "'{$v}',";
			}
		}
		if( $action == 'add' ){
			
			$check = $database->select('distinct u.company,b.codice_gestionale',"b2b_cliente as b join user as u on u.id=b.id_user","codice_gestionale  = '{$codice}'");
			if( okArray($check) ){
				$text .= "{$codice}\n";
				$where .= "'{$codice}',";
				$this->displayMessage('Cliente aggiunto con successo!');
			}else{
				$this->displayMessage('Cliente non trovato!',"danger");
			}
			
		}
		if( $action == 'delete' ){
			$this->displayMessage('Cliente rimosso con successo!');
		}
		Marion::setConfig('b2b_no_promo','lista',$text);
		

		
		
		
		$where = preg_replace('/\,$/','',$where);
		$list = $database->select('distinct u.company,b.codice_gestionale',"b2b_cliente as b join user as u on u.id=b.id_user","codice_gestionale IN ({$where}) order by company");
		
		$this->setVar('list',$list);
		if( _var('add')){
			$this->setVar('add',1);
		}
		$this->output('no_promo/list.htm');
	}
}

?>