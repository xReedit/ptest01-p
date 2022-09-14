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

	$g_ido = isset($_SESSION['ido']) ? $_SESSION['ido'] : 0;
	$g_idsede = isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0;
	$g_idusuario = isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0;
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
			$isFiltroCumple = isset($_POST['filtro_cumple']) ? $_POST['filtro_cumple'] : '0';
			$pagination = $_POST['pagination'];						
			$filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(c.nombres,c.ruc, if(LENGTH(c.ruc)>8, 'PJ', 'PN'), c.direccion, c.telefono, c.f_registro) like '%".$pagination['pageFilter']."%'";
			$filtrocumple = '';

			if ( $isFiltroCumple == '1' ) {
				$filtrocumple = " AND TIMESTAMPDIFF(DAY, CURDATE(), STR_TO_DATE(concat(SUBSTRING(f_nac,1,6), YEAR(NOW())), '%d/%m/%Y')) BETWEEN 0 and 6";
			}
			// $filtroFecha = $fecha === '' ? ' and cierre=0 ' : " AND SUBSTRING_INDEX(fecha,' ',1) = '".$fecha."' ";
			// $filtroFechaCount = $fecha === '' ? '' : " and (SUBSTRING_INDEX(c.fecha,' ',1)= '".$fecha."')";

			$sql = "
				SELECT  c.*, if(LENGTH(c.ruc)>8, 'PJ', 'PN') as tipo 
					, rp.importe_consumo
					, TIMESTAMPDIFF(DAY, CURDATE(), STR_TO_DATE(concat(SUBSTRING(f_nac,1,6), YEAR(NOW())), '%d/%m/%Y')) dias_cumple
					, if ( c.f_nac = 'null', '',  c.f_nac) f_nacimiento
				from cliente_sede cs
					inner join cliente c on cs.idcliente = c.idcliente	
					left join ( select rp.idcliente, format(sum(rp.total_r), 2) importe_consumo 
								from pedido rp where rp.idsede = $g_idsede  group by rp.idcliente  ) rp on rp.idcliente = c.idcliente
				where cs.idsede = $g_idsede$filtrocumple$filtro
				order by rp.importe_consumo desc, c.nombres asc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];			

				// echo $sql;
			// $sql = "
			// 	select c.*, if(LENGTH(c.ruc)>8, 'PJ', 'PN') as tipo from cliente c 
			// 	LEFT join pedido p on p.idcliente = c.idcliente
			// 	where (p.idorg=".$g_ido." or c.idorg=".$g_ido.")".$filtro." and c.nombres != '' and c.estado=0
			// 	order by c.nombres asc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];
			
			$sqlCount="SELECT count(cs.idcliente) from cliente_sede cs inner join cliente c on cs.idcliente = c.idcliente where cs.idsede = $g_idsede$filtrocumple$filtro and c.nombres != '' and c.estado=0";				
				
			// echo $sqlCount;
            
			$rowCount = $bd->xDevolverUnDato($sqlCount);
			
			
			$rpt = $bd->xConsulta($sql);            
			print $rpt."**".$rowCount;
			// echo 'restaurar';
			break;
		case 401:// historial cliente
			// $sql = "select idcliente, STR_TO_DATE(fecha, '%d/%m/%Y') fecha, fecha as fecha_mostrar, total  from registro_pago where idcliente=".$_POST['i']." and idsede=$g_idsede and estado=0";
			$sql = "select idcliente, STR_TO_DATE(fecha, '%d/%m/%Y') fecha, fecha as fecha_mostrar, total_r as total  from pedido where idcliente=".$_POST['i']." and idsede=$g_idsede";
			$bd->xConsulta($sql);
			break;
		case 402: //direccion del cliente
			$idcliente = $_POST['id'];
			$sql = "SELECT * from cliente_pwa_direccion cpd where idcliente = ".$idcliente;
			$bd->xConsulta($sql);
			break;
		case 403: //guardar direccion
			$arrItem=json_encode($_POST['item'], JSON_UNESCAPED_UNICODE);
			$sql = "CALL procedure_registra_nueva_direccion_cliente(".$g_idsede.",'".$arrItem."')";			
			$bd->xConsulta($sql);
			break;
		case 404: // obtener canales de mensjae
			$sql = "SELECT * from msj_canal mc where estado = 0";
			$bd->xConsulta($sql);
			break;		
		case 405: // obtener canales de mensjae plantilla
			$idCanal = $_POST['id'];
			$sql = "SELECT * from msj_plantilla mp where idmsj_canal = $idCanal and estado = 0";
			$bd->xConsulta($sql);
			break;
		case 406: // obtener las imagenes precargadas para la plantilla
			$idCanal = $_POST['id'];
			$sql = "SELECT * from canal_plantilla_imgen mp where estado = 0 order by titulo";
			$bd->xConsulta($sql);
			break;
		case 40601: // obtener datos ingresados logo y link accion			
			$sql = "SELECT * from sede_datos_msj where idsede = $g_idsede";
			$bd->xConsulta($sql);
			break;
		case 407: // guardar logo correo y datos
			$urlLogo = $_POST['logo'];
			$arrItem=json_encode($_POST['item']);
			$sql = "CALL procedure_guardar_logo_correo($g_idsede, '$urlLogo', '".$arrItem."')";			
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
			// and ( mes_cierre = '' or STR_TO_DATE(mes_cierre, '%d/%m/%Y') >= LAST_DAY(STR_TO_DATE(CONCAT('01/','".$_POST['mes']."'), '%d/%m/%Y')) )
			$sql="
				SELECT p.idplanilla, p.idcolaborador, p.idcargo, p.idplanilla_periodo, c.nombres, c.profesion, p.area, cargo.descripcion as descargo, p.mes_activo, c.f_ingreso, pp.descripcion as periodo_pago
					, format((IFNULL(ppd.ingresos, 0) + cargo.remuneracion),2) as ingresos, format(IFNULL(ppd.descuentos,0),2) as descuentos, p.fecha_baja
				from planilla as p
					inner join colaborador as c on p.idcolaborador=c.idcolaborador
					inner join cargo on p.idcargo = cargo.idcargo
					inner join planilla_periodo as pp on p.idplanilla_periodo = pp.idplanilla_periodo
					left join (select idplanilla, sum(if(tipo=0, importe,0)) ingresos, sum(if(tipo=1, importe,0)) descuentos from planilla_detalle where estado=0 group by idplanilla) as ppd on p.idplanilla=ppd.idplanilla
				where (p.idorg=".$g_ido." and p.idsede=".$g_idsede.") and p.estado=0 
					and ( STR_TO_DATE(CONCAT('01/',p.mes_ingreso), '%d/%m/%Y') <= LAST_DAY(STR_TO_DATE(CONCAT('01/','".$_POST['mes']."'), '%d/%m/%Y')) )					
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
		case 7:// encuesta -- load preguntas sugeridas
			$sql = "Select * from encuesta_pregunta where idsede=0 and estado=0";
			$bd->xConsulta($sql);
			break;
		case 701:// encuesta -- load preguntas usuario
			$sql = "Select * from encuesta_pregunta where estado=0 and idsede=$g_idsede";
			$bd->xConsulta($sql);
			break;
		case 702: // guardar encuensta
			//$sqlIdEncuensta = "select idencuesta_sede_conf from encuesta_sede_conf where idsede=$g_idsede";
			//$idEncuenta = $bd->xDevolverUnDato($sqlIdEncuensta);
			// $arrItem=addslashes(json_encode($_POST['item']));
			$link = $_POST['link'];
			$arrItem=$_POST['item'];

			$idEncuenta = $arrItem['idencuesta'];			
			$arrItem=addslashes(json_encode($_POST['item']));			
			
			$sql = "update encuesta_sede_conf set link = '".$link."', preguntas = '".$arrItem."' where idencuesta_sede_conf=$idEncuenta";

			$bd->xConsulta($sql);
			break;
		case 703: // load all encuestas
			$sql = "select * from encuesta_sede_conf where idsede=$g_idsede and estado=0";
			$bd->xConsulta($sql);
			break;
		case 704: // load enceusta select
			$id = $_POST['id'];
			$sql = "select * from encuesta_sede_conf where idencuesta_sede_conf=$id";
			$bd->xConsulta($sql);
			break;
		case 8:// subitems // guardar			
			$arrItem=json_encode($_POST['item']);
			$sql = "CALL procedure_guardar_subitem('".$arrItem."')";
			$bd->xConsulta($sql);			
			break;
		case 800:// subitems // guardar
			$arrItem=$_POST['item'];			
			// $sql = "update item set subitem_required_select = ".$arrItem['required_select'].", subitem_cant_select=".$arrItem['cant_select']." where iditem=".$arrItem['iditem'];
			// $bd->xConsulta_NoReturn($sql);
			
			$sql = "update item_subitem_content set 
						subitem_required_select = ".$arrItem['required_select']."
						, subitem_cant_select=".$arrItem['cant_select']."
						, show_cant_item = ".$arrItem['show_cant_item']."
						, is_sum_cant_subitems = ".$arrItem['is_sum_cant_subitems']."
						, controlable = ".$arrItem['controlable']."
					where iditem_subitem_content=".$arrItem['iditem_subitem_content'];
			// $sql = "update item_subitem_content set is_sum_cant_subitems = ".$arrItem['is_sum_cant_subitems']."  where iditem_subitem_content=".$arrItem['iditem_subitem_content'];
			$bd->xConsulta_NoReturn($sql);

			$sql = "update item_subitem_content_detalle set 
						subitem_required_select = ".$arrItem['required_select']."
						, subitem_cant_select=".$arrItem['cant_select']." 
						, show_cant_item = ".$arrItem['show_cant_item']."
						, controlable = ".$arrItem['controlable']."
					where iditem_subitem_content=".$arrItem['iditem_subitem_content']." and iditem=".$arrItem['iditem'];
			$bd->xConsulta_NoReturn($sql);
			
			// cantidad cambia si es fija si no es nd
			$cantidad = $arrItem['cantidad'];
			if ( $cantidad != null ) {
				$sqlCartaLista = "update carta_lista set cantidad = '".$cantidad."' where iditem = ".$arrItem['iditem'];
				$bd->xConsulta_NoReturn($sqlCartaLista);	
			}

			echo $sql;
			break;
		case 801: // subitems // load						
			$sql = "select i.*, if(i.idporcion > 0 ,'Porcion', if(i.idproducto > 0, 'Producto', 'Libre')) as tipo from item_subitem i where i.iditem_subitem_content = ".$_POST['i']." and i.estado=0";
			$bd->xConsulta($sql);
			break;
		case 802: // subitems // item
			$sql = "select subitem_required_select, subitem_cant_select from item where iditem = ".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 803: // subitems // content
			// $sql = "select * from item_subitem_content where iditem = ".$_POST['i']. " and estado=0";
			$sql = "select isubd.iditem_subitem_content_detalle, isub.iditem_subitem_content, isub.iditem, isub.titulo, isub.compartido, isub.is_sum_cant_subitems, isubd.subitem_required_select, isubd.subitem_cant_select 
				, isubd.show_cant_item, isubd.controlable
				from item_subitem_content_detalle isubd
				inner join item_subitem_content isub on isubd.iditem_subitem_content = isub.iditem_subitem_content
				where isubd.iditem =".$_POST['i']." and isubd.estado=0";
			$bd->xConsulta($sql);
			break;
		case 804: // subitems // save modificado
			$arrItem=$_POST['item'];
			$sql = "update item_subitem set descripcion = '".$arrItem['descripcion']."', cantidad = '".$arrItem['cantidad']."', precio = '".$arrItem['precio']."' where iditem_subitem = ".$arrItem['iditem_subitem'];
			$bd->xConsulta($sql);
			break;
		case 805:// subitems content
			$arrItem=$_POST['item'];
			
			if ( $arrItem['isContentCompartido'] == '0') {
				$sql = "insert into item_subitem_content (iditem, titulo, idsede) values (".$arrItem['iditem'].", '".$arrItem['des']."', ".$g_idsede.")";
				$idSubItemContent=$bd->xConsulta_UltimoId($sql);
			} else {
				$idSubItemContent=$arrItem['iditem_subitem_content'];
			}


			$sql = "insert into item_subitem_content_detalle (iditem_subitem_content, iditem) values (".$idSubItemContent.", ".$arrItem['iditem'].")";
			$bd->xConsulta($sql);
			break;
		case 806: // compartir
			$sql = "update item_subitem_content set compartido = '1' where iditem_subitem_content =".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 807: // list item_subitem_content compartidos
			$sql = "SELECT iditem_subitem_content as value, titulo as label from item_subitem_content where idsede = ".$g_idsede." and compartido='1' and estado = 0 ORDER by titulo";
			$bd->xConsulta($sql);
			break;
		// case 806: // borrar subitem_content y de subitem_content_detalle
		// 	$sql="delete from item_subitem_content where iditem_subitem_content = ".$_POST['idcontent'];
		// 	break;
		
		case 9: // carta virual configuracion
			// load
			$sql = "select * from pwa_reglas_app where estado=0";
			$bd->xConsulta($sql);
			break;
		case 901: // parametros			
			$sql = "select * from sede where idsede=".$g_idsede." and estado=0";
			$bd->xConsulta($sql);
			break;
		case 902: // save parametros			
			$arrItem=$_POST['item'];
			$sql = "update sede set 
						pwa_time_limit=".$arrItem['pwa_time_limit'].", 
						pwa_msj_ini='".$arrItem['pwa_msj_ini']."',
						pwa_time_min_despacho=".$arrItem['pwa_time_min_despacho'].", 
						pwa_time_max_despacho=".$arrItem['pwa_time_max_despacho'].", 
						latitude='".$arrItem['latitude']."', 
						longitude='".$arrItem['longitude']."'
					where idsede=".$g_idsede." and estado=0";
			$bd->xConsulta($sql);
			break;
		case 903:// get mesas qr			
			$sql = "CALL procedure_generator_qr_mesa('".$g_idsede."')";			
			$bd->xConsulta($sql);
			break;
		case 100: //frases inicio
			$sql = "SELECT frase, autor from frases ORDER BY RAND() LIMIT 1";
			$bd->xConsulta($sql);
			break;
		case 110: // registro pago app
			$f_de = $_POST['f_de'];
			$f_a = $_POST['f_a'];
			// $sql = "SELECT rp.idregistro_pago, c.nombres as nomcliente, rp.fecha, 
			// 		(select nummesa from pedido where idregistro_pago = rp.idregistro_pago limit 1) as nummesa,
			// 		format(rp.total, 2) as importe, tpc.descripcion as canal, cast(rp.data_pago_pwa as json)  as datos
			// 	from registro_pago rp
			// 		inner join cliente c on rp.idcliente = c.idcliente
			// 		inner join tipo_consumo tpc on rp.idtipo_consumo = tpc.idtipo_consumo
			// 	where (rp.idsede=$g_idsede and rp.from_pwa = 1) and rp.cierre=0
			// 	order by rp.idregistro_pago desc";
			// $bd->xConsulta($sql);

			// $sql = "SELECT * from pwa_pago_transaction where idsede=$g_idsede and cast(fecha as date) = cast('".$fecha."' as date) order by idpwa_pago_transaction desc";
			$sql =  "SELECT tr.fecha, p.idpedido, p.correlativo_dia, p.nummesa, tpc.descripcion canal, sum(p.total_r) as importe, c.nombres cliente, tr.data_transaction
						, p.check_liquidado, p.check_pagado, p.check_pago_fecha
					from pedido p
					inner join cliente c on c.idcliente = p.idcliente
					inner join tipo_consumo tpc on tpc.idtipo_consumo = p.idtipo_consumo
					inner join pwa_pago_transaction tr on tr.idpwa_pago_transaction = p.idpwa_pago_transaction
					where p.idsede = $g_idsede and  STR_TO_DATE(p.fecha, '%d/%m/%Y') between STR_TO_DATE('$f_de', '%d/%m/%Y') and STR_TO_DATE('$f_a', '%d/%m/%Y')
					GROUP by p.idpwa_pago_transaction";
			$bd->xConsulta($sql);

			break;
		// guardar repartidor
		case 11:
			$arrItem=json_encode($_POST['item']);
			$sql = "CALL procedure_registrar_repartidor_sede('".$arrItem."')";
			$bd->xConsulta($sql);	
			break;
		
		case 12: // comercio online delivery app
			$val = $_POST['val'];
			$sql = "update sede set pwa_delivery_comercio_online=".$val." where idsede =".$g_idsede;
			$bd->xConsulta($sql);	
			break;
		
		case 13: // load repartidor sede
			$sql = "select * from repartidor where idsede_suscrito =".$g_idsede." and estado = 0 order by nombre";
			$bd->xConsulta($sql);
			break;
		
		case 1301:// asignar repartidor
			$idrepartidor = $_POST['idrepartidor'];
			$idpedido = $_POST['idpedido'];
			$sql = "update pedido set idrepartidor = ".$idrepartidor." where idpedido = ".$idpedido;
			$bd->xConsulta($sql);
			break;
		
		case 1302: // lamar repartidor papaya
			$idpedido = $_POST['idpedido'];
			$sql = "update pedido set flag_solicita_repartidor_papaya = 1 where idpedido = ".$idpedido;
			$bd->xConsulta($sql);
			break;
		
		// conectar sede
		case 14: 
			$soketId = isset($_POST['socketId']);
			$sql = "insert into sede_socketid (idsede, socketid, conectado) values (".$g_idsede.", '".$soketId."',  '1')  ON DUPLICATE KEY UPDATE socketid = '".$soketId."', conectado='1'";
			$bd->xConsulta($sql);
			break;
		
		// INDICADORES
		case 15:  // metas			
			$idsede = $_POST['idsede'];
			$idsede = $idsede == 0 ? $g_idsede : $idsede;
			$sql = "select * from sede_meta where idsede = ".$idsede;
			$bd->xConsulta($sql);
			break;
		case 16: // indicadores
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$idsede = $idsede == 0 ? $g_idsede : $idsede;
			$sql = "call procedure_indicador_venta_semana($idsede, '$fecha')";
			$bd->xConsulta($sql);
			break;
		case 1601: // indicadores tipo consumo top secciones e items
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$fecha = $fecha == 0 ? 'CURDATE()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
			$idsede = $idsede == 0 ? $g_idsede : $idsede;
			$sql="SELECT p.idpedido, p.fecha, CURDATE() f_registro, if (p.flag_is_cliente = 1, 'APP', tpc.descripcion) destpc, s.descripcion dessec, i.descripcion ides, u.usuario usuario
					,pd.ptotal_r importe, pd.cantidad_r cantidad
				from pedido p
					inner join pedido_detalle pd on pd.idpedido = p.idpedido
					inner join tipo_consumo tpc on tpc.idtipo_consumo = p.idtipo_consumo	
					inner join item i on i.iditem = pd.iditem
					inner join seccion s on s.idseccion = pd.idseccion
					left join usuario u on u.idusuario = p.idusuario
				where p.idsede= $idsede and p.estado!=3 and pd.estado = 0 and STR_TO_DATE(p.fecha, '%d/%m/%Y') = $fecha";
			$bd->xConsulta($sql);
			break;
		case 1602: // movimientos de caja
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$fecha = $fecha == 0 ? 'CURDATE()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
			$idsede = $idsede == 0 ? $g_idsede : $idsede;
			$sql = "SELECT c.motivo, c.monto, u.usuario, if(c.tipo=1, 'Ingreso', 'Salida' ) tipo
					from ie_caja c
						inner join usuario u on c.idusuario = u.idusuario
					where c.idsede = $idsede and STR_TO_DATE(c.fecha, '%d/%m/%Y') = $fecha";
			$bd->xConsulta($sql);
			break;
		case 1603: // pedidos eliminados
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$fecha = $fecha == 0 ? 'CURDATE()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
			$idsede = $idsede == 0 ? $g_idsede : $idsede;

				$sql="SELECT count(idpedido) cantidad, COALESCE(sum(total_r), 0) importe from pedido 
					where idsede = $idsede and estado=3 and STR_TO_DATE(fecha, '%d/%m/%Y') = $fecha";
				$bd->xConsulta($sql);
			break;
		case 1604: // pedidos detalle eliminados
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$fecha = $fecha == 0 ? 'CURDATE()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
			$idsede = $idsede == 0 ? $g_idsede : $idsede;

				$sql="SELECT count(pd.idpedido) cantidad, COALESCE(sum(pd.ptotal_r), 0) importe 
				from pedido_detalle pd
					inner join pedido p on pd.idpedido = p.idpedido
					where p.idsede=$g_idsede and pd.estado=1 and STR_TO_DATE(p.fecha, '%d/%m/%Y') = $fecha";
			$bd->xConsulta($sql);
			break;
		case 1605: // cmabiar meta
				$idsede = $_POST['idsede'];				
				$meta = $_POST['meta'];
				$new = $_POST['new'];
				$idsede = $idsede == 0 ? $g_idsede : $idsede;

				if ( $new == "1" ) {
					$meta_m = $meta * 30;
					$meta_y = $meta_m * 12;
					$sql = "insert into sede_meta(idorg, idsede, diaria, mensual, anual, fecha) values ($g_ido, $g_idsede,'$meta', '$meta_m', '$meta_y', CURDATE())";
				} else {
					$sql="update sede_meta set diaria='$meta' where idsede = $idsede";
				}
				$bd->xConsulta($sql);
			break;
		case 1606: // chequear si existe cierre de caja para volver a imprimirlo
			$fecha = $_POST['fecha'];
			$idsede = $_POST['idsede'];
			$fecha = $fecha == 0 ? 'CURDATE()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
			$idsede = $idsede == 0 ? $g_idsede : $idsede;
			$sql = "SELECT idprint_server_detalle from print_server_detalle where idsede=$idsede and idprint_server_estructura = 4 and STR_TO_DATE(fecha, '%d/%m/%Y') = $fecha";
			$bd->xConsulta($sql);
			break;
		case 16061: // mandar imprimir cierre
			$ids = $_POST['ids'];
			$sql = "update print_server_detalle set impreso = 0, estado = 0 where idprint_server_detalle in ($ids)";
			$bd->xConsulta_NoReturn($sql);

			// para enviarlo por socket
			$sql = "select * from print_server_detalle where idprint_server_detalle in ($ids)";
			$bd->xConsulta($sql);
			break;
		case 16062: // cliente calificacion
			$idsede = $_POST['idsede'];
			$idsede = $idsede == 0 ? $g_idsede : $idsede;
			$pagination = $_POST['pagination'];
			$sql = "
				SELECT p.fecha_hora, p.idpedido, p.correlativo_dia, tc.descripcion, SUBSTRING_INDEX(c2.nombres, ' ',1) nombre, sc.calificacion, sc.comentario
					from sede_calificacion sc 
					inner join cliente c2 on c2.idcliente = sc.idcliente 
					inner join pedido p on sc.idpedido = p.idpedido
					inner join tipo_consumo tc on tc.idtipo_consumo = p.idtipo_consumo 
				where sc.idsede = $idsede order by sc.idsede_calificacion desc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];
			// $bd->xConsulta($sql);

			$sqlCount = "SELECT count(idsede_calificacion) as d1 from sede_calificacion where idsede = 13";

			$rowCount = $bd->xDevolverUnDato($sqlCount);
			// echo $sqlCount;
			$rpt = $bd->xConsulta($sql);            
            print $rpt."**".$rowCount;			
			break;



		case 17: // reimprimir cierre de caja
			$f = $_POST['f'];			
			$sql = "INSERT INTO print_server_detalle (idorg, idsede, idprint_server_estructura, descripcion_doc, detalle_json,fecha,hora, idusuario) 
			SELECT idorg, idsede, idprint_server_estructura, descripcion_doc, detalle_json,fecha,hora, idusuario 
				from print_server_detalle 
			where idprint_server_estructura = 4 and idusuario = $g_idusuario and fecha = '$f' LIMIT 1";
			$bd->xConsulta($sql);
			break;
		case 1701: // reimprimir comprobante
			$c = $_POST['comprobante'];			
			$sql = "INSERT INTO print_server_detalle (idorg, idsede, idprint_server_estructura, descripcion_doc, detalle_json,fecha,hora, idusuario) 
			SELECT idorg, idsede, idprint_server_estructura, descripcion_doc, detalle_json,fecha,hora, idusuario 
				from print_server_detalle 
			where idprint_server_estructura = 2 and CONCAT(detalle_json->>'$.ArrayComprobante.inicial',detalle_json->>'$.ArrayComprobante.serie','-', detalle_json->>'$.ArrayComprobante.correlativo') = '$c'";
			$bd->xConsulta($sql);
			break;
		case 1702: // reimprimir comprobante
			$c = $_POST['comprobante'];			
			$sql = "select idprint_server_detalle, detalle_json from print_server_detalle where idsede = $g_idsede and CONCAT(detalle_json->>'$.ArrayComprobante.inicial',detalle_json->>'$.ArrayComprobante.serie','-', detalle_json->>'$.ArrayComprobante.correlativo') = '$c'";
			$bd->xConsulta($sql);
			break;
		case 1703: // reimprimir cuadre de caja
			$c = $_POST['idprint'];			
			$sql = "select idprint_server_detalle, detalle_json from print_server_detalle where idprint_server_detalle = $c";
			$bd->xConsulta($sql);
			break;
		case 18: // contenido dinamico
			echo '<hr>';
			break;
		
		case 19: // update item no visible cliente
			$check = $_POST['check'];
			$id = $_POST['id'];
			$sql="update carta_lista set is_visible_cliente = $check where idcarta_lista='$id'";
			$bd->xConsulta($sql);
			break;
		case 1901: // update item no visible cliente
			$check = $_POST['check'];
			$id = $_POST['id'];
			$sql="update seccion set is_visible_cliente = $check where idseccion=$id";
			$bd->xConsulta($sql);
			break;
		
		case 20: // orden de pago			
			$arrItem=$_POST['item'];
			$sql="call procedure_orden_pedido($g_idsede, $g_idusuario,'".$arrItem."')";
			$bd->xConsulta($sql);
			break;
		case 2001: // guadar adelanto
			$idOrden = $_POST['id'];
			$concepto = $_POST['concepto'];
			$importe = $_POST['importe'];
			$idtipo_pago = $_POST['idtipo_pago'];

			if ( $concepto !== '' ) {
				$sql = "insert into orden_pedido_adelanto (idorden_pedido, idtipo_pago, idusuario, concepto, importe, fecha_hora) values ($idOrden, $idtipo_pago, $g_idusuario, '$concepto', '$importe', DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'))";
				$bd->xConsulta_NoReturn($sql);
			}


			$sql = "select tp.descripcion des_tipo_pago, opa.* from orden_pedido_adelanto opa inner join tipo_pago tp on tp.idtipo_pago = opa.idtipo_pago where opa.idorden_pedido = $idOrden";
			$bd->xConsulta($sql);

			break;
		case 2002: // guadar nota
			$idOrden = $_POST['id'];
			$nota = $_POST['nota'];			

			if ( $nota !== '' ) {
				$sql = "insert into orden_pedido_notas (idorden_pedido, nota, fecha_hora) values ($idOrden, '$nota', DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'))";
				$bd->xConsulta_NoReturn($sql);
			}


			// registra ingreso en caja


			$sql = "select * from orden_pedido_notas where idorden_pedido = $idOrden";
			$bd->xConsulta($sql);
			break;
		case 30: // lista orden pedido
			$mm = $_POST['m'];
			$yy = $_POST['y'];
			$sql= "
			select STR_TO_DATE(fecha_entrega, '%Y-%m-%d') fentrega, DAY(fecha_entrega) dia, DAYOFWEEK(fecha_entrega) dia_semana, DATE_FORMAT(fecha_entrega, '%H:%i') hora, format(COALESCE(opaa.adelanto, 0), 2) total_adelanto, op.*
			from orden_pedido  op 
				left join (select idorden_pedido, sum(importe) adelanto from orden_pedido_adelanto opa group by opa.idorden_pedido) as opaa on opaa.idorden_pedido = op.idorden_pedido
			where op.idsede = $g_idsede and (MONTH(op.fecha_entrega) = $mm and YEAR(op.fecha_entrega) = $yy)
			order by date(op.fecha_entrega) 
			";
			$bd->xConsulta($sql);			
			// print $aa['success'];
			break;

		case 3001: // lista orden pedido
			$mm = $_GET['month'];
			$yy = $_GET['year'];
			$sql= "
			select STR_TO_DATE(fecha_entrega, '%Y-%m-%d') date,concat(count(fecha_entrega), ' Ordenes') title, 'true' badge
			from orden_pedido  op 				
			where op.idsede = $g_idsede and (MONTH(op.fecha_entrega) = $mm and YEAR(op.fecha_entrega) = $yy)
			group by STR_TO_DATE(fecha_entrega, '%Y-%m-%d')
			order by date(op.fecha_entrega)
			";
			// $aa = $bd->xConsulta($sql);

			$aabd = $bd->xConsulta3($sql);
			echo $aabd;
			
			break;
		

		// estadisticas
		case 40:
			$idsede = $_POST['idsede'];			
			$fecha = $_POST['fecha'];
			$hoy = "if (rp.fecha_cierre = '', 1, 0 )";
			$columm_add = '';
			$rango = $_POST['rango'];

			$idsede = $idsede == 0 ? $g_idsede : $idsede;
			

			switch ($rango) {
				case 'fecha':
					$lastIdRegistroPago = $bd->xDevolverUnDato("select COALESCE (min(idregistro_pago), 0) d1 from registro_pago where idsede = $idsede and STR_TO_DATE(fecha, '%d/%m/%Y') >= date_sub(curdate(), INTERVAL 2 day) limit 2");	
					// if ( $fecha == 0 ) {
						// $fecha = " and (STR_TO_DATE(rp.fecha_cierre, '%d/%m/%Y') =  DATE_ADD(CURDATE(), INTERVAL -1 DAY))";
						$fecha = " and rp.idregistro_pago >= $lastIdRegistroPago and rp.cierre = 0"; //(rp.cierre = 0 and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 DAY) AND CURDATE())";
						$hoy = "if (STR_TO_DATE(rp.fecha, '%d/%m/%Y') = curdate(), 1, 0 )";
					// } else {
					// 	$hoy = "if (STR_TO_DATE(rp.fecha, '%d/%m/%Y') = STR_TO_DATE('$fecha', '%d/%m/%Y'), 1, 0 )";				
					// 	$fecha = " and (STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN  DATE_ADD(STR_TO_DATE('$fecha', '%d/%m/%Y'), INTERVAL -1 DAY) and STR_TO_DATE('$fecha', '%d/%m/%Y'))";
					// }

					$columm_add = '';
					break;
				case 'semana':
						$hoy = "if(WEEK(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) = WEEK(now()), 1 ,0)";
						$fecha = " and STR_TO_DATE(rp.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 2 WEEK) and now()";					
						$columm_add = ", WEEK(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) num_semana, if(WEEK(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) = WEEK(now()), 1 ,0) semana_actual
						, DAYNAME(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) nom_dia
						, DAYOFWEEK(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) num_dia";
					break;
				case 'mes':
					$option = $_POST['option'];
					$mm = $option['num_mm'];
					$yy = $option['num_yy'];
					$first_day_month = $option['value'].'-01'; // el primer dia mes
					

					$_sql = "select concat(min(rp.idregistro_pago),',',max(rp.idregistro_pago)) from registro_pago rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN date_sub('$first_day_month',INTERVAL 1 MONTH) and LAST_DAY('$first_day_month')";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$columm_add = ", MONTHNAME(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) nom_mes
					, MONTH(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) num_mes";
					
					// $fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
					// $hoy = "if(MONTH(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) = MONTH($fecha), 1 ,if(MONTH(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) < MONTH($fecha) - 1, 2, 0 ))";
					$hoy = "if(MONTH(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) = $mm, 1 ,if(MONTH(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) < $mm - 1, 2, 0 ))";
					$fecha = " and (rp.idregistro_pago between $firstIdRegistroPago and $lastIdRegistroPago)"; // and  STR_TO_DATE(rp.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 2 MONTH) and $fecha";					
					break;
				
				case 'rango':
					$option = $_POST['option'];					
					$date1 = $option['value1'];
					$date2 = $option['value2'];					

					$_sql = "select concat(min(rp.idregistro_pago),',',max(rp.idregistro_pago)) from registro_pago rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN cast('$date1' as date) and cast('$date2' as date)";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$columm_add = '';
					$hoy = "1";
					$fecha = " and (rp.idregistro_pago between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
			}
			
			// $idsede = $idsede == 0 ? $g_idsede : $idsede;
			// $sql="SELECT p.idpedido, p.fecha, CURDATE() f_registro, if (p.is_from_client_pwa = 1, 'APP', tpc.descripcion) destpc, s.descripcion dessec, i.descripcion ides, u.usuario usuario
			// 		,pd.ptotal_r importe, pd.cantidad_r cantidad
			// 	from pedido p
			// 		inner join pedido_detalle pd on pd.idpedido = p.idpedido
			// 		inner join tipo_consumo tpc on tpc.idtipo_consumo = p.idtipo_consumo	
			// 		inner join item i on i.iditem = pd.iditem
			// 		inner join seccion s on s.idseccion = pd.idseccion
			// 		left join usuario u on u.idusuario = p.idusuario
			// 	where p.idsede= $idsede and p.estado!=3 and pd.estado = 0 and STR_TO_DATE(p.fecha, '%d/%m/%Y') = $fecha";
			// $bd->xConsulta($sql);

			

			$sql = "SELECT rpd.*, STR_TO_DATE(rp.fecha, '%d/%m/%Y') fecha, DATE_FORMAT(STR_TO_DATE(rp.fecha, '%d/%m/%Y %H:%i:%s %p'), '%H %p') hora, rp.estado, rp.fecha_cierre, tp.descripcion des_tp, tc.idtipo_comprobante, tc.descripcion comprobante, rp.correlativo 
				, $hoy hoy, tp.img, tpc.descripcion destpc
				$columm_add
			from registro_pago_detalle rpd 
				 inner join registro_pago rp on rp.idregistro_pago = rpd.idregistro_pago 
				 inner join tipo_pago tp on rpd.idtipo_pago = tp.idtipo_pago 
				 inner join tipo_consumo tpc on tpc.idtipo_consumo = rp.idtipo_consumo
				 left join tipo_comprobante_serie tcs on rp.idtipo_comprobante_serie = tcs.idtipo_comprobante_serie
				 LEFT join tipo_comprobante tc on tcs.idtipo_comprobante = tc.idtipo_comprobante 
				 where rp.idsede = $idsede $fecha
			 order by rpd.idregistro_pago desc";
			 
			// echo $sql;
			$bd->xConsulta($sql);
			break;
		
		// estadisticas // pedidos
		case 4001:
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$rango = $_POST['rango'];
			$hoy = "if (p.cierre = '', 1, 0 )";

			$idsede = $idsede == 0 ? $g_idsede : $idsede;

			switch ($rango) {
				case 'fecha':
						$_sql = "select COALESCE (min(idpedido), 0) d1 from pedido where idsede = $idsede and STR_TO_DATE(fecha, '%d/%m/%Y') >= date_sub(curdate(), INTERVAL 1 day) limit 2";
						$firstIdPedidoToDay = $bd->xDevolverUnDato($_sql);
						// if ( $fecha == 0 ) { 				
							$hoy = "if (p.cierre = '', 1, 0 )";
							$fecha = " and p.idpedido >= $firstIdPedidoToDay ";
						// } else {
							// $hoy = "if (STR_TO_DATE(p.fecha, '%d/%m/%Y') = STR_TO_DATE('$fecha', '%d/%m/%Y'), 1, 0 )";
							// $fecha = " and (STR_TO_DATE(p.fecha, '%d/%m/%Y') BETWEEN  DATE_ADD(STR_TO_DATE('$fecha', '%d/%m/%Y'), INTERVAL -1 DAY) and STR_TO_DATE('$fecha', '%d/%m/%Y'))";
						// }
					break;
				case 'semana':
						$hoy = "if(WEEK(STR_TO_DATE(p.fecha, '%d/%m/%Y')) = WEEK(now()), 1 ,0)";
						$fecha = " and STR_TO_DATE(p.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 2 WEEK) and now()";					
					break;
				case 'mes':
						$option = $_POST['option'];
						$mm = $option['num_mm'];
						$yy = $option['num_yy'];
						$first_day_month = $option['value'].'-01'; // el primer dia mes

						$_sql = "select concat(min(rp.idpedido),',',max(rp.idpedido)) from pedido rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN date_sub('$first_day_month',INTERVAL 1 MONTH) and LAST_DAY('$first_day_month')";
						$minMaxID = $bd->xDevolverUnDato($_sql);					
						$minMaxID = explode(",", $minMaxID);
						$lastIdRegistroPago = $minMaxID[1];
						$firstIdRegistroPago = $minMaxID[0];

						// $fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
						// $hoy = "if(MONTH(STR_TO_DATE(p.fecha, '%d/%m/%Y')) = MONTH($fecha), 1 ,if(MONTH(STR_TO_DATE(p.fecha, '%d/%m/%Y')) < MONTH($fecha) - 1, 2, 0 ))";						
						// $fecha = " and STR_TO_DATE(p.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 2 MONTH) and $fecha";					

						$hoy = "if(MONTH(STR_TO_DATE(p.fecha, '%d/%m/%Y')) = $mm, 1 ,if(MONTH(STR_TO_DATE(p.fecha, '%d/%m/%Y')) < $mm - 1, 2, 0 ))";
						$fecha = " and (p.idpedido between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;

				case 'rango':
						$option = $_POST['option'];					
						$date1 = $option['value1'];
						$date2 = $option['value2'];					

						$_sql = "select concat(min(rp.idpedido),',',max(rp.idpedido)) from pedido rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN cast('$date1' as date) and cast('$date2' as date)";
						$minMaxID = $bd->xDevolverUnDato($_sql);					
						$minMaxID = explode(",", $minMaxID);
						$lastIdRegistroPago = $minMaxID[1];
						$firstIdRegistroPago = $minMaxID[0];

						$hoy = "1";
						$fecha = " and (p.idpedido between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
			}

			

			$sql = "select p.idpedido, p.estado, p.fecha_hora, p.total_r importe, pd.estado anulado 
				, $hoy hoy, pd.ptotal_r importe_item
				, WEEK(STR_TO_DATE(p.fecha, '%d/%m/%Y')) num_semana, if(WEEK(STR_TO_DATE(p.fecha, '%d/%m/%Y')) = WEEK(now()), 1 ,0) semana_actual
				, tc.descripcion destpc
				, p.flag_is_cliente as is_from_client_pwa
			from pedido p
				inner join pedido_detalle pd on p.idpedido = pd.idpedido 
				inner join tipo_consumo tc on tc.idtipo_consumo = p.idtipo_consumo
			where p.idsede = $idsede $fecha";
			 
			$bd->xConsulta($sql);
			break;
		
		case 4002: // pedidos borrados de pedidos_borrados
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$rango = $_POST['rango'];
			// $fecha = $fecha == 0 ? " pb.fecha_cierre = ''" : " STR_TO_DATE(pb.fecha_cierre, '%Y-%m-%d') = STR_TO_DATE('$fecha', '%d/%m/%Y')";

			$idsede = $idsede == 0 ? $g_idsede : $idsede;

			switch ($rango) {
				case 'fecha':
					// $fecha = $fecha == 0 ? " pb.fecha = ''" : " pb.fecha = '$fecha'";
					$fecha = " STR_TO_DATE(pb.fecha, '%d/%m/%Y') = curdate() ";
					break;
				
				case 'semana':
					$fecha = "STR_TO_DATE(pb.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 1 WEEK) and now()";
					break;
				case 'mes':
					$option = $_POST['option'];
					$mm = $option['num_mm'];
					$yy = $option['num_yy'];
					$first_day_month = $option['value'].'-01'; // el primer dia mes

					$_sql = "select concat(min(pb.idpedido_borrados),',',max(pb.idpedido_borrados)) from pedido_borrados pb INNER JOIN pedido p on p.idpedido = pb.idpedido where p.idsede=$idsede and STR_TO_DATE(pb.fecha, '%d/%m/%Y') BETWEEN cast('$first_day_month' as date) and LAST_DAY('$first_day_month')";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$fecha = " (pb.idpedido_borrados between $firstIdRegistroPago and $lastIdRegistroPago)";


					// $fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
					// $fecha = " and STR_TO_DATE(pb.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 1 MONTH) and $fecha";
					break;
				case 'rango':
					$option = $_POST['option'];
					$date1 = $option['value1'];
					$date2 = $option['value2'];

					$_sql = "select concat(min(pb.idpedido_borrados),',',max(pb.idpedido_borrados)) from pedido_borrados pb INNER JOIN pedido p on p.idpedido = pb.idpedido where p.idsede=$idsede and STR_TO_DATE(pb.fecha, '%d/%m/%Y') BETWEEN cast('$date1' as date) and cast('$date2' as date)";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$fecha = " (pb.idpedido_borrados between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
			}

			$sql="select pb.*, u.usuario, i.descripcion 
				, WEEK(STR_TO_DATE(pb.fecha_cierre, '%d/%m/%Y')) num_semana, if(WEEK(STR_TO_DATE(pb.fecha_cierre, '%d/%m/%Y')) = WEEK(now()), 1 ,0) semana_actual
				from pedido_borrados pb 
				inner join usuario u on u.idusuario = pb.idusuario_permiso
				inner join pedido_detalle i on i.idpedido_detalle = pb.idpedido_detalle 
				where u.idsede = $idsede 
				and $fecha GROUP by pb.idpedido_borrados";			
			$bd->xConsulta($sql);
			break;
		
		case 4003: // iecaja
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$rango = $_POST['rango'];

			$idsede = $idsede == 0 ? $g_idsede : $idsede;			

			switch ($rango) {
				case 'fecha':
					// $fecha = $fecha == 0 ? " ic.cierre = 0 and STR_TO_DATE(ic.fecha, '%d/%m/%Y') BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND NOW()" : " STR_TO_DATE(ic.fecha, '%d/%m/%Y') = STR_TO_DATE('$fecha', '%d/%m/%Y')";
					$fecha = " ic.cierre = 0 and STR_TO_DATE(ic.fecha, '%d/%m/%Y') BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND NOW()";
					// $fecha = " STR_TO_DATE(ic.fecha, '%d/%m/%Y') = curdate() ";
					break;
				
				case 'semana':
					$fecha = "STR_TO_DATE(ic.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 1 WEEK) and now()";
					break;
				case 'mes':
					// $fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
					// $fecha = "STR_TO_DATE(ic.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 1 MONTH) and $fecha";

					$option = $_POST['option'];
					$mm = $option['num_mm'];
					$yy = $option['num_yy'];
					$first_day_month = $option['value'].'-01'; // el primer dia mes

					$fecha = "STR_TO_DATE(ic.fecha, '%d/%m/%Y') between cast('$first_day_month' as date) and LAST_DAY('$first_day_month')";					
					break;
				case 'rango':
					$option = $_POST['option'];
					$date1 = $option['value1'];
					$date2 = $option['value2'];

					$fecha = "STR_TO_DATE(ic.fecha, '%d/%m/%Y') between cast('$date1' as date) and cast('$date2' as date)";
					break;
			}

			

			$sql = "SELECT ic.fecha, ic.motivo, ic.monto, ic.tipo numtipo, if(ic.tipo = 1, 'INGRESO', 'SALIDA') tipo, u.usuario, u.nombres nomusuario from ie_caja ic 
					inner join usuario u on u.idusuario = ic.idusuario
				where ic.idsede = $idsede and $fecha
				order by ic.idie_caja desc";

			$bd->xConsulta($sql);
			break;


		case 4004: // top productos
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$rango = $_POST['rango'];

			$idsede = $idsede == 0 ? $g_idsede : $idsede;			

			switch ($rango) {
				case 'fecha':
					// se agrega que solo muestre los pedidos que no fueron cerrados en los 2 ultimos dias
					// $fecha = $fecha == 0 ? " p.cierre = 0 and STR_TO_DATE(p.fecha, '%d/%m/%Y') BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND NOW() " : " STR_TO_DATE(p.fecha, '%d/%m/%Y') = STR_TO_DATE('$fecha', '%d/%m/%Y')";
					$_sql = "select COALESCE (min(idpedido), 0) d1 from pedido where idsede = $idsede and STR_TO_DATE(fecha, '%d/%m/%Y') >= date_sub(curdate(), INTERVAL 2 day) limit 2";
					$firstIdPedidoToDay = $bd->xDevolverUnDato($_sql);
					$fecha = " p.cierre = 0 and p.idpedido >= $firstIdPedidoToDay";
					break;
				
				case 'semana':
					$fecha = "STR_TO_DATE(p.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 1 WEEK) and now()";
					break;
				case 'mes':
					// $fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
					// $fecha = "STR_TO_DATE(p.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 1 MONTH) and $fecha";

					$option = $_POST['option'];
					$mm = $option['num_mm'];
					$yy = $option['num_yy'];
					$first_day_month = $option['value'].'-01'; // el primer dia mes

					$_sql = "select concat(min(rp.idpedido),',',max(rp.idpedido)) from pedido rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN date_sub('$first_day_month',INTERVAL 1 MONTH) and LAST_DAY('$first_day_month')";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$fecha = " (p.idpedido between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
				case 'rango':
						$option = $_POST['option'];					
						$date1 = $option['value1'];
						$date2 = $option['value2'];					

						$_sql = "select concat(min(rp.idpedido),',',max(rp.idpedido)) from pedido rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN cast('$date1' as date) and cast('$date2' as date)";
						$minMaxID = $bd->xDevolverUnDato($_sql);					
						$minMaxID = explode(",", $minMaxID);
						$lastIdRegistroPago = $minMaxID[1];
						$firstIdRegistroPago = $minMaxID[0];

						// $hoy = "1";
						$fecha = " (p.idpedido between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
			}

			$sql = "SELECT pd.cantidad_r cantidad, pd.punitario, pd.ptotal_r total, 0 procede, i.descripcion des_item
					,'CARTA' des_procede
					, s.descripcion des_seccion
					, u.usuario, u.nombres 
				from pedido_detalle pd 
					inner join pedido p on p.idpedido = pd.idpedido 
					INNER join seccion s on s.idseccion = pd.idseccion 
					inner join item i on i.iditem = pd.iditem 
					left join usuario u on u.idusuario  = p.idusuario 
				where p.idsede = $idsede and pd.estado = 0 and $fecha
				UNION ALL
				SELECT pd.cantidad_r cantidad, pd.punitario, pd.ptotal_r total, 1 procede, i.descripcion des_item
					,'BODEGA' des_procede
					, s.descripcion des_seccion
					, u.usuario, u.nombres 
				from pedido_detalle pd 
					inner join pedido p on p.idpedido = pd.idpedido 
					inner join producto_familia s on s.idproducto_familia = pd.idseccion 
					inner join producto i on i.idproducto = pd.iditem 
					left join usuario u on u.idusuario  = p.idusuario 
				where p.idsede = $idsede and pd.estado = 0 and $fecha ";

			$bd->xConsulta($sql);
			break;

		case 4005: // prociones

			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$rango = $_POST['rango'];

			$idsede = $idsede == 0 ? $g_idsede : $idsede;			

			switch ($rango) {
				case 'fecha':
					// $fecha = $fecha == 0 ? " p.cierre = 0 and STR_TO_DATE(p.fecha, '%d/%m/%Y') BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND NOW()" : " STR_TO_DATE(p.fecha, '%d/%m/%Y') = STR_TO_DATE('$fecha', '%d/%m/%Y')";
					
					$_sql = "select COALESCE (min(idpedido), 0) d1 from pedido where idsede = $idsede and STR_TO_DATE(fecha, '%d/%m/%Y') >= date_sub(curdate(), INTERVAL 2 day) limit 2";
					$firstIdPedidoToDay = $bd->xDevolverUnDato($_sql);
					$fecha = " p.cierre = 0 and p.idpedido >= $firstIdPedidoToDay";
					break;
				
				case 'semana':
					$fecha = "STR_TO_DATE(p.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 1 WEEK) and now()";
					break;
				case 'mes':
					// $fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
					// $fecha = "STR_TO_DATE(p.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 1 MONTH) and $fecha";

					$option = $_POST['option'];
					$mm = $option['num_mm'];
					$yy = $option['num_yy'];
					$first_day_month = $option['value'].'-01'; // el primer dia mes

					$_sql = "select concat(min(rp.idpedido),',',max(rp.idpedido)) from pedido rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN date_sub('$first_day_month',INTERVAL 1 MONTH) and LAST_DAY('$first_day_month')";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$fecha = " (p.idpedido between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
				case 'rango':
						$option = $_POST['option'];					
						$date1 = $option['value1'];
						$date2 = $option['value2'];					

						$_sql = "select concat(min(rp.idpedido),',',max(rp.idpedido)) from pedido rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN cast('$date1' as date) and cast('$date2' as date)";
						$minMaxID = $bd->xDevolverUnDato($_sql);					
						$minMaxID = explode(",", $minMaxID);
						$lastIdRegistroPago = $minMaxID[1];
						$firstIdRegistroPago = $minMaxID[0];

						// $hoy = "1";
						$fecha = " (p.idpedido between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
			}

			$sql = "SELECT (pd.cantidad_r * ii.cantidad) cantidad, pd.punitario, pd.ptotal_r total, 2 procede
					,'PORCION' des_procede
					, s.descripcion des_seccion
					, po.descripcion des_item
				from pedido_detalle pd 
					inner join pedido p on p.idpedido = pd.idpedido 
					INNER join seccion s on s.idseccion = pd.idseccion
					inner join item i on i.iditem = pd.iditem
					inner join item_ingrediente ii on ii.iditem = i.iditem 
					inner join porcion po on po.idporcion = ii.idporcion 
					where p.idsede = $idsede and pd.estado = 0 and $fecha ";

			$bd->xConsulta($sql);					
			break;
		
		case 4006:// top colaborador caja
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$rango = $_POST['rango'];

			$idsede = $idsede == 0 ? $g_idsede : $idsede;			

			switch ($rango) {
				case 'fecha':
					// $fecha = $fecha == 0 ? " rp.cierre = 0 and STR_TO_DATE(p.fecha, '%d/%m/%Y') BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND NOW()" : " STR_TO_DATE(rp.fecha, '%d/%m/%Y') = STR_TO_DATE('$fecha', '%d/%m/%Y')";
					$lastIdRegistroPago = $bd->xDevolverUnDato("select COALESCE (min(idregistro_pago), 0) d1 from registro_pago where idsede = $idsede and STR_TO_DATE(fecha, '%d/%m/%Y') >= date_sub(curdate(), INTERVAL 2 day) limit 2");	
					$fecha = " rp.idregistro_pago >= $lastIdRegistroPago and rp.cierre = 0";
					break;
				
				case 'semana':
					$fecha = "STR_TO_DATE(rp.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 1 WEEK) and now()";
					break;
				case 'mes':
					// $fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
					// $fecha = "STR_TO_DATE(rp.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 1 MONTH) and $fecha";

					$option = $_POST['option'];
					$mm = $option['num_mm'];
					$yy = $option['num_yy'];
					$first_day_month = $option['value'].'-01'; // el primer dia mes
					

					$_sql = "select concat(min(rp.idregistro_pago),',',max(rp.idregistro_pago)) from registro_pago rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN cast('$first_day_month' as date) and LAST_DAY('$first_day_month')";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$fecha = " (rp.idregistro_pago between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;

				case 'rango':
					$option = $_POST['option'];					
					$date1 = $option['value1'];
					$date2 = $option['value2'];					

					$_sql = "select concat(min(rp.idregistro_pago),',',max(rp.idregistro_pago)) from registro_pago rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN cast('$date1' as date) and cast('$date2' as date)";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$columm_add = '';
					$hoy = "1";
					$fecha = " (rp.idregistro_pago between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
			}

			$sql = "SELECT u.idusuario, u.usuario, u.nombres , rp.total from registro_pago rp 
			inner join usuario u on u.idusuario = rp.idusuario 
			where rp.idsede = $idsede and rp.estado = 0 and $fecha";

			$bd->xConsulta($sql);	

			break;

		case 40061:// top colaborador caja - si tiene cierre de caja
				$idsede = $_POST['idsede'];
				$fecha = $_POST['fecha'];
				$rango = $_POST['rango'];
	
				$idsede = $idsede == 0 ? $g_idsede : $idsede;			
	
				if ($rango == 'fecha') {
					$fecha = " STR_TO_DATE(fecha, '%d/%m/%Y') = CURDATE()";
					
					$sql = "SELECT * from print_server_detalle where idsede = $idsede and idprint_server_estructura = 4 and $fecha";
					$bd->xConsulta($sql);	
				} else if ($rango == 'rango'){
					$option = $_POST['option'];					
					$date1 = $option['value1'];
					$date2 = $option['value2'];	

					if ($date1 == $date2 ) {
						$fecha = " STR_TO_DATE(fecha, '%d/%m/%Y') = STR_TO_DATE($date1, '%d/%m/%Y') ";
					
						$sql = "SELECT * from print_server_detalle where idsede = $idsede and idprint_server_estructura = 4 and $fecha";
						$bd->xConsulta($sql);	
					}

				} else {
					echo false;
				}
				

	
			break;

		case 4007: // comprobantes de pago
			$idsede = $_POST['idsede'];
			$fecha = $_POST['fecha'];
			$rango = $_POST['rango'];

			$idsede = $idsede == 0 ? $g_idsede : $idsede;			

			switch ($rango) {
				case 'fecha':
					// $fecha = $fecha == 0 ? " STR_TO_DATE(ce.fecha, '%d/%m/%Y') = CURDATE()" : " STR_TO_DATE(ce.fecha, '%d/%m/%Y') = STR_TO_DATE('$fecha', '%d/%m/%Y')";
					$fecha = " STR_TO_DATE(ce.fecha, '%d/%m/%Y') = CURDATE()";
					break;				
				case 'semana':
					$fecha = "STR_TO_DATE(ce.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 1 WEEK) and now()";
					break;
				case 'mes':
					// $fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
					// $fecha = "STR_TO_DATE(ce.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 1 MONTH) and $fecha";

					$option = $_POST['option'];
					$mm = $option['num_mm'];
					$yy = $option['num_yy'];
					$first_day_month = $option['value'].'-01'; // el primer dia mes

					$_sql = "select concat(min(rp.idce),',',max(rp.idce)) from ce rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN cast('$first_day_month' as date) and LAST_DAY('$first_day_month')";
					$minMaxID = $bd->xDevolverUnDato($_sql);					
					$minMaxID = explode(",", $minMaxID);
					$lastIdRegistroPago = $minMaxID[1];
					$firstIdRegistroPago = $minMaxID[0];

					$fecha = " (ce.idce between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;

				case 'rango':
						$option = $_POST['option'];					
						$date1 = $option['value1'];
						$date2 = $option['value2'];					

						$_sql = "select concat(min(rp.idce),',',max(rp.idce)) from ce rp where idsede=$idsede and STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN cast('$date1' as date) and cast('$date2' as date)";
						$minMaxID = $bd->xDevolverUnDato($_sql);					
						$minMaxID = explode(",", $minMaxID);
						$lastIdRegistroPago = $minMaxID[1];
						$firstIdRegistroPago = $minMaxID[0];

						$fecha = " (ce.idce between $firstIdRegistroPago and $lastIdRegistroPago)";
					break;
			}

			$sql = "select count(ce.idce) cantidad, tc.descripcion, format(sum(ce.total), 2) total from ce
				inner join tipo_comprobante_serie tcs on tcs.idtipo_comprobante_serie = ce.idtipo_comprobante_serie 
				inner join tipo_comprobante tc on tc.idtipo_comprobante = tcs.idtipo_comprobante 
			where ce.idsede = $idsede and $fecha 
			GROUP by tc.idtipo_comprobante ";

			$bd->xConsulta($sql);
			break;
		
		// vesus sedes
		case 4008:
				$idsede = $_POST['idsede'];			
				$fecha = $_POST['fecha'];
				$hoy = "if (rp.fecha_cierre = '', 1, 0 )";
				$columm_add = '';
				$rango = $_POST['rango'];
				
	
				switch ($rango) {
					case 'fecha':
							
						if ( $fecha == 0 ) {
							$fecha = " and (rp.cierre = 0 and STR_TO_DATE(rp.fecha_cierre, '%d/%m/%Y') BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND NOW()";
							$hoy = "if (rp.fecha_cierre = '', 1, 0 )";
						} else {
							$hoy = "if (STR_TO_DATE(rp.fecha, '%d/%m/%Y') = STR_TO_DATE('$fecha', '%d/%m/%Y'), 1, 0 )";				
							$fecha = " and (STR_TO_DATE(rp.fecha, '%d/%m/%Y') BETWEEN  DATE_ADD(STR_TO_DATE('$fecha', '%d/%m/%Y'), INTERVAL -1 DAY) and STR_TO_DATE('$fecha', '%d/%m/%Y'))";
						}
	
						$columm_add = '';
						break;
					case 'semana':
							$hoy = "if(WEEK(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) = WEEK(now()), 1 ,0)";
							$fecha = " and STR_TO_DATE(rp.fecha, '%d/%m/%Y') between date_sub(now(),INTERVAL 1 WEEK) and now()";					
							$columm_add = ", WEEK(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) num_semana, if(WEEK(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) = WEEK(now()), 1 ,0) semana_actual
							, DAYNAME(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) nom_dia
							, DAYOFWEEK(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) num_dia";
						break;
					case 'mes':
						$columm_add = ", MONTHNAME(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) nom_mes
						, MONTH(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) num_mes, YEAR(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) num_yy";
						
						$fecha = $fecha == 0 ? 'now()' : "STR_TO_DATE('$fecha', '%d/%m/%Y')";
						$hoy = "if(MONTH(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) = MONTH($fecha), 1 ,if(MONTH(STR_TO_DATE(rp.fecha, '%d/%m/%Y')) < MONTH($fecha) - 1, 2, 0 ))";
						$fecha = " and STR_TO_DATE(rp.fecha, '%d/%m/%Y') between date_sub($fecha,INTERVAL 6 MONTH) and $fecha";					
						break;
				}
				
	
				$idsede = $idsede == 0 ? $g_idsede : $idsede;

				$sql = "SELECT s.idsede, s.nombre nomsede, rpd.importe, STR_TO_DATE(rp.fecha, '%d/%m/%Y') fecha, DATE_FORMAT(STR_TO_DATE(rp.fecha, '%d/%m/%Y %H:%i:%s %p'), '%H %p') hora, rp.estado
						, rp.fecha_cierre, tp.descripcion des_tp 
						, $hoy hoy, tp.img $columm_add				
					from registro_pago_detalle rpd 
						 inner join registro_pago rp on rp.idregistro_pago = rpd.idregistro_pago 
						 inner join tipo_pago tp on rpd.idtipo_pago = tp.idtipo_pago
						 inner join sede s on s.idsede = rp.idsede 
						 where rp.idorg = $g_ido  $fecha
					 order by rpd.idregistro_pago desc";
				 
				$bd->xConsulta($sql);
				break;
			
			// verificar si sede debe
			case 5000:				
				$id = isset($_POST['i']) ? $_POST['i'] : $g_idsede;
				$sql = "call procedure_sede_pago_notifica($id)";
				$bd->xConsulta($sql);
				break;
			case 5001: // cuenta a pagar
				$sql = "call procedure_sede_calc_pago_pendiente($g_idsede)";
				$bd->xConsulta($sql);
				break;
			case 5002: //
				$sql= "call procedure_pwa_purchasenumber()";
				$bd->xConsulta($sql);
				break;
			case 5003:
				$sql = "SELECT o.idorg, CONCAT('PUTCS', o.idorg) as idcliente_card, '' nombres, o.email as correo, 
						DATEDIFF(curdate(), STR_TO_DATE(s.finicio,'%d/%m/%Y')) as dias_registrado 
						from org o
							inner join sede s on s.idorg = o.idorg 
						where o.idorg = $g_ido LIMIT 1";
				$bd->xConsulta($sql);
				break;
			case 5004: // cuentas de abono
				$sql= "select* from cuenta_papaya where estado=0";
				$bd->xConsulta($sql);
				break;
			case 5005: // registrar confirmacion
				$dt = $_POST['data'];
				$sql= "insert into sede_pago_confirmacion (idsede, idcuenta_papaya, fecha, n_operacion, comentario)
						value ($g_idsede, ".$dt['idcuenta'].", now(),'". $dt['n']."', '".$dt['comentario']."')";
				$bd->xConsulta($sql);
				break;
			case 5006: // registrar confirmacion
				$dt = $_POST['data'];	
				$subtotales = $dt['st'];
				$importe = $dt['i'];	
				$importe_facturar = $dt['impf'];
				$iscard = $dt['iscard'];
				if ($importe < 0) {
					$dtSend = array(
						"noperacion" => "0",
						"comentario" => "Importe tarjeta supera o iguala al importe pendiente.",
						"idcuenta_papaya" => 3,
						"idconfirmacion" => -1,
						"ispago_tarjeta" => 0,
						"data_pago_tarjeta" => "[]",
						"importe" => 0,
						"importe_facturar" => $importe_facturar,
						"subtotales" => $subtotales
					);
				}

				if ($iscard == "1") {
					$dtSend = array(
						"noperacion" => "Pago con tarjeta",
						"comentario" => "Pago con tarjeta desde Restobar.",
						"idcuenta_papaya" => 3,
						"idconfirmacion" => -1,
						"ispago_tarjeta" => 1,
						"data_pago_tarjeta" => "[]",
						"importe" => $importe,
						"importe_facturar" => $importe_facturar,						
						"subtotales" => $subtotales
					);
				} else {
					// abono en cuenta
					$dtOtros = $dt['dtOtros'];
					$dtSend = array(
						"noperacion" => $dtOtros['noperacion'],
						"comentario" => $dtOtros['comentario'],
						"idcuenta_papaya" => $dtOtros['idcuenta_papaya'],
						"idconfirmacion" => -1,
						"ispago_tarjeta" => 0,
						"data_pago_tarjeta" => "[]",
						"importe" => $importe,
						"importe_facturar" => $importe_facturar,						
						"subtotales" => $subtotales
					);
				}

				

				$dtSend = json_encode($dtSend, true);
				$sql= "call procedure_sede_confirmacion_pago_servicio($g_idsede, '$dtSend')";
				$bd->xConsulta($sql);
				break;
			
			case 5007: // descargar comprobantes
				$sql = "SELECT concat(MONTHNAME(fecha), ' ', YEAR(fecha)) mes, external_id 
					from sede_pago_confirmacion spc 
					where idsede = $g_idsede and confirmado = 1 AND external_id IS NOT NULL 
					order by idsede_pago_confirmacion desc";
				$bd->xConsulta($sql);
				break;
			
			
			case 21: //modificar list porcion
				$dtSend = json_encode($_POST['data'], true);
				$sql= "call procedure_guardar_mod_procion('$dtSend')";
				$bd->xConsulta($sql);
				break;
				
			case 22: // encuestas
				$idencuesta = $_POST['idencuesta'];
				$sql = "select esc.idencuesta_sede_conf,erd.idencuesta_pregunta, ep.pregunta,  erd.idencuesta_respuesta, count(erd.idencuesta_respuesta) cantidad
				from encuesta_resultados_detalle erd 
					inner join encuesta_resultados er on erd.idencuesta_resultados = erd.idencuesta_resultados 
					inner join encuesta_pregunta ep on erd.idencuesta_pregunta = ep.idencuesta_pregunta 
					inner join encuesta_sede_conf esc on esc.idencuesta_sede_conf = er.idencuesta_sede_conf 
				where esc.idencuesta_sede_conf=$idencuesta and esc.estado = 0  
				GROUP by erd.idencuesta_pregunta, erd.idencuesta_respuesta
				order by ep.idencuesta_pregunta asc";
				$bd->xConsulta($sql);
				break;

			case 2201: // encuestas - repuestas
				$sql = 'select * from encuesta_respuesta';
				$bd->xConsulta($sql);
				break;

			case 2202: // lista de encuestas activas
				$sql="select esc.idencuesta_sede_conf, esc.nombre, esc.fecha_creacion from encuesta_sede_conf esc where idsede = $g_idsede and estado = 0";
				$bd->xConsulta($sql);
				break;
		
			case 2203: // comentarios
				$idencuesta = $_POST['idencuesta'];
				$pagination = $_POST['pagination'];
				$sql="select esc.idencuesta_sede_conf, erd.comentario
				from encuesta_resultados_detalle erd 
					inner join encuesta_resultados er on erd.idencuesta_resultados = erd.idencuesta_resultados 
					inner join encuesta_pregunta ep on erd.idencuesta_pregunta = ep.idencuesta_pregunta 
					inner join encuesta_sede_conf esc on esc.idencuesta_sede_conf = er.idencuesta_sede_conf 
				where esc.idencuesta_sede_conf = $idencuesta and esc.estado = 0 and erd.comentario != ''
				order by ep.idencuesta_pregunta asc";


				$sqlCount = "SELECT count(idsede_calificacion) as d1 from sede_calificacion where idsede = $g_idsede";
				
				$bd->xConsulta($sql);
				break;
			
			case 23: // verifica si hay cajas abiertas - control de pedidos
				$sql = "select count(idregistro_pago) cant from registro_pago rp where idsede = $g_idsede and STR_TO_DATE(fecha, '%d/%m/%Y') = CURDATE() and idusuario!=$g_idusuario and cierre=0 order by idregistro_pago desc limit 1";
				$bd->xConsulta($sql);
				break;
	}
?>
