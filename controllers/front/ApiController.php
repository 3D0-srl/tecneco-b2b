<?php
require_once ('modules/b2b/vendor/autoload.php');
use \Firebase\JWT\JWT;
use \Firebase\JWT\ExpiredException;
define("ACCESS_DENIED", "Credenziali non valide");
/*
        $key = "t3cn3c0";
        $payload = array(
            "aud" => "http://catalogo.tecneco.com",
            "iat" => mktime (date("H"), date("i"), date("s") , date("n") , date("j"), date("Y")),
            "exp" => mktime (((date("H")+3)%24), date("i"), date("s") , date("n") , date("j"), date("Y"))
        );
        */
class ApiController extends FrontendController{
    public $user;
    public $jwtToken;
    public $jwtString;
    public $key = "t3cn3c0";

	function display(){
		$this->checkJWT();
	}

	//metodo che controlla il token JWT
	function checkJWT(){
        $headers = $this->getAuthorizationHeader();
        if ($this->checkJwtData($headers)) {
            return true;
        }
        else{
			$this->error(ACCESS_DENIED);
        }
    }
    
    function checkJwtData($headers){
        if ($headers) {
            $jwt = preg_replace('/Bearer /', '', $headers);
            $decoded = JWT::decode($jwt, $this->key, array('HS256'));
            if($decoded && $decoded->aud == 'http://catalogo.tecneco.com'){
                $this->user = (array) $decoded->user;
                $this->jwtToken = $decoded;
                $this->jwtString = $jwt;
                return true;
            }
            else{
                return false;
            }
        }
    }
	//metodo che restituisce l'errore in formato JSON
	function error($message='',$data=null){
		$data = array(
			'success' => 0,
			'error_message' => $message,
			'data' => $data
		);
		$this->send($data);

	}
	//metodo che restituisce il successo in formato JSON
	function success($data=null){
        if($this->jwtToken && $this->jwtToken->exp >= time('-10 minutes')){
            $jwtTemp = $this->generateJwt($this->user);
			
        }else{
            $jwtTemp = $this->jwtString;
        }
		$data = array(
			'success' => 1,
            'data' => $data,
            'jwt' => $jwtTemp
		);
		
		$this->send($data);

	}

	function send($data=array()){
		if( $this->user['id'] == 2356 ){
			
			header('Content-Type: application/json; charset=utf8');
			//header('Content-Type: charset=utf-8');
			if( $data['data']['error'] ) $data['data']['error'] = utf8_encode($data['data']['error']);
			//debugga($data);exit;
		}else{
			header('Content-Type: application/json');
		}
		echo json_encode($data);
		exit;
	}


    function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }else if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
        }else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }
/**
 * get access token from header
 * */
function getBearerToken() {
    $headers = $this->getAuthorizationHeader();
    // HEADER: Get the access token from the header
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return ''.trim($matches[1]);
        }
    }
    return null;
}

function generateJwt($user){
    /*$payload = array(
        "aud" => "http://catalogo.tecneco.com",
        "iat" => mktime (date("H"), date("i"), date("s") , date("n") , date("j"), date("Y")),
        "exp" => mktime (((date("H")+3)%24), date("i"), date("s") , date("n") , date("j"), date("Y")),
        'user' => $user
    );*/
    $payload = array(
        "aud" => "http://catalogo.tecneco.com",
        "iat" => time(),
        "exp" => strtotime('+3 hours'),
        'user' => $user
    );
    $jwtCreation = JWT::encode($payload, $this->key);
    return $jwtCreation;
}


}
?>