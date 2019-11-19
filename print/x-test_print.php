<?php	
// session_start();
date_default_timezone_set('America/Lima');	
require __DIR__ . '/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;	
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

	
$ArrayEnca=$_POST['Array_enca'];
$ip_printer=$ArrayEnca['ip_print'];
$logo_post="./logo/".$ArrayEnca['logo'];
$var_size_font=(int)$ArrayEnca['size_font'];
$var_margen_iz=intLowHigh((int)$ArrayEnca['margen_iz'], 2);
/// tamaño de letra de la comanda
$val_size_font_comanda_tall = array_key_exists('var_size_font_tall_comanda', $ArrayEnca) ? $ArrayEnca['var_size_font_tall_comanda'] : "0";
// $size_font_comanda_tall = $val_size_font_comanda_tall == "0" ? false : true;

$size_font_comanda_tall = (int)$val_size_font_comanda_tall===0 ? false : true;
$val_size_font_comanda_tall++; //tamaño de letra,1+1>2 2+1>3

// $printImg64=$ArrayEnca['img64']; // si imprime el logo en 64btis

$fecha_actual=date('d').'/'.date('m').'/'.date('y');
$hora_actual=date('H').':'.date('i').':'.date('s');
try {
	//por ip o por usb
	$impresora_print=$ip_printer;	
	$pos_print=strrpos($ip_printer,'//');		
	if($pos_print===false){
		$connector = new NetworkPrintConnector($impresora_print);
	}else{						
		$impresora_print="smb:".$impresora_print;		
		$connector = new WindowsPrintConnector($impresora_print);
	}
	
	$printer = new Printer($connector);
} catch (Exception $e) {
	print 'Error, Verifique que la ticketera este prendida y que tenga papel.';		
	return;
}

$printer -> text('MESA 01'."\n");


// $connector->write(Printer::GS.'L'.$var_margen_iz);
// $printer -> setFont($var_size_font);


// tamaño de papel
// 0 = 80mm 1 = 58mm
$papel_size = (int)$ArrayEnca['papel_size'];

// lineas hr - divisor
$linea_hr = '';
$espacioAlFinal = false; // en impresoras de 58- 57mm  no aparece el ultimo texto 
$espacioLeftCols = 32;
$GLOBALS['leftCols'] = 38;
$GLOBALS['leftColsSubItem'] = 54; // la letra es mas pequeña
switch ($papel_size) {
	case '0': // 80mm
		$linea_hr = "------------------------------------------------\n";
		$GLOBALS['leftCols'] = 38;
		$GLOBALS['leftColsSubItem'] = 54;
		$espacioAlFinal = false;
		break;
	case '1': // 58mm
		$linea_hr = "------------------------------------------\n";
		$GLOBALS['leftCols'] = 32;
		$GLOBALS['leftColsSubItem'] = 48;
		$espacioAlFinal = true;
		break;	
}

$connector->write(Printer::GS.'L'.$var_margen_iz);			
$printer -> setFont($var_size_font);

