<?php
session_start();
date_default_timezone_set('America/Lima');	

require __DIR__ . '/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;


$ArrayItem=$_POST['ArrayItem'];
$ArrayEnca=$_POST['Array_enca'];
$ip_printer=$ArrayEnca[0]['ip_print'];
$detalle_cierre=$ArrayEnca[0]['detalle_cierre'];

$logo_post="./logo/".$ArrayEnca[0]['logo'];
$titulo_document=$ArrayEnca[0]['titulo'];

$logo_cierre_caja='';
if($titulo_document=='CIERRE DE CAJA'){$logo_cierre_caja="_cierre_caja.png";}
//$us=$ArrayEnca[0]['us'];

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

//configuracion de la impresora //margen font
$var_margen_iz=(int)$ArrayEnca[0]['var_margen_iz'];
$var_size_font=(int)$ArrayEnca[0]['var_size_font'];
$var_margen_iz=intLowHigh($var_margen_iz, 2);
//			

$connector->write(Printer::GS.'L'.$var_margen_iz);			
$printer -> setFont($var_size_font);
//---------------////////////////

$nom_us=$_SESSION['nomUs'];
$fecha_actual=date('d').'/'.date('m').'/'.date('y');
$hora_actual=date('H').':'.date('i').':'.date('s');
//$sum_total=0;
	
	if($logo_cierre_caja!=''){
		$logo_cierre_caja = EscposImage::load($logo_cierre_caja, false);
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> graphics($logo_cierre_caja);
	}
	/* Print top logo */
	if($logo_post!=''){
		$logo = EscposImage::load($logo_post, false);
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> graphics($logo);		
	}
	$printer -> feed();
	//encabezado
	$printer -> setJustification(Printer::JUSTIFY_LEFT);
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);		
	$printer -> text($titulo_document."\n");
	$printer -> selectPrintMode();
	
	$printer -> selectPrintMode();	
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> selectPrintMode(Printer::MODE_UNDERLINE);
	$printer -> setEmphasis(true);
	$printer -> text($_SESSION['nom_sede']."\n");	
	$printer -> selectPrintMode();	
	$printer -> setEmphasis(false);
	$printer -> text($detalle_cierre."\n");
	
	$printer -> selectPrintMode();	
	$printer -> setEmphasis(true);
	$printer -> text($nom_us."\n");
	$printer -> text($fecha_actual.' | '.$hora_actual."\n");	
	$printer -> setEmphasis(true);	
	$printer -> text("------------------------------------------------\n");
	$printer -> setEmphasis(true);
	$printer -> feed();

	/* CUERPO , ITEMS*/
	foreach ($ArrayItem as $item) {
		if($item==null){continue;}
		if($item['visible']=='1'){continue;}
		//if($item["des"]!='PEDIDOS ATENDIDOS'){continue;}


		$des_seccion=$item["des"];	
		//$sum_total=0;
		$sum_t1=0;
		$sum_t2=0;
		$sum_t3=0;
		$es_moneda=0;

		//usort($item, "cmp");			
		$printer -> setEmphasis(true);
		//$printer -> text($des_seccion."\n");
		$printer -> text(new item2($des_seccion,$item["t1"], $item["t2"], $item["t3"]));
		$printer -> text("------------------------------------------------\n");	
		$printer -> setEmphasis(false);

		//ordenar si es LISTADO DE COMPRAS
		if($titulo_document=='LISTADO DE COMPRAS'){
			usort($item, "cmp");
		}
		
		foreach ($item as $subitem) {
			if(is_array($subitem)==false){continue;}			


			$printer -> setEmphasis(false);
			$des_item=$subitem["des"];
			$t1=str_replace(',','', $subitem["t1"]);
			$t2=str_replace(',','', $subitem["t2"]);
			$t3=str_replace(',','', $subitem["t3"]);

			//si tiene coma // ejemplo 1,100.00 solo cuenta 1
			$t1=str_replace(',','', $t1);
			$t2=str_replace(',','', $t2);
			$t3=str_replace(',','', $t3);

			if($t1===null){$t1=0;}
			if($t2===null){$t2=0;}
			if($t3===null){$t3=0;}

			if(strpos($t3, '.')){$es_moneda=1;}	
			
			//$des_item=$t1.' '.$des_item;
			$des_item=trim($des_item);
			//$des_part2='';
			//$des_part3='';
			
			if(strlen($des_item) > 28){				
				$des_item=substr($des_item,0,24)."...";			
			}
			
			/*if(strlen($des_item) > 28){
				$des_part2='  '.substr($des_item,28,strlen($des_item));
				$des_item=substr($des_item,0,24)."...";			
			}
			if(strlen($des_part2) > 28){
				$des_part3='  '.substr($des_part2,28,strlen($des_part2));
				$des_part2=substr($des_part2,0,28)."-";			
			}*/


			//$r_subitem = strlen($r_subitem) > 35 ? substr($r_subitem,0,35)."..." : $r_subitem;						
			//$sum_total=(float)$sum_total+(float)$t2;
			$printer -> text(new item2($des_item,$t1, $t2, $t3));
			//if($des_part2!=''){$printer -> text(new item($des_part2, ''));}
			//if($des_part3!=''){$printer -> text(new item($des_part3, ''));}

			$sum_t1=$sum_t1+$t1;
			$sum_t2=$sum_t2+$t2;
			$sum_t3=$sum_t3+$t3;
		}
		//total
		//$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);				
		if($sum_t1==0){$sum_t1='';}
		if($sum_t2==0){$sum_t2='';}

		if($es_moneda==1){$sum_t3=number_format($sum_t3,2);}		
		//$sum_t1=$sum_t1;
		$printer -> text("------------------------------------------------\n");
		$printer -> setEmphasis(true);
		$printer -> text(new item2('',$sum_t1,$sum_t2, $sum_t3));
		$printer -> feed();
	}


	/* PIE DE PAGINA */	
	/*$printer -> feed(2);
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> text($xArray_print[0]['pie_pagina']."\n");
	$printer -> feed(2);
	$printer -> text("Atendido por:".$nom_us[0]."\n");
	$printer -> text($fecha_actual.' | '.$hora_actual. "\n");

	$printer -> text("www.papaya.com.pe\n");
	$printer -> cut();
	$printer -> pulse();

	$num_copias--;
	$cuenta_copias++;*/
	
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

        $sign = ($this -> dollarSign ? 'S/.' : '');
        $right = str_pad($sign . $this -> price, $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }
}

class item2
{
    private $name;
    private $col1;
    private $col2;
    private $col3;

    public function __construct($name = '', $col1 = '', $col2 = '', $col3 = '')
    {
        $this -> name = $name;
        $this -> col1 = $col1;
        $this -> col2 = $col2;
        $this -> col3 = $col3;
    }

    public function __toString()
    {
        $rightCols = 10;
        $leftCols = 30;        

        $left = str_pad($this -> name, $leftCols) ;

        $right = str_pad($this -> col1, 1,' ', STR_PAD_LEFT);
        $right = $right.str_pad($this -> col2, 5,' ', STR_PAD_LEFT);
        $right = $right.str_pad($this -> col3, $rightCols, ' ', STR_PAD_LEFT);
        
        return "$left$right\n";
    }
}


function cmp($a, $b)
{
	if(is_array($a)==false){return;}
    return strcmp($a["des_orden"], $b["des_orden"]);
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