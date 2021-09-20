<?php
class PromosController extends AdminModuleController{
    public $_auth = 'cms';
	public $_twig = true; //per i successivi no
    function displayList(){
        $this->setMenu('b2b_tecneco_promos');
        $success = _var('success');
        if( $success ){
            $this->displayMessage('Dati salvati con successo','success');
        }
        $list = Promos::prepareQuery()
        ->get();
        $this->setVar('list',$list);
		$this->output('promos/list.htm');
    }

    function displayForm(){
        $this->setMenu('b2b_tecneco_promos');
        
        $action= $this->getAction();
        //sto sottomettendo il form
        if( $this->isSubmitted() ){
            //attachment/download/{id}.htm
            $dati = $this->getFormdata(); // prendo i dati del $_POST
            //debugga($dati);
            $array = $this->checkDataForm('b2b_promos',$dati); //controllo i dati
            //debugga($array);
            //exit;
            if( $array[0] == 'ok'){
                if( $action == 'add'){
                    $obj =  Promos::create(); //creo un nuovo oggetto
                }else{
                     // $action == 'edit'
                     $obj = Promos::withId($array['id']); // prendo l'oggetto da modificare
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
                $obj = Promos::withId($id);
                $dati = $obj->prepareForm2();
            }
        }
        $dataform = $this->getDataForm('b2b_promos',$dati);
        $this->setVar('dataform',$dataform);
        $this->output('promos/form.htm');
    }

     function delete(){
		$id = $this->getId();
		if( $id ){
			$obj = Promos::withId($id);
			if( is_object($obj) ){
				$obj->delete();
			}
		}
		$this->redirectTolist(array('deleted'=>1));
    }



}

?>