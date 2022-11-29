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
            $data = str_replace("\\n", "", $data);       
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
        
        case 6: // solicitudes remotas de cierre
            $sql = "SELECT cpr.*, u.usuario  from cierre_permiso_remoto cpr
                left join usuario u on cpr.idusuario_solicita = u.idusuario 
                where cpr.idusuario_admin = $g_us order by cpr.idcierre_permiso_remoto desc limit 15";
            $bd->xConsulta($sql);
            break;
        case 7: // control de delivery - control pedidos
            $arrItemPedido=$_POST['obj'];
            $fecha= isset($_POST['fecha']) ? $_POST['fecha']: '';
            $arrItemPedido = isset($arrItemPedido) ? "'".json_encode($arrItemPedido)."'" : 'null';
            $sql="call procedure_refresh_delivery($g_idsede, $arrItemPedido, '$fecha')";
            $bd->xConsulta($sql);
            break;
        case 8: // control delivery
            $data = $postBody;
            $opcion = $data->opcion;
            $idpedido = $data->idpedido;


            switch ($opcion) {
                case '1': // llamar repartidor
                    $sql = "update pedido set flag_solicita_repartidor_papaya = 1 where idpedido = ".$idpedido;
                    break;
            }

            $bd->xConsulta($sql);
            break;
        case 9: // pedidos anulados
            $fecha = $_POST['f'];
            $sql = "call procedure_show_pedidos_borrados($g_idsede, '$fecha')";
            $bd->xConsulta($sql);
            break;
        case 901: //count pedidos borrados
            $sql = "
                    select COUNT(pb.idpedido_borrados) cant from pedido_borrados pb 
                    inner join usuario u using(idusuario)
                    inner join sede s using(idsede)
                    where s.idsede = $g_idsede and pb.fecha_cierre = ''
            ";
            $bd->xConsulta($sql);
            break;
    }
?>    