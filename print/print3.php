<?php
session_start();
date_default_timezone_set('America/Lima');	

require __DIR__ . '/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\ImagickEscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;



$ArraySubTotales=$_POST['ArraySubTotales'];
$ArrayItem=$_POST['ArrayItem'];
$ArrayEnca=$_POST['Array_enca'];
$xArray_print=$_POST['Array_print'];
$ip_printer=$xArray_print[0]['ip_print'];
$printImg64=$xArray_print[0]['img64']; // si imprime el logo en 64btis

//////*** para la estructura */
// $data = $_POST['data'];
// $ArraySubTotales=$data['ArraySubTotales'];
// $ArrayItem=$data['ArrayItem'];
// $ArrayEnca=$data['Array_enca'];
// $xArray_print=$data['Array_print'];
// $ip_printer=$xArray_print[0]['ip_print'];
// $printImg64=$xArray_print[0]['img64']; // si imprime el logo en 64btis
///// ***


// verifica si imprime una copia en la impresora local
if (array_key_exists('copia_local', $xArray_print[0])){
	if ($xArray_print[0]['local'] === "1" && $xArray_print[0]['copia_local'] === "0" ) {
		return;
	}
}

if($ip_printer===''){return;}
try {
	//por ip o por usb
	$impresora_print=$ip_printer;
	$pos_print=strrpos($impresora_print,'//');
	if($pos_print===false){
		$connector = new NetworkPrintConnector($impresora_print);
	}else{
		$impresora_print='smb:'.$impresora_print;
		$connector = new WindowsPrintConnector($impresora_print);
	}
	
	$printer = new Printer($connector);	
} catch (Exception $e) {
	print 'Error, Verifique que la ticketera este prendida y que tenga papel.';
	return;
}


// $imLogo = "logo.png";

$imLogo="./logo/".$xArray_print[0]['logo'];

$num_mesa=$ArrayEnca['m'];
$num_pedido=$ArrayEnca['num_pedido'];
$correlativo_dia=$ArrayEnca['correlativo_dia'];
$referencia=$ArrayEnca['r'];
$reservar=$ArrayEnca['reservar'];
$solo_llevar=$ArrayEnca['solo_llevar'];
$EsDelivery=$ArrayEnca['delivery'];
$nom_us=$ArrayEnca['nom_us'];
$pre_cuenta=!empty($ArrayEnca['precuenta']) ? $ArrayEnca['precuenta'] : '';
$logo_solo_llevar="_ico_solo_llevar2.png";
$logo_delivery = "_ico_delivery.png";

$nom_us=explode(' ',$_SESSION['nomUs']);

$fecha_actual=date('d').'/'.date('m').'/'.date('y');
$hora_actual=date('H').':'.date('i').':'.date('s');
$sum_total=0;
$num_copias=(int)$xArray_print[0]['num_copias'];
$cuenta_copias=0;

//configuracion de la impresora //margen font
$var_margen_iz=(int)$xArray_print[0]['var_margen_iz'];
$var_size_font=(int)$xArray_print[0]['var_size_font'];
$var_margen_iz=intLowHigh($var_margen_iz, 2);
$local = (int)$xArray_print[0]['local'] || 0;
//			

/// tamaño de letra de la comanda
$val_size_font_comanda_tall = array_key_exists('var_size_font_tall_comanda', $xArray_print[0]) ? $xArray_print[0]['var_size_font_tall_comanda'] : "0";
$size_font_comanda_tall = (int)$val_size_font_comanda_tall===0 ? false : true;

$val_size_font_comanda_tall_rigth = (int)$val_size_font_comanda_tall;
$val_size_font_comanda_tall++; //tamaño de letra,1+1>2 2+1>3


// tamaño de papel
// 0 = 80mm 1 = 58mm
$papel_size = (int)$xArray_print[0]['papel_size'];

// lineas hr - divisor
$linea_hr = '';
$linea_titulo = '';
$espacioAlFinal = false; // en impresoras de 58- 57mm  no aparece el ultimo texto 
$GLOBALS['leftCols'] = 38;
$GLOBALS['leftColsSubItem'] = 54; // la letra es mas pequeña
switch ($papel_size) {
	case '0': // 80mm
		$linea_hr = "------------------------------------------------\n";
		$linea_titulo = '******';
		$GLOBALS['leftCols'] = 38;
		$GLOBALS['leftColsSubItem'] = 54;
		break;
	case '1': // 58mm
		$linea_hr = "------------------------------------------\n";
		$linea_titulo = '***';
		$GLOBALS['leftCols'] = 32;
		$GLOBALS['leftColsSubItem'] = 48;
		$espacioAlFinal = true;
		break;	
}

