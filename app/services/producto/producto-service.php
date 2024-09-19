<?php

    // produccion

    session_start();
    header('Content-Type: application/json;charset=utf-8');
    header('content-type: text/html; charset: utf-8');
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    include "../../../bdphp/ManejoBD.php";
    $bd = new xManejoBD("restobar");

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
        case 'calc-costo-conversion':
            $postBody = json_decode(file_get_contents('php://input'));
            $stock_actual = $postBody->stock_actual;
            // // $sql = "select p.idproducto, p.precio, uk.conversion_a_base uk_base, uc.conversion_a_base uc_base, p.factor_conversion from producto p 
            // // 				inner join unidad_medida uk on uk.idunidad_medida = p.idunidad_kardex 
            // // 				inner join unidad_medida uc on uc.idunidad_medida = p.idunidad_conversion
            // // 				where idproducto =$postBody->idproducto";
            $sql = "select p.idproducto, p.precio, p.factor_conversion from producto p where p.idproducto =$postBody->idproducto";
            $producto = $bd->xConsulta3($sql);
            $producto = json_decode($producto, true);

            $precio_actual = $producto[0]['precio'];
            $factor_conversion = $producto[0]['factor_conversion'];

            $precio_unidad = $precio_actual / $stock_actual;
            $costo_conversion = $precio_unidad / $factor_conversion;

            $sql = "update producto set costo_conversion = $costo_conversion where idproducto = $postBody->idproducto";
            $bd->xConsulta_NoReturn($sql);
            echo json_encode(array('success' => true, 'costo_conversion' => $costo_conversion));
            break;
    }

?>