<?php
class SurveyQuestionAnswersController extends AdminModuleController{
    public $_auth = 'cms';
    public $_twig = true; //per i successivi no
    
    function displayList(){
        $this->setMenu('b2b_tecneco_survey');
        $id_survey = _var('id_survey');
        $id_survey_question = _var('id_survey_question');
        $success = _var('success');
        if( $success ){
            $this->displayMessage('Dati salvati con successo','success');
        }
        $list = SurveyQuestionAnswers::prepareQuery()
        ->where('id_survey_question', $id_survey_question)
        ->get();
        $this->setVar('list',$list);
        $this->setVar('id_survey',$id_survey);
        $this->setVar('id_survey_question',$id_survey_question);
		$this->output('surveys/questions/answers/list.htm');
    }

    function displayForm(){
        $this->setMenu('b2b_tecneco_survey');
        $id_survey = _var('id_survey');
        $id_survey_question = _var('id_survey_question');
        $action= $this->getAction();
        //sto sottomettendo il form
        if( $this->isSubmitted() ){
            //attachment/download/{id}.htm
            $dati = $this->getFormdata(); // prendo i dati del $_POST
            $array = $this->checkDataForm('b2b_surveys_questions_answer',$dati); //controllo i dati
            //debugga($array);
            if( $array[0] == 'ok'){
                if( $action == 'add'){
                    //debugga('oh');
                    $obj =  SurveyQuestionAnswers::create(); //creo un nuovo oggetto
                }else{
                     // $action == 'edit'
                     $obj = SurveyQuestionAnswers::withId($array['id']); // prendo l'oggetto da modificare
                }
                $obj->set($array)->save();
                $this->redirectTolist(array('success'=>1, 'id_survey'=>$dati['id_survey'], 'id_survey_question'=>$dati['id_survey_question']));
            }else{
                $this->errors[] = $array[1];
            }
        }else{
            if( $action == 'add'){
                $dati = null;
                $dati['id_survey'] = $id_survey;
                $dati['id_survey_question'] = $id_survey_question;
            }else{
                $id = $this->getId();
                //$id = _var('id');
                //$id = $_GET['id'];
                $obj = SurveyQuestionAnswers::withId($id);
                $dati = $obj->prepareForm2();
            }
        }
        $dataform = $this->getDataForm('b2b_surveys_questions_answer',$dati);
        $this->setVar('dataform',$dataform);
        $this->output('surveys/questions/answers/form.htm');
    }

    function delete(){
		$id = $this->getId();
		if( $id ){
			$obj = SurveyQuestionAnswers::withId($id);
			if( is_object($obj) ){
				$obj->delete();
			}
		}
		$this->redirectTolist(array('deleted'=>1));
    }

}

?>