<?php	
session_start();
date_default_timezone_set('America/Lima');	
require __DIR__ . '/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;	
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

// echo "entra";
	
$ArrayEnca=$_POST['Array_enca'];
$ip_printer=$ArrayEnca['ip_print'];
$logo_post="./logo/".$ArrayEnca['logo'];
$var_size_font=(int)$ArrayEnca['size_font'];
$var_margen_iz=intLowHigh((int)$ArrayEnca['margen_iz'], 2);

$fecha_actual=date('d').'/'.date('m').'/'.date('y');
$hora_actual=date('H').':'.date('i').':'.date('s');
try {
	//por ip o por usb
	$impresora_print=$ip_printer;	
	$pos_print=strrpos($ip_printer,'//');		
	//echo $impresora_print;
	if($pos_print===false){
		$connector = new NetworkPrintConnector($impresora_print);
	}else{						
		$impresora_print="smb:".$impresora_print;	
		// $impresora_print = "/dev/usb/lp0";
		// $impresora_print = "smb://marcelo:182182@desa1/ticketera";
		// echo $impresora_print;
		// $connector = new WindowsPrintConnector("smb://marcelo:182182@desa1/ticketera");
		$connector = new WindowsPrintConnector($impresora_print);
	}
	// echo $connector;
	$printer = new Printer($connector);
} catch (Exception $e) {
	print 'Error, Verifique que la ticketera este prendida y que tenga papel.';		
	return;
}


$connector->write(Printer::GS.'L'.$var_margen_iz);
$printer -> setFont($var_size_font);

// echo $logo_post;
if($logo_post!=''){
	$logo = EscposImage::load($logo_post, false);
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> graphics($logo);
}	
/* ENCABEZADO */
echo "ecabezado";
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text('MESA 01'."\n");
	$printer -> selectPrintMode();
	$printer -> text("------------------------------------------------\n");
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
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> setEmphasis(true);				
	$printer -> text('ENTRADAS'."\n");
	$printer -> text("------------------------------------------------\n");	
	$printer -> setEmphasis(false);

	//destalles
	$printer -> setEmphasis(false);
	$printer -> text(new item('01 ARROZ CON LECHE', '2.00'));
	$printer -> text(new item('01 ENSALADA FRESCA', '2.00'));
	$printer -> text(new item('01 CAUSA RELLENA', '5.00'));
	$printer -> feed();


	//SECCION 2
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> setEmphasis(true);				
	$printer -> text('PLATOS DE FONDO'."\n");
	$printer -> text("------------------------------------------------\n");	
	$printer -> setEmphasis(false);

	//destalles
	$printer -> setEmphasis(false);
	$printer -> text(new item('01 AJI DE GALLINA', '10.00'));
	$printer -> text(new item('01 ARROZ CON MARISCOS', '10.00'));
	$printer -> text(new item('01 LOMITO SALTADO', '10.00'));
	
	/* TOTALES */
	$printer -> feed();
	$printer -> text("------------------------------------------------\n");
	$printer -> setEmphasis(true);
	
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);	
	$printer -> text(new item('TOTAL', '39.00', true));
	$printer -> selectPrintMode();

	/* PIE DE PAGINA */	
	$printer -> feed(2);
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> text($ArrayEnca['pie_pagina']."\n");
	$printer -> feed(2);
	$printer -> text("Atendido por: PEDRO \n");
	$printer -> text($fecha_actual.' | '.$hora_actual. "\n");

	$printer -> text("www.papaya.com.pe\n");

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
        $leftCols = 38;
        if ($this -> dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad($this -> name, $leftCols) ;

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