<?php
class SurveyQuestionsController extends AdminModuleController{
    public $_auth = 'cms';
    public $_twig = true; //per i successivi no
    
    function displayList(){
        $this->setMenu('b2b_tecneco_survey');
        $id_survey = _var('id_survey');
        $success = _var('success');
        if( $success ){
            $this->displayMessage('Dati salvati con successo','success');
        }
        $list = SurveyQuestions::prepareQuery()
        ->where('id_survey', $id_survey)
        ->get();
        $this->setVar('list',$list);
        $this->setVar('id_survey',$id_survey);
		$this->output('surveys/questions/list.htm');
    }

    function displayForm(){
        $this->setMenu('b2b_tecneco_survey');
        $id_survey = _var('id_survey');
        $action= $this->getAction();
        //sto sottomettendo il form
        if( $this->isSubmitted() ){
            //attachment/download/{id}.htm
            $dati = $this->getFormdata(); // prendo i dati del $_POST
            $array = $this->checkDataForm('b2b_surveys_questions',$dati); //controllo i dati
           
            if( $array[0] == 'ok'){
                if( $action == 'add'){
                    //debugga('oh');
                    $obj =  SurveyQuestions::create(); //creo un nuovo oggetto
                }else{
                     // $action == 'edit'
                     $obj = SurveyQuestions::withId($array['id']); // prendo l'oggetto da modificare
                }
                $obj->set($array)->save();
                $this->redirectTolist(array('success'=>1, 'id_survey'=>$dati['id_survey']));
            }else{
                $this->errors[] = $array[1];
            }
        }else{
            if( $action == 'add'){
                $dati = null;
                $dati['id_survey'] = $id_survey;
            }else{
                $id = $this->getId();
                //$id = _var('id');
                //$id = $_GET['id'];
                $obj = SurveyQuestions::withId($id);
                $dati = $obj->prepareForm2();
            }
        }
        $dataform = $this->getDataForm('b2b_surveys_questions',$dati);
        $this->setVar('dataform',$dataform);
        $this->output('surveys/questions/form.htm');
    }

    function delete(){
		$id = $this->getId();
		if( $id ){
			$obj = SurveyQuestions::withId($id);
			if( is_object($obj) ){
				$obj->delete();
			}
		}
		$this->redirectTolist(array('deleted'=>1));
    }

}

?>