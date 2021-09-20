<?php

ini_set('memory_limit','2048M');
ini_set('max_execution_time', 8000);

class StatisticheImportController extends FrontendController{
	public $path_sincro = _MARION_MODULE_DIR_."b2b/ftp/files/STATISTICHE/w400fat_000000.txt";
	public $path_log = _MARION_MODULE_DIR_."b2b/log/statistiche/";
	public $linea_prodotto_b2b = [];
	public $agenti_b2b = [];
	public $linea_b2b = [];
	public $tracciati = [];
	public $carrelli_old = [];
	public $nazioni = [];
	public $regioni = [];
	public $province = [];

	public $gruppo_cliente = [];
	public $limite_ordini = 4000;

	public $reimport_ordini = [];
	public $update_ordini = false;


	public $mapping_values = [];
	

	function getDataOld(){
		$database = _obj('Database');

		$carrelli = $database->select('numero,stato','b2b_carrello',"anno ='".date('Y')."'");
		foreach($carrelli as $v){
			
			$this->carrelli_old[$v['numero']] = $v['numero'];
		}
		
		

		
		$gruppi = $database->select('codice_gestionale_int as codice,gruppo','b2b_cliente',"1=1");
		foreach($gruppi as $v){
			$this->gruppo_cliente[$v['codice']] = $v['gruppo'];
		}
		

		$countries = Country::getAll();
		foreach($countries as $v){
			$this->nazioni[strtoupper($v->get('name'))] = $v->id;
		}
		$regioni = $database->select('*','regione',"1=1");
		foreach($regioni as $v){
			$this->regioni[strtoupper($v['nome'])] = $v['codice'];
		}

		$province = $database->select('*','provincia',"1=1");
		
		foreach($province as $v){
			$this->province[strtoupper($v['nome'])] = $v['sigla'];
		}

		
		$codice_agente = $database->select('*','b2b_agente',"1=1");
		$codice_linea = $database->select('*','b2b_lineaprodotto',"1=1");
		$associa = $database->select('*','b2b_prodotto_linea',"1=1");
		
		if( okArray($codice_agente) ){
			foreach($codice_agente as $v){
				$this->agenti_b2b[$v['codice_gestionale']] = $v['id'];
			}
		}

		if( okArray($codice_linea) ){
			foreach($codice_linea as $v){
				$this->linea_b2b[$v['nome']] = $v['id'];
			}
		}

		if( okArray($associa) ){
			foreach($associa as $v){
				$this->linea_prodotto_b2b[$v['codart']] = $v['linea'];
			}
		}

		$carrelli = $database->select('numero,stato','b2b_carrello',"1=1");
		foreach($carrelli as $v){
			$this->carrelli_old[$v['numero']] = $v['stato'];
		}
		
	}


