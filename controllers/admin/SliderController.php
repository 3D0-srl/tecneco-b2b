<?php
class SliderController extends AdminModuleController{
    public $_auth = 'cms';
	public $_twig = true; //per i successivi no


	
	
	
    function displayList(){
        $this->setMenu('b2b_tecneco_slider');
        $success = _var('success');
        if( $success ){
            $this->displayMessage('Dati salvati con successo','success');
        }
		$deleted = _var('deleted');
        if( $deleted ){
            $this->displayMessage('Slide eliminata con successo','success');
        }

        $list = SlideHome::prepareQuery()
        ->get();


       
        $this->setVar('mia_variabile','ciccio');
        $this->setVar('list',$list);

       
		
		$this->output('slider/list.htm');

	
        

    }




    function displayForm(){
        $this->setMenu('b2b_tecneco_slider');
        $action= $this->getAction();
        //sto sottomettendo il form
        if( $this->isSubmitted() ){
            //attachment/download/{id}.htm
            $dati = $this->getFormdata(); // prendo i dati del $_POST
            //debugga($dati);
            $array = $this->checkDataForm('b2b_slider_home',$dati); //controllo i dati
            //debugga($array);
            if( $array[0] == 'ok'){
                if( $action == 'add'){
                    $obj =  SlideHome::create(); //creo un nuovo oggetto
                 }else{
                     // $action == 'edit'
                     $obj = SlideHome::withId($array['id']); // prendo l'oggetto da modificare
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
                $obj = SlideHome::withId($id);
                $dati = $obj->prepareForm2();
            }
        }

        $dataform = $this->getDataForm('b2b_slider_home',$dati);
        $this->setVar('dataform',$dataform);

        $this->output('slider/form.htm');
    }


    function delete(){

		$id = $this->getId();
		if( $id ){
			$obj = SlideHome::withId($id);
			if( is_object($obj) ){
				$obj->delete();
			}
		}
		$this->redirectTolist(array('deleted'=>1));
        
    }

}

?>