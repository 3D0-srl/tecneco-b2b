<?php
class ResetController extends ModuleController{

	public function display(){
		exit;
		$database = _obj('Database');
		/*$database->delete('b2b_cliente',"1=1");
		$database->execute('ALTER TABLE b2b_cliente AUTO_INCREMENT = 1');

		$database->delete('user',"id > 20");
		$database->execute('ALTER TABLE user AUTO_INCREMENT = 20');
		*/
		$database->delete('b2b_carrello','1=1');
		$database->delete('b2b_ordine','1=1');
		$database->execute('ALTER TABLE b2b_ordine AUTO_INCREMENT = 1');

		$database->delete('b2b_prodotto_linea','1=1');
		

		debugga('finito');exit;
	}
}

?>