	function display(){
		$this->readTracciati();
		
		$this->getDataOld();
		$this->getMappingValues();
		
		$data = $this->leggiFile();

		
		/*$testata = $data['2017/2533/17']['testata'];
		debugga($testata);
		$ordini = $data['2016/2403']['ordini'];
		foreach($ordini as $v){
			
			$tmp[$v['numero_riga']][] = $v;
		}
		
		debugga($ordini);exit;
		/*foreach($ordini as $v){
			
				$tmp[$v['numero_riga']][] = $v;
			
		}*/
		//debugga($data);exit;*/
		$this->import($data);
		debugga('finito');
		
		/*$database = _obj('Database');

		$carrelli = $database->select('numero,stato','b2b_carrello',"1=1");
		foreach($carrelli as $v){
			$this->carrelli_old[$v['numero']] = $v['stato'];
		}



		$codice_agente = $database->select('*','b2b_agente',"1=1");
		$codice_linea = $database->select('*','b2b_lineaprodotto',"1=1");
		$associa = $database->select('*','b2b_prodotto_linea',"1=1");
		
		if( okArray($codice_agente) ){
			foreach($codice_agente as $v){
				$this->agenti_b2b[$v['codice']] = $v['codice'];
			}
		}

		if( okArray($codice_linea) ){
			foreach($codice_linea as $v){
				$this->linea_b2b[$v['nome']] = $v['codice'];
			}
		}

		if( okArray($associa) ){
			foreach($associa as $v){
				$this->linea_prodotto_b2b[$v['codart']] = $v['linea'];
			}
		}







//debugga($toreturn);exit;

/*foreach($list_codici as $v){
	if( !in_array($v,$app)){
		$app[] = $v;
	}else{
		$dup[] = $v;
	}
}

debugga($dup);
//debugga(count(array_unique($list_codici)));
//debugga(count($toreturn));

//debugga($i);
//debugga(count($list_codici));
//debugga(count(array_values($list_codici)));
//$temp = array_unique(array_keys($toreturn));
//$diff = array_diff($temp,$list_codici);
//debugga($list_codici);
//debugga(array_unique($temp));
//debugga(count($diff));
//debugga(count(array_keys($toreturn)));exit;

scrivi_log('INIZIO IMPORTAZIONE');
$ordini_importati = 0;
if( okArray($toreturn) ){

	
	foreach($toreturn as $v){
		
		if( in_array($v['testata']['numero'],$reimport_ordini)){
			unset($carrelli_old[$v['testata']['numero']]);
			$database->delete('b2b_carrello',"numero = '{$v['testata']['numero']}'");
			$database->delete('b2b_ordine',"carrello = '{$v['testata']['numero']}'");
			
		}
		$fattore_moltiplicativo = 1;
		$testata = $v['testata'];
		if( $testata['tipo_documento'] == 'N' ){
			$fattore_moltiplicativo = -1;
		}
		if( $testata['progressivo'] == '9999999') continue;
		if( $tipo_file == 'fatture'){
			$testata['stato'] = 'Fattur.';
		}

		$testata['totale'] = $fattore_moltiplicativo*$testata['totale'];
		
		unset($testata['tipo']);
		unset($testata['descrizione_agente']);
		if( $carrelli_old[$testata['numero']]){
			if( $update_ordini ){
				if( $carrelli_old[$testata['numero']] != $testata['stato'] ){
					$update = array(
						'stato' => $testata['stato']
					);
					$database->update('b2b_carrello',array('numero' => $testata['numero']),$update);
				}
			}
		}else{
			unset($testata['email']);
			$ordini = $v['ordini'];
			
			
			$database->insert('b2b_carrello',$testata);
			
			$ordini_importati++;
			scrivi_log('IMPORTAZIONE ORDINE '. $testata['numero']." -- ".$ordini_importati." ORDINI IMPORTATI");
			
			//debugga($database->lastquery);
			if( okArray($ordini) ){
				foreach($ordini as $v1){
					$v1['quantita'] = $fattore_moltiplicativo*$v1['quantita'];
					unset($v1['tipo']);
					unset($v1['anno_ordine']);
					unset($v1['linea_prodotto']);
					unset($v1['descrizione_articolo']);
					
					$database->insert('b2b_ordine',$v1);
				}
			}
			

			
		}
		
	}
	
}
scrivi_log('FINE IMPORTAZIONE');
$database->update('utenti',"codice_gestionale::integer IN (select cliente from b2b_carrello)",array('attivo_b2b' => 't'));
configura_province();
debugga(count($toreturn));
$_SESSION['sincronia_in_corso'] = false;
debugga('finito');exit;

function email_trim($email){
	return strtolower(trim($email));
}

function nazione_b2b($val){
	$val = trim($val);
	if( !$val ) return '';
	if( $_SESSION['nazioni_b2b'][$val] ){
		return $_SESSION['nazioni_b2b'][$val];
	}else{
		$database = get_object('DataBase');

		global $colori_nazione_disp;
		$colore_array = array_values($colori_nazione_disp);
		$colore = $colore_array[0];
		unset($colori_nazione_disp[$colore]);

		$toinsert = array(
			'nome' => $val,
			'colore' => $colore
		);
		$database->insert('b2b_nazione',$toinsert);
		$risultato = $database->select("currval('b2b_nazioni_codice_seq') AS codice");
        $codice = $risultato[0]['codice'];
		$_SESSION['nazioni_b2b'][$val] = $codice;
		
		return $codice;*/
	}


