<?php
require('modules/b2b/controllers/front/ApiController.php');
class PricelistsController extends ApiController{
	function display(){
        parent::display();
		$action = $this->getAction();
		switch($action){
			case 'price_lists':
				$this->getPriceList();
			break;
		}
	}
	function getPriceList(){
		require_once('modules/b2b/classes/PriceLists.class.php');
		$slides = PriceLists::prepareQuery()
		//->where('active',1)
		->get();
		$data = array();
		foreach($slides as $v){
			if( ($v->file_pdf && $v->file_excel) && $v-> active){
				$data[] = array(
					'id' => $v->id, //$v->get('id')
					'name' => $v->get('name'),
					'file_pdf' => $this->url().$v->getUrlPdf(),
					'file_excel' => $this->url().$v->getUrlExcel()
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