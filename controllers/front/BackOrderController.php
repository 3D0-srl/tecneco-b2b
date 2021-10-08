<?php
require('modules/b2b/controllers/front/ApiController.php');
require('modules/b2b/classes/BackOrder.class.php');
ini_set('serialize_precision',5);
class BackOrderController extends ApiController{

    function display(){
		if( !_var('escape') ){
			parent::display();
		}

		
		$action = $this->getAction();

        switch($action){
            case 'delete':
                $id = _var('id');
                $this->delete($id);
                break;
        }
    }

    function delete($id){
        $database = _obj('Database');
        $id_user = $this->user['id'];
        $database->delete('back_orders',"id={$id} AND user_id={$id_user}");
        $this->success(1);
    }

}