<?php
session_start();	
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
date_default_timezone_set('America/Lima');

include "ManejoBD.php";
$bd=new xManejoBD("restobar");

switch($_GET['op'])
	{
		case 1://verifica si existe o se añado algun pedido para actualiza, monitor de pedidos
			$sql="SELECT (sum(total) + sum(despachado)) as d1 FROM pedido where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and cierre=0";
			$numero_pedidos_actual=$bd->xDevolverUnDato($sql);
			echo "retry: 6000\n"."data:".$numero_pedidos_actual."\n\n";
			ob_flush();
			flush();
			break;
		case 2: //	verifica si existe pedido nuevo || zona de despacho
			$tipo_consumo=$_GET["tp"];
			$idseccion=$_GET["ids"];

			$sql = "
			SELECT max(p.idpedido) 
			FROM pedido AS p
				inner join pedido_detalle as pd USING (idpedido)
				left join seccion as s on s.idseccion = pd.idseccion
				left join producto_familia as pf on pf.idproducto_familia = pd.idseccion
			where (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") and (pd.idtipo_consumo in (".$tipo_consumo.") and (s.idimpresora in (".$idseccion.") or pf.idimpresora in (".$idseccion.") ) )
			";

			$numero_pedidos_actual_2=$bd->xDevolverUnDato($sql);
			$hora=date('H:i:s');
			echo "retry: 4000\n"."data:".$numero_pedidos_actual_2.",".$hora."\n\n";
			ob_flush();
			flush();
			break;			
	}

/*$time = date('r');
echo "data: The server time is: {$time}\n\n";
flush();*/
?>
