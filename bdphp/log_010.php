<?php

    // produccion

use function PHPSTORM_META\sql_injection_subst;

	require_once __DIR__ . '/SecurityGuard.php';
	SecurityGuard::verificarAcceso();
	header('Content-Type: application/json;charset=utf-8');
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");
	//header("Cache-Control: no-cache,no-store");
	// header("Access-Control-Allow-Origin: *");


    // a = registro pedido | d=registro cliente | b=registro pago total | c=registro pago parcial
    $op = isset($_POST['op']) ? $_POST['op'] : null;
    if (!isset($op)) { 
        $op = isset($_GET['op']) ? $_GET['op'] : null;
    }
	if (!isset($op)) {
		$postBody = json_decode(file_get_contents('php://input'));
		$op = isset($postBody->op) ? $postBody->op : null;
	}

    $g_ido = $_SESSION['ido'];
	$g_idsede = $_SESSION['idsede'];
	$g_us = $_SESSION['idusuario'];

    switch ($op) {
        case 'get-promociones-activas':
            // Obtener promociones activas
            $fecha_actual = date('Y-m-d');
            $hora_actual = date('H:i');
            $dia_semana = date('N'); // 1 (lunes) a 7 (domingo)
            
            // Consulta para obtener promociones activas según fecha, hora y día de la semana
            $sql = "SELECT p.*, ps.idseccion, ps.iditem, ps.porc_descuento, ps.cantidad, ps.iditem_subitem, 
                    ps.idproducto_stock, ps.is_nxn, ps.cantidad_x
                    FROM promociones p 
                    LEFT JOIN promociones_seccion ps ON p.idpromocion = ps.idpromocion
                    WHERE p.estado = 1 
                    AND p.idsede = $g_idsede
                    AND p.fecha_inicio <= '$fecha_actual' 
                    AND p.fecha_fin >= '$fecha_actual'
                    AND p.hora_inicio <= '$hora_actual' 
                    AND p.hora_fin >= '$hora_actual'
                    AND FIND_IN_SET($dia_semana, p.dias_semana) > 0
                    ORDER BY p.idpromocion";
            
            $promociones = $bd->xConsulta($sql);
            
            // Procesar los resultados para agrupar por promoción
            $lista_promociones = [];
            $promocion_actual = null;
            
            foreach ($promociones as $promo) {
                if ($promocion_actual === null || $promocion_actual['idpromocion'] != $promo['idpromocion']) {
                    // Nueva promoción
                    if ($promocion_actual !== null) {
                        $lista_promociones[] = $promocion_actual;
                    }
                    
                    // Extraer secciones
                    $secciones = '';
                    if ($promo['idseccion']) {
                        $secciones = $promo['idseccion'];
                    }
                    
                    $promocion_actual = [
                        'idpromocion' => $promo['idpromocion'],
                        'parametros' => [
                            'header' => [
                                'titulo' => $promo['titulo'],
                                'descripcion' => $promo['descripcion'],
                                'icon' => $promo['icon'],
                                'establecio_img' => $promo['establecio_img']
                            ],
                            'body' => [
                                'f_inicio' => $promo['fecha_inicio'],
                                'f_fin' => $promo['fecha_fin'],
                                'h_inicio' => $promo['hora_inicio'],
                                'h_fin' => $promo['hora_fin'],
                                'dias_semana' => $promo['dias_semana'],
                                'solo_app' => $promo['solo_app'],
                                'importe_consumo_min' => $promo['importe_consumo_min'],
                                'num_primeros_pedidos' => $promo['num_primeros_pedidos']
                            ]
                        ],
                        'img' => $promo['img'],
                        'secciones' => $secciones,
                        'productos' => null,
                        'items' => null,
                        'lista' => []
                    ];
                }
                
                // Agregar item a la promoción actual
                if ($promo['idseccion'] || $promo['iditem']) {
                    $promocion_actual['lista'][] = [
                        'idseccion' => $promo['idseccion'],
                        'iditem' => $promo['iditem'],
                        'porc_descuento' => $promo['porc_descuento'],
                        'cantidad' => $promo['cantidad'],
                        'iditem_subitem' => $promo['iditem_subitem'],
                        'idproducto_stock' => $promo['idproducto_stock'],
                        'is_nxn' => $promo['is_nxn'],
                        'cantidad_x' => $promo['cantidad_x']
                    ];
                }
            }
            
            // Agregar la última promoción si existe
            if ($promocion_actual !== null) {
                $lista_promociones[] = $promocion_actual;
            }
            
            // Devolver resultado
            if (count($lista_promociones) > 0) {
                echo json_encode(['success' => true, 'lista_promociones' => $lista_promociones]);
            } else {
                echo json_encode(['success' => false, 'mensaje' => 'No hay promociones activas']);
            }
            break;
        case 'change-tipo-pago-registro-pago':
            $postBody = json_decode(file_get_contents('php://input'));
            $sql = "insert into cambios_tipo_pago (idusuario_admin, idusuario_solicita, fecha, hora, idsede, idtipo_pago_before, idtipo_pago_after, importe, idregistro_pago, idregistro_pago_detalle)
                    values ($postBody->idusuario_admin, $g_us, curdate(), curtime(), $g_idsede, $postBody->idtipo_pago_before, $postBody->idtipo_pago_after, $postBody->importe, $postBody->idregistro_pago, $postBody->idregistro_pago_detalle)";
            $bd->xConsulta_NoReturn($sql);

            // actualiza la tabla permiso_remoto como ejecutado
            $sql = "update permiso_remoto set ejecutado = '1' where idpermiso_remoto = $postBody->idpermiso_remoto";    
            $bd->xConsulta_NoReturn($sql);

            // cambia el tipo de pago en registro_pago_detalle
            $sql = "update registro_pago_detalle set idtipo_pago = $postBody->idtipo_pago_after, permission_change = '0' where idregistro_pago_detalle = $postBody->idregistro_pago_detalle";    
            $bd->xConsulta_NoReturn($sql);

            // reponder ok
            echo json_encode(array('success' => true, 'message' => 'ok'));

            break;  
        case 'show-permiso-cerrar-caja':
            $sql= "call procedure_cierre_show_importe_caja($g_idsede,$g_us)";            
            $bd->xConsulta($sql);

            // echo json_encode(array('success' => true, 'message' => $sql));
            break;       
        case 'check-cuentas-cobrar':
            $sql= "call procedure_check_pedidos_cobrar($g_idsede)";
            $bd->xConsulta($sql);
            break;
        case 'get-und-conversion-by-id':
            $postBody = json_decode(file_get_contents('php://input'));
            $sql = "select * from unidad_medida where idunidad_medida = $postBody->id";
            $bd->xConsulta($sql);
            break;
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
            // // $uk_base = $producto[0]['uk_base'];
            // // $uc_base = $producto[0]['uc_base'];

            $precio_unidad = $precio_actual / $stock_actual;
            $costo_conversion = $precio_unidad / $factor_conversion;

            $sql = "update producto set costo_conversion = $costo_conversion where idproducto = $postBody->idproducto";
            $bd->xConsulta_NoReturn($sql);
            
            echo json_encode(array('success' => true, 'costo_conversion' => $costo_conversion));

            
            break;
        
        case 'set-tipo-precio-producto':
            $postBody = json_decode(file_get_contents('php://input'));
            $idproducto_precio = $postBody->idproducto_precio;
            if ($idproducto_precio == 0) {
                // insertar nuevo tipo de precio
                $sql = "insert into producto_precio (idproducto_stock, idproducto, idtipo_precio, precio) values ($postBody->idproducto_stock, $postBody->idproducto, $postBody->idtipo_precio, $postBody->precio)";
                $bd->xConsulta_NoReturn($sql);
                $idproducto_precio = $bd->lastInsertId();
            } else {
                // actualizar tipo de precio
                $sql = "update producto_precio set idtipo_precio = $postBody->idtipo_precio, precio = $postBody->precio where idproducto_precio = $idproducto_precio";
                $bd->xConsulta_NoReturn($sql);
            }

            // update producto precio venta
            if ($postBody->titulo === 'GENERAL') {
                $sql = "update producto set precio_venta = $postBody->precio where idproducto = $postBody->idproducto";
                $bd->xConsulta_NoReturn($sql);
            }
                        
            echo json_encode(array('success' => true, 'idproducto_precio' => $idproducto_precio));
            break;
        case 'get-tipo-precio-producto':            
            $sql = "select * from tipo_precio where idsede = $g_idsede and estado=0 and visible=1";
            $bd->xConsulta($sql);
            break;
        case 'get-list-pinpad':
            $sql = "select * from sede_pinpad where idsede = $g_idsede and estado=0";
            $bd->xConsulta($sql);
            break;
        case 'add-pinpad':
            $postBody = json_decode(file_get_contents('php://input'));            
            $sql = "insert into sede_pinpad (idsede, descripcion, pinpad_sn, area, fecha_registro, idusuario) values ($g_idsede, '$postBody->descripcion', '$postBody->pinpad_sn', '$postBody->area', NOW(), $g_us)";
            $bd->xConsulta_NoReturn($sql);
            echo json_encode(array('success' => true, '$postBody', $sql));
            break;
        case 'remove-pinpad':
            $postBody = json_decode(file_get_contents('php://input'));            
            $sql = "update sede_pinpad set estado = 1 where idsede_pinpad = $postBody->idsede_pinpad";
            $bd->xConsulta_NoReturn($sql);
            echo json_encode(array('success' => true));
            break;
        case 'save-transaccion-pinpad':
            $postBody = json_decode(file_get_contents('php://input'));            
            $data = json_encode($postBody->response_pinpad);
            $data = addslashes($data);        
            // $data = $bd->real_escape_string($data);         
            $sql = "insert into registro_pago_pinpad (idregistro_pago, idsede, data_response) values ($postBody->idregistro_pago, $g_idsede, '$data');";
            // $sql = "call procedure_save_transaccion_pinpad($g_idsede, $postBody->idregistro_pago, '$data')";
            $bd->xConsulta($sql);
            echo json_encode(array('success' => $sql));
            break;
        case 'get-data-pago-pinpad':
            $postBody = json_decode(file_get_contents('php://input'));            
            $sql = "select * from registro_pago_pinpad where idregistro_pago = $postBody->idregistro_pago";
            $bd->xConsulta($sql);
            break;
        case 'save-remove-pinpad':
            $postBody = json_decode(file_get_contents('php://input'));    
            $sql = "update registro_pago_pinpad set estado = 1, anulado=1, motivo_anulacion='$postBody->motivo' where idregistro_pago = $postBody->idregistro_pago";
            $bd->xConsulta($sql);  
            
            // update registro_pago
            // $sql = "update registro_pago set estado = 1, motivo_anular='$postBody->motivo' where idregistro_pago = $postBody->idregistro_pago";
            // $bd->xConsulta($sql);
            break;
        case 'get-list-pedidos-despachados':
            $postBody = json_decode(file_get_contents('php://input'));
            $sql = "call procedure_zona_despacho_pedidos_despachados($g_idsede, '$postBody->idzona')";
            $bd->xConsulta($sql);
            // echo json_encode(array('success' => $sql));
            break;
        case 'get-pedido-despachado':
            $postBody = json_decode(file_get_contents('php://input'));
            $sql = "select cantidad_r cantidad, descripcion, ptotal_r total, borrado, estado from pedido_detalle pd where idpedido = $postBody->idpedido";
            $bd->xConsulta($sql);
            break;
        case 'get-holding':
            $sql = "select * from sede_holding where idsede=$g_idsede";
            $bd->xConsulta($sql);
            break;
        case 'get-load-holding-marcas':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "select sdm.*, s.nombre nom_sede from sede_holding_marcas sdm
	            inner join sede s on s.idsede = sdm.idsede_marca 
                where sdm.idsede_holding = $data->idsede_holding and sdm.estado=0";
            $bd->xConsulta($sql);
            break;
        case 'set-holding':
            $data = json_decode(file_get_contents('php://input'));
            $postBody = $data->holding;
            
            if (isset($postBody->idsede_holding)) {
                $sql="Update sede_holding set idsede = $postBody->idsede, nombre = '$postBody->nombre', ciudad = '$postBody->ciudad' where idsede_holding = $postBody->idsede_holding";
                $bd->xConsulta_NoReturn($sql);
            } else {
                $sql="Insert into sede_holding (idsede, idorg, nombre, ciudad) values ($postBody->idsede, $g_ido, '$postBody->nombre', '$postBody->ciudad')";
                $bd->xConsulta_NoReturn($sql);
                $postBody->idsede_holding = $bd->lastInsertId();
            }      
            
            // update sede como holding
            $sql_sede = "update sede set is_holding = 1 where idsede = $postBody->idsede";
            $bd->xConsulta_NoReturn($sql_sede);
            
            echo json_encode(array('success' => true, 'idsede_holding' => $postBody->idsede_holding));
            break;
        case 'set-holdin-marcas':
            $data = json_decode(file_get_contents('php://input'));
            $postBody = $data;
            $ids_marcas_remove = '';

            foreach ($postBody->marcas as $marca) {
                if ($marca->is_remove) {
                    $sql = "update sede_holding_marcas set estado = 1 where idsede_marca = $marca->idsede_marca";
                    $bd->xConsulta_NoReturn($sql);
                } else {
                    if ($marca->is_new) {                        
                        $sql = "insert into sede_holding_marcas (idsede_holding, idsede_marca, fecha_ingreso, imagen_url_comercial, nombre_comercial, oferta_comercial) 
                        values ($marca->idsede_holding, $marca->idsede_marca, NOW(), '$marca->imagen_url_comercial', '$marca->nombre_comercial', '$marca->oferta_comercial')";
                        $bd->xConsulta_NoReturn($sql);
                    }
                }
            }
            echo json_encode(array('success' => true, 'sql' => $sql)); 
            break;
        case 'set-change-image-marca-holding':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "update sede_holding_marcas set imagen_url_comercial = '$data->url_image' where idsede_holding_marcas = $data->idsede_holding_marcas";
            $bd->xConsulta_NoReturn($sql);
            echo json_encode(array('success' => true, 'sql' => $sql));
            break;
        
        // holding
        case 'get-pedido-holding-by-id':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "select pj.fecha, pj.idpedido, p.idregistro_pago, u.nombres, u.usuario as nom_us, s.nombre as marca, p.nummesa, p.referencia, p.total_r, pj.pedido_json 
                    ,pj.debitado, pj.idsede, pj.idregistro_pago_marca
                from pedido_json pj 
                inner join pedido p on pj.idpedido = p.idpedido
                inner join sede s on pj.idsede = s.idsede 
                inner join usuario u on p.idusuario = u.idusuario 
                where pj.idpedido = $data->idpedido";

            $bd->xConsulta($sql);         
            break;
        case 'get-pedidos-holding-by-day':
            $data = json_decode(file_get_contents('php://input'));

            $sql_hora_cierre = "select hora_cierre_dia d1 from sede_opciones where idsede = $idsede";
            $hora_cierre = $bd->xDevolverUnDato($sql_hora_cierre);

            // si no se obtuvo un registro, establecer la hora de cierre a '00:00:00'
            if (!isset($hora_cierre)) {
                $hora_cierre = '00:00:00';
            }
            
            $fecha = $data->fecha; 
            $fecha_hora_inicio = date('Y-m-d H:i:s', strtotime($fecha . ' ' . $hora_cierre));
            $fecha_hora_cierre = date('Y-m-d H:i:s', strtotime($fecha_hora_inicio . ' +1 day'));

            $sql = "select pj.fecha, pj.idpedido, p.idregistro_pago, u.nombres, u.usuario as nom_us, s.nombre as marca, p.nummesa, p.referencia, p.total_r, pj.pedido_json
                    ,pj.debitado, pj.idsede, pj.idregistro_pago_marca
                from pedido_json pj 
                inner join pedido p on pj.idpedido = p.idpedido
                inner join sede s on pj.idsede = s.idsede 
                inner join usuario u on p.idusuario = u.idusuario 
                where pj.fecha between '$fecha_hora_inicio' and '$fecha_hora_cierre'
                and pj.idsede_holding = $data->idsede_holding";

            $bd->xConsulta($sql);         
            break;
        case 'set-debitar-pedido-holding':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "update pedido_json set debitado = 1 where idpedido in ($data->idpedidos)";
            $bd->xConsulta_NoReturn($sql);    
            
            // insertar en registro_pago_marca
            $sql = "insert into registro_pago_marca(fecha, resumen, idusuario, idsede, num_operacion) 
                values (NOW(), '$data->resumen', $g_us, $data->idsede, $data->numoperacion)";

            $bd->xConsulta($sql);
            
            break;

        case 'get-comision-metodo-pago-holding':
            $data = json_decode(file_get_contents('php://input'));
            // $sql = "select idtipo_pago, comision from sede_holding_metodo_pago where idsede = $g_idsede";
            $sql = "select s.idtipo_pago, s.comision from sede_holding_marcas shm
                    inner join sede_holding_metodo_pago s on s.idsede_holding = shm.idsede_holding 
                where idsede_marca = $g_idsede and s.comision > 0";
            $bd->xConsulta($sql);         
            break;

        case 'get-registro-pago-marca':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "select fecha, num_operacion from registro_pago_marca where idregistro_pago_marca = $data->idregistro_pago_marca";
            $bd->xConsulta($sql);         
            break;
        case 'get-cuenta-debitar-marca':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "select cuenta_debitar from sede_holding_marcas where idsede_marca = $data->idsede_marca";
            $bd->xConsulta($sql);         
            break;
        
        case 'get-pedidos-holding-meseros-by-day':        
            $data = json_decode(file_get_contents('php://input'));

            $sql_hora_cierre = "select hora_cierre_dia d1 from sede_opciones where idsede = $idsede";
            $hora_cierre = $bd->xDevolverUnDato($sql_hora_cierre);

            // si no se obtuvo un registro, establecer la hora de cierre a '00:00:00'
            if (!isset($hora_cierre)) {
                $hora_cierre = '00:00:00';
            }

            // verificar si es holding
            $where_holding = $data->idsede_holding == 0 ? 
                "pj.idsede = $g_idsede" : 
                "pj.idsede_holding = $data->idsede_holding";

            $fecha = $data->fecha == '' ? date('Y-m-d') : $data->fecha;
            $fecha_hora_inicio = date('Y-m-d H:i:s', strtotime($fecha . ' ' . $hora_cierre));
            $fecha_hora_cierre = date('Y-m-d H:i:s', strtotime($fecha_hora_inicio . ' +1 day'));
            
            $sql = "select rp.fecha_hora fecha, rp.idregistro_pago, pj.idpedido, tp.idtipo_pago, tp.descripcion des_tp, tp.img as icon, rpd.importe, u.nombres as nom_mozo, u.usuario nom_us, u.idusuario, p.nummesa, p.referencia, s.nombre marca, s.idsede, pj.cerrado_mesero
	            from pedido_json pj 
                inner join pedido p on pj.idpedido = p.idpedido 
                inner join registro_pago rp on rp.idregistro_pago = p.idregistro_pago
                inner join registro_pago_detalle rpd on rp.idregistro_pago = rpd.idregistro_pago 
                inner join tipo_pago tp on rpd.idtipo_pago = tp.idtipo_pago 
                inner join usuario u on p.idusuario = u.idusuario 
                inner join sede s on pj.idsede = s.idsede
                where 
                rp.fecha_hora between '$fecha_hora_inicio' and '$fecha_hora_cierre' and 
                $where_holding
                GROUP by rp.idregistro_pago, tp.idtipo_pago";

            $bd->xConsulta($sql);         
            break;

        case 'get-pedidos-holding-meseros-by-id':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "select rp.fecha_hora fecha, rp.idregistro_pago, pj.idpedido, tp.idtipo_pago, tp.descripcion des_tp, tp.img as icon, rpd.importe, u.nombres as nom_mozo, u.usuario nom_us, u.idusuario, p.nummesa, p.referencia, s.nombre marca, s.idsede, pj.cerrado_mesero
                from pedido_json pj 
                inner join pedido p on pj.idpedido = p.idpedido 
                inner join registro_pago rp on rp.idregistro_pago = p.idregistro_pago
                inner join registro_pago_detalle rpd on rp.idregistro_pago = rpd.idregistro_pago 
                inner join tipo_pago tp on rpd.idtipo_pago = tp.idtipo_pago 
                inner join usuario u on p.idusuario = u.idusuario 
                inner join sede s on pj.idsede = s.idsede
                where 
                pj.idpedido = $data->idpedido                                 
                GROUP by rp.idregistro_pago, tp.idtipo_pago";                

            $bd->xConsulta($sql);         
            break;

        case 'set-cerrar-pedidos-meseros':
            $data = json_decode(file_get_contents('php://input'));
            $sql = "update pedido_json set cerrado_mesero = 1 where idpedido in ($data->idpedidos)";
            $bd->xConsulta_NoReturn($sql);    
            break;

    case 'get-pedidos-marca-by-day':
        $data = json_decode(file_get_contents('php://input'));

        $sql_hora_cierre = "select hora_cierre_dia d1 from sede_opciones where idsede = $idsede";
        $hora_cierre = $bd->xDevolverUnDato($sql_hora_cierre);

        // si no se obtuvo un registro, establecer la hora de cierre a '00:00:00'
        if (!isset($hora_cierre)) {
            $hora_cierre = '00:00:00';
        }

        $fecha = $data->fecha;
        $fecha_hora_inicio = date('Y-m-d H:i:s', strtotime($fecha . ' ' . $hora_cierre));
        $fecha_hora_cierre = date('Y-m-d H:i:s', strtotime($fecha_hora_inicio . ' +1 day'));

        $sql = "select p.idpedido, p.fecha_hora fecha, rp.idregistro_pago, u.nombres, u.usuario nom_us , COALESCE(pcl.table_number , p.nummesa) nummesa, p.referencia
                , p.total_r as total, sede_usuario.is_holding			
                , p.idusuario, sede_usuario.idsede idsede_usuario, sede_usuario.idorg idorg_usuario
                , pcl.table_number 
                , COALESCE(pcl.estado, 0) estado
				, JSON_ARRAYAGG(JSON_OBJECT(
					'id', rpd.idtipo_pago,
					'descripcion', tp.descripcion,
					'icon', tp.img,
					'amount_real', rpd.importe
					)) tipo_pago
	            from pedido p  
                inner join registro_pago rp on rp.idregistro_pago = p.idregistro_pago
                inner join registro_pago_detalle rpd on rp.idregistro_pago = rpd.idregistro_pago 
                inner join tipo_pago tp on rpd.idtipo_pago = tp.idtipo_pago 
                inner join usuario u on p.idusuario = u.idusuario
                inner join sede sede_usuario on u.idsede = sede_usuario.idsede                
                left join pedido_codigo_localizador pcl on pcl.idpedido = p.idpedido                
                where p.fecha_hora between '$fecha_hora_inicio' and '$fecha_hora_cierre'
                and p.idsede = $g_idsede GROUP by rp.idregistro_pago";

        $bd->xConsulta($sql);
        break;

    case 'get-pedido-marca-by-id':
        $data = json_decode(file_get_contents('php://input'));
        $sql = "select p.idpedido, p.fecha_hora fecha, rp.idregistro_pago, u.nombres, u.usuario nom_us, p.nummesa, p.referencia
                ,p.total_r as total, sede_usuario.is_holding			
                , p.idusuario, sede_usuario.idsede idsede_usuario, sede_usuario.idorg idorg_usuario
                , pcl.table_number 
                , COALESCE(pcl.estado, 0) estado
				, JSON_ARRAYAGG(JSON_OBJECT(
					'id', rpd.idtipo_pago,
					'descripcion', tp.descripcion,
					'icon', tp.img,
					'amount_real', rpd.importe
					)) tipo_pago
	            from pedido p  
                inner join registro_pago rp on rp.idregistro_pago = p.idregistro_pago
                inner join registro_pago_detalle rpd on rp.idregistro_pago = rpd.idregistro_pago 
                inner join tipo_pago tp on rpd.idtipo_pago = tp.idtipo_pago 
                inner join usuario u on p.idusuario = u.idusuario
                inner join sede sede_usuario on u.idsede = sede_usuario.idsede                
                left join pedido_codigo_localizador pcl on pcl.idpedido = p.idpedido                
                where p.idpedido = $data->idpedido GROUP by rp.idregistro_pago";

        $bd->xConsulta($sql);
        break;

    case 'get-is-marca-belongs-holding':
        $data = json_decode(file_get_contents('php://input'));
        $sql = "select idsede_marca isBelongsHolding from sede_holding_marcas shm where idsede_marca = $g_idsede";
        $bd->xConsulta($sql);
        break;

    case 'set-codigo-localizador':
        $data = json_decode(file_get_contents('php://input'));
        $sql = "insert into pedido_codigo_localizador (idsede, idorg, codigo_localizador, idpedido) values ($g_idsede, $g_ido, '$data->codigo_localizador', $data->idpedido)";
        $bd->xConsulta_NoReturn($sql);
        break;

    case 'set-pedido-listo':
        $data = json_decode(file_get_contents('php://input'));
        $sql_check = "select count(*) as count from pedido_codigo_localizador where idpedido = $data->idpedido";
        $count = $bd->xDevolverUnDato($sql_check);

        if ($count > 0) {
            $sql = "update pedido_codigo_localizador set estado = '2', fecha_hora_listo=NOW() where idpedido = $data->idpedido";
        } else {
            $sql = "insert into pedido_codigo_localizador (idsede, idorg, idpedido, estado, fecha_hora_listo) values ($g_idsede, $g_ido, $data->idpedido, '2', NOW())";
        }

        $bd->xConsulta_NoReturn($sql);
        echo json_encode(array('success' => $sql));
        break;

    case 'set-pedido-entregado':
        $data = json_decode(file_get_contents('php://input'));
        $sql = "update pedido_codigo_localizador set estado = '3' where idpedido = $data->idpedido";
        $bd->xConsulta_NoReturn($sql);
        echo json_encode(array('success' => true));
        break;
    case 'get-holding-opciones-marca':
        $sql = "select * from us_home_opciones where codigo = 'A22'";
        $bd->xConsulta($sql);
        break;
    case 'get-holding-opciones-center':
        $sql = "select * from us_home_opciones where codigo in ('A23', 'A24')";
        $bd->xConsulta($sql);
        break;
    case 'get-holding-metodo-pago':
        $sql = "select sh.*, tp.descripcion as nom_tipo_pago from sede_holding_metodo_pago sh inner join tipo_pago tp on sh.idtipo_pago = tp.idtipo_pago where sh.idsede = $g_idsede and sh.estado = 0";
        $bd->xConsulta($sql);
        break;
    case 'set-holding-metodo-pago':
        $data = json_decode(file_get_contents('php://input'));
        // actualizar si existe id
        if (isset($data->id)) {
            $sql = "update sede_holding_metodo_pago set idtipo_pago = $data->idtipo_pago, comision = $data->comision, estado = $data->estado where idsede_holding_metodo_pago = $data->id";
        } else {
            $sql = "insert into sede_holding_metodo_pago (idsede, idtipo_pago, comision, idsede_holding) values ($g_idsede, $data->idtipo_pago, $data->comision, $data->idsede_holding)";
        }
        $bd->xConsulta_NoReturn($sql);
        echo json_encode(array('success' => true));
        break;
    case 'get-url-servicio-carta':
        echo json_encode(array('url' => 'https://piter-py-gpt-production.up.railway.app/test-carta'));
        break;
    case 'get-promociones': // para el punto de venta
        $sql = "call procedure_get_promciones($g_idsede)";
        $bd->xConsulta($sql);
        break;

    }
?>