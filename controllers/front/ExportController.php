<?php
require('modules/b2b/controllers/front/ApiController.php');
class ExportController extends ApiController{
	public $tempo_massimo_riapri_ordine = 0;
	public $path = _MARION_MODULE_DIR_."b2b/ftp/files/ORDINI/";
	
	
	function display(){

	

		$database = _obj('Database');

		ini_set('memory_limit','1024M');
		ini_set('max_execution_time', 8000);



		
		

	
	

		$carrelli = $database->select('*','cart',"status = 'in_attesa' AND esportato=0 AND aggiunto_a IS NULL");
		
		if( $this->tempo_massimo_riapri_ordine ){
			//prendo i carrelli di almeno 15 min fa
			$date_now = date('Y-m-d H:i');
			foreach($carrelli as $k => $v){
				$diff = (int)((strtotime($date_now)-strtotime($v['data']))/60);
				if( $diff < $this->tempo_massimo_riapri_ordine ){
					unset($carrelli[$k]);
				}
			}
		}

		//}


		foreach($carrelli as $carrello){
			
			$check_import = true;

			$progressivo = $this->getProgressivo();
			
			
			$carrello['progressivo'] = $progressivo;
			if( $progressivo < 10 ){
				$progressivo = "00000".$progressivo;
			}elseif( $progressivo >= 10 && $progressivo < 100 ){
				$progressivo = "0000".$progressivo;
			}elseif( $progressivo >= 100 && $progressivo < 1000 ){
				$progressivo = "000".$progressivo;
			}elseif( $progressivo >= 1000 && $progressivo < 10000 ){
				$progressivo = "00".$progressivo;
			}elseif( $progressivo >= 10000 && $progressivo < 100000 ){
				$progressivo = "0".$progressivo;
			}
			

			

			
			//creo il nome del file
			$nome_file = 'ORDINI.'.$progressivo.".txt";
			$path = $this->path.$nome_file;
			unlink($path);
			//debugga($path);exit;
			//creo la testata dell'ordine
			
			$datastring = '';
			$testata = $this->crea_testata($carrello);
			$datastring .= $testata."\n";
			//$res = file_put_contents($path, $testata."\n",FILE_APPEND);
			
			$ordini = $database->select('*','cartRow',"cart={$carrello['id']} order by sku");
			if( okArray($ordini) ){
				foreach($ordini as $ordine){
					//creo  il dettaglio dell'ordine
					$ordine['progressivo'] = $carrello['progressivo'];
					//if( $carrello['user'] == 2356 || $carrello['user'] == 1457 ){
						$dettaglio = $this->crea_dettaglio2($ordine);
					/*}else{
						$dettaglio = $this->crea_dettaglio($ordine);
					}*/
					
					//debugga($dettaglio);exit;
					if( !$dettaglio ){
						$check_import = false;
						break;
					}
					//debugga($dettaglio);exit;
					//file_put_contents($path, $dettaglio."\n",FILE_APPEND);

					$datastring .= $dettaglio."\n";
				}
			}else{
				exit;
			}
			//creo le note dell'ordine
			$note = $this->crea_note($carrello);
			$datastring .= $note."\n";
			
			if( $check_import ){
				file_put_contents($path, $datastring,FILE_APPEND);
				
				$database->update('cart',"id = {$carrello['id']}",
					
						[
							'esportato' => 1,
							'tracciato' => $path,
							'progressivo' => $carrello['progressivo']

						]
							
				);
			}
			
			
		}

		debugga($carrelli);exit;





		




		



		
	}

	function crea_testata($carrello){
			$tracciato = $this->getTracciato('testata');
			
			uasort($tracciato,function($a,$b){
				if ($a[0]==$b[0]) return 0;
				return ($a[0]<$b[0])?-1:1;	
			});
			
			
			foreach($tracciato as $k => $v){
				switch($k){
					case 'NumeroOrdineWeb':
						$val = $this->aggiungi_zeri($carrello['progressivo'],$v[2]);
						break;
					case 'DataOrdineWeb':
						$val = date('Ymd',strtotime($carrello['evacuationDate']));
						break;
					case 'CodiceCliente':
						$val = $carrello['codice_cliente'];
						break;
					case 'CodiceClienteContabile':
						$val = $carrello['codice_destinazione'];
						break;
					default:
						$val = $v['4'];
						break;
				 }
				 $tracciato[$k]['5'] = $val;
			}
			
			$toreturn = '';
			foreach($tracciato as $k => $v){
				if( strlen($v[5]) > $v[2] ){
					$v[5] = substr($v[5],0,$v[2]);
				}
				$toreturn .=  str_pad($v[5],$v[2]);
			}
			return $toreturn;
		}
	