	function import($data){
		$database = _obj('Database');
		$ordini_importati = 0;
		foreach($data as $v){

			if( $v['testata']['nazione'] =='ERROR' || $v['testata']['provincia'] =='ERROR' || $v['testata']['regione'] =='ERROR' ){				
				//debugga($v);exit;
				$this->scrivi_log('ORDINE '. $testata['numero']." -- "." ERRORE DATI provincia/regione/nazione",'error');
				continue;
			}
			
			if( in_array($v['testata']['numero'],$this->reimport_ordini)){
				debugga('qui');exit;
				unset($this->carrelli_old[$v['testata']['numero']]);
				$database->delete('b2b_carrello',"numero = '{$v['testata']['numero']}'");
				$database->delete('b2b_ordine',"carrello = '{$v['testata']['numero']}'");
				
			}
			$fattore_moltiplicativo = 1;
			$testata = $v['testata'];
			if( $testata['tipo_documento'] == 'N' ){
				$fattore_moltiplicativo = -1;
			}
			if( $testata['progressivo'] == '9999999') continue;
			if( $tipo_file == 'fatture'){
				$testata['stato'] = 'Fattur.';
			}

			$testata['totale'] = $fattore_moltiplicativo*$testata['totale'];
			
			unset($testata['tipo']);
			unset($testata['descrizione_agente']);


			if( array_key_exists($testata['numero'],$this->carrelli_old)){
				
				/*if( $this->update_ordini ){
					if( $this->carrelli_old[$testata['numero']] != $testata['stato'] ){
						$update = array(
							'stato' => $testata['stato']
						);
						$database->update('b2b_carrello',array('numero' => $testata['numero']),$update);
					}
				}*/
			}else{
				
				unset($testata['email']);
				$ordini = $v['ordini'];
				
				unset($testata['email']);
				$testata['gruppo_cliente'] = $this->gruppo_cliente[$testata['cliente']];
				
				//debugga($testata);
				$check = $database->insert('b2b_carrello',$testata);
				if( $check ){
					$ordini_importati++;
					$this->scrivi_log('IMPORTAZIONE ORDINE '. $testata['numero']." -- ".$ordini_importati." ORDINI IMPORTATI");
					
					
					if( okArray($ordini) ){
						foreach($ordini as $v1){
							$v1['quantita'] = $fattore_moltiplicativo*$v1['quantita'];
							unset($v1['tipo']);
							unset($v1['anno_ordine']);
							unset($v1['linea_prodotto']);
							unset($v1['descrizione_articolo']);
							
							$check1 = $database->insert('b2b_ordine',$v1);
							if( !$check1 ){
								$this->scrivi_log('ORDINE '. $testata['numero']." RIGA ".$v['numero_riga']." -- ".$database->error);
							}
						}
					}
				}else{
					//debugga($this->carrelli_old[$testata['numero']]);
					//debugga($testata['numero']);exit;
					$this->scrivi_log('ORDINE '. $testata['numero']." -- ".$database->error);
				}
				

				
			}
			
		}

	}

