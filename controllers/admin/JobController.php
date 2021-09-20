<?php
class JobController extends ModuleController{
	public $_twig = true;
	public $_auth = 'admin';
	

	function display(){
		$this->setMenu('sincronizzazione_b2b');
		$database = _obj('Database');
		$now = date('Y-m-d');
		$list = $database->select('*','b2b_import_job',"timestamp > '{$now}'");
		foreach($list as $k => $v){
			$list[$k]['timestamp'] = strftime('%d/%m/%Y %H:%M',strtotime($v['timestamp']));
			$stato = $v['stato'];
			switch($v['stato']){
				case 'IN CODA':
					$color = 'info';
					break;
				case 'IN PROCESSAZIONE':
					$color = 'warning';
					break;
				case 'TERMINATO':
					$color = 'success';
					break;	
			}
			$list[$k]['stato'] = "<span class='label label-{$color}'>{$stato}</span>";
		}
		$this->setVar('lista',$list);
		$this->output('sincro/actions.htm');
	}


	function ajax(){
		$action = $this->getAction();
		switch($action){
			case 'run':
				$this->run();
				break;
			
			case 'import':
				$check = $this->import(_var('type'));
				if( $check == 1 ){
					$risposta = array(
						'result' => 'ok'
					);
				}else{
					$risposta = array(
						'result' => 'nak'
					);
				}
				break;
		}
		echo json_encode($risposta);
	}


	function import($type){
		$database = _obj('Database');
		$check = $database->select('*','b2b_import_job',"type='{$type}' AND eseguito = 0");
		if( okArray($check) ){
			return 'error';	
		}else{
			$insert['type'] = $type;
			$insert['stato'] = "IN CODA";
			$database->insert('b2b_import_job',$insert);
			return 1;
		}
	}

	function run(){
		$database = _obj('Database');
		$check = $database->select('*','b2b_import_job',"eseguito = 0 order by timestamp");
		if( okArray($check) ){
			if( $check[0]['stato'] == 'IN CODA' ){

			}
		}
	}
}

?>