	function crea_dettaglio2($ordine,$ordine_omaggio=false){
		$tracciato = $this->getTracciato('dettaglio');
		//debugga($tracciato);exit;
		uasort($tracciato,function($a,$b){
			if ($a[0]==$b[0]) return 0;
			return ($a[0]<$b[0])?-1:1;	
		});
		

		$decimali_tracciato['Quantita'] = 3;
		$decimali_tracciato['QuantitaOmaggio'] = 3;
		$decimali_tracciato['PrezzoUnitario'] = 5;
		
		$dati = unserialize($ordine['custom1']);
		$omaggio = 0;
		if( $dati['quantita_omaggio'] > 0 ){
			$multiplo = (int)($ordine['quantity']/$dati['quantita_totale']);
			$omaggio = $dati['quantita_omaggio']*$multiplo;
		}
		
		$database = _obj('Database');
		$product_info = $database->select('sku,percentage as iva',"product as p left outer join (tax as t join product_shop_values as s on s.id_tax = t.id) on p.id=s.id_product","p.id={$ordine['product']}");
		if( okArray($product_info) ){
			$product_info = $product_info[0];
		}else{
			return false;
		}
		
		foreach($tracciato as $k => $v){
			switch($k){
				case 'NumeroOrdineWeb':
					$val = $this->aggiungi_zeri($ordine['progressivo'],$v[2]);
					break;
				case 'Quantita':
					$val = $this->aggiungi_zeri2($ordine['quantity'],$v[2],$decimali_tracciato[$k]);
					break;
				case 'CodiceArticolo':
					$val = $product_info['sku'];
					break;
				case 'Promo1':
					$val = $dati['campagna'];
					break;
				case 'PrezzoUnitario':
					$val = $this->aggiungi_zeri2($dati['prezzo_base'],$v[2],$decimali_tracciato[$k]);
					break;
				case 'AliquotaIva':
					$val = $product_info['iva']; // DA INTEGRARE LA GESTIONE DELL?IVA NELL?IMPORTAZIONE
					break;
				case 'CausaleMovimento':
					$val = $ordine_omaggio?'OM':'';
					break;
				case 'QuantitaOmaggio':
					$val =  $this->aggiungi_zeri2(0,$v[2],$decimali_tracciato[$k]);
					break;
				default:
					if( preg_match('/Sconto/',$k)){
						$val = $dati[strtolower($k)];
						$val = $this->aggiungi_zeri2($val,5,2);
					}else{
						$val = $v['4'];
					}
					break;
			 }
			 $tracciato[$k]['5'] = $val;
		}
		
		$toreturn = '';
		foreach($tracciato as $k => $v){
			if( strlen($v[5]) > $v[2] ){
				$v[5] = substr($v[5],0,$v[2]);
			}
			$toreturn .=  str_pad($v[5],$v[2]);
		}
		if( !$ordine_omaggio && $omaggio > 0){
			
			$ordine['quantity'] = $omaggio;
			$toreturn .= "\n".$this->crea_dettaglio2($ordine,true);
		}
		//debugga($toreturn);exit;
		return $toreturn;
	}
	