	function readTracciati(){
		
		$this->tracciati = array(
			'testata' => array(
				'tipo' => array(1,1,'trim'), 
				'anno' => array(2,5,'trim'), 
				//'progressivo2' => array(6,11,'trim'), 
				'data' => array(12,17,'dataOrdine'), 
				'cliente' => array(18,27,'convertiIntero'), 
				'ragione_sociale' => array(28,57,'trim'), 
				'partita_iva' => array(68,78,'trim'), 
				'agente' => array(79,80,'trim'),
				'email' => array(101,140,'email_trim'),
				'descrizione_agente' => array(81,100,'agente_b2b'), 
				'nazione' => array(181,200,'nazione_b2b'),
				'regione' => array(161,180,'regione_b2b'),
				'provincia' => array(141,160,'provincia_b2b'),
				'citta' => array(141,160,'trim'),
				'totale' => array(201,213,'trim',2),
				//'stato' => array(214,233,'trim'),
				'progressivo' => array(214,220,'trim'),
				'tipo_documento' => array(221,221,'trim'),
				
			),
			'dettaglio' => array(
				'tipo' => array(1,1,'trim'), 
				'anno_ordine' => array(2,5,'trim'), 
				'carrello' => array(6,11,'trim'), 
				'codart' => array(12,24,'trim'), 
				'descrizione_articolo' => array(25,64,'trim'), 
				'linea_prodotto' => array(65,84,'tipo_prodotto_b2b'), 
				'quantita' => array(85,97,'trim',3), 
				'prezzo' => array(98,114,'trim',5), 
				'costo1' => array(115,131,'trim',5), 
				'costo2' => array(132,148,'trim',5), 
				'costo3' => array(149,165,'trim',5),
				'omaggio' => array(166,167,'campo_omaggio'),
				'numero_riga' => array(167,177,'convertiIntero'),
			),
			
			

			
		);

	}


	function leggiFile(){
		if( !file_exists($this->path_sincro) ){
			echo "File not found";
			exit;
		}
		$numero_linee = count(file($this->path_sincro));
		//debugga($numero_linee);exit;
		$f = fopen($this->path_sincro, 'r');
		$first_line = fgets($f);
		fclose($f);

		$file = fopen($this->path_sincro, "r");
				
		$i = 0;
		$repeat = true;
		while(!feof($file) && $repeat){
			$line = fgets($file);
			
			$f = $line[0];
			$dati = array();
			if( $f == 'T' ){
				
				//debugga($line);
				if( $this->limite_ordini ){
					if( $i > $this->limite_ordini ){
						$repeat = false;
						continue;
					}
				}
				//TESTATA
				$tracciato = $this->tracciati['testata'];
			}else{
				$tracciato = $this->tracciati['dettaglio'];
			}
			$importa_riga = true;
			foreach($tracciato as $k => $v){
				
				$start = $v[0]-1;
				$end = $v[1]-1;
				$length = $end-$start+1;
				
				$function = $v[2];
				
				$dec = $v[3]; // decimali
				$valore = substr($line,$start,$length);
				if( !$update_ordini ){
					if( ($f == 'T' && $k == 'numero') || ( $f == 'D' && $k == 'carrello') ){
					
						if( $this->carrelli_old[trim($valore)]){
							$importa_riga = false;
							break;
						}
					}
					
				}
				if( $function ) {
					if( method_exists($this,$function) ){
						if( $function == 'regione_b2b' ){
							
							$valore = $this->$function($valore);
						}elseif( $function == 'provincia_b2b' ){
							
							$valore = $this->$function($valore);
						}elseif( $function == 'agente_b2b' ){
							
							$valore = $this->$function($valore,$dati['agente'],$dati['email']);
						}elseif( $function == 'tipo_prodotto_b2b' ){
							
							$valore = $this->$function($valore,$dati['codart']);
						}else{
							//debugga($function);
							$valore = $this->$function($valore);
						}
					}elseif( function_exists($function) ){
						$valore = $function($valore);
					}
					
				}
				if( $dec ){
					$valore_tmp = $valore;
					$lung = strlen($valore)-$dec;
					
					$intera = substr($valore_tmp, 0,$lung);
					
					$decimale = substr($valore_tmp, -$dec);
					$valore = $intera.".".$decimale;
					$valore = (float)$valore;
				}
				
				
				$dati[$k] = $valore;
			}
			//debugga($dati);exit;
			if( $importa_riga ){
				if( $dati['tipo'] == 'T'){
					
					$i++;
					$codice_carrello = $dati['anno']."/".$dati['progressivo'];
					
					$dati['numero'] = $codice_carrello;
					$this->nazioni[$dati['nazione']] = $dati['nazione'];
					$this->province[$dati['provincia']] = $dati['provincia'];
					$toreturn[$codice_carrello]['testata'] = $dati;
					$list_codici[] = $codice_carrello;
					
					
				}elseif( $dati['tipo'] == 'D'){
					//$codice_carrello = $dati['anno_ordine']."/".$dati['carrello'];
					//debugga($codice_carrello);
					$dati['carrello'] = $codice_carrello;
					$toreturn[$dati['carrello']]['ordini'][] = $dati;
				}
			}
			

			
			
		}
		return $toreturn;
	}


