<?php
class Test3Controller extends FrontendController{

		

		function display(){
			$generale = Marion::getConfig('generale');
			//$this->disabilitaUtenti();
			//exit;
			//debugga($generale);exit;
			/*$username = '03917820874';
			$password = '0401002831';
			$user = User::login($username,$password);
			$database = _obj('Database');
			$list = $database->select('*','user',"username='{$username}'");
			debugga($list);exit;*/
			$database = _obj('Database');
			$id_user = 2385;
			$id_user = 1502;
			$qnt = 1;
			$id_product = 2869;
			$sku = 'CK10025C';
			$pr = $database->select('*','product',"sku='{$sku}'");

			$id_product = $pr[0]['id'];
			require('modules/b2b/classes/ToolsNew.class.php');
			require('modules/b2b/classes/Tools.class.php');
			$prezzo = Tools::buildPrice($id_user,$id_product,$qnt);
			debugga($prezzo);exit;
			/*$codice = '0402001514';
			$database = _obj('Database');
			$check = $database->select('*',USER_TABLE,"username='{$codice}'");

			if ( okArray($check) ){
				
				$hashedPassword = $check[0]['password'];
				
				if (!password_verify($codice, $hashedPassword)) {
				debugga($check);exit;
				}
			}
			$user = User::login('0402001514','0402001514');
			
			debugga($user);exit;
			//SELECT * FROM `b2b_listini` where cliente='ITALIA'*/
		}

		function disabilitaUtenti(){
			exit;
			$path = _MARION_MODULE_DIR_."b2b/utenti.csv";
			$row = 1;
			$db = _obj('Database');
			$users = $db->select('u.id,u.username,b.codice_gestionale_int as cod,b.codice_gestionale,u.deleted',"user as u join b2b_cliente as b on b.id_user=u.id","1=1");
			foreach($users as $u){
				$old[$u['cod']][] = $u; 
			}
			
			if (($handle = fopen($path, "r")) !== FALSE) {
			  $testata2 = fgetcsv($handle, 1000, ";");
			  foreach($testata2 as $k => $v){
				$testata[] = trim($v);
			  }
			  $testata[1] = 'CODICE';
			  
			 
			  while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
				$riga = [];
				foreach($data as $k => $v){
					$riga[$testata[$k]] = $v;
				}
				$lista[] = $riga;
			  }
			  fclose($handle);
			}
			$cont = 0;
			foreach($lista as $v){
				$utenti = $old[$v['CODICE']];
				
				foreach($utenti as $u){
					if( $u['username'] == $v['USERNAME'] ){
						$id_user = $u['id'];
						if( $v['Eliminato'] ){
							//debugga($id_user);
							//debugga($v);
							//debugga('------');
							if( !$u['deleted'] ){
								$cont++;
							
								$db->update('user',"id={$id_user}",array('deleted'=>1));
								debugga($db->lastquery);
							}
						}else{
							
							//debugga($u);
							$password = $v['PASSWORD'];
							if( $password != $u['cod'] ){
								
								$password = password_hash(trim($password), PASSWORD_DEFAULT);
								$db->update('user',"id={$id_user}",array('password'=>$password));
								debugga($db->lastquery);
								//debugga($password);
								//exit;
							}else{
								/*debugga($u);
								debugga($v);
								exit;
								$db->update('user',"id={$id_user}",array('deleted'=>1));*/
							}	
							//$db->update('user',"id={$id_user}",array('password'=>1));
						}
					}
				}
				
			}
			debugga($cont);exit;
		}
}

?>