$connector->write(Printer::GS.'L'.$var_margen_iz);			
$printer -> setFont($var_size_font);
//---------------////////////////

if($num_mesa=='' || $num_mesa=='00'){$num_mesa='Pedido: '.$correlativo_dia;}else{$num_mesa='MESA: '.$num_mesa;}

$precio='';

while($num_copias>=0){
	// icono delivery
	if ($EsDelivery==1) {
		$_logo_delivery = EscposImage::load($logo_delivery, false);
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> bitImage($_logo_delivery);
		$printer -> feed();
	}
	//icono solo llevar
	if($solo_llevar==1){
		$logoLlevar = EscposImage::load($logo_solo_llevar, false);
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> bitImage($logoLlevar);
		$printer -> feed();
	}
	//reservar
	if($reservar==true){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		//$printer -> selectPrintMode(Printer::MODE_UNDERLINE);		
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_WIDTH);
		$printer -> text("RESERVAR\n");
		$printer -> selectPrintMode();
	}

	// si es impresora local y es pedido
	if ($local>0 && $pre_cuenta!=true) {
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text($linea_titulo." COPIA ".$linea_titulo."\n");
		$printer -> selectPrintMode();	
	}

	if($cuenta_copias>0 && $pre_cuenta!=true){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text($linea_titulo." COPIA ".$linea_titulo."\n");
		$printer -> selectPrintMode();
	}

	if($pre_cuenta==true){		
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);		
		$printer -> text($linea_titulo." PRE-CUENTA ".$linea_titulo."\n");
		$printer -> selectPrintMode();
	}
	
	/*num pedido*/
	$printer -> selectPrintMode();	
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> setEmphasis(true);
	$printer -> text('CO-'.$correlativo_dia."\n");
	$printer -> setEmphasis(false);
	
	/* Print top logo */
	if($imLogo!=''){
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$logo = EscposImage::load($imLogo, false);
		$printer -> bitImage($logo);
	}

	/* ENCABEZADO */
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	if ( $size_font_comanda_tall === true ) {
		$printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
		$printer -> setTextSize($val_size_font_comanda_tall, $val_size_font_comanda_tall_rigth);	
	}

	$printer -> text($num_mesa."\n");
	$printer -> selectPrintMode();
	$printer -> text($linea_hr);		
	if ( $referencia!="" ){
		$printer -> text($referencia."\n");		
	}
	$printer -> feed();

	// si es deliver y si viene datos adjuntos (direccion, telefono, paga con)
	if ($EsDelivery==1) {
		if (array_key_exists('arrDatosDelivery', $ArrayEnca) ) {
			$arrDatosDelivery=$ArrayEnca['arrDatosDelivery'];
			$printer -> setJustification(Printer::JUSTIFY_LEFT);
			$printer -> setEmphasis(true);						
			$printer -> text("DATOS ADJUNTOS DEL DELIVERY:"."\n");			
			$printer -> selectPrintMode();	
			$printer -> text("Nombre: ".$arrDatosDelivery['nombre']."\n");
			$printer -> text("Direccion: ".$arrDatosDelivery['direccion']."\n");
			$printer -> text("Telefono: ".$arrDatosDelivery['telefono']."\n");
			$printer -> text("Forma pago: ".$arrDatosDelivery['paga_con']."\n");
			$printer -> text($linea_hr);
			$printer -> feed();
		}
	}

	/* CUERPO , ITEMS*/
	$si_tiene_item=0;
	$cuenta_row=0;
	$cuenta_tpc=0;
	$tipo_consumo='';
	$indicaciones_item='';
	$des_part2='';
	$sum_total=0;

	foreach ($ArrayItem as $item) {
		if($item==null){continue;}

		$tipo_consumo=$item["des"];
		
		$si_tiene_item=0;
		$cuenta_row=0;
		$seccion='';
		$des_part2='';
		//$sum_total=0;
		usort($item, "cmp");

		//ordena por donde procede 0 de la carta(sigue el orden) !=0 bodega
		foreach ($item as $key => $row) {
		    $procede[$key]  = !empty($row['procede_index']) ? $row['procede_index'] : '';
			// $or_des_seccion[$key]  = !empty($row['des_seccion']) ? $row['des_seccion'] : '';
			$or_des_seccion[$key]  = !empty($row['sec_orden']) ? $row['sec_orden'] : ''; 
		}
		//array_multisort($procede, SORT_ASC,$or_des_seccion, SORT_ASC, $item);
		array_multisort($procede, SORT_ASC, SORT_NUMERIC,
						$or_des_seccion, SORT_ASC, SORT_NUMERIC,
						$item, SORT_ASC, SORT_LOCALE_STRING);
		
		// print_r($item);
		
		foreach ($item as $subitem) {
			if(is_array($subitem)==false){continue;}
			if($subitem['cantidad']==0){continue;}			
			
			if ( $size_font_comanda_tall === true ) {
				$printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
				$printer -> setTextSize($val_size_font_comanda_tall, $val_size_font_comanda_tall_rigth);	
			}
			
			/*titulo tipo consumo*/			
			if($si_tiene_item==0){			
				$printer -> setJustification(Printer::JUSTIFY_CENTER);								
				$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
				$printer -> selectPrintMode(Printer::MODE_UNDERLINE);
				$printer -> setEmphasis(true);			
				if($cuenta_tpc>0){$printer -> text("\n\n");}				
				$printer -> text("*** ".$tipo_consumo." ***\n");
				//$printer -> text($linea_hr);
				$printer -> setEmphasis(false);
				$printer -> selectPrintMode();
				$cuenta_tpc++;
			}


			$si_tiene_item=1;

			$printer -> setJustification(Printer::JUSTIFY_LEFT);
			$printer -> setEmphasis(true);	
			
			if ( $size_font_comanda_tall === true ) {
				$printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
				$printer -> setTextSize($val_size_font_comanda_tall, $val_size_font_comanda_tall_rigth);	
			}
			
			if($seccion!=$subitem["des_seccion"]){			
				if($cuenta_row>0){$printer -> text("\n");}	
				$seccion=$subitem["des_seccion"];				
				$printer -> text($seccion."\n");
				$printer -> text($linea_hr);	
				$printer -> setEmphasis(false);
				$cuenta_row++;
			}		
			
			$printer -> setEmphasis(false);
			$precio=$subitem["precio_print"];
			$indicaciones_item=$subitem["indicaciones"];
			if($indicaciones_item!=''){$indicaciones_item='('.$indicaciones_item.')';}
			if($precio==''){$precio=$subitem["precio_total"];}
			$r_subitem=$numeroConCeros = str_pad($subitem["cantidad"], 2, "0", STR_PAD_LEFT).' '.$subitem["des"].$indicaciones_item;
			$des_part2='';
			$des_part3='';
			if(strlen($r_subitem) > 35){

				// para que corte en el ultimo espacio en blanco
				// $pos_last_space =  $strrpos


				$des_part2='   '.substr($r_subitem,35,strlen($r_subitem));
				$r_subitem=substr($r_subitem,0,35)."-";			
			}
			if(strlen($des_part2) > 35){
				$des_part3='   '.substr($des_part2,35,strlen($des_part2));
				$des_part2=substr($des_part2,0,35)."-";			
			}
			//$r_subitem = strlen($r_subitem) > 35 ? substr($r_subitem,0,35)."..." : $r_subitem;

			$sum_total=(float)$sum_total+(float)$precio;
		
			$printer -> text(new item($r_subitem, $precio));			
			if($des_part2!=''){$printer -> text(new item($des_part2, ''));}
			if($des_part3!=''){$printer -> text(new item($des_part3, ''));}

			// ITEM-SUBITEMS ----------->
			$ListSubItem = array_key_exists('subitems_view', $subitem) ? $subitem['subitems_view'] : null;

			if ( $ListSubItem && count($ListSubItem) > 0) {
				$printer -> setFont(Printer::FONT_B);
				foreach ($ListSubItem as $sub) {
					$indicaciones_item_sub=$sub["indicaciones"];
					if($indicaciones_item_sub!=''){$indicaciones_item_sub='('.$indicaciones_item_sub.')';}

					$des_sub_item = str_pad($sub['cantidad_seleccionada'], 2, "0", STR_PAD_LEFT).' '.$sub['des'].$indicaciones_item_sub;
					$precio_sub_item = $sub['precio'] === '0' ? '.' : '+'. number_format((float)$sub['precio'], 2, '.', '');

					$des_sub_item_p2 = '';
					$des_sub_item_p3 = '';
					
					if(strlen($des_sub_item) > 48){
						$des_sub_item_p2='   '.substr($des_sub_item,48,strlen($des_sub_item));
						$des_sub_item=substr($des_sub_item,0,48)."-";
					}

					if(strlen($des_sub_item_p2) > 48){
						$des_sub_item_p3='   '.substr($des_sub_item_p2,48,strlen($des_sub_item_p2));
						$des_sub_item_p2=substr($des_sub_item_p2,0,48)."-";
					}

					$printer -> text(new item_subitem($des_sub_item, $precio_sub_item));			
					if($des_sub_item_p2!=''){$printer -> text(new item_subitem($des_sub_item_p2, ''));}
					if($des_sub_item_p3!=''){$printer -> text(new item_subitem($des_sub_item_p3, ''));}
					// $printer -> text(new item_subitem(strtolower($des_sub_item), $precio_sub_item));
				}
				$printer -> text(new item_subitem('....', ''));
				// $printer -> feed();
				$printer -> setFont(Printer::FONT_A);
			}

			// ITEM-SUBITEMS -----------<

			$printer -> selectPrintMode();
			// $printer -> setTextSize(1, 1);
		}	
	}

	/* TOTALES */
	$printer -> feed();
	$printer -> text($linea_hr);
	$printer -> setEmphasis(true);
	$r_subt_t=0;	
	$importeTotal=0;	
	
	foreach ($ArraySubTotales as $item_sbt) {//
		if($item_sbt['visible']=='false'){continue;}
		if($item_sbt['tachado']=='true'){continue;}
		// if($item_sbt['importe']=='0.00'){continue;} // caso de igv si es 0 no muestra
		
		$des_sbt=$item_sbt['descripcion'];//
		$imp_sbt=$item_sbt['importe'];//

		if(strtoupper($des_sbt)==strtoupper('Total')){
			$importeTotal = $imp_sbt;
			$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
			$printer -> text(new item($des_sbt, $imp_sbt, true));
			$printer -> selectPrintMode();
			continue;			
		}
		
		$printer -> text(new item($des_sbt, $imp_sbt));		
	}
	//
	/* CODEBAR */
	$printer -> feed();
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer->setBarcodeHeight(45);
	$printer->setBarcodeWidth(2);	
	$valCodBar = (int)$correlativo_dia."%".(float)$importeTotal;	
	$printer->barcode($valCodBar);

	/* PIE DE PAGINA */	
	$pie_pagina = $xArray_print[0]['pie_pagina'];
	if ( $pie_pagina != '' ){
		$printer -> feed();
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> text($xArray_print[0]['pie_pagina']."\n");
	}
	$printer -> feed();

	$printer -> text("Atendido por:".$nom_us[0]."\n");
	$printer -> text($fecha_actual.' | '.$hora_actual. "\n");

	$printer -> text("www.papaya.com.pe\n");
	if ( $espacioAlFinal ) { $printer -> text("\n\n"); }
	$printer -> feed(2);
	
	$printer -> cut();
	$printer -> pulse();

	$num_copias--;
	$cuenta_copias++;
}

