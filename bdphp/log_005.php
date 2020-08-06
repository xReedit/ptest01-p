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
	$g_idusuario = $_SESSION['idusuario'];
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
			
			$sql = "update item_subitem_content set subitem_required_select = ".$arrItem['required_select'].", subitem_cant_select=".$arrItem['cant_select'].", is_sum_cant_subitems = ".$arrItem['is_sum_cant_subitems']."  where iditem_subitem_content=".$arrItem['iditem_subitem_content'];
			// $sql = "update item_subitem_content set is_sum_cant_subitems = ".$arrItem['is_sum_cant_subitems']."  where iditem_subitem_content=".$arrItem['iditem_subitem_content'];
			$bd->xConsulta_NoReturn($sql);

			$sql = "update item_subitem_content_detalle set subitem_required_select = ".$arrItem['required_select'].", subitem_cant_select=".$arrItem['cant_select']." where iditem_subitem_content=".$arrItem['iditem_subitem_content']." and iditem=".$arrItem['iditem'];
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
			$sql = "select isubd.iditem_subitem_content_detalle, isub.iditem_subitem_content, isub.iditem, isub.titulo, isub.compartido, isub.is_sum_cant_subitems, isubd.subitem_required_select, isubd.subitem_cant_select from item_subitem_content_detalle isubd
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
			$fecha = $_POST['f'];			
			// $sql = "SELECT rp.idregistro_pago, c.nombres as nomcliente, rp.fecha, 
			// 		(select nummesa from pedido where idregistro_pago = rp.idregistro_pago limit 1) as nummesa,
			// 		format(rp.total, 2) as importe, tpc.descripcion as canal, cast(rp.data_pago_pwa as json)  as datos
			// 	from registro_pago rp
			// 		inner join cliente c on rp.idcliente = c.idcliente
			// 		inner join tipo_consumo tpc on rp.idtipo_consumo = tpc.idtipo_consumo
			// 	where (rp.idsede=$g_idsede and rp.from_pwa = 1) and rp.cierre=0
			// 	order by rp.idregistro_pago desc";
			// $bd->xConsulta($sql);

			$sql = "SELECT * from pwa_pago_transaction where idsede=$g_idsede and cast(fecha as date) = cast('".$fecha."' as date) order by idpwa_pago_transaction desc";
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
			$soketId = $_POST['socketId'];
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
			$sql="SELECT p.idpedido, p.fecha, CURDATE() f_registro, if (p.is_from_client_pwa = 1, 'APP', tpc.descripcion) destpc, s.descripcion dessec, i.descripcion ides, u.usuario usuario
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
			$bd->xConsulta($sql);
			break;
	}
?>