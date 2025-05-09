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



    // $op = $_POST['op']; // a = registro pedido | d=registro cliente | b=registro pago total | c=registro pago parcial
    // if (!isset($op)) { 
    //     $op = $_GET['op'];
    // }
	// if (!isset($op)) {
	// 	$postBody = json_decode(file_get_contents('php://input'));
	// 	$op = $postBody->op;
	// }

    if (isset($_POST['op'])) {
        $op = $_POST['op'];
    } else if (isset($_GET['op'])) {
        $op = $_GET['op'];
    } else {
        $postBody = json_decode(file_get_contents('php://input'));
        if (isset($postBody->op)) {
            $op = $postBody->op;
        }
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
            echo json_encode(array('respuesta' => $rpt, 'sql', $sql));

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
                case '2': // cancelar llamada repartidor
                    $sql = "update pedido set flag_solicita_repartidor_papaya = 0 where idpedido = ".$idpedido;
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
                    where s.idsede = $g_idsede and pb.fecha_hora >= CURDATE() - INTERVAL 2 DAY and pb.fecha_cierre = '' 
            ";
            $bd->xConsulta($sql);
            break;
        case 10: // modificacion de stock porciones
            $postBody = json_decode(file_get_contents('php://input'));
            $data = $postBody;            
            $hora = date('h:i a');

            $sql = "update porcion set stock='$data->stock_actual' where idporcion=$data->idporcion";
            $bd->xConsulta_NoReturn($sql);

            //idtipo_movimiento_stock = 1 => entrada
            $idtipo_movimiento_stock = $data->movimiento == 'Aumenta' ? 1 : 2;

            $sql = "insert into porcion_historial(tipo_movimiento,idtipo_movimiento_stock,fecha_date,fecha,hora,cantidad,idusuario, idsede,idporcion,stock_total)
                    values ('$data->movimiento',$idtipo_movimiento_stock, curdate(), curdate(), '$hora','$data->cantidad', $g_us,$g_idsede,$data->idporcion, $data->stock_total)";
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
            $pages = isset($postBody->pages) ? $postBody->pages :json_encode('{"limit":"10", "offset":"0"}');
            $sql="call procedure_producto_historial($postBody->idproducto, $postBody->idproducto_stock, $g_idsede, $postBody->idalmacen, $pages)";
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
            $sql="select count(idporcion) from item_ingrediente ii where idporcion = $postBody->idporcion and estado = 0";
            $bd->xConsulta($sql);
            break;
        case 11601: // load cantidad de recetas enlazadas
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="select i.descripcion, ii.cantidad from item_ingrediente ii 
                inner join item i using(iditem)
                where ii.idporcion = $postBody->idporcion and ii.estado = 0";
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
        case 21: // load lista proveedores
            $sql="select p.*, count(c.idcompra) num_compras from proveedor p
                    left join compra c on c.idsede = p.idsede and p.idproveedor = c.idproveedor
                where p.idsede = $g_idsede and p.estado = 0
                GROUP by p.idproveedor 
                order by num_compras desc limit 50";            
            $bd->xConsulta($sql);
            break;        
        case 2101: // lista de compras por proveedor
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="select c.idcompra, c.f_compra, c.total, c.f_pago, c.nota_de_compra 
                    ,a.descripcion nom_almacen, tp.descripcion as des_tipo_pago
                    ,u.nombres nom_usuario
                from compra c 
                    inner join almacen a on a.idalmacen = c.idalmacen 
                    inner join tipo_pago tp on tp.idtipo_pago = c.idtipo_pago 
                    inner join usuario u on c.idusuario = u.idusuario 
                where c.idproveedor = $postBody->idproveedor and c.idsede = $g_idsede
                order by c.idcompra desc";

            $bd->xConsulta($sql);
            break;
        case 2102: // listado de las 30 ultimas compras
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="select ci.idcompra_items, ci.idcompra, ci.idproducto, p.descripcion nom_producto
                ,format(ci.punitario, 2) punitario, sum(ci.cantidad) cantidad, format(sum(ci.ptotal), 2) total
            from compra_items ci 
                inner join compra c on c.idcompra = ci.idcompra 
                inner join producto p on p.idproducto = ci.idproducto 
            where c.idsede = $g_idsede and c.idproveedor = $postBody->idproveedor
            GROUP by  ci.idproducto
            order by cantidad desc, c.idcompra desc limit 50";

            $bd->xConsulta($sql);
            break;
        case 2103: //lista de productos por idcompra
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="select ci.idcompra_items, ci.idcompra, ci.idproducto, p.descripcion nom_producto
                ,format(ci.punitario, 2) punitario, ci.cantidad, format(ci.ptotal, 2) total
            from compra_items ci 
                inner join compra c on c.idcompra = ci.idcompra 
                inner join producto p on p.idproducto = ci.idproducto 
            where c.idcompra = $postBody->idcompra and c.idsede = $g_idsede
            order by total desc";

            $bd->xConsulta($sql);
            break;
        case 22: //compras listado
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="select c.idcompra, c.f_compra,c.f_registro,c.total,c.f_pago,c.nota_de_compra
                    ,tp.descripcion nom_tipo_pago, p.descripcion nom_proveedor,a.descripcion nom_almacen
                    ,u.nombres nom_usuario
                from compra c
                    left join proveedor p using(idproveedor)
                    inner join tipo_pago tp using(idtipo_pago)
                    inner join almacen a using(idalmacen)
                    inner join usuario u on c.idusuario = u.idusuario 
                where c.idsede = $g_idsede 
                    and (MONTH(STR_TO_DATE(c.f_compra, '%d/%m/%Y')) = $postBody->mm 
                        and YEAR(STR_TO_DATE(c.f_compra, '%d/%m/%Y')) = $postBody->yy)
                order by c.idcompra desc";

            $bd->xConsulta($sql);
            break;
        case 23: // cuentas por cobrar - ventas al credito            
            // $sql="select rp.idcliente, rp.idregistro_pago, c.nombres nom_cliente, c.ruc as num_dni, c.direccion, c.telefono
            //         ,format(sum(rp.total),2) total, count(rp.idregistro_pago) cantidad 		
            //         , format(COALESCE(cpc.importe, 0), 2) pago, format(COALESCE(cpc.debe,0),2) debe
            //         ,c.telefono, c.ruc 
            //     from cliente c
            //     inner join registro_pago rp using(idcliente)
            //     inner join registro_pago_detalle rpd using(idregistro_pago)
            //     left join cliente_paga_credito cpc on c.idcliente = cpc.idcliente
            // where rpd.idtipo_pago = 3 and rp.idsede = $g_idsede and rpd.pagado != rpd.importe
            // GROUP by c.idcliente
            // order by rp.idregistro_pago desc";

            $sql = "select rp.idcliente,format(sum(rpd.importe),2) total, GROUP_CONCAT(rpd.idregistro_pago_detalle) , rp.idregistro_pago, c.nombres nom_cliente, c.ruc as num_dni, c.direccion, c.telefono
                ,format(sum(rpd.importe),2) total, count(rp.idregistro_pago) cantidad 		
                , format(COALESCE(cpc.importe, 0), 2) pago
                , format((sum(rpd.importe) - COALESCE(cpc.importe, 0)),2) debe
                ,c.telefono
            from cliente_sede cs
                inner join cliente c on cs.idcliente = c.idcliente 
                inner join registro_pago rp on cs.idcliente = rp.idcliente
                inner join registro_pago_detalle rpd using(idregistro_pago)
                left join (select cc.idcliente, cc.idsede, sum(cpcd.importe) importe, cc.debe 
                	from cliente_paga_credito cc
            		inner join cliente_paga_credito_detalle cpcd using(idcliente_paga_credito)
		            where cc.idsede =$g_idsede
            		GROUP by cc.idcliente
            	) cpc on cpc.idcliente = cs.idcliente
            where rpd.idtipo_pago = 3 and (cs.idsede=$g_idsede and rp.idsede=$g_idsede)
            GROUP by cs.idcliente
            order by rp.idregistro_pago desc, cast(debe as UNSIGNED) desc";

            $bd->xConsulta($sql);
            break;
        case 23001: // historial
            $sql = "select rp.idcliente,format(sum(rpd.importe),2) total, GROUP_CONCAT(rpd.idregistro_pago_detalle) , rp.idregistro_pago, c.nombres nom_cliente, c.ruc as num_dni, c.direccion, c.telefono
                ,format(sum(rpd.importe),2) total, count(rp.idregistro_pago) cantidad 		
                , format(COALESCE(cpc.importe, 0), 2) pago
                , format((sum(rpd.importe) - COALESCE(cpc.importe, 0)),2) debe
                ,c.telefono
            from cliente_sede cs
                inner join cliente c on cs.idcliente = c.idcliente 
                inner join registro_pago rp on cs.idcliente = rp.idcliente
                inner join registro_pago_detalle rpd using(idregistro_pago)
                left join (select cc.idcliente, cc.idsede, sum(cpcd.importe) importe, cc.debe 
                        from cliente_paga_credito cc
                        inner join cliente_paga_credito_detalle cpcd using(idcliente_paga_credito)
                        where cc.idsede =$g_idsede GROUP by cc.idcliente
                    ) cpc on cpc.idcliente = cs.idcliente and cpc.idsede = cs.idsede                 
            where rpd.idtipo_pago = 3 and (cs.idsede=$g_idsede and rp.idsede=$g_idsede)
            GROUP by cs.idcliente
            order by cast(debe as UNSIGNED) desc, rp.idregistro_pago";

            $bd->xConsulta($sql);
            break;
        case 2301: // lista de consumos x cliente
            $postBody = json_decode(file_get_contents('php://input'));
            $sql = "select rp.idregistro_pago, COALESCE(c.external_id, 0) external_id, rpd.idregistro_pago_detalle,STR_TO_DATE(rp.fecha, '%d/%m/%Y') fecha , rp.total, rpd.importe, format(rpd.pagado,2) pagado
                ,CONCAT('Pasaron: ',DATEDIFF(now(),STR_TO_DATE(rp.fecha, '%d/%m/%Y')), ' dia(s)') as pasaron
                ,format((rpd.importe - rpd.pagado),2) debe
                ,rpd.flag_pagado
            from registro_pago rp 
                inner join registro_pago_detalle rpd using(idregistro_pago)
                left join ce c on c.idce = rp.idce
            where rp.idcliente=$postBody->idcliente and rpd.idtipo_pago = 3 and rp.idsede = $g_idsede and rpd.flag_pagado = 0
            order by rp.idregistro_pago desc";

            $bd->xConsulta($sql);
            break;
        case 230101: // lista de consumos x cliente HISTORIAL
            $postBody = json_decode(file_get_contents('php://input'));
            $sql = "select rp.idregistro_pago, rpd.idregistro_pago_detalle,STR_TO_DATE(rp.fecha, '%d/%m/%Y') fecha , rp.total, rpd.importe, format(rpd.pagado,2) pagado
                ,format((rpd.importe - rpd.pagado),2) debe
                ,if ( rpd.flag_pagado = 0,  CONCAT('Pasaron: ',DATEDIFF(now(),STR_TO_DATE(rp.fecha, '%d/%m/%Y')), ' dia(s)'), 'Pagado') as pasaron
                ,rpd.flag_pagado
            from registro_pago rp 
                inner join registro_pago_detalle rpd using(idregistro_pago)
            where rp.idcliente=$postBody->idcliente and rpd.idtipo_pago = 3 and rp.idsede = $g_idsede
            order by rp.idregistro_pago desc";

            $bd->xConsulta($sql);
            break;
        case 2302: // registrar pago cuenta credito cliente
            $postBody = json_decode(file_get_contents('php://input'));            

            $sql="select idcliente_paga_credito as d1 from cliente_paga_credito where idcliente=$postBody->idcliente and idsede=$g_idsede";
            $idcliente_paga_credito = $bd->xDevolverUnDato($sql);

            if ( isset($idcliente_paga_credito) ) {
                $sql = "update cliente_paga_credito set importe = '$postBody->importe', debe = '$postBody->debe', fecha=curdate() where idcliente_paga_credito = $idcliente_paga_credito";
                $bd->xConsulta_NoReturn($sql);
            } else {
                $sql = "insert into cliente_paga_credito(idcliente, importe, debe, fecha, idsede)
                    values ($postBody->idcliente, '$postBody->importe', '$postBody->debe', curdate(), $g_idsede)";
                $idcliente_paga_credito = $bd->xConsulta_UltimoId($sql);
            }

            // insert detalle
            $sql = "insert into cliente_paga_credito_detalle(idcliente_paga_credito, importe, fecha_hora, idusuario, idtipo_pago)
                values ($idcliente_paga_credito, '$postBody->importe', now(), $g_us, $postBody->idtipo_pago)";

            $bd->xConsulta_NoReturn($sql);



                        
            // actualiza registro_pago_detalle los importes pagados
            $sql2 = "";
            // $postBody->listPagados = json_decode($postBody->listPagados);   
            foreach ($postBody->listPagados as $item) {
                $sql2.="update registro_pago_detalle set pagado = $item->pagado, flag_pagado = '$item->flag_pagado' where idregistro_pago_detalle = $item->idregistro_pago_detalle; ";
            }

            $bd->xMultiConsultaNoReturn($sql2);   
            
            echo json_encode(array('success' => true));
                        
            break;
        
        case 2303: // historial de pagos credito por cliente
            $postBody = json_decode(file_get_contents('php://input'));
            $sql = "select cp.idcliente_paga_credito, cp.fecha_hora, format(cp.importe,2) importe
	            ,u.nombres nom_usuario, tp.descripcion nom_tipo_pago, cp.idtipo_pago
                from cliente_paga_credito_detalle cp
                	inner join cliente_paga_credito cpc using(idcliente_paga_credito)
                    inner join usuario u on cp.idusuario = u.idusuario 
                    inner join tipo_pago tp using(idtipo_pago)
                where cpc.idcliente = $postBody->idcliente and cpc.idsede = $g_idsede
                order by cp.idcliente_paga_credito_detalle desc";
            
            $bd->xConsulta($sql);
            break;

        // recibe distribuicion
        case 24: 
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="select d.iddistribuicion, sd.nombre sede_de, ad.descripcion almacen_de, ud.usuario usuario_de
                ,d.fecha, d.detalle	
                ,sa.nombre sede_a
                , ua.usuario idusuario_recibe
	            , d.fecha_recibe
                , if (COALESCE(d.idusuario_recibe,0) = 0,0,1) recibido
            from distribuicion d 
                inner join sede sd on sd.idsede = d.idsede 
                inner join usuario ud on ud.idusuario = d.idusuario 
                inner join almacen ad on ad.idalmacen = d.idalmacen_de
                inner join sede sa on sa.idsede= d.idsede_a
                left join usuario ua on ua.idusuario = d.idusuario_recibe
            where d.idsede_a = $g_idsede and d.is_to_sede=1 
                and (MONTH(STR_TO_DATE(d.fecha, '%d/%m/%Y')) = $postBody->mm 
                and YEAR(STR_TO_DATE(d.fecha, '%d/%m/%Y')) = $postBody->yy)
                and d.estado = 0
            order by d.iddistribuicion desc";

            $bd->xConsulta($sql);
            break;

        case 2401: //
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="select dd.*, p.idproducto, if(COALESCE(p.idproducto,0)=0,0,1) registrado
                ,trim(SUBSTRING_INDEX(dd.descripcion, '|', -1)) nom_producto, trim(SUBSTRING_INDEX(dd.descripcion, '|', 1)) nom_familia
                , pt.precio, pt.precio_unitario, pt.precio_venta, pt.codigo_barra, pt.venta_x_peso, pt.stock_minimo
                from distribuicion_detalle dd
                    inner join producto pt on pt.idproducto = dd.idproducto
                    left join (
                        select pp.idproducto, pp.descripcion from producto pp
                        where pp.idsede = $g_idsede
                    ) as p on trim(upper(p.descripcion)) = upper(trim(SUBSTRING_INDEX(dd.descripcion, '|', -1)))
                where dd.iddistribuicion = $postBody->iddistribuicion limit 90";
            $bd->xConsulta($sql);
            break;

        case 2402: // recibir producto
            // $postBody = json_decode(file_get_contents('php://input'));
            $postBody = file_get_contents('php://input');
            $sql = "call procedure_recibir_producto_distribuicion($g_idsede, $g_ido, '$postBody')";
            // echo json_encode(array('success' => $sql));
            $bd->xConsulta($sql);
            break;

        case 2403: // marcar como recibido
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="update distribuicion set idusuario_recibe=$g_us, fecha_recibe=now() where iddistribuicion = $postBody->iddistribuicion"; 
            $bd->xConsulta($sql);
            break;
        
        case 30: // configuracion de el costo de entrega de la tienda en linea            
            $sql="select * from sede_costo_delivery where idsede = $g_idsede";
            $bd->xConsulta($sql);
            break;
        case 3001: // guardar configuracion 
            $postBody = json_decode(file_get_contents('php://input'));
            if ($postBody->isRegisterNew == 1) {
                $sql="insert into sede_costo_delivery(idsede, parametros) values ($g_idsede, '$postBody->parametros')"; 
            } else {
                $sql="update sede_costo_delivery set parametros = '$postBody->parametros' where idsede = $g_idsede";
            }
            $bd->xConsulta($sql);            
            break;
        case 31: // bloquear facturacion electronica por error cpe ej: certificado vencido
            $postBody = json_decode(file_get_contents('php://input'));

            $sql="update sede set is_bloqueado_facturacion = 1,msj_cpe_alert='$postBody->mensaje' where idsede = $g_idsede";
            $bd->xConsulta_NoReturn($sql);

            $sql="update tipo_comprobante_serie set is_deshabilitado_cpe = 1, estado = 1 where idsede = $g_idsede and estado = 0 and idtipo_comprobante in (2,3)";  
            $bd->xConsulta_NoReturn($sql);
            break;
        case 40: // permiso remoto
            $data = file_get_contents('php://input');
            $postBody = json_decode($data);


            // Generar un ID único
            $uniqueId = uniqid();

            // Truncar el ID a 6 caracteres
            $shortUniqueId = substr($uniqueId, 0, 10);

            $sql="insert into permiso_remoto(idsede, idusuario_solicita, idusuario_admin, fecha, hora, data, link) 
                values ($g_idsede, $g_us, $postBody->idusuario_admin, curdate(), curtime(), '$data', '$shortUniqueId')";  
            $bd->xConsulta_NoReturn($sql);     
            
            echo json_encode(array('success' => true, 'link' => $shortUniqueId));
            break;
        case 4001: // trae la lista de solicitudes atendidas
            $sql="select pr.* from permiso_remoto pr                
                where pr.idsede = $g_idsede and pr.atendido=1 and pr.ejecutado=0
                order by pr.idpermiso_remoto desc";
            $bd->xConsulta($sql);
            break;    
        case 50: // guardar areas y mesas
            $postBody = json_decode(file_get_contents('php://input'));            
            $listAreas = $postBody->listAreas;
            
            try {
                $sql = "UPDATE area_mesa set estado='1' WHERE idsede = $g_idsede";
                $bd->xConsulta_NoReturn($sql);
                
    
                $bd->prepare("INSERT INTO area_mesa(idsede, idorg, descripcion, idimpresora_precuenta, titulo, num_mesa_ini, num_mesa_fin) VALUES ($g_idsede, $g_ido, '', 0, ?, ?, ?)");
                foreach ($listAreas as $item) {                    
                    $bd->execute([
                        $item->titulo,
                        $item->desde,
                        $item->hasta
                    ]);                
                }

                $bd->xCommit();
                                    
                echo json_encode(array('success' => true));
            }
            catch (Exception $e) {
                $bd->rollBack();
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
            
            break;    
        case 5001: // load areas
            $sql="select idarea_mesa, titulo, REPLACE(titulo, ' ', '') AS title, descripcion, num_mesa_ini as desde, num_mesa_fin as hasta, idimpresora_precuenta  from area_mesa where idsede = $g_idsede and estado = 0";
            $bd->xConsulta($sql);
            break;        
        case 60: // cupones
            $postBody = json_decode(file_get_contents('php://input'));

            if ($postBody->idcupon != 0) {
                // Actualizar cupon
                $idcupon = $postBody->idcupon;
                $sql = "UPDATE cupon SET idsede = $g_idsede, idusuario = $g_us, fecha_creacion = NOW(), fecha_inicio = '$postBody->fecha_inicio', fecha_termina = '$postBody->fecha_fin', fecha_inicio_emitir = '$postBody->fecha_inicio_emitir', titulo = '$postBody->titulo', descripcion = '$postBody->descripcion', is_automatico = '$postBody->is_automatico', cantidad_maxima = '$postBody->cantidad_maxima', cupon_manual = '$postBody->cupon_manual', importe_minimo = '$postBody->importe_minimo', solo_clientes = '$postBody->solo_clientes' WHERE idcupon = $postBody->idcupon";
                $bd->xConsulta($sql);
            
                // Eliminar todos los detalles existentes
                $sql = "DELETE FROM cupon_detalle WHERE idcupon = $postBody->idcupon";
                $bd->xConsulta($sql);
            } else {
                // insertar cupon
                $sql="insert into cupon(idsede, idusuario, fecha_creacion, fecha_inicio, fecha_termina, fecha_inicio_emitir, titulo, descripcion, is_automatico, cantidad_maxima, cupon_manual, importe_minimo, solo_clientes) 
                    values ($g_idsede, $g_us, NOW(), '$postBody->fecha_inicio','$postBody->fecha_fin','$postBody->fecha_inicio_emitir', '$postBody->titulo', '$postBody->descripcion' , '$postBody->is_automatico', '$postBody->cantidad_maxima', '$postBody->cupon_manual', '$postBody->importe_minimo', '$postBody->solo_clientes')";
                $idcupon = $bd->xConsulta_UltimoId($sql);
    
                
            }

            // insertar detalle
            $listDetalle = $postBody->listDetalle;
            foreach ($listDetalle as $item) {
                $iditem = isset($item->iditem) ? $item->iditem : '0';
                $idproducto_stock = isset($item->idproducto_stock) ? $item->idproducto_stock : '0';
                $idseccion = isset($item->idseccion) ? $item->idseccion : '0';

                $sql = "insert into cupon_detalle(idcupon, tipo, iditem, idproducto_stock, idseccion, precio, dsct,precio_final,tipo_dsct, descripcion) 
                        values ($idcupon, '$item->descripcion_tipo', $iditem, $idproducto_stock, $idseccion, '$item->precio', '$item->descuento', '$item->precio_final', '$item->tipo_descuento', '$item->descripcion_producto')";
                $bd->xConsulta($sql);
            }


            echo json_encode(array('success' => 'ok'));
            break;
        case 6001: // load list cupones
            $sql="select c.idcupon, c.activo, DATE_FORMAT(c.fecha_creacion, '%d/%m/%Y') fecha_creacion, DATE_FORMAT(c.fecha_inicio, '%d/%m/%Y') fecha_inicio, DATE_FORMAT(c.fecha_termina, '%d/%m/%Y')fecha_termina, DATE_FORMAT(c.fecha_inicio_emitir, '%d/%m/%Y') fecha_inicio_emitir, c.titulo, c.is_automatico, c.cantidad_maxima, c.cupon_manual, c.descripcion, c.cantidad_emitido, c.cantidad_activado , u.usuario
                from cupon c 
                inner join usuario u on c.idusuario = u.idusuario
                where c.idsede = $g_idsede
                order by c.idcupon desc";
            $bd->xConsulta($sql);
            break;
        case 6002: // load datos de cupon para modificar
            $postBody = json_decode(file_get_contents('php://input'));
            $idcupon = $postBody->idcupon;

            $sql="select *
                from cupon c                 
                where c.idsede = $g_idsede and c.idcupon = $idcupon";
            
            $datosCupon = json_decode($bd->xConsulta3($sql));

            // detalles del cupon
            $sql1 = "select cd.*
                from cupon_detalle cd                 
                where cd.idcupon = $idcupon";

            $datosDetalle = json_decode($bd->xConsulta3($sql1));

            echo json_encode(array('cupon' => $datosCupon, 'list' => $datosDetalle));

            break;
        case 6003: //activar o desactivar cupon
            $postBody = json_decode(file_get_contents('php://input'));
            $sql="update cupon set activo = $postBody->activo where idcupon = $postBody->idcupon";
            $bd->xConsulta($sql);
            break;
        case 6004: // cargar cupones activos y que esten en rango de fecha
            $sql= "SELECT 
                    c.idcupon, 
                    c.titulo, 
                    c.descripcion, 
                    c.fecha_inicio, 
                    c.fecha_termina, 
                    c.fecha_inicio_emitir, 
                    c.cupon_manual, 
                    c.cantidad_maxima, 
                    c.importe_minimo,
                    c.is_automatico,
                    c.solo_clientes,
                    JSON_ARRAYAGG(JSON_OBJECT('tipo_dsct',cd.tipo_dsct, 'dsct', cd.dsct, 'descripcion', cd.descripcion, 'iditem', cd.iditem, 'idseccion', cd.idseccion, 'idproducto_stock', cd.idproducto_stock)) AS cupon_detalle
                FROM 
                    cupon c 
                INNER JOIN 
                    cupon_detalle cd ON c.idcupon = cd.idcupon
                WHERE 
                    c.idsede = $g_idsede
                    AND c.activo = 0 
                    AND c.fecha_inicio_emitir <= CURDATE() 
                    AND c.fecha_termina >= CURDATE() 
                    AND c.estado=0
                GROUP BY 
                    c.idcupon;";
            $bd->xConsulta($sql);
            break;
        case 6005: //generar codigo cupon numeros aleatorios de 5 digitos
            $postBody = json_decode(file_get_contents('php://input'));
            $codigo = rand(10000, 99999);
            
            // guardar en la tabla cupon_codigo
            $sql = "insert into cupon_codigo(idcupon, idsede, codigo, fecha_creacion, fecha_uso, activado, estado) 
                values ($postBody->idcupon, $g_idsede, '$codigo', now(), '', 0, 0)";
            $bd->xConsulta_NoReturn($sql);

            echo json_encode(array('codigo' => $codigo));
            break;
        case 6006: // busca el cupon dado en cupon_codigo
            $postBody = json_decode(file_get_contents('php://input'));
            $codigo = $postBody->codigo;
            $sql = "select cc.idcupon_codigo, cc.idcupon, cc.codigo, cc.fecha_creacion, cc.fecha_uso, cc.activado, cc.estado, c.titulo, c.descripcion, c.fecha_inicio, c.fecha_termina, c.fecha_inicio_emitir, c.cupon_manual, c.cantidad_maxima, c.is_automatico
                from cupon_codigo cc
                inner join cupon c on c.idcupon = cc.idcupon
                where cc.codigo = '$codigo' and cc.idsede = $g_idsede and cc.estado = 0";
            $bd->xConsulta($sql);
            break;
        case 6007: // cupon canjeado
            $postBody = json_decode(file_get_contents('php://input'));
            $idcupon = $postBody->idcupon;
            $idcupon_codigo = $postBody->idcupon_codigo;
            if (isset($idcupon_codigo) ) {
                $sql = "update cupon_codigo set activado = 1, fecha_uso = now(), idusuario = $g_us where idcupon_codigo = $idcupon_codigo and idsede = $g_idsede";
                $bd->xConsulta_NoReturn($sql);
            }

            // aumentar la cantidad de activaciones del cupon
            $sql = "update cupon set cantidad_activado = cantidad_activado + 1 where idcupon = $idcupon";
            $bd->xConsulta_NoReturn($sql);

            echo json_encode(array('success' => 'ok'));            
            break;
        case 6008: // contar cupon emitido
            $postBody = json_decode(file_get_contents('php://input'));
            $idcupon = $postBody->idcupon;
            $sql = "update cupon set cantidad_emitido = cantidad_emitido + 1 where idcupon = $idcupon";
            $bd->xConsulta($sql);
            break;
        case 70: // historial consumo cliente
            $postBody = json_decode(file_get_contents('php://input'));
            $yy = $postBody->yy;
            $idsCliente = $postBody->ids;

            $sql = "select concat(min(idregistro_pago),',',max(idregistro_pago)) from registro_pago rp 
                where idsede=$g_idsede and YEAR(fecha_hora) = '$yy' limit 1";
            $idregistro_pago_yy = $bd->xDevolverUnDato($sql);
            $minId = explode(',', $idregistro_pago_yy)[0];
            $maxId = explode(',', $idregistro_pago_yy)[1];

            $sql = "
                SELECT  cs.idcliente, SUBSTRING_INDEX(rp.fecha, ' ', 1) fecha_consumo, sum(rp.total) total 
                from cliente_sede cs 
                inner join cliente c on c.idcliente = cs.idcliente 
                inner join registro_pago rp on rp.idcliente = cs.idcliente
                where cs.idsede = $g_idsede and rp.idregistro_pago between $minId and $maxId
                and c.idcliente in ($idsCliente) and c.nombres != ''
                GROUP by fecha_consumo
                order by rp.idregistro_pago desc
            ";
            
            $listConsumo = json_decode($bd->xConsulta3($sql));

            $sql= "SELECT  sum(pd.cantidad_r) cantidad_item, pd.descripcion, sum(pd.ptotal_r)  total_item
                from registro_pago rp 
                inner join registro_pago_pedido rpp on rpp.idregistro_pago = rp.idregistro_pago 
                inner join pedido_detalle pd on pd.idpedido_detalle = rpp.idpedido_detalle 
                where rp.idsede = $g_idsede and rp.idregistro_pago between $minId and $maxId
                    and rp.idcliente in ($idsCliente)
                GROUP by pd.iditem
                order by total_item desc, cantidad_item desc limit 10";

            $listProductos = json_decode($bd->xConsulta3($sql));

            echo json_encode(array('consumo' => $listConsumo, 'productos' => $listProductos));
                    
            break;        
    }
?>    