<?php
class ExportMotorplanController extends ModuleController{
	public $_twig = true;

	function display(){
		$this->setMenu('export_motorplan');
		$database = _obj('Database');
		$utenti = $database->select("id,username,password,company as ragsoc","user as u join b2b_cliente as c on c.id_user=u.id","(id_profile IS NULL or id_profile = 0) AND (asso='AS' OR eprocurement=1) AND duplicato=0 AND active=1");
		//debugga($utenti);exit;
		foreach($utenti as $k => $v){
			if( (int)$v['username'] == 0 ){
				unset($utenti[$k]);
				continue;
			}
			
			$utenti[$k]['username'] = $v['username'];
			
			$utenti[$k]['password'] = md5($v['id']."||".$v['username']);
			
		}
		
		
		
		$this->setVar('utenti',$utenti);

	
		header("Cache-Control: ");
		header("Pragma: ");
		header("Accept-Ranges: bytes");
		header("Content-type: application/vnd.ms-excel");
		header("Content-Language: eng-US");
		header('Content-Disposition: attachment; filename="'.'Utenti Tecneco'.'.xls"');
		header("Content-Transfer-Encoding: binary");

		//header("Content-Encoding: gzip");

		ob_start();
		$this->output('esporta_motorplan.htm');
		$size=ob_get_length();

		$diff = date('Z', $now);
		$gmt_mtime = date('D, d M Y H:i:s', $now-$diff).' GMT';

		header("Last-Modified: ".$gmt_mtime);
		header("Expires: ".$gmt_mtime);
		header("X-Server: angela");
		header("Content-Length: $size");
		sleep(1);
		ob_end_flush();
		
		exit;
	}
}


?>