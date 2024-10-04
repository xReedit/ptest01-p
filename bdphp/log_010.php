<?php

    // produccion

    session_start();
	header('Content-Type: application/json;charset=utf-8');
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");
	//header("Cache-Control: no-cache,no-store");
	// header("Access-Control-Allow-Origin: *");


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
    }
?>