<?php
require('modules/b2b/controllers/front/ApiController.php');
use \Firebase\JWT\JWT;
use \Firebase\JWT\ExpiredException;

class HomeController extends ApiController
{
    public function display()
    {
        /**
         * IMPORTANT:
         * You must specify supported algorithms for your application. See
         * https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40
         * for a list of spec-compliant algorithms.
         */

        parent::display();
        $action = $this->getAction();
        switch ($action) {
            case 'userdata':
				$this->success($this->user);
				break;
			case 'slider_home':
                $this->getSliderHome();
            break;
        }

        /*//utilizzo i controlli del parent
        parent::display();



        $database = _obj('Database');


        //per fare una select
        $sel = $database->select('*',"nome_tabella","codizione");


        //insert
        $insert = array(
            'name' => 'Ciro',
            'surname' => 'Tizio'
        );
        $database->insert('nome_tabella',$insert);


        //update
        $database->update('nome_tabella',"codizione",$insert);


        //delete
        $database->delete('nome_Tabela',"codizione");
        */
    }

    public function getSliderHome()
    {
        require_once('modules/b2b/classes/SlideHome.class.php');

        $slides = SlideHome::prepareQuery()
        ->where('active', 1)
        ->get();
        $data = array();
        foreach ($slides as $v) {
            if ($v->image) {
                $data[] = array(
                    'id' => $v->id, //$v->get('id')
                    'message' => $v->get('message'),
                    'image' => $this->url().$v->getUrlImage()
                );
            }
        }


        $this->success($data);
    }

    public function url()
    {
        return sprintf(
            "%s://%s",
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
            $_SERVER['SERVER_NAME']
        );
    }
}
