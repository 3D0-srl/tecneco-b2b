<?php
class GdprController extends AdminModuleController{
    public $_auth = 'cms';
    public $_twig = true; //per i successivi no

	function displayContent(){
        $this->setMenu('b2b_tecneco_gdpr');
        $database = _obj('Database');
        switch ($this->getAction()) {
            case 'details':
                $id_request = _var('id');
                $database = _obj('Database');
                $request = $database->select('*','b2b_gdpr_request',"id={$id_request}");
                $_requestData = unserialize($request[0]['request']);
                switch(_var('type')){
                    case 'DATAREQUEST':
                        $data = $database->select('*','user',"id={$request[0]['id_user']}");
                    break;
                    case 'ADDRESSREQUEST':
                        $data = $database->select('*','address',"id={$request[0]['id_address']}");
                    break;
                }
                $_toShow = array();
                $_keys = array_keys($_requestData);
                foreach($_keys as $key){
                    if($key != 'iva'){
                        $_toShow[]= array(
                            'name'=>$key,
                            'original'=>$data[0][$key],
                            'toRev'=>$_requestData[$key],
                        );
                    }
                }
                $translator = array(
                    'email'=> 'email',
                    'company'=> 'ragione sociale',
                    'vatNumber'=> 'p.iva',
                    'postalCode'=> 'cap',
                    'address'=> 'indirizzo',
                    'city'=> 'città',
                    'province'=> 'provincia',
                    'nation'=> 'nazione',
                    'name'=>'nome',
                    'phone'=>'telefono',
                    'cellulare'=>'cellulare'
                );
                $this->setVar('id',$request[0]['id']);
                $this->setVar('data',$_toShow);
                $this->setVar('translator',$translator);
                $this->output('gdpr-request/details.htm');
            break;
            case 'approve':
                $id_request = _var('id');
                $approved = _var('approved');

                $request = $database->select('*','b2b_gdpr_request',"id={$id_request}");
                $data = $request[0];
                $_requestData = unserialize($data['request']);
                unset($_requestData['id']);
                $data['state'] = 1;
                $data['approved'] = $approved;
                $database->update('b2b_gdpr_request',"id={$id_request}",$data);
                if($approved){
                    switch($data['type']){
                        case 'DATAREQUEST':
                            $_data = $database->select('*','user',"id={$data['id_user']}");
                            $user = $_data[0];
                            $_keys = array_keys($user);
                            foreach($_keys as $key){
                                if($_requestData[$key]){
                                    $user[$key]=$_requestData[$key];
                                }
                            }
                            $success = $database->update('user',"id={$data['id_user']}",$user);
                        break;
                        case 'ADDRESSREQUEST':
                            $_data = $database->select('*','address',"id={$data['id_address']}");
                            $address = $_data[0];
                            $_keys = array_keys($address);
                            foreach($_keys as $key){
                                if($_requestData[$key]){
                                    $address[$key]=$_requestData[$key];
                                }
                            }
                            $success = $database->update('address',"id={$data['id_address']}",$address);
                        break;
                    }
                }
                $_user = $database->select('*','user',"id={$data['id_user']}");
                $user = $_user[0];
                //$this->sendMailGdprUpdate($user['email'],$user['name'],$user['surname'],$approved);
                $this->sendMailGdprUpdate('info.tortora.aniello@gmail.com',$user['name'],$user['surname'],$approved);
                header('Location: http://catalogo.tecneco.com/backend/index.php?ctrl=Gdpr&mod=b2b&action=list&success='.$success);
            break;
        }
    }
    function displayList(){
        $this->setMenu('b2b_tecneco_gdpr');
        $success = _var('success');
        if( $success ){
            $this->displayMessage('Richiesta approvata con successo');
        }
        $database = _obj('Database');
        $list = $database->select('r.id, r.type, r.id_user, r.state, r.approved, r.id_address, u.name','b2b_gdpr_request as r join user as u on r.id_user = u.id','1=1 order by r.state asc');
        $this->setVar('list',$list);
		$this->output('gdpr-request/list.htm');
    }
    
    function sendMailGdprUpdate($emailTo, $name, $surname, $approved){	
		$data = array(
			'email' => $emailTo,
			'time' => time()
		);
        $this->setVar('name', $name);
        $this->setVar('surname', $surname);
        $this->setVar('approved', $approved);
        $this->_twig_tepplates_dir[] = '../themes/jewels/templates_twig';
        //preparo l'html
		ob_start();
		$this->output('mail/gdpr.htm');
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

?>