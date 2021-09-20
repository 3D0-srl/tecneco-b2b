<?php
class PopupHomeController extends AdminModuleController{
    public $_auth = 'cms';
	public $_twig = true; //per i successivi no
    function displayList(){
        $success = _var('success');
        if( $success ){
            $this->displayMessage('Dati salvati con successo','success');
        }
        $list = PopupHome::prepareQuery()
        ->get();
        $this->setVar('list',$list);
		$this->output('popups/list.htm');
    }

    function displayForm(){
        
        $action= $this->getAction();
        //sto sottomettendo il form
        if( $this->isSubmitted() ){
            //attachment/download/{id}.htm
            $dati = $this->getFormdata(); // prendo i dati del $_POST
            //debugga($dati);
            $array = $this->checkDataForm('b2b_popup',$dati); //controllo i dati
            //debugga($array);
            if( $array[0] == 'ok'){
                if( $action == 'add'){
                    //debugga('oh');
                    $obj =  PopupHome::create(); //creo un nuovo oggetto
                }else{
                     // $action == 'edit'
                     $obj = PopupHome::withId($array['id']); // prendo l'oggetto da modificare
                }
                $obj->set($array)->save();
                $this->redirectTolist(array('success'=>1));
            }else{
                $this->errors[] = $array[1];
            }
        }else{
            if( $action == 'add'){
                $dati = null;
            }else{
                $id = $this->getId();
                //$id = _var('id');
                //$id = $_GET['id'];
                $obj = PopupHome::withId($id);
                $dati = $obj->prepareForm2();
            }
        }
        $dataform = $this->getDataForm('b2b_popup',$dati);
        $this->setVar('dataform',$dataform);
        $this->output('popups/form.htm');
    }

    function delete(){
		$id = $this->getId();
		if( $id ){
			$obj = PopupHome::withId($id);
			if( is_object($obj) ){
				$obj->delete();
			}
		}
		$this->redirectTolist(array('deleted'=>1));
    }

}

?>