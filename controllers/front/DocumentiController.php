<?php
require('modules/b2b/controllers/front/ApiController.php');
class DocumentiController extends ApiController{
	
	
	function display(){
        parent::display();
		$action = $this->getAction();
		switch($action){
			case 'ddt':
				$this->getList('ddt');
				break;
			case 'view':
				$this->getDocumento();
				break;
			default:
				$this->getList('fatture');
				break;
		}
		
		
	}

	function getDDT(){

	}

	function getList($tipo){
		//$codice_gestionale = '0401002441'; //prendere dinamicamente

		$dir = 'modules/b2b/ftp/'.$tipo;

       
        $codice_gestionale = $this->user['codiceGestionale'];
	  
		$base_url = $this->getUrl(); 
		$list = scandir($dir);
		foreach($list as $v){
			if( preg_match('/'.$codice_gestionale.'/',$v) ){

				
				$ext = explode('.',$v);
				$file = "{$dir}/".$v;
				$pathinfo = pathinfo($file);
				
				$anno = explode('_',$v);
				
				if( is_file($file) && $v != '.ftpquota'){
					$riga = array(
						'nome' => preg_replace('/-'.$codice_gestionale.'/','',$v),
						'path' => $base_url."/{$dir}/".$v,
						'ext' => $pathinfo['extension'],
						'data' => strftime("%d/%m/%Y %H:%M",filemtime($file)),
						'data_tmp' => filemtime($file),
						'anno' => $anno[0],
						'file' => $v
					);
					$riga['numero'] = preg_replace('/([a-z\.]+)/','',strtolower($riga['nome']));
					$app = explode('_',$riga['numero']);
					$riga['numero'] = $app[1];
					
					$documenti[] = $riga;
				}
			}
		}
		if( okArray($documenti) ){
			
			
			uasort($documenti,function($a,$b){
				if ($a['anno'] == $b['anno']) {
					return 0;
				}
				return ($a['anno'] > $b['anno']) ? -1 : 1;
			});
			$tmp = array();
			foreach($documenti as $v){
				$tmp[$v['anno']][] = $v;
			}
			//debugga($documenti);exit;
			foreach($tmp as $t => $r){
				uasort($tmp[$t],function($a,$b){
					if ($a['numero'] == $b['numero']) {
						return 0;
					}
					return ($a['numero'] > $b['numero']) ? -1 : 1;
				});
			
			}

			$documenti = null;
			foreach($tmp as $t => $r){
				foreach($r as $v){
					$documenti[] = $v;
				}
			}

			
		
		}




		
		$this->success($documenti);
	}


	function getUrl(){
		return sprintf(
		  "%s://%s",
		  isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
		  $_SERVER['SERVER_NAME']
		);
	  }
	function getDocumento(){
		
        $data = json_decode(file_get_contents('php://input'), true);
		$file = $data['file'];
		
		$codice_gestionale = $this->user['codiceGestionale'];
		if( preg_match('/'.$codice_gestionale.'/',$file) ){
			if( preg_match('/fattura/',strtolower($file)) ){
				$path = 'modules/b2b/ftp/fatture/'.$file;
			}
			if( preg_match('/ddt/',strtolower($file)) ){
				$path = 'modules/b2b/ftp/ddt/'.$file;
			}
			$this->success(base64_encode(file_get_contents($path)));
		}else{
			$this->error('404');
		}
		
	}
	
}
?>