	function getMappingValues(){
		$this->mapping_values['regioni'] = [
				'EMILIA ROMAGNA' => 'EMILIA-ROMAGNA',
				'ABRUZZI' => 'ABRUZZO',
				'FRIULI VENZIA GIULIA' => 'FRIULI-VENEZIA GIULIA',
				'TRENTINO ALTO ADIGE' => 'TRENTINO-ALTO ADIGE',
			];
		$this->mapping_values['nazioni'] = [
				'SUD AFRICA' => 'SUDAFRICA',
				'BOSNIA' => 'BOSNIA ERZEGOVINA',
				'ISOLA DI MALTA' => 'MALTA',
				'U.S.A.' => 'STATI UNITI',
				'INGHILTERRA' => 'REGNO UNITO',
				'OLANDA' => 'PAESI BASSI',
				'REP. CECA' => 'REPUBBLICA CECA',
				'TAJIKINSTAN' => 'TAGIKISTAN',
				'MAURITANIE' => 'MAURITANIA',
				'AZERBAIJAN' => 'AZERBAIGIAN',
				'MOLDOVA' => 'MOLDAVIA',
				'RUSSIA' => 'FEDERAZIONE RUSSA'
			];
		$this->mapping_values['province'] = [
				"FORLI'" => "FORLI'-CESENA",
				'PESARO' => 'PESARO E URBINO',
				"AND-BAR-TRANI" => "BARLETTA-ANDRIA-TRANI",
				"MASSA" => "MASSA-CARRARA",
				"DOMODOSSOLA" => "VERBANO-CUSIO-OSSOLA",
				"MONOPOLI" => 'BARI'
			];
	}



	/*** UTILITIES ****/
	function convertiIntero($val){
		$val = trim($val);
		return (int)$val;
	}
	function campo_omaggio($val){
		$val = trim($val);
		
		if( strtoupper($val) == 'O' ){ 
			
			return 1;
		}
		return 0;
	}

	function agente_b2b($val,$codice,$email){
	
		$val = trim($val);

		if( !$val ) return '';
		if( !$codice ) return '';
		$codice = (int)$codice;
		if( !$this->agenti_b2b[$codice] ){
			
			$database = _obj('Database');
			$toinsert = array(
				'descrizione' => $val,
				'codice_gestionale' => $codice,
				'username' => "agente{$codice}",
				'password' => "agente{$codice}",
				'attivo' => 1,
				'email' => $email,
			);
			
			$database->insert('b2b_agente',$toinsert);
			//debugga($database->lastquery);exit;
			
			
			$this->agenti_b2b[$codice] = $codice;
		}
		return $val;
	}

	
	function dataOrdine($data){
		$data = trim($data);
		$giorno = substr($data,0,2);
		$mese = substr($data,2,2);
		$anno = "20".substr($data,4,2);
		return "{$anno}-{$mese}-{$giorno}";
	}

	function tipo_prodotto_b2b($val,$codart){
	
		$val = trim($val);
		if( !$val ) return '';
		if( !$codart ) return '';
		$database = _obj('Database');

		//debugga($val);
		if( !$this->linea_b2b[$val] ){
		
			//debugga('qua');exit;
			$toinsert = array(
				'nome' => $val,
			);
			$codice =  $database->insert('b2b_lineaprodotto',$toinsert);
			
			
			
			
			$this->linea_b2b[$val] = $codice;
		}else{
			$codice = $this->linea_b2b[$val];
		}

		//debugga($this->linea_prodotto_b2b);exit;

		if( !$this->linea_prodotto_b2b[$codart] ){
			
			$toinsert = array(
				'codart' => $codart,
				'linea' => $codice
			);
			$database->insert('b2b_prodotto_linea',$toinsert);
			$this->linea_prodotto_b2b[$codart] = $codice;
		}
		return $codice;
	}

