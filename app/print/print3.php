<?php
session_start();
date_default_timezone_set('America/Lima');	

require __DIR__ . '/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;


$ArrayItem=$_POST['ArrayItem'];
$ArrayEnca=$_POST['Array_enca'];
$xArray_print=$_POST['Array_print'];
$xArraySubtotales=$_POST['ArraySubTotales'];
$ip_printer=$xArray_print[0]['ip_print'];
if($ip_printer===''){return;}

try {
	$connector = new NetworkPrintConnector($ip_printer);	    	   
	$printer = new Printer($connector);
} catch (Exception $e) {
	print 'Error, Verifique que la ticketera este prendida y que tenga papel.';
	return;
}


$logo_post="./logo/".$xArray_print[0]['logo'];
$num_mesa=$ArrayEnca['m'];
$num_pedido=$ArrayEnca['num_pedido'];
$correlativo_dia=$ArrayEnca['correlativo_dia'];
$referencia=$ArrayEnca['r'];
$reservar=$ArrayEnca['reservar'];
$solo_llevar=$ArrayEnca['solo_llevar'];
$pre_cuenta=$ArrayEnca['precuenta'];
$logo_solo_llevar='_ico_solo_llevar.png';

$nom_us=split(' ',$_SESSION['nomUs']);
$fecha_actual=date('d').'/'.date('m').'/'.date('y');
$hora_actual=date('H').':'.date('i').':'.date('s');
$sum_total=0;
$num_copias=$xArray_print[0]['num_copias'];
$cuenta_copias=0;

if($num_mesa=='' || $num_mesa=='00'){$num_mesa='Pedido: '.$correlativo_dia;}else{$num_mesa='MESA: '.$num_mesa;}

$precio='';

while($num_copias>=0){
	//icono solo llevar
	if($solo_llevar==1){
		$logo = EscposImage::load($logo_solo_llevar, false);
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> graphics($logo);
	}
	//reservar
	if($reservar==true){
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> selectPrintMode(Printer::MODE_UNDERLINE);
		$printer -> text("RESERVAR\n");
		$printer -> selectPrintMode();
	}

	if($cuenta_copias>0){
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text("************ COPIA ************\n");
		$printer -> selectPrintMode();
	}	
	
	//if($pre_cuenta==true){		
	//$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	//$printer -> text("********* PRE-CUENTA *********\n");
	//$printer -> selectPrintMode();
	//}

	/*num pedido*/
	$printer -> selectPrintMode();	
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> setEmphasis(true);
	//$printer -> text("********* PRE-CUENTA *********\n");
	$printer -> text('CO-AAAAAAAAA'.$correlativo_dia."\n");
	$printer -> setEmphasis(false);
	/* Print top logo */
	if($logo_post!=''){
		$logo = EscposImage::load($logo_post, false);
		$printer -> setJustification(Printer::JUSTIFY_CENTER);
		$printer -> graphics($logo);
	}	

	/* ENCABEZADO */
	$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
	$printer -> text($num_mesa."\n");
	$printer -> selectPrintMode();
	$printer -> text("------------------------------------------------\n");
	$printer -> text($referencia."\n");
	$printer -> feed();

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
		/*$printer -> setEmphasis(true);
		$printer -> text($item["des"]."\n");
		$printer -> text("------------------------------------------------\n");
		$printer -> setEmphasis(false);*/
		
		$si_tiene_item=0;
		$cuenta_row=0;
		$seccion='';
		$des_part2='';
		//$sum_total=0;
		usort($item, "cmp");	
		foreach ($item as $subitem) {
			if(is_array($subitem)==false){continue;}
			if($subitem['cantidad']==0){continue;}
			
			/*titulo tipo consumo*/		
			if($si_tiene_item==0){			
				$printer -> setJustification(Printer::JUSTIFY_CENTER);				
				$printer -> selectPrintMode(Printer::MODE_UNDERLINE);
				$printer -> selectPrintMode(Printer::MODE_ONE_WIDTH);
				$printer -> setEmphasis(true);			
				if($cuenta_tpc>0){$printer -> text("\n\n");}
				$printer -> text($tipo_consumo."\n");
				//$printer -> text("------------------------------------------------\n");
				$printer -> setEmphasis(false);
				$printer -> selectPrintMode();
				$cuenta_tpc++;
			}


			$si_tiene_item=1;
			$printer -> setJustification(Printer::JUSTIFY_LEFT);
			$printer -> setEmphasis(true);	
			if($seccion!=$subitem["des_seccion"]){			
				if($cuenta_row>0){$printer -> text("\n");}	
				$seccion=$subitem["des_seccion"];
				$printer -> text($seccion."\n");
				$printer -> text("------------------------------------------------\n");	
				$printer -> setEmphasis(false);
				$cuenta_row++;
			}		
			
			$printer -> setEmphasis(false);
			$precio=$subitem["precio_print"];
			$indicaciones_item=$subitem["indicaciones"];
			if($indicaciones_item!=''){$indicaciones_item='('.$indicaciones_item.')';}
			if($precio==''){$precio=$subitem["precio_total"];}
			$r_subitem=$subitem["cantidad"].' '.$subitem["des"].$indicaciones_item;
			$des_part2='';
			$des_part3='';
			if(strlen($r_subitem) > 35){
				$des_part2='  '.substr($r_subitem,35,strlen($r_subitem));
				$r_subitem=substr($r_subitem,0,35)."-";			
			}
			if(strlen($des_part2) > 35){
				$des_part3='  '.substr($des_part2,35,strlen($des_part2));
				$des_part2=substr($des_part2,0,35)."-";			
			}
			//$r_subitem = strlen($r_subitem) > 35 ? substr($r_subitem,0,35)."..." : $r_subitem;

			$sum_total=(float)$sum_total+(float)$precio;
			$printer -> text(new item($r_subitem, $precio));
			if($des_part2!=''){$printer -> text(new item($des_part2, ''));}
			if($des_part3!=''){$printer -> text(new item($des_part3, ''));}
		}	
	}

	/* TOTALES */
	$printer -> feed();
	$printer -> text("------------------------------------------------\n");
	$printer -> setEmphasis(true);
	$r_subt_t=0;		
	foreach ($xArraySubtotales as $item) {
		$des_subtotal = $item['descripcion'];
		$precio_subtotal = $item['importe'];
		$printer -> text(new item($des_subtotal, $precio_subtotal));
		
		
		if(strtoupper($item['descripcion'])!=strtoupper('Total')){continue;}
		$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
		$printer -> text(new item('Total', $sum_total, true));
		$printer -> selectPrintMode();
	
	}
	
	/* PIE DE PAGINA */	
	$printer -> feed(2);
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> text($xArray_print[0]['pie_pagina']."\n");
	$printer -> feed(2);
	$printer -> text("Atendido por:".$nom_us[0]."\n");
	$printer -> text($fecha_actual.' | '.$hora_actual. "\n");

	$printer -> text("www.papaya.com.pe\n");
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

function cmp($a, $b)
{
	if(is_array($a)==false){return;}
    return strcmp($a["des_seccion"], $b["des_seccion"]);
}
?>