	function crea_dettaglio($ordine){
		$tracciato = $this->getTracciato('dettaglio');
		//debugga($tracciato);exit;
		uasort($tracciato,function($a,$b){
			if ($a[0]==$b[0]) return 0;
			return ($a[0]<$b[0])?-1:1;	
		});

		$decimali_tracciato['Quantita'] = 3;
		$decimali_tracciato['QuantitaOmaggio'] = 3;
		$decimali_tracciato['PrezzoUnitario'] = 5;
		
		$dati = unserialize($ordine['custom1']);
		$database = _obj('Database');
		$product_info = $database->select('sku,percentage as iva',"product as p left outer join (tax as t join product_shop_values as s on s.id_tax = t.id) on p.id=s.id_product","p.id={$ordine['product']}");
		if( okArray($product_info) ){
			$product_info = $product_info[0];
		}else{
			return false;
		}
		
		foreach($tracciato as $k => $v){
			switch($k){
				case 'NumeroOrdineWeb':
					$val = $this->aggiungi_zeri($ordine['progressivo'],$v[2]);
					break;
				case 'Quantita':
					$val = $this->aggiungi_zeri2($ordine['quantity']+$dati['quantita_omaggio'],$v[2],$decimali_tracciato[$k]);
					break;
				case 'CodiceArticolo':
					$val = $product_info['sku'];
					break;
				case 'Promo1':
					$val = $dati['campagna'];
					break;
				case 'PrezzoUnitario':
					$val = $this->aggiungi_zeri2($dati['prezzo_base'],$v[2],$decimali_tracciato[$k]);
					break;
				case 'AliquotaIva':
					$val = $product_info['iva']; // DA INTEGRARE LA GESTIONE DELL?IVA NELL?IMPORTAZIONE
					break;
				case 'QuantitaOmaggio':
					$val =  $this->aggiungi_zeri2($dati['quantita_omaggio'],$v[2],$decimali_tracciato[$k]);
					break;
				default:
					if( preg_match('/Sconto/',$k)){
						$val = $dati[strtolower($k)];
						$val = $this->aggiungi_zeri2($val,5,2);
					}else{
						$val = $v['4'];
					}
					break;
			 }
			 $tracciato[$k]['5'] = $val;
		}
		
		$toreturn = '';
		foreach($tracciato as $k => $v){
			if( strlen($v[5]) > $v[2] ){
				$v[5] = substr($v[5],0,$v[2]);
			}
			$toreturn .=  str_pad($v[5],$v[2]);
		}

		//debugga($toreturn);exit;
		return $toreturn;
	}


	
	function crea_note($carrello){
			$tracciato = $this->getTracciato('note');
			
			uasort($tracciato,function($a,$b){
				if ($a[0]==$b[0]) return 0;
				return ($a[0]<$b[0])?-1:1;	
			});
			//debugga($tracciato);exit;
			
			foreach($tracciato as $k => $v){
				switch($k){
					case 'NumeroOrdineWeb':
						$val = $this->aggiungi_zeri($carrello['progressivo'],$v[2]);
						break;
					case 'Telefono1':
						$val = $carrello['phone'];
						break;
					case 'Telefono2':
						$val = $carrello['cellular'];
						break;
					case 'Note':
						$val = $carrello['note'];
						break;
					default:
						$val = $v['4'];
						break;
				 }
				 $tracciato[$k]['5'] = $val;
			}
			
			$toreturn = '';
			foreach($tracciato as $k => $v){
				if( strlen($v[5]) > $v[2] ){
					$v[5] = substr($v[5],0,$v[2]);
				}
				$toreturn .=  str_pad($v[5],$v[2]);
			}

			//debugga($toreturn);exit;
			return $toreturn;
		}

	function aggiungi_zeri($val,$lung,$converti=false){
		if( $converti ){
			
			$val = number_format($val,2,'.','.')*100;
			
		}
		

		$val = "".$val;
		
		if( strlen($val) < $lung ){
			$val2 = '';
			$max = ($lung-strlen($val));
			for( $i =0; $i<$max; $i++ ){
				$val2 .= "0";
			}
			$val2 .= $val;

			return $val2;
		}
		return $val;
	}

	function aggiungi_zeri2($val,$lung,$decimali=false){
		$tmp = $val;
		
		if( $decimali ){
			if( preg_match('.',$val) ){
				$val = number_format($val,$decimali,'.','');
			}
			$val = $val*pow(10,$decimali);
			

		}
		
		
		$val = "".$val;
		
		if( strlen($val) < $lung ){
			$val2 = '';
			$max = ($lung-strlen($val));
			for( $i =0; $i<$max; $i++ ){
				$val2 .= "0";
			}
			$val2 .= $val;

			return $val2;
		}

		return $val;
	}


