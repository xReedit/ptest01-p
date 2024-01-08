<?php

    // produccion

    session_start();
	//header("Cache-Control: no-cache,no-store");
	// header("Access-Control-Allow-Origin: *");
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
    }
?>