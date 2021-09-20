<?php
class LimitOrderController extends ModuleController{
	public $_auth = 'cms';
    public $_twig = true; //per i successivi no

	public function display(){
		$this->setMenu('limit_order');
		

		if( $this->isSubmitted()){
			$dati = $this->getFormdata();
			$array = $this->checkDataForm('b2b_limit_order_general',$dati);
			if( $array[0] == 'ok' ){
				unset($array[0]);
				foreach($array as $k => $v){
					Marion::setConfig('b2b_limit_order_general',$k,$v);
				}
				$this->displayMessage('Dati salvati con successo!');
			}else{
				$this->errors[] = $array[1];
			}
		}else{
			$dati = Marion::getConfig('b2b_limit_order_general');
		}

		$dataform = $this->getDataForm('b2b_limit_order_general',$dati);
		$this->setVar('dataform',$dataform);
		$this->output('limit_order/setting_general.htm');
	}
}

?>