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
            $rpt = $bd->xDevolverUnDatoSP($sql);
            echo json_encode(array('respuesta' => $rpt));
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
        case '4'; // guardar distribuicion
            $listData = file_get_contents('php://input');
            $sql = "call procedure_guardar_distribuicion($g_idsede, $g_us, '$listData')";
            $rpt = $bd->xDevolverUnDatoSP($sql);
            echo json_encode(array('respuesta' => $rpt));            
            break;
        case '401': // lista Distribuicion
            $sql = "SELECT d.iddistribuicion , d.fecha, u.usuario, ad.descripcion desde, if ( d.is_to_sede = 0, aa.descripcion, CONCAT('LOCAL ', s.nombre) ) hasta
                    , d.is_to_sede, d.detalle 
                from distribuicion d 
                inner join sede s on d.idsede_a = s.idsede 
                inner join usuario u on d.idusuario = u.idusuario 
                inner join almacen ad on d.idalmacen_de = ad.idalmacen 
                inner join almacen aa on d.idalmacen_a = aa.idalmacen
                where d.idsede = $g_idsede
                order by d.iddistribuicion desc";
            $bd->xConsulta($sql);
            break;
        case '402': //detalle produccion
            $sql = "select * from distribuicion_detalle where iddistribuicion = ".$_POST['id'];
            $bd->xConsulta($sql);
            break;

        // promociones
        case '5': // load tipo promocion
            $sql="select * from promocion_lista where estado=0";
            $bd->xConsulta($sql);
            break;

        case 501: //guardar promocion
            $data = file_get_contents('php://input');            
            $sql = "call procedure_guardar_promocion($g_idsede, $g_ido, $g_us,'$data')";
            $rpt = $bd->xDevolverUnDatoSP($sql);
            echo json_encode(array('respuesta' => $rpt));

            break;

        case 502: // lista de promociones
            $sql="select * from promocion where idsede = $g_idsede order by idpromocion desc";
            $bd->xConsulta($sql);
            break;

        case 503: // cambia de estado activo
            $sql = "update promocion set activo = '".$_POST['estado']."' where idpromocion = ".$_POST['id'];
            $bd->xConsulta($sql);
            break;

        case 504: // detalle items descuentos
            $sql = "select * from promocion_detalle where idpromocion = ".$_POST['id'];
            $bd->xConsulta($sql);
            break;
        
        case 505: // ico gif pormo
            $sql = "select * from promocion_gif where estado=0";
            $bd->xConsulta($sql);
            break;
    }
?>    