if($logo_post!=''){
	// $logo = EscposImage::load($logo_post, false);
	// $printer -> graphics($logo);
	
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$logo = EscposImage::load($logo_post, false);
	$printer -> bitImage($logo);	
}	
/* ENCABEZADO */	
	if ($espacioAlFinal) {$printer -> feed();}
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text('MESA 01'."\n");
	$printer -> selectPrintMode();
	$printer -> text($linea_hr);
	$printer -> text('Referencia del pedido ej: Sr. Perez'."\n");
	$printer -> feed();

	$printer -> setJustification(Printer::JUSTIFY_CENTER);								
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> selectPrintMode(Printer::MODE_UNDERLINE);
	$printer -> setEmphasis(true);				
	$printer -> text("*** CONSUMIR EN EL LOCAL ***\n");	
	$printer -> setEmphasis(false);
	$printer -> selectPrintMode();

	//SECCION 1
	if ( $size_font_comanda_tall ) {
		$printer -> selectPrintMode(Printer::MODE_EMPHASIZED);
		$printer -> setTextSize($val_size_font_comanda_tall, 1);	
	}

	// $printer -> setJustification(Printer::JUSTIFY_LEFT);
	// $printer -> setEmphasis(true);				
	// $printer -> text('ENTRADAS'."\n");
	// $printer -> text($linea_hr);	
	// $printer -> setEmphasis(false);

	// //destalles
	// $printer -> setEmphasis(false);
	// $printer -> text(new item('01 ARROZ CON LECHE', '2.00'));
	// $printer -> text(new item('01 ENSALADA FRESCA', '2.00'));
	// $printer -> text(new item('01 CAUSA RELLENA', '5.00'));
	// $printer -> feed();


	//SECCION 2
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> setEmphasis(true);				
	$printer -> text('PLATOS DE FONDO'."\n");
	$printer -> text($linea_hr);	
	$printer -> setEmphasis(false);

	//destalles
	$printer -> setEmphasis(false);
	$printer -> text(new item('01 AJI DE GALLINA BIEN GUISADA', '10.00'));

		// item-subitems
		// $printer -> setFont(Printer::FONT_B);		
		
		$printer -> setEmphasis(false);
		// sub item len > 50
		$des_sub_item = '-> 1 Entre pierna, Papas fritas, Bien cocido, Aji, Aji especial de casa, Mayonesa, Mostaza';
		$des_sub_item_p2 = '';
		$des_sub_item_p3 = '';
		
		if(strlen($des_sub_item) > $espacioLeftCols){
			$des_sub_item_p2=substr($des_sub_item,$espacioLeftCols,strlen($des_sub_item));
			$des_sub_item=substr($des_sub_item,0,$espacioLeftCols)."-";
		}

		if(strlen($des_sub_item_p2) > $espacioLeftCols){
			$des_sub_item_p3=substr($des_sub_item_p2,$espacioLeftCols,strlen($des_sub_item_p2));
			$des_sub_item_p2=substr($des_sub_item_p2,0,$espacioLeftCols)."-";
		}

		$printer -> text(new item_subitem($des_sub_item, '+2.00'));			
		if($des_sub_item_p2!=''){$printer -> text(new item_subitem($des_sub_item_p2, ''));}
		if($des_sub_item_p3!=''){$printer -> text(new item_subitem($des_sub_item_p3, ''));}

		///
		
		$printer -> text(new item_subitem('-> 02 entrepierna solo cremas mas', ''));		
		$printer -> text(new item_subitem('-> 02 pecho especial', '+5.00'));
		// $printer -> text("\n");
		// $printer -> text(new item_subitem('..', ''));
		$printer -> feed();
		// $printer -> setFont(Printer::FONT_A);
		

	$printer -> text(new item('01 ARROZ CON MARISCOS', '10.00'));
	$printer -> text(new item('01 LOMITO SALTADO', '10.00'));
	
	/* TOTALES */
	$printer -> feed();
	$printer -> text($linea_hr);
	$printer -> setEmphasis(true);
	
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);	
	$printer -> text(new item('TOTAL', '39.00', true));
	$printer -> selectPrintMode();

	/* CODIGO QR */
	$testStr = 'CodigoQREjemplo';
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> qrCode($testStr, Printer::QR_ECLEVEL_H, 5);	
	$printer -> feed();

	/* PIE DE PAGINA */	
	$printer -> feed(2);
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> text($ArrayEnca['pie_pagina']."\n");
	$printer -> feed(2);
	$printer -> text("Atendido por: PEDRO \n");
	$printer -> text($fecha_actual.' | '.$hora_actual. "\n");		
	$printer -> text("www.papaya.com.pe\n");
	if ( $espacioAlFinal ) { $printer -> text("\n\n"); }
	$printer -> feed(2);

	$printer -> cut();
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

        $sign = ($this -> dollarSign ? 'S/. ' : '');
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
		$rightCols = 10;
        $leftCols = $GLOBALS['leftCols'];
				
		if ( strlen($this -> price) === 0 ) {		
			$rightCols =  0;
		}



        // $leftCols = $GLOBALS['leftColsSubItem'];
        if ($this -> dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
		}

        $left = str_pad('  '.$this -> name, $leftCols) ;

        $sign = ($this -> dollarSign ? 'S/. ' : '');
        $right = str_pad($sign . $this -> price, $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }
}

function intLowHigh($input, $length)
{
    // Function to encode a number as two bytes. This is straight out of Mike42\Escpos\Printer
    $outp = "";
    for ($i = 0; $i < $length; $i++) {
        $outp .= chr($input % 256);
        $input = (int)($input / 256);
    }
    return $outp;
}

?>