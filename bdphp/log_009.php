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
        case 10: // modificacion de stock porciones
            $postBody = json_decode(file_get_contents('php://input'));
            $data = $postBody;            
            $hora = date('h:i a');

            $sql = "update porcion set stock='$data->stock_actual' where idporcion=$data->idporcion";
            $bd->xConsulta_NoReturn($sql);

            $sql = "insert into porcion_historial(tipo_movimiento,fecha,hora,cantidad,idusuario, idsede,idporcion)
                    values ('$data->movimiento', curdate(), '$hora','$data->cantidad', $g_us,$g_idsede,$data->idporcion)";
            $bd->xConsulta($sql);
            // echo json_encode(array('respuesta' => 'ok'));
            break;
        case 101; //load porciones
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="call procedure_porcion_historial($postBody->idporcion, $g_idsede)";
            $bd->xConsulta($sql);
            break;
        case 11: // guardar modificacion de stock producto almacen
            $postBody = json_decode(file_get_contents('php://input'));
            $data = $postBody;            
            $hora = date('h:i a');

            $sql = "update producto_stock set stock='$data->stock_actual' where idproducto_stock=$data->idproducto_stock";
            $bd->xConsulta_NoReturn($sql);

            $sql = "insert into producto_historial(tipo_movimiento,fecha,hora,cantidad,idusuario, idsede,idproducto,idalmacen)
                    values ('$data->movimiento', curdate(), '$hora','$data->cantidad', $g_us,$g_idsede,$data->idproducto,$data->idalmacen)";
            $bd->xConsulta($sql);
            break;
        case 111; //load productos
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="call procedure_producto_historial($postBody->idproducto, $postBody->idproducto_stock, $g_idsede, $postBody->idalmacen)";
            $bd->xConsulta($sql);
            break;
        case 112: //load producto familias
            $sql="select pf.idproducto_familia, pf.descripcion, COALESCE(count(pf.idproducto_familia), 0) cantidad_relacionados from producto_familia pf 
                    left join producto p on p.idproducto_familia = pf.idproducto_familia 
                where pf.idsede = $g_idsede and pf.estado = 0   
                GROUP by pf.idproducto_familia
                order by pf.descripcion";
            $bd->xConsulta($sql);
            break;
        case 113: //remove familia
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="update producto_familia set estado = 1 WHERE idproducto_familia = '$postBody->idproducto_familia'";
            $bd->xConsulta($sql);
            break;
        case 114: //remove familia
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="update producto_familia set descripcion = '$postBody->descripcion' WHERE idproducto_familia = '$postBody->idproducto_familia'";
            $bd->xConsulta($sql);
            break;
        case 115: //guardar modificacion descripcion porcion
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="update porcion set descripcion = '$postBody->descripcion' WHERE idporcion = $postBody->idporcion";
            $bd->xConsulta($sql);
            break;
        case 116: // load cantidad de recetas enlazadas
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="select count(idporcion) cantidad from item_ingrediente ii where idporcion = $postBody->idporcion and estado = 0";
            $bd->xConsulta($sql);
            break;
        case 117: // borrar porcion 
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="update porcion set estado = 1 WHERE idporcion = $postBody->idporcion";
            $bd->xConsulta($sql);
            break;
        case 118: // borrar producto
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="update producto set estado = 1 WHERE idproducto = $postBody->idproducto";
            $bd->xConsulta($sql);
            break;
        case 20: // guardar pago repartidor
            $postBody = json_decode(file_get_contents('php://input'));
            $list = $postBody->list;
            // $list = json_encode($postBody->list);
            // $sql="call procedure_registra_pago_repartidor($g_idsede,$g_us,$postBody->id,'$list')";
            $sql="call procedure_registra_pago_repartidor($g_idsede,$g_us,$postBody->id,'$list')";
            $bd->xConsulta($sql);
            break;
    }
?>    