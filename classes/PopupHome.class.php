<?php
class PopupHome extends Base{
	
	// COSTANTI DI BASE
	const TABLE = 'b2b_popup'; // nome della tabella a cui si riferisce la classe
	const TABLE_PRIMARY_KEY = 'id'; //chiave primaria della tabella a cui si riferisce la classe
	const TABLE_LOCALE_DATA = 'b2b_popup_lang'; // nome della tabella del database che contiene i dati locali
	const TABLE_EXTERNAL_KEY = 'id_popup';// / nome della chiave esterna alla tabella del database
	const PARENT_FIELD_TABLE = ''; //nome del campo padre
	const LOCALE_FIELD_TABLE = 'lang'; // nome del campo locale nella tabella contenente i dati locali
	const LOCALE_DEFAULT = 'it'; //il locale di dafault
	const LOG_ENABLED = true; //abilita i log
	const PATH_LOG = ''; // file  in cui verranno memorizzati i log
	const NOTIFY_ENABLED = false; // notifica all'amministratore
	const NOTIFY_ADMIN_EMAIL = 'ciro.napolitano87@gmail.com'; // email a cui inviare la notifica

	function getUrlFile(){
        return _MARION_BASE_URL_.'attachment/download/'.$this->attachment[0].'.htm';
    }
	function getUrlToShowFile(){
        return _MARION_BASE_URL_.'attachment/display/'.$this->attachment[0].'.htm';
    }
    function geturlImage($type = 'or'){
		return _MARION_BASE_URL_.'img/'.$this->image.'/'.$type.'-nw/popup.png';
	}

}

?>