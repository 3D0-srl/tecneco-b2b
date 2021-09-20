<?php
require('modules/b2b/controllers/front/ApiController.php');
class PopupHomeController extends ApiController{
	function display(){
        parent::display();
		$action = $this->getAction();
		switch($action){
			case 'get_popup':
				$this->get_popup();
			break;
		}
	}
	function get_popup(){
		$now = date('Y-m-d');
		require_once('modules/b2b/classes/PopupHome.class.php');
		$popup = PopupHome::prepareQuery()
		->where('active',1)
		->where('date_from',$now,'<=')
		->where('date_to',$now,'>=')
		->get();
		$data = array();
		foreach($popup as $v){
			if($v->image && $v-> active){
				$data[] = array(
					'id' => $v->id, //$v->get('id')
					'name' => $v->get('name'),
					'attachment' => $this->url().$v->getUrlToShowFile(),
                    'image' => $this->url().$v->getUrlImage(),
                    'one_time_at_day' => $v->one_time_at_day,
                    'date_from' => $v->date_from,
                    'date_to' => $v->date_to
				);
			}
		}
		$this->success($data);
    }
    
	function url(){
		return sprintf(
		  "%s://%s",
		  isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
		  $_SERVER['SERVER_NAME']
		);
      }
      
}
?>