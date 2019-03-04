<?php
    //log registrar peidod y pago
    // session_set_cookie_params('4000'); // 1 hour
    // session_regenerate_id(true); 
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
        case 1:// load sedes
            $sql = "select idsede, nombre, ciudad from sede where idorg = ".$_SESSION['ido']." and estado=0 ";
            $bd->xConsulta($sql);
            break;
        case 2:// load comprobantes generales
            // $sql = "SELECT * FROM tipo_comprobante where estado=0";
            $sql = "
            SELECT tpcs.idtipo_comprobante_serie, tpcs.serie, tpcs.correlativo,tp.*
			from tipo_comprobante_serie tpcs
				inner join tipo_comprobante tp using(idtipo_comprobante)
				inner join sede s on s.idsede = tpcs.idsede
            where (tpcs.idorg=".$_SESSION['ido']." and tpcs.idorg=".$_SESSION['idsede'].") and tpcs.estado=0
            ";            
            $bd->xConsulta($sql);
            break;
        case '201':// solo de tipo_comprobantes
            $sql="SELECT * from tipo_comprobante where estado=0";
            $bd->xConsulta($sql);
            break;
        case 3:// load categoria
            $sql="SELECT idcategoria, descripcion FROM categoria WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
            $bd->xConsulta($sql);
            break;
    }