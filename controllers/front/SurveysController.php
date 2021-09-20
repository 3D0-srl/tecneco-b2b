<?php
require('modules/b2b/controllers/front/ApiController.php');
class SurveysController extends ApiController{

	function checkSurvey(){
		$id_user = $this->user['id'];
		
		require_once('modules/b2b/classes/Surveys.class.php');
		$survey = Survey::prepareQuery()
		->where('show_popup',1)
		->where('active',1)
		->whereExpression("(id not in (select id_survey from b2b_user_surveys where id_user = '{$id_user}'))")
        ->get();
		
		if( okArray($survey) ){
			$this->success($survey[0]);
		}else{
			
			$this->success();
		}
		exit;
	}


	function display(){
        parent::display();
		$action = $this->getAction();
		switch($action){
			case 'check':
				
				$this->checkSurvey();
				break;
			case 'get_survey':
				$this->get_survey();
			break;

			case 'save_survey':
				$this->save_survey();
			break;

		}
	}
	function save_survey(){
        $data = json_decode(file_get_contents('php://input'), true);
        $database = _obj('Database');
		$risposte = $data['surveyData'];
		$id_user = $this->user['id'];
		$salvare = [];
		foreach($risposte as $v){
			$v['id_user'] = $id_user;
			$salvare[] = $v;
		}

        $success = $database->insert('b2b_surveys_results',$salvare);
        
       
        $surveyData = array(
            'id_user' => $id_user,
            'id_survey' => $data['surveyData'][0]['id_survey']
        );
        $success = $database->insert('b2b_user_surveys',$surveyData);
        
		$this->success($success);
    }

	function get_survey(){
		require_once('modules/b2b/classes/Surveys.class.php');
		$survey = Survey::prepareQuery()
		//->where('show_popup',1)
		->where('active',1)
        ->get();
        
        $database = _obj('Database');
        $id_user = $this->user['id'];
        $dones = $database->select('id_survey',"b2b_user_surveys","id_user = '{$id_user}' ");

		$data = array();
		foreach($survey as $v){
			if($v-> active){
                $flag = 1;
                foreach($dones as $done){
                    if($done['id_survey'] == $v->id){
                        $flag = 0;
                    }
                }
                if ($flag) {
                    $questions = $this->get_questions($v->id);
                    $data[] = array(
                        'id' => $v->id, //$v->get('id')
                        'name' => $v->get('name'),
                        'questions' => $questions
                    );
                }
			}
		}
		$this->success($data);
    }

    function get_questions($id_survey){
		require_once('modules/b2b/classes/SurveyQuestions.class.php');
        $questions = SurveyQuestions::prepareQuery()
		->where('id_survey',$id_survey)
        ->get();
		$data = array();
		foreach($questions as $v){
			if($v-> active){
                $answers = $this->get_answers($v->id);
				$data[] = array(
					'id' => $v->id,
					'title' => $v->get('title'),
					'description' => $v->get('description'),
                    'sort' => $v->sort,
                    'answers' => $answers
				);
			}
		}
        return $data;
    }

    function get_answers($id_survey_question){
		require_once('modules/b2b/classes/SurveyQuestionAnswers.class.php');
        $answers = SurveyQuestionAnswers::prepareQuery()
		->where('id_survey_question',$id_survey_question)
        ->get();
		$data = array();
		foreach($answers as $v){
			if($v-> active){
				$data[] = array(
					'id' => $v->id,
					'description' => $v->get('description'),
                    'sort' => $v->sort,
				);
			}
        }
        return $data;
    }
}
?>