<?php
session_start();	
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
date_default_timezone_set('America/Lima');

include "ManejoBD.php";
$bd=new xManejoBD("restobar");

$g_ido = $_SESSION['ido'];
$g_idsede = $_SESSION['idsede'];
switch($_GET['op'])
	{
		case 1://verifica si existe o se añado algun pedido para actualiza, monitor de pedidos
			$sql="CALL procedure_run_pedidos_caja_1(".$g_ido.",".$g_idsede.");";
			// $sql="SELECT (sum(total) + sum(despachado)) as d1 FROM pedido where cierre=0 and (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].")";
			$numero_pedidos_actual=$bd->xDevolverUnDato($sql);
			echo "retry: 8000\n"."data:".$numero_pedidos_actual."\n\n";
			ob_flush();
			flush();
			// ob_end_clean();
			break;		
		case 2: //	verifica si existe pedido nuevo || zona de despacho
			$tipo_consumo=$_GET["tp"];
			$idseccion=$_GET["ids"];

			// $sql = "
			// SELECT max(p.idpedido) 
			// FROM pedido AS p
			// 	inner join pedido_detalle as pd USING (idpedido)
			// 	left join seccion as s on s.idseccion = pd.idseccion
			// 	left join producto_familia as pf on pf.idproducto_familia = pd.idseccion
			// where (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") and (pd.idtipo_consumo in (".$tipo_consumo.") and (s.idimpresora in (".$idseccion.") or pf.idimpresora in (".$idseccion.") ) )
			// ";

			$sql="CALL procedure_run_zona_d_2('".$tipo_consumo."','".$idseccion."',".$g_ido.",".$g_idsede.");";
			$numero_pedidos_actual_2=$bd->xDevolverUnDato($sql);
			$hora=date('H:i:s');
			echo "retry: 4000\n"."data:".$numero_pedidos_actual_2.",".$hora."\n\n";
			ob_flush();
			flush();
			break;
		case 201: // fom socket	verifica si existe pedido nuevo || zona de despacho
			$tipo_consumo=$_GET["tp"];
			$idseccion=$_GET["ids"];

			$sql="CALL procedure_run_zona_d_2('".$tipo_consumo."','".$idseccion."',".$g_ido.",".$g_idsede.");";
			$numero_pedidos_actual_2=$bd->xDevolverUnDato($sql);
			$hora=date('H:i:s');
			echo $numero_pedidos_actual_2.",".$hora;
			break;
		case 3: // comprobar conexion
			echo true; 
			break;
	}

/*$time = date('r');
echo "data: The server time is: {$time}\n\n";
flush();*/
?>