$printer -> close();

class item
{
    private $name;
    private $price;
    private $dollarSign;

    public function __construct($name = '', $price = '', $dollarSign = false)
    {
        $this -> name = $name;
        $this -> price = $price;
        $this -> dollarSign = $dollarSign;
    }

    public function __toString()
    {
        $rightCols = 10;
        $leftCols = $GLOBALS['leftCols'];
        if ($this -> dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad($this -> name, $leftCols) ;

        $sign = ($this -> dollarSign ? 'S/.' : '');
        $right = str_pad($sign . $this -> price, $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }
}

class item_subitem
{
    private $name;
    private $price;
    private $dollarSign;

    public function __construct($name = '', $price = '', $dollarSign = false)
    {
        $this -> name = $name;
        $this -> price = $price;
        $this -> dollarSign = $dollarSign;
    }

    public function __toString()
    {				
		$rightCols =  10;
				
		if ( strlen($this -> price) === 0 ) {		
			$rightCols =  0;
		}


        $leftCols = $GLOBALS['leftColsSubItem'];
        if ($this -> dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
		}

        $left = str_pad('    '.$this -> name, $leftCols) ;

        $sign = ($this -> dollarSign ? 'S/. ' : '');
        $right = str_pad($sign . $this -> price, $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }
}

function cmp($a, $b)
{
	if(is_array($a)==false){return;}
    return strcmp($a["des_seccion"], $b["des_seccion"]);
}
function cmp_procede($a, $b)
{
	if(is_array($a)==false){return;}
    return strcmp($a["procede"], $b["procede"]);
}


function intLowHigh($input, $length)
{
    // Function to encode a number as two bytes. This is straight out of Mike42\Escpos\Printer
    $outp = "";
    for ($i = 0; $i < $length; $i++) {
        $outp .= chr($input % 250);
        $input = (int)($input / 250);
    }
    return $outp;
}
?>