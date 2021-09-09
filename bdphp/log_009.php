<?php

    // produccion

    session_start();
	//header("Cache-Control: no-cache,no-store");
	header("Access-Control-Allow-Origin: *");
	header('Content-Type: application/json;charset=utf-8');
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");


    $op = $_POST['op']; // a = registro pedido | d=registro cliente | b=registro pago total | c=registro pago parcial
    if (!isset($op)) { 
        $op = $_GET['op'];
    }
	if (!isset($op)) {
		$postBody = json_decode(file_get_contents('php://input'));
		$op = $postBody->op;
	}

    $g_ido = $_SESSION['ido'];
	$g_idsede = $_SESSION['idsede'];
	$g_us = $_SESSION['idusuario'];

    switch ($op) {
        case '1': // crear almacen produccion u obtener idalmacen
            $sql = "select idalmacen AS D1 from almacen where idsede = $g_idsede and descripcion = 'PRODUCCION' and estado=0";
            echo $bd->xDevolverUnDato($sql);
            break;
        case '2'; // guardar produccion
            $listData = file_get_contents('php://input');
            $sql = "call procedure_guardar_produccion($g_idsede, $g_us, '$listData')";
            $bd->xConsulta_NoReturn($sql);
            echo json_encode(array('repuesta' => $sql));
            break;
        case '3': // lista de produccion
            $sql="SELECT pp.*, u.usuario from produccion_producto pp 
                inner join usuario u on u.idusuario = pp.idusuario 
                where pp.idsede = $g_idsede
            order by pp.idproduccion_producto desc";
            $bd->xConsulta($sql);
            break;
        case 301: //detalle produccion
            $sql = "select * from produccion_producto_detalle where idproduccion_producto = ".$_POST['id'];
            $bd->xConsulta($sql);
            break;
    }
?>    