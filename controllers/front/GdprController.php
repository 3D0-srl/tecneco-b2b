<?php
require('modules/b2b/controllers/front/ApiController.php');
class GdprController extends ApiController{

	function display(){
		//if( !_var('escape') ){
			parent::display();
		//}
		$id_user = $this->user['id'];
		$action = $this->getAction();
		switch($action){
            case 'change_password':
                $array = array();
				$database = _obj('Database');
                $_user = json_decode(file_get_contents('php://input'), true);
                $id_user = $this->user['id'];
                $list = $database->select('password','user',"id={$id_user}");
                $oldPassword = $_user['oldPassword'];
                if(password_verify($oldPassword, $list[0]['password'])){
                    $user = User::withId($id_user);
                    if(is_object($user)){
                        $array['password'] = password_hash($_user['newPassword'], PASSWORD_DEFAULT); 
                        $user->set($array);
                        $res = $user->save();
                        $this->success('ok');
                    }
                }
                else{
                    $this->error('La pasword è sbagliata');
                }
                break;
			case 'get_provinces':
				$database = _obj('Database');
				$list = $database->select('sigla as id,nome as name','provincia','1=1 order by nome');
				$this->success($list);
				break;
			case 'get_countries':
				$database = _obj('Database');
				$list = Country::getAll();
				$toreturn = array();
				foreach($list as $v){
					$toreturn[] =array(
						'id' => $v->id,
						'name' => $v->get('name')
					);
				}
				$this->success($toreturn);
				break;
			case 'get_addresses2':
				$id_user = $this->user['id'];
				$database = _obj('Database');
                $list = $database->select('*', 'address', "id_user={$id_user}");
				foreach($list as $k => $v){
					$list[$k]['address'] = utf8_encode($v['address']);
				}
				
				$this->success($list);
				exit;
			case 'get_addresses':
                $id_user = $this->user['id'];
				
				/*if( !$id_user ){
					$id_user = 866;
				}*/
                $database = _obj('Database');
                $list = $database->select('*', 'address', "id_user={$id_user}");
				foreach($list as $k => $v){
					$list[$k]['address'] = utf8_encode($v['address']);
				}
				$in_rev = $database->select('a.id','address as a left join b2b_gdpr_request as r on a.id=r.id_address',"a.id_user={$id_user} AND state=0");
                
				foreach($in_rev as $add){
                    $i=0;
                    foreach($list as $address){
                        if($add['id'] == $address['id']){
                            $list[$i]['state'] = 0;
                        }
                        $i++;
                    }
                }
				
                $this->success($list);
				exit;
				break;
			case 'get_datauser':
                $id_user = $this->user['id'];
				/*if( !$id_user ){
					$id_user = 866;
				}*/

				$database = _obj('Database');
				$list = $database->select('u.email,u.company,u.vatNumber,u.postalCode,u.address,u.city,u.province,r.state','user as u left join b2b_gdpr_request as r on u.id=r.id_user',"u.id={$id_user} AND r.type='DATAREQUEST' AND r.state=0");
                if($list){
                    $this->success($list[0]);
                }
                else{
                    $list = $database->select('email,company,vatNumber,postalCode,address,city,province','user',"id={$id_user}");
                    $this->success($list[0]);
                }
				break;
			case 'export_data':

                break;
            case 'request_update_address':
                $_data = json_decode(file_get_contents('php://input'), true);
                $id_user = $this->user['id'];
                $user = $database->select('email,name,surname','user',"id={$id_user}");
                $serializedData = serialize($_data['data']);
                $data = array(
                    'id_user' => $id_user,
                    'type' => 'ADDRESSREQUEST',
                    'request' => $serializedData,
                    'state' => 0,
                    'id_address' => $_data['data']['id']
                );
				$database = _obj('Database');
                $list = $database->select('*','b2b_gdpr_request',"id_user={$id_user} AND type='ADDRESSREQUEST' AND state=0");
                if($list){
                    $success = $database->update('b2b_gdpr_request',"id_user={$id_user} AND type='ADDRESSREQUEST' AND state=0",$data);
                }
                else{
                    $success = $database->insert('b2b_gdpr_request',$data);
                }
                $this->sendMailGdprUpdate($user[0]['email'], $user[0]['name'], $user[0]['surname']);
                $this->success($success);
                break;
            
            case 'request_update_data':
                $data = json_decode(file_get_contents('php://input'), true);
                $id_user = $this->user['id'];
                $user = $database->select('email,name,surname','user',"id={$id_user}");
                $serializedData = serialize($data['data']);
                $data = array(
                    'id_user' => $id_user,
                    'type' => 'DATAREQUEST',
                    'request' => $serializedData,
                    'state' => 0
                );
                $database = _obj('Database');
                $list = $database->select('*','b2b_gdpr_request',"id_user={$id_user}, type='DATAREQUEST' AND state=0");
                if($list){
                    $success = $database->update('b2b_gdpr_request',"id_user={$id_user}, type='DATAREQUEST' AND state=0",$data);
                }
                else{
                    $success = $database->insert('b2b_gdpr_request',$data);
                }
                $this->sendMailGdprUpdate($user[0]['email'], $user[0]['name'], $user[0]['surname']);
                $this->success($success);
                break;
            
            case 'request_delete_account':
                $data = json_decode(file_get_contents('php://input'), true);
                $id_user = $this->user['id'];
                $data = array(
                    'id_user' => $id_user,
                    'type' => 'DELETEREQUEST',
                    'request' => $data['choice'],
                    'state' => false
                );
                $database = _obj('Database');
                $list = $database->select('*','b2b_gdpr_request',"id_user={$id_user}, type=DELETEREQUEST");
                if($data['choice']){
                    if(count($list) == 0){
                        $success = $database->insert('b2b_gdpr_request',$data);
                    }
                }
                else{
                    $database->delete('b2b_gdpr_request',"id_user={$id_user}, type=DELETEREQUEST");
                }
                $this->success($success);
                break;
		}

	}
    function sendMailGdprUpdate($emailTo, $name, $surname){	
		$data = array(
			'email' => $emailTo,
			'time' => time()
		);
        $this->setVar('name', $name);
        $this->setVar('surname', $surname);
        //preparo l'html
		ob_start();
		$this->output('mail/gdpr_request.htm');
		$html = ob_get_contents();
        ob_end_clean();
        
		$generale = Marion::getConfig('generale');
        $subject = sprintf($GLOBALS['gettext']->strings['subject_lostpass'],$generale['nomesito']);
        
		$mail = _obj('Mail');
        $mail->setHtml($html);
		$mail->setSubject($subject);
		//più destinatari
		$mail->setToFromArray(array('info.tortora.aniello@gmail.com','tortora.aniello.ta@gmail.com'));
		//$mail->setTo($emailTo);
		$mail->setFrom($generale['mail']);
        $res = $mail->send();
		return $res;
	}
}