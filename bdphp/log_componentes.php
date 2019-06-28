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
            $sql = "select idorg, idsede, nombre, ciudad from sede where idorg = ".$_SESSION['ido']." and estado=0 ";
            $bd->xConsulta($sql);
            break;
        case 2:// load comprobantes generales
            // $sql = "SELECT * FROM tipo_comprobante where estado=0";
            $sql = "
            SELECT tpcs.idtipo_comprobante_serie, tpcs.serie, tpcs.correlativo, tpcs.facturacion_correlativo_api, tp.*
			from tipo_comprobante_serie tpcs
				inner join tipo_comprobante tp using(idtipo_comprobante)
				inner join sede s on s.idsede = tpcs.idsede
            where (tpcs.idorg=".$_SESSION['ido']." and tpcs.idsede=".$_SESSION['idsede'].") and tpcs.estado=0
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
        case 4:// load tipo de gasto
            $sql="SELECT idtipo_gasto_detalle, descripcion FROM tipo_gasto_detalle WHERE idtipo_gasto=".$_POST['id']." and estado=0 order by descripcion";
            $bd->xConsulta($sql);
            break;
        case 5:// load tipo de ingreos
            $sql="SELECT idtipo_ingreso, descripcion FROM tipo_ingreso WHERE estado=0 order by descripcion";
            $bd->xConsulta($sql);
            break;
        case 6:// load tipo pago
            $sql="SELECT idtipo_pago, descripcion FROM tipo_pago WHERE estado=0";
            $bd->xConsulta($sql);
            break;
        case 7://load clientes
			$sql="SELECT * FROM cliente where (idorg=".$_SESSION['ido'].") AND estado=0 order by nombres";
			$bd->xConsulta($sql);
            break;
        case 8://load cargos
			$sql="SELECT * FROM cargo where (idorg=".$_SESSION['ido'].") AND estado=0 order by descripcion";
			$bd->xConsulta($sql);
            break;
        case 9://load colaboradores
			$sql="SELECT * FROM colaborador where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") AND estado=0 order by nombres";
			$bd->xConsulta($sql);
            break;
        case 10://load planilla_periodo
			$sql="SELECT * FROM planilla_periodo where estado=0";
			$bd->xConsulta($sql);
			break;
    }