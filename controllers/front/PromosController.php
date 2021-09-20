<?php
require('modules/b2b/controllers/front/ApiController.php');
class PromosController extends ApiController{
	function display(){
        parent::display();
		$action = $this->getAction();
		switch($action){
			case 'get_promos':
				$this->getPromos();
			break;
		}
	}
	function getPromos(){
		require_once('modules/b2b/classes/Promos.class.php');
		$queryStart = _var('from');
		$promos = Promos::prepareQuery()
		->where('active',1)
		->get();
        $data = array();
        $i = 0;
		foreach($promos as $v){
			if( ($v->file && $v->image) && $v->active){
				if($i>=$queryStart && ($i<$queryStart+4)){
                    $data[] = array(
                        'id' => $v->id, //$v->get('id')
                        'title' => $v->get('title'),
                        'description' => $v->get('description'),
                        'file' => $this->url().$v->getUrlFile(),
                        'image' => $this->url().$v->getUrlImage()
                    );
                }
                $i++;
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