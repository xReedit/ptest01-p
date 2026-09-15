<?php
session_start();
date_default_timezone_set('America/Lima');	

require __DIR__ . '/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

$ArrayEnca=$_POST['Array_enca'];
$xArray_print=$_POST['Array_print'];
$ip_printer=$xArray_print[0]['ip_print'];

if($ip_printer===''){return;}
try {
	//por ip o por usb
	$impresora_print=$ip_printer;
	$pos_print=strrpos($impresora_print,'//');
	if($pos_print==false){
		$connector = new NetworkPrintConnector($impresora_print);
	}else{
		$connector = new WindowsPrintConnector($impresora_print);
	}
	
	$printer = new Printer($connector);
} catch (Exception $e) {
	print 'Error, Verifique que la ticketera este prendida y que tenga papel.';
	return;
}


$print_ver='';
$num_mesa=$ArrayEnca['m'];
$correlativo_dia=$ArrayEnca['correlativo_dia'];
$item_change=$ArrayEnca['item'];
$item_change=explode('|',$item_change);


$nom_us=explode(' ',$_SESSION['nomUs']);
$fecha_actual=date('d').'/'.date('m').'/'.date('y');
$hora_actual=date('H').':'.date('i').':'.date('s');

//if($num_mesa=='' || $num_mesa=='00'){$num_mesa='Pedido: '.$correlativo_dia;}else{$num_mesa='MESA: '.$num_mesa;}

	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text("****** CAMBIAR ******\n");
	$printer -> selectPrintMode();

	/*num pedido*/
	$printer -> selectPrintMode();	
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> setEmphasis(true);
	$printer -> text($correlativo_dia."\n");
	$printer -> setEmphasis(false);


	/* ENCABEZADO */
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text($num_mesa."\n");
	$printer -> selectPrintMode();
	$printer -> text("------------------------------------------------\n");
	$printer -> feed();


	/* CUERPO , ITEMS*/
	$des_part2='';
	$des_part3='';
	$precio=$item_change[2];
	$r_subitem=$item_change[0].' '.$item_change[1];
	if(strlen($r_subitem) > 35){
		$des_part2='  '.substr($r_subitem,35,strlen($r_subitem));
		$r_subitem=substr($r_subitem,0,35)."-";			
	}
	if(strlen($des_part2) > 35){
		$des_part3='  '.substr($des_part2,35,strlen($des_part2));
		$des_part2=substr($des_part2,0,35)."-";			
	}

	$printer -> text(new item($r_subitem, $precio));
	if($des_part2!=''){$printer -> text(new item($des_part2, ''));}
	if($des_part3!=''){$printer -> text(new item($des_part3, ''));}		
	
	/* PIE DE PAGINA */
	$printer -> feed(2);
	$printer -> text("Cambiado por:".$nom_us[0]."\n");
	$printer -> text($fecha_actual.' | '.$hora_actual. "\n");

	$printer -> text("www.papaya.com.pe\n");
	$printer -> cut();
	$printer -> pulse();

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