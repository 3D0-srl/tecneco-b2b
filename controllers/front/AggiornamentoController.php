<?php
require('modules/b2b/controllers/front/ApiController.php');
class AggiornamentoController extends ApiController{

	
	function display(){
		$check = Marion::getConfig('b2b','aggiornamento');
		$this->success($check?true:false);
	}
}

?>