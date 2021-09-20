<?php
class PricelistController extends AdminModuleController{
    public $_auth = 'cms';
    public $_twig = true; //per i successivi no
    public function displayList() {
        $this->setMenu('b2b_tecneco_price_lists');
        $list = PriceLists::prepareQuery()
        ->get();
        $this->setVar('list', $list);
        $this->output('pricelist/list.htm');
    }
    public function displayForm() {
        $this->setMenu('b2b_tecneco_price_lists');
        $action= $this->getAction();
        //sto sottomettendo il form
        if ($this->isSubmitted()) {
            //attachment/download/{id}.htm
            $dati = $this->getFormdata(); // prendo i dati del $_POST
            $array = $this->checkDataForm('b2b_price_lists', $dati); //controllo i dati
            if ($array[0] == 'ok') {
                if ($action == 'add') {
                    $obj =  PriceLists::create(); //creo un nuovo oggetto
                } else {
                    // $action == 'edit'
                     $obj = PriceLists::withId($array['id']); // prendo l'oggetto da modificare
                }
                $obj->set($array)->save();
                $this->redirectTolist(array('success'=>1));
            } else {
                $this->errors[] = $array[1];
            }
        } else {
            if ($action == 'add') {
                $dati = null;
            } else {
                $id = $this->getId();
                //$id = _var('id');
                //$id = $_GET['id'];
                $obj = PriceLists::withId($id);
                $dati = $obj->prepareForm2();
            }
        }
        $dataform = $this->getDataForm('b2b_price_lists', $dati);
        $this->setVar('dataform', $dataform);
        $this->output('pricelist/form.htm');
    }
    public function delete() {
	   $id = $this->getId();
       $obj = PriceLists::withId($id);
	   $obj->delete();
	   $this->redirectToList();

    }
}
?>