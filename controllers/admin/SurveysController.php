<?php
class SurveysController extends AdminModuleController{
    public $_auth = 'cms';
	public $_twig = true; //per i successivi no
    function displayList(){
        $this->setMenu('b2b_tecneco_survey');
        $success = _var('success');
        if( $success ){
            $this->displayMessage('Dati salvati con successo','success');
        }
       
		$list = Survey::prepareQuery()
		->get();
		$this->setVar('list',$list);
		$this->output('surveys/list.htm');
		
		
    }


	function displayContent(){
        $this->setMenu('b2b_tecneco_survey');
        $database = _obj('Database');
		switch($this->getAction()){
            case 'results':
                $id_survey = _var('id_survey');
                $survey = Survey::prepareQuery()
                ->where('id', $id_survey)
                ->get();
				
				$this->setVar('inizio',$survey[0]->date_from);
                $surveyQuestions = SurveyQuestions::prepareQuery()
                ->where('id_survey', $id_survey)
                ->get();
                 $surveyAnswers = SurveyQuestionAnswers::prepareQuery()
                ->where('id_survey', $id_survey)
                ->get();

			
                $results = $database->select('*',"b2b_surveys_results","id_survey = '{$id_survey}' ");
				
				$partecipanti = $database->select('count(*) as tot','b2b_user_surveys',"id_survey={$id_survey}");
				$agg = $database->select('max(timestamp) as max','b2b_user_surveys',"id_survey={$id_survey}");
				$this->setVar('partecipanti',$partecipanti[0]['tot']);

				$this->setVar('aggiornamento',$agg[0]['max']);
				
                $data = array();
                foreach ($surveyQuestions as $question) {
                    $questionCount = 0;
                    foreach($surveyAnswers as $answer){
                        if($answer->id_survey_question == $question->id){
                            $answerCount = 0;
                            foreach($results as $result){
                                if($result['id_answer'] == $answer->id){
                                    $answerCount++;
                                    $questionCount++;
                                }
                            }
                            $answersData[$answer->id]->name = $answer->get('description');
                            $answersData[$answer->id]->count = $answerCount;
                        }
                    }
                    $questionsData[$question->id]->name = $question->get('description');
                    $questionsData[$question->id]->answers = $answersData;
                    $questionsData[$question->id]->count = $questionCount;
                    $answersData = null;
                }
                $resultsBack = $questionsData;

                $this->setVar('survey',$survey);
                $this->setVar('results',$resultsBack);
                $this->output('surveys/results.htm');
				break;
		}
	}

    function displayForm(){
        $this->setMenu('b2b_tecneco_survey');
        $action= $this->getAction();
        //sto sottomettendo il form
        if( $this->isSubmitted() ){
            //attachment/download/{id}.htm
            $dati = $this->getFormdata(); // prendo i dati del $_POST
            //debugga($dati);
            $array = $this->checkDataForm('b2b_surveys',$dati); //controllo i dati
            //debugga($array);exit;
            if( $array[0] == 'ok'){
                if( $action == 'add'){
                    //debugga('oh');
                    $obj =  Survey::create(); //creo un nuovo oggetto
                }else{
                     // $action == 'edit'
                     $obj = Survey::withId($array['id']); // prendo l'oggetto da modificare
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
                $obj = Survey::withId($id);
                $dati = $obj->prepareForm2();
            }
        }
        $dataform = $this->getDataForm('b2b_surveys',$dati);
        $this->setVar('dataform',$dataform);
        $this->output('surveys/form.htm');
    }

    function delete(){
		$id = $this->getId();
		if( $id ){
			$obj = Survey::withId($id);
			if( is_object($obj) ){
				$obj->delete();
			}
		}
		$this->redirectTolist(array('deleted'=>1));
    }

}

?>