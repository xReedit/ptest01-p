<?php
	session_start();
	//header("Cache-Control: no-cache,no-store");
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');

  switch($_GET['op'])
  {
    case 1://grafico tiempo de despacho
      $arr_filtro=$_POST['arr_filtro'];
      $sql="
      SELECT pd.despachado_hora, CONVERT(pd.despachado_tiempo, SIGNED INTEGER) AS minutos,p.estado
        FROM pedido_detalle AS pd
        INNER JOIN pedido AS p using(idpedido)
        INNER JOIN seccion AS s ON pd.idseccion=s.idseccion
        INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
        WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.cierre=0 AND (pd.despachado=1 OR p.estado=4) and (s.idimpresora IN (".$arr_filtro['idseccion'].") AND tp.idtipo_consumo in(".$arr_filtro['tipo_consumo']."))
				GROUP BY pd.idpedido, pd.despachado_hora
      ";
      $bd->xConsulta($sql);
      break;
  }
/*
function xObtenerArrayGrafico($sql){
  $bd=new xManejoBD("restobar");
  $cuenta=1;
  $data[0] = [];
	$results=$bd->xConsulta2($sql);
  while ($fila = $results->fetch_row()) {
    $data[$cuenta]=array($fila[0],(int)$fila[1]); // la fila a devolver es d1
    $cuenta++;
  }
	return json_encode($data);
}*/
?>