	function nazione_b2b($val){
		$val = strtoupper(trim($val));
		if( !$val ) return '';
		if( $this->nazioni[$val] ){
			return $this->nazioni[$val];
		}
		$mapping = $this->mapping_values['nazioni'][$val];
			
		if( $mapping && $this->nazioni[$mapping]){
			return $this->nazioni[$mapping];
		}
		$this->scrivi_log("Nazione {$val} non trovata",'error');
		return 'ERROR';
	}

	function regione_b2b($val){
	
	
		$val = strtoupper(trim($val));

		
		
		if( !$val ) return '';
		
		//debugga($val);exit;
		if( $this->regioni[$val] ){
			return $this->regioni[$val];
		}else{
			$mapping = $this->mapping_values['regioni'][$val];
			
			if( $mapping && $this->regioni[$mapping]){
				return $this->regioni[$mapping];
			}
			
			$this->scrivi_log("Regione {$val} non trovata",'error');
			return 'ERROR';
		}
	}

	

	function provincia_b2b($val){
		
		$val = strtoupper(trim($val));
		
		if( !$val ) return '';
		if( $this->province[$val] ){
			return $this->province[$val];
		}else{

			$mapping = $this->mapping_values['province'][$val];
			
			if( $mapping && $this->province[$mapping]){
				return $this->province[$mapping];
			}

			$this->scrivi_log("Provincia {$val} non trovata",'error');
			
			return 'ERROR';
		}
	}


	function scrivi_log($testo,$type='info'){
	
		$path = $this->path_log.$type."/";
		if($path){
			$data_corrente = date('Y-m-d');
			if( $file_name ){
				$file = $path.$file_name."_".$data_corrente.".txt";
			}else{
				$file = $path.$data_corrente.".txt";
			}
			$date = "[".date('Y-m-d H:i')."] ";						   
			error_log($date.$testo."\n", 3, $file);
		}


	}


}

/*function regione_b2b($val,$nazione){
	
	
	$val = trim($val);

	
	
	if( !$val ) return '';
	if( !$nazione ) return '';
	//debugga($val);exit;
	if( $_SESSION['regioni_b2b'][$nazione][$val] ){
		return $_SESSION['regioni_b2b'][$nazione][$val];
	}else{
		$database = get_object('DataBase');

		global $colori_regione_disp;
		
		$colore_array = array_values($colori_regione_disp);
		$colore = $colore_array[0];
		unset($colori_regione_disp[$colore]);

		$toinsert = array(
			'nome' => $val,
			'nazione' => $nazione,
			'colore' => $colore
		);
		$database->insert('b2b_regione',$toinsert);
		$risultato = $database->select("currval('b2b_regione_codice_seq') AS codice");
        $codice = $risultato[0]['codice'];
		
		$_SESSION['regioni_b2b'][$nazione][$val] = $codice;
		
		return $codice;
	}
}

function provincia_b2b($val,$regione){
	
	$val = trim($val);
	if( !$val ) return '';
	if( !$regione ) return '';
	if( $_SESSION['province_b2b'][$regione][$val] ){
		return $_SESSION['province_b2b'][$regione][$val];
	}else{
		$database = get_object('DataBase');
		$toinsert = array(
			'nome' => $val,
			'regione' => $regione
		);
		$database->insert('b2b_provincia',$toinsert);
	
		$risultato = $database->select("currval('b2b_provincia_codice_seq') AS codice");
        $codice = $risultato[0]['codice'];
		
		$_SESSION['province_b2b'][$regione][$val] = $codice;
		
		return $codice;
	}
}

function agente_b2b($val,$codice,$email){
	
	$val = trim($val);
	
	if( !$val ) return '';
	if( !$codice ) return '';
	if( !$_SESSION['agenti_b2b'][$codice] ){
	
		$database = get_object('DataBase');
		$toinsert = array(
			'descrizione' => $val,
			'codice' => $codice,
			'username' => "agente{$codice}",
			'password' => "agente{$codice}",
			'email' => $email,
		);
		
		$database->insert('b2b_agente',$toinsert);
		//debugga($database->lastquery);exit;
		
		
		$_SESSION['agenti_b2b'][$codice] = $codice;
	}
	return $val;
}

function campo_omaggio($val){
	$val = trim($val);
	
	if( strtoupper($val) == 'O' ){ 
		
		return 't';
	}
	return 'f';
}

function tipo_prodotto_b2b($val,$codart){
	
	$val = trim($val);
	if( !$val ) return '';
	if( !$codart ) return '';
	$database = get_object('DataBase');
	if( !$_SESSION['linea_b2b'][$val] ){
	
		
		$toinsert = array(
			'nome' => $val,
		);
		$database->insert('b2b_lineaprodotto',$toinsert);
		$risultato = $database->select("currval('b2b_lineaproodotto_codice_seq') AS codice");
        $codice = $risultato[0]['codice'];
		
		
		$_SESSION['linea_b2b'][$val] = $codice;
	}else{
		$codice = $_SESSION['linea_b2b'][$val];
	}

	if( !$_SESSION['linea_prodotto_b2b'][$codart] ){
		$toinsert = array(
			'codart' => $codart,
			'linea' => $codice
		);
		$database->insert('b2b_prodotto_linea',$toinsert);
		$_SESSION['linea_prodotto_b2b'][$codart] = $codice;
	}
	return $codice;
}



function dataOrdine($data){
	$data = trim($data);
	$giorno = substr($data,0,2);
	$mese = substr($data,2,2);
	$anno = "20".substr($data,4,2);
	return "{$anno}-{$mese}-{$giorno}";
}



function configura_province(){
	$database = get_object('DataBase');
	$prov = $database->select('*','b2b_provincia',"1=1");
	$prov_db = $database->select('*','province',"1=1");
	foreach($prov_db as $t){
		$nome = trim(strtoupper($t['provincia']));
		
		if( $nome == 'PESARO E URBINO'){
			$associa['PESARO'] = $t['codice'];
			$regione['PESARO'] = $t['regione'];
		}elseif( $nome == "FORLI' E CESENA"){
			$nome = "FORLI'";
		}elseif( $nome == "MASSA CARRARA"){
			$nome = "MASSA-CARRARA";
		}elseif( $nome == 'PESARO'){
			continue;
		}
		$associa[$nome] = $t['codice'];
		$regione[$nome] = $t['regione'];
	}
	
	foreach($prov as $v){
		$insert = array(
			'codice_sito' => $associa[trim($v['nome'])],
			'codice_regione_sito' => $regione[trim($v['nome'])],
		);
		$database->update('b2b_provincia',"codice={$v['codice']}",$insert);
	}

	$nazioni = $database->select('*','b2b_nazione',"1=1");
	$nazioni_db = $database->select('*','nazioni',"1=1");
	foreach($nazioni_db as $t){
		$nome = trim(strtoupper($t['nazione']));
		$associa2[$nome] = $t['codice'];
	}
	foreach($nazioni as $v){
		$insert = array(
			'codice_sito' => $associa2[strtoupper(trim($v['nome']))]
		);
		$database->update('b2b_nazione',"codice={$v['codice']}",$insert);
	}
}




	}

	function scrivi_log($testo){
	
		$path = "../statistiche/";
		if($path){
			$data_corrente = date('Y-m-d');
			if( $file_name ){
				$file = $path.$file_name."_".$data_corrente.".txt";
			}else{
				$file = $path.$data_corrente.".txt";
			}
			$date = "[".date('Y-m-d H:i')."] ";						   
			error_log($date.$testo."\n", 3, $file);
		}


	}


	

}
*/
?>