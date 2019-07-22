<?php
	// session_set_cookie_params('14400'); // 4 hour
	// session_cache_expire(180); // minutes
	// session_set_cookie_params('4000'); // 1 hour
	// session_regenerate_id(true); 
	session_start();	
	//header("Cache-Control: no-cache,no-store");
	// header('content-type: text/html; charset: utf-8');
	// header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	include "token.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');

	$g_ido = $_SESSION['ido'];
	$g_idsede = $_SESSION['idsede'];
	$fecha_now = date("d/m/Y");
	$hora_now = date("H:i:s");
	
	switch($_GET['op'])
	{
		case 1:// list gastos fijos
			$sql = "
				SELECT cgf.*, FORMAT(cgf.importe,2) total, DATEDIFF(CONCAT(YEAR(NOW()),'-',cgf.mes_ultimo_pago,'-' ,cgf.dia_pago), NOW()) as dif_dia_pago, tpg_d.descripcion as tp_gasto
				from contable_gasto_fijo as cgf
					inner join tipo_gasto_detalle as tpg_d using (idtipo_gasto_detalle)
				where (cgf.idorg=".$g_ido." and cgf.idsede=".$g_idsede.") and cgf.estado=0 order by tpg_d.descripcion
			";
			$bd->xConsulta($sql);
			break;
		case 101: // pagar gasto fijo
			$arrItem=json_encode($_POST['item']);
			$sql = "CALL procedure_registra_pago_gf(".$g_ido.",".$g_idsede.",'".$arrItem."')";			
			$bd->xConsulta($sql);
			break; 
		case 102: // load detalles de pago
			$sql = "select fecha_pago, cuota, importe, doc from contable_gasto_fijo_detalle where idcontable_gasto_fijo = ".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 103: // load gastos variables
			$mm = $_POST['mm'];
			$yy = $_POST['yy'];
			$sql = "
				SELECT cgv.*, FORMAT(cgv.importe,2) total, tpg_d.descripcion as tp_gasto
				from contable_gasto_variable as cgv
					inner join tipo_gasto_detalle as tpg_d using(idtipo_gasto_detalle)
				where (cgv.idorg=".$g_ido." and cgv.idsede=".$g_idsede.") and MONTH(STR_TO_DATE(cgv.fecha_pago, '%d/%m/%Y')) = ".$mm." and YEAR(STR_TO_DATE(cgv.fecha_pago, '%d/%m/%Y')) = ".$yy." and cgv.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 2: // filtrar ingresos por mes
			$mm = $_POST['mm'];
			$yy = $_POST['yy'];
			$sql = "
				SELECT * , FORMAT(cio.importe,2) as total, cio.descripcion as des_concepto, tpi.descripcion as tp_ingreso
				FROM contable_ingreso_otro as cio
					INNER JOIN tipo_ingreso as tpi using (idtipo_ingreso)
				WHERE (cio.idorg=".$g_ido." and cio.idsede=".$g_idsede.") and MONTH(STR_TO_DATE(cio.fecha_ingreso, '%d/%m/%Y')) = ".$mm." and YEAR(STR_TO_DATE(cio.fecha_ingreso, '%d/%m/%Y')) = ".$yy." and cio.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 3: // cuenta por pagar 
			$sql = "
				SELECT c.idcompra, c.f_compra, c.f_pago, c.f_registro, c.a_pagar AS importe, FORMAT(c.a_pagar,2) as total, FORMAT(c.total,2) as total_total, c.pagado, tp.idtipo_pago, tp.descripcion as des_tp, p.idproveedor, p.descripcion as des_proveedor, p.dni
						, DATEDIFF(STR_TO_DATE(c.f_pago, '%d/%m/%Y'), now()) as faltan
				from compra as c
					inner join tipo_pago as tp using (idtipo_pago)
					inner join proveedor as p using(idproveedor)
				where (c.idorg=".$g_ido." and c.idsede=".$g_idsede.") and tp.idtipo_pago=3 and c.pagado=0 and c.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 301: //detalle de cuentas pagar - abonos
			$sql="select idcompra_pago_cuenta, idcompra, fecha, importe, FORMAT(importe,2) as total from compra_pago_cuenta where idcompra=".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 302: // guardar pago cuentas por pagar
			$arrItem=json_encode($_POST['item']);
			$sql = "CALL procedure_pago_cuenta('".$arrItem."')";			
			$bd->xConsulta($sql);
			break;
		case 303:// historial cuentas por pagar
			$pagination = $_POST['pagination'];						
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(p.descripcion, c.f_pago, c.f_ultimo_pago) like '%".$pagination['pageFilter']."%'";
			// $filtroFecha = $fecha === '' ? ' and cierre=0 ' : " AND SUBSTRING_INDEX(fecha,' ',1) = '".$fecha."' ";
			// $filtroFechaCount = $fecha === '' ? '' : " and (SUBSTRING_INDEX(c.fecha,' ',1)= '".$fecha."')";

			$sql = "
				SELECT c.idcompra, c.f_ultimo_pago, c.f_compra, c.f_pago, c.f_registro, c.a_pagar AS importe, FORMAT(c.a_pagar,2) as total, FORMAT(c.total,2) as total_total, c.pagado, tp.idtipo_pago, tp.descripcion as des_tp, p.idproveedor, p.descripcion as des_proveedor, p.dni
						, DATEDIFF(STR_TO_DATE(c.f_ultimo_pago, '%d/%m/%Y'), STR_TO_DATE(c.f_pago, '%d/%m/%Y')) as faltan
				from compra as c
					inner join tipo_pago as tp using (idtipo_pago)
					inner join proveedor as p using(idproveedor)
				where (c.idorg=".$g_ido." and c.idsede=".$g_idsede.")".$filtro." and tp.idtipo_pago=3 and c.pagado=1 and c.estado=0
				order by STR_TO_DATE(c.f_ultimo_pago, '%d/%m/%Y') desc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];				
			
			$sqlCount="
                SELECT count(c.idcompra) as d1 from compra as c inner join proveedor as p using(idproveedor)
                where (c.idorg=".$g_ido." and c.idsede=".$g_idsede.")".$filtro." and c.idtipo_pago=3 and c.pagado=1 and c.estado=0";            
            
			$rowCount = $bd->xDevolverUnDato($sqlCount);
			// echo $sqlCount;
			$rpt = $bd->xConsulta($sql);            
            print $rpt."**".$rowCount;
			break;
		case 304: // cuentas por cobrar
			$pagination = $_POST['pagination'];						
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(c.nombres, rp.fecha, tpc.descripcion) like '%".$pagination['pageFilter']."%'";
			$sql = "
				SELECT rpd.idregistro_pago_detalle, rp.idcliente, rp.fecha, DATEDIFF(STR_TO_DATE(rp.fecha, '%d/%m/%Y'), now()) as pasaron 
					, c.nombres as nomcliente , tpc.descripcion as tipoconsumo, rpd.importe, format(rpd.importe, 2) total_total
				from registro_pago as rp
					inner join registro_pago_detalle as rpd using(idregistro_pago)
					inner join cliente as c on rp.idcliente = c.idcliente
					inner join tipo_consumo as tpc on rp.idtipo_consumo = tpc.idtipo_consumo
				where (rp.idorg=".$g_ido." and rp.idsede=".$g_idsede.")".$filtro." and rp.estado = 0 and rpd.idtipo_pago=3 and rpd.pagado=0
			";

			$sqlCount="
				SELECT count(rp.idregistro_pago) as d1 from registro_pago as rp 
					inner join registro_pago_detalle as rpd using(idregistro_pago)
					inner join cliente as c on rp.idcliente = c.idcliente
					inner join tipo_consumo as tpc on rp.idtipo_consumo = tpc.idtipo_consumo
				where (rp.idorg=".$g_ido." and rp.idsede=".$g_idsede.")".$filtro." and rp.estado = 0 and rpd.idtipo_pago=3 and rpd.pagado=0 and rp.idcliente!=0";
				
			$rowCount = $bd->xDevolverUnDato($sqlCount);
			// echo $sqlCount;
			$rpt = $bd->xConsulta($sql);
            print $rpt."**".$rowCount;
			
			break;
		case 305: // registrar como pagados
			$sql="update registro_pago_detalle set pagado=1 where idregistro_pago_detalle in (".$_POST['ids'].")";
			$bd->xConsulta($sql);
		case 4:// clientes
			$pagination = $_POST['pagination'];						
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(nombres,ruc, if(LENGTH(ruc)>8, 'PJ', 'PN'), direccion, telefono, f_registro) like '%".$pagination['pageFilter']."%'";
			// $filtroFecha = $fecha === '' ? ' and cierre=0 ' : " AND SUBSTRING_INDEX(fecha,' ',1) = '".$fecha."' ";
			// $filtroFechaCount = $fecha === '' ? '' : " and (SUBSTRING_INDEX(c.fecha,' ',1)= '".$fecha."')";

			$sql = "
				select *, if(LENGTH(ruc)>8, 'PJ', 'PN') as tipo from cliente
				where (idorg=".$g_ido.")".$filtro." and nombres != '' and estado=0
				order by nombres asc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];
			
			$sqlCount="
                SELECT count(idcliente) as d1 from cliente
                where (idorg=".$g_ido.")".$filtro." and nombres != '' and estado=0";            
            
			$rowCount = $bd->xDevolverUnDato($sqlCount);
			// echo $sqlCount;
			$rpt = $bd->xConsulta($sql);            
            print $rpt."**".$rowCount;
			break;
		case 401:// historial cliente
			$sql = "select idcliente, STR_TO_DATE(fecha, '%d/%m/%Y') fecha, fecha as fecha_mostrar, total  from registro_pago where idcliente=".$_POST['i']." and estado=0";
			$bd->xConsulta($sql);
			break;
		case 5: //planilla -cargo
			$sql = "select *, format(remuneracion, 2) importe from cargo where idorg=".$g_ido." and estado=0 order by descripcion";
			$bd->xConsulta($sql);
			break;
		case 501: // colaboradores
			$sql="
				SELECT *, DATEDIFF(now(), STR_TO_DATE(f_ingreso, '%d/%m/%Y')) laborando,  FLOOR(DATEDIFF(now(), STR_TO_DATE(f_nac, '%d/%m/%Y'))/365) edad,  DATE_FORMAT(STR_TO_DATE(f_nac, '%d/%m/%Y'), '%d %M') cumple 
				from colaborador
				where (idorg=".$g_ido." and idsede=".$g_idsede.") and estado=0
				ORDER BY nombres
			";
			$bd->xConsulta($sql);
			break;
		case 502:// lista planilla
			$sql="
				SELECT p.idplanilla, p.idcolaborador, p.idcargo, p.idplanilla_periodo, c.nombres, c.profesion, p.area, cargo.descripcion as descargo, p.mes_activo, c.f_ingreso, pp.descripcion as periodo_pago
					, format((IFNULL(ppd.ingresos, 0) + cargo.remuneracion),2) as ingresos, format(IFNULL(ppd.descuentos,0),2) as descuentos, p.fecha_baja
				from planilla as p
					inner join colaborador as c on p.idcolaborador=c.idcolaborador
					inner join cargo on p.idcargo = cargo.idcargo
					inner join planilla_periodo as pp on p.idplanilla_periodo = pp.idplanilla_periodo
					left join (select idplanilla, sum(if(tipo=0, importe,0)) ingresos, sum(if(tipo=1, importe,0)) descuentos from planilla_detalle where estado=0 group by idplanilla) as ppd on p.idplanilla=ppd.idplanilla
				where (p.idorg=".$g_ido." and p.idsede=".$g_idsede.") and p.estado=0 
					and ( mes_cierre = '' or STR_TO_DATE(mes_cierre, '%d/%m/%Y') >= LAST_DAY(STR_TO_DATE(CONCAT('01/','".$_POST['mes']."'), '%d/%m/%Y')) )
			";
			$bd->xConsulta($sql);
			break;
		case 503: // encabezado colaborador planilla detalle
			$sql="
				SELECT c.nombres, c.profesion, c.cuenta, p.area, cargo.descripcion as descargo, c.dni, c.f_ingreso, DATEDIFF(if(p.mes_cierre = '', now(), STR_TO_DATE(p.fecha_baja, '%d/%m/%Y')), STR_TO_DATE(c.f_ingreso, '%d/%m/%Y')) laborando
					,p.mes_activo, cargo.remuneracion, format(cargo.remuneracion,2) sueldo, p.fecha_baja
				from planilla as p
					inner join colaborador as c on p.idcolaborador=c.idcolaborador
					inner join cargo on p.idcargo = cargo.idcargo
					inner join planilla_periodo as pp on p.idplanilla_periodo = pp.idplanilla_periodo
				where idplanilla=".$_POST['id'];
				//. " and mes_activo='".$_POST['mes']."'" ;
			$bd->xConsulta($sql);
			break;
		case 5031 ://detalle planilla | ingresos descuentos
			$sql="select *, format(importe, 2) monto from planilla_detalle where idplanilla=".$_POST['id']." and mes_activo='".$_POST['mes']."' and estado=0";
			$bd->xConsulta($sql);
			break;
		case 504:// guardar colaborador en planilla:
			$arrItem=json_encode($_POST['item']);
			$sql = "CALL procedure_add_colaborador_planilla('".$arrItem."')";			
			$bd->xConsulta($sql);
			break;
		case 505: // dar de baja a colaborador
			$sql = "update planilla set mes_cierre=DATE_FORMAT(LAST_DAY(NOW()),'%d/%m/%Y'), fecha_baja=".$fecha_now." where idplanilla=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 6: // historial de la carta
			$sql="
				SELECT s.descripcion desseccion, i.descripcion desitem, ch.precio, sum(p.cantidad_r) cant_vendido, format(sum(p.ptotal_r),2) importe_vendido
				FROM carta_lista_historial as ch
					inner join seccion as s on ch.idseccion = s.idseccion
					inner join item as i on ch.iditem = i.iditem
					inner join carta as c on ch.idcarta=c.idcarta
					inner join pedido_detalle as p on ch.iditem = p.iditem
				WHERE (c.idorg=".$g_ido." and c.idsede=".$g_idsede.") and concat(s.descripcion,i.descripcion) like '%".$_POST['filtro']."%'
				GROUP BY ch.iditem
				ORDER by ch.sec_orden, s.descripcion, i.descripcion
			";
			$bd->xConsulta($sql);
			break;
	}
?>