	function getTracciato($tipo){
		$tracciati = array(
			'testata' => array(
				'TipoRiga' => array(1,1,1,'t',"T"), 

				'NumeroOrdineWeb' => array(2,11,10,'t'), 

				'DataOrdineWeb' => array(12,19,8,'t'), 

				'CodiceAgente' => array(20,29,10), 

				'CodiceCapoarea' => array(30,39,10), 

				'CodiceCliente' => array(40,59,20), 

				'CodiceClienteContabile' => array(60,79,20),

				'NumeroOrdineCliente' => array(80,89,10), 

				'DataOrdineCliente' => array(90,97,8), 

				'NumeroOrdineAgente' => array(98,107,10), 

				'DataOrdineAgente' => array(108,115,8), 

				'NettoMerce' => array(116,125,10), 

				'ImpostaFabbricazione' => array(126,135,10), 

				'ImpostaContrassegno' => array(136,145,10), 

				'Iva' => array(146,155,10), 

				'TotaleImporto' => array(156,165,10), 

				'ImportoOmaggio' => array(166,175,10), 

				'ImportoPagamento' => array(176,185,10), 

				'DataPagamento' => array(186,193,8), 

				'EstremiTitolo' => array(194,243,50), 

				'TipoConsegna' => array(244,246,3), 

				'DataConsegna' => array(247,254,8), 

				'CodiceReparto' => array(255,279,25), 

				'ModalitaPagamento' => array(280,282,3), 

				'ModalitaSpedizione' => array(283,285,3), 

				'CausaleTrasporto' => array(286,288,3), 

				'ScontoTestata' => array(289,293,5), 

				'ImposteTestata' => array(294,303,10), 

				'Provenienza' => array(304,304,1), 
			),
		   'dettaglio' => array(
				'TipoRiga' => array(1,1,1,'t','D'), 

				'NumeroOrdineWeb' => array(2,11,10,'t'), 

				'CodiceArticolo' => array(12,31,20,'t'), 
				
				'UnitaMisura' => array(32,34,3), 

				'CausaleMovimento' => array(35,37,3,'t'), 

				'Quantita' => array(38,50,13,'t'), 

				'QuantitaOmaggio' => array(51,63,13), 

				'PrezzoUnitario' => array(64,76,13,'t'), 

				'Abbuono' => array(77,89,13), 

				'ImpostaFabbricazione' => array(90,104,15), 

				'ImpostaContrassegno' => array(105,119,15),

				'AliquotaIva' => array(120,121,2), 

				'TotaleRiga' => array(122,134,13), 

				'ValoreOmaggio' => array(135,147,13), 

				'Sconto1' => array(148,152,5), 

				'Sconto2' => array(153,157,5), 

				'Sconto3' => array(158,162,5), 

				'Sconto4' => array(163,167,5), 

				'Sconto5' => array(168,172,5), 

				'Promo1' => array(173,179,7), 

				'Promo2' => array(180,186,7), 

				'Promo3' => array(187,193,7), 

				'Promo4' => array(194,200,7), 

				'Promo5' => array(201,207,7), 

				'Note' => array(208,462,255), 

				'FlagModificata' => array(463,463,1), 

			),

			 'note' => array(
				'TipoRiga' => array(1,1,1,'t','N'), 

				'NumeroOrdineWeb' => array(2,11,10), 

				'TurnoChiusura' => array(12,14,3), 

				'GiornoConsegna' => array(15,17,3), 

				'OraConsegna' => array(18,20,3), 

				'Telefono1' => array(21,40,20), 

				'Telefono2' => array(41,60,20),

				'Note' => array(61,315,255), 

				'NoteCorriere' => array(316,570,570), 

			),
		);
			
		return $tracciati[$tipo];

	}


	function getProgressivo(){
		$database = _obj('Database');
		$data = $database->select('max(progressivo) as max',"cart","1=1");
		return $data[0]['max']+1;
	}
}

?>