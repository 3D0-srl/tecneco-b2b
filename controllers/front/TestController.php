<?php

require('modules/b2b/controllers/front/ApiController.php');


class TestController extends ApiController{
	 


	function display(){
		$status = $this->checkStatus2();
		debugga($status);exit;
	}


	function checkStatus2(){
		
		$id_user = 1628;

		$user = User::withId($id_user);
			
		$database = _obj('Database');
		$check = $database->select('id,number,status','cart',"user={$id_user} AND id<>12693 AND aggiunto_a is NULL ORDER BY evacuationDate DESC");

		
		if( okArray($check) ){
			$check = $check[0];
			if( in_array($check['status'],['active','spedito','canceled','deleted'] )){
				return false;
			}else{
				$tot = $database->select('count(*) as tot',"cart","AND id <> 12693 AND aggiunto_a = {$check['id']}");
				
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

}