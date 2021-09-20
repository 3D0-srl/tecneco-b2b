<?php
require('modules/b2b/controllers/front/ApiController.php');
use \Firebase\JWT\JWT;
use \Firebase\JWT\ExpiredException;
class RecoveryPasswordController extends ApiController{
	//public $url_site = 'http://newecommerce.tecneco.com/';
	public $url_site = 'https://ecommerce.tecneco.com/';
    public function display(){

        switch ($this->getAction()) {
            case 'generate':
                $database = _obj('Database');
                $_data = json_decode(file_get_contents('php://input'), true);
                $user = $database->select('id, name, surname,email','user',"username='{$_data['username']}' OR email='{$_data['email']}'");

				
                if ($user) {//If user exist
                    /*Create the jwt*/
                    $key = "t3cn3c0";
                    $payload = array(
                    "aud" => "http://catalogo.tecneco.com/recovey_password",
                    "iat" => time(),
                    "exp" => strtotime('+70 minutes'),
                    'user_id' => $user[0]['id']
                    );
                    $jwt = JWT::encode($payload, $key);
                    /*Prepare Email*/
                    if($this->sendMailLostPassword($user[0]['email'], $user[0]['name'], $user[0]['surname'], $jwt)){
                        $data = array(
                            'message' => "Un'email è stata inviata all'indirizzo ".$_data['email']
                        );
                        $this->success($data);
                    }
                    else{
                        $this->error('Errore email');
                    }
                }
                else{
                    $this->error('Utente non trovato');
                }
                break;
            case 'check':
                $key = "t3cn3c0";
                $_data = json_decode(file_get_contents('php://input'), true);
				try{
                    $decoded = JWT::decode($_data['jwt'], $key, array('HS256'));
					if($decoded && $decoded->aud == 'http://catalogo.tecneco.com/recovey_password' && time()<$decoded->exp){
                        $data = array( 'id' => $decoded->user_id);
						$this->success($data);
					}else{
						$this->error('Token non valido');
					}
				}catch(Exception $e ){
					$this->error($e->getMessage());
				}
                break;
            case 'change':
				$database = _obj('Database');
                $key = "t3cn3c0";
                $_data = json_decode(file_get_contents('php://input'), true);
                try{
                    $decoded = JWT::decode($_data['jwt'], $key, array('HS256'));
                    if($decoded && $decoded->aud == 'http://catalogo.tecneco.com/recovey_password' && time()<$decoded->exp){
                        $id_user = $decoded->user_id;
                        $user = User::withId($id_user);
                        if(is_object($user)){
                            $array['password'] = password_hash($_data['password'], PASSWORD_DEFAULT); 
                            $user->set($array);
							//debugga($user);exit;
                            $res = $user->save();
                            if($res){
                                $data = array( 'message' => 'Password aggiornata con successo');
                                $this->success($data);
                            }
                            else{
                                $this->error('Errore interno, contattare il supporto');
                            }
                        }
                    }else{
                        $this->error('Token non valido');
                    }
                }catch(Exception $e ){
                    $this->error($e->getMessage());
                }
                break;
        }
    }

    function sendMailLostPassword($emailTo, $name, $surname, $jwt){	
		$data = array(
			'email' => $emailTo,
			'time' => time()
		);
        $this->setVar('link', $this->url_site.'recovery/'.$jwt);
        $this->setVar('name', $name);
        $this->setVar('surname', $surname);
        //preparo l'html
        /*http://localhost:4200/recovery/{{jwt}}*/
        //
		ob_start();
		$this->output('mail/forgot_pwd.htm');
		$html = ob_get_contents();
        ob_end_clean();
        
		$generale = Marion::getConfig('generale');
        $subject = sprintf($GLOBALS['gettext']->strings['subject_lostpass'],$generale['nomesito']);
        
		$mail = _obj('Mail');
        $mail->setHtml($html);
		$mail->setSubject($subject);
		$mail->setTo($emailTo);
		//più destinatari
		//$mail->setToFromArray(array($email1,$email2));
		$mail->setFrom($generale['mail']);
        $res = $mail->send();

		
		return $res;
	}
}
?>