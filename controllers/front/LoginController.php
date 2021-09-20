<?php
require_once ('modules/b2b/vendor/autoload.php');
require('modules/b2b/controllers/front/ApiController.php');
use \Firebase\JWT\JWT;
use \Firebase\JWT\ExpiredException;
class LoginController extends ApiController{

	function display(){
		//parent::display();
		
		switch($this->getAction()){
			case 'login':
				$this->loginJWT();
				break;
			case 'lostpwd':
				$this->lostPassword();
				break;
		}
	}

	function loginJWT(){
        $key = "t3cn3c0";
        
        /* Example Data 
        $data = array(
            'username' => 'cicciobello',
			'password' => '0401002441',
        );
        */
        
		$database = _obj('Database');
		$data = json_decode(file_get_contents('php://input'), true);
		
		
		//$password = password_hash($data['password'],PASSWORD_DEFAULT);
		//$database->update('user',"username='{$data['username']}'",['password'=>$password]);
		//debugga($database->lastquery);
		/*debugga($password);
		debugga(password_verify('0401002441',$password));
		//exit;
		*/
		//debugga($password);
		

		//$user = $database->select('*','user',"username='{$data['username']}'");
		$utenti_no_promo = Marion::getConfig('b2b_no_promo');
		$utenti_no_promo = array_map('trim', array_unique(explode("\n",$utenti_no_promo['lista'])));
		$user = User::login($data['username'],$data['password']);
		
		//debugga($data);exit;
		if( is_object($user)){
            $database = _obj('Database');
            $temp = get_object_vars($user);
            $id = $temp['id'];
            $b2b_utente = $database->select('codice_gestionale','b2b_cliente',"id_user={$id}");
			$b2b_utente = $b2b_utente[0];
			
			$estero = false;
			if( preg_match('/^0402/',$b2b_utente['codice_gestionale']) ){
				$estero = true;
			}
            $payload = array(
                    "aud" => "http://catalogo.tecneco.com",
                    "iat" => time(),
                    "exp" => strtotime('+3 hours'),
					'user' => array(
						'id' => $temp['id'],
						'username' => $temp['username'],
						'token' => $temp['token'],
						'company' => $user->company,
						'backorder' => $user->backorder,
						'codiceGestionale' => $b2b_utente['codice_gestionale'],
						'estero' => $estero,
						'no_promo' => $estero?true:(in_array($b2b_utente['codice_gestionale'],$utenti_no_promo)?true:false),
					)
            );
            $jwt = JWT::encode($payload, $key);
            $this->jwtString = $jwt;
			$data = array(
				'user' => array(
                    'id' => $temp['id'],
                    'username' => $temp['username'],
                    'token' => $temp['token'],
                    'fiscalCode' => $temp['fiscalCode'],
					'company' => $user->company,
                    'codiceGestionale' => $b2b_utente['codice_gestionale'],
					'estero' => $estero,
					'backorder' => $user->backorder?true:false,
					'no_promo' => $estero?true:(in_array($b2b_utente['codice_gestionale'],$utenti_no_promo)?true:false),
                )
            );
			$this->success($data);
		}else{
			$this->error($user);
		}
	}

	function lostPassword(){
		if( $this->isSubmitted()){
			$data = json_decode(file_get_contents('php://input'), true);
			if($data['username'] && $data['email']){
				$user = User::prepareQuery()->where('email',$data['email'])->getOne();
				if(is_object($user)){
					$this->sendMailLostPassword($user);
				}else{
					
					$this->errors[] = __('no_user');
				}
			}else{
				$this->errors[] = 'Invalid Input';
			}


		}
		
	}

	// funzione che invia la mail di recupero password
	function sendMailLostPassword($user){
		
		$data = array(
			'id_user' => $user->id,
			'email' => $user->email,
			'time' => time()
		);
		$this->setVar('serialized', base64_encode(serialize($data)));



		//preparo l'html
		ob_start();
		$this->output('mail/mail_forgot_pwd.htm');
		$html = ob_get_contents();
		ob_end_clean();


		$generale = Marion::getConfig('generale');

		$subject = sprintf($GLOBALS['gettext']->strings['subject_lostpass'],$generale['nomesito']);

		$mail = _obj('Mail');
		
		$mail->setHtml($html);
		$mail->setSubject($subject);
		
		
		$mail->setTo($user->email);
		$mail->setFrom($generale['mail']);
		
		$res = $mail->send();

		//debugga($res);exit;

		return true;
	}

}
?>