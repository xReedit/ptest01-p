<?php
	//log - adm
	session_start();		
	// header('content-type application/json');
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');

	include('log_006.php');

	$op = $_GET['op'];
	$g_idsede = $_SESSION['idsede'];	
	$g_idorg = $_SESSION['ido'];	
	$g_idusuario  = $_SESSION['idusuario'];	
	
    switch ($op) {
		case '1': //lista sede
			$sql= "
				SELECT o.idorg, s.idsede, o.ruc, o.telefono, o.nombre as razonsocial, s.nombre as nomsede, s.ciudad, s.tipo, s.finicio
					,sd.is_bloqueado, sd.is_baja, s.costo_restobar_fijo_mensual
					, spc.importe importe_plan, spc.descripcion plan, ss.frecuencia
					, ss.idsede_suscripcion, ss.idsede_plan_contratado
				FROM org as o 
					LEFT JOIN sede as s on s.idorg=o.idorg
					LEFT join sede_estado sd on s.idsede=sd.idsede
					left join sede_suscripcion ss on s.idsede=ss.idsede
					left join sede_plan_contratado spc on ss.idsede_plan_contratado = spc.idsede_plan_contratado
				WHERE o.estado=0 order by o.idorg, s.idsede, s.estado
			";
			$bd->xConsulta($sql);
			break;		
		case '101': //lista sede sergun org
			$idorg = $_POST['idorg'];
			$sql="select * from sede where idorg=$idorg and estado=0";			
			$bd->xConsulta($sql);
			// echo $sql;
			// $bd->xConsulta($sql);
			break;
		case '102': //load usuario implementador
			$idorg = $_POST['idorg'];
			$sql="select usuario from usuario where idorg=".$idorg." and cargo='IMPLEMENTADOR' and estado=0";
			$rpt = $bd->xDevolverUnDato($sql);
			print $rpt;
			break;		
		case '2':// validar usuario
			$nom_usuario=$_POST['u'];
			$sql="select usuario from usuario where usuario='".$nom_usuario."' and estado=0";
			$rpt = $bd->xDevolverUnDato($sql);
			print $rpt;
			break;
		case '201':// guardar super usuario
			$datos=$_POST['d'];
			$sql = "Insert into usuario (idorg, idsede, nombres, cargo, usuario, pass, acc, per, rol, super) values 
										(".$datos['idorg'].",".$datos['idsede'].",'SISTEMA','IMPLEMENTADOR','".$datos['u']."','".$datos['p']."','A1,A2,A3,A4,A5,A6,A7,A8,A9,A10,A11,B1,B2,B3,B4,B5,B6,C1,C2,','Pe1,Pe2,Pe3,Pe4,',1,1)";
			$bd->xConsulta($sql);
			break;
		case '202':// validar sufijo
			$sufijo=$_POST['u'];
			$sql="select sufijo from sede where sufijo='".$sufijo."' and estado=0";
			$rpt = $bd->xDevolverUnDato($sql);
			print $rpt;
			break;
		case '3': // new script org
			$idorg = $_POST['idorg'];
			$idsede = $_POST['idsede'];
			$sql="				
				INSERT INTO almacen (idorg, idsede, descripcion, bodega, imprimir_comanda, estado)
					VALUES(".$idorg.", ".$idsede.", 'ALMACEN CENTRAL', 0, 0, 0);
				INSERT INTO almacen (idorg, idsede, descripcion, bodega, imprimir_comanda, estado)
					VALUES(".$idorg.", ".$idsede.", 'COCINA', 0, 0, 0);
				INSERT INTO almacen (idorg, idsede, descripcion, bodega, imprimir_comanda, estado)
					VALUES(".$idorg.", ".$idsede.", 'BODEGA', 1, 0, 0);				

				INSERT INTO tipo_consumo (idorg, idsede, descripcion, titulo, estado)
						VALUES(".$idorg.", ".$idsede.", 'CONSUMIR EN EL LOCAL', 'LOCAL', 0), (".$idorg.", ".$idsede. ", 'PARA LLEVAR', '', 0);

				INSERT INTO sede_estado (idsede,is_bloqueado, is_baja) values (".$idsede.", '0', '0');

			";

			$sql_cnf_print="INSERT INTO conf_print (idorg, idsede, ip_print, num_copias) VALUES(".$idorg.", ".$idsede.", '', 0);";
			$idConfigPrint = $bd->xConsulta_UltimoId($sql_cnf_print);

			$sql_pd="INSERT INTO conf_print_detalle
				(idconf_print, descripcion, porcentaje, es_impuesto, activo, estado, idorg, idsede)
				VALUES(".$idConfigPrint.",'I.G.V', '18', 1, 1, 0, ".$idorg.", ".$idsede.");";
			
			$bd->xMultiConsulta($sql.$sql_pd);
			break;
		case 4: // usuario contadores adm
			$sql = "
				SELECT cpc.*, u.*, count(s.idsede) as num_sede
				FROM  us_cpc AS cpc	
					inner join usuario as u using(idusuario)
					left join us_cpc_sedes as s on u.idsede=s.idsede
				GROUP by cpc.idus_cpc
				order by cpc.nombre_cpc, cpc.ciudad
			";
			$bd->xConsulta($sql);
			break;
		case 401: // lad establecimientos asignados
			// $sql = "
			// 	SELECT s_u.idorg, s_u.idsede, o.nombre as establecimiento, s.nombre as sede, s.ciudad
			// 	FROM us_cpc_sedes as s_u
			// 		inner join sede as s using(idsede)
			// 		inner join org as o on s.idorg = o.idorg
			// 	where s_u.idus_cpc = ".$_POST['id']."
			// 	order by o.nombre, s.idsede
			// ";
			$sql="select idus_cpc,idus_cpc_sedes,razonsocial,nomsede,serie,ciudad, mes_inicio from us_cpc_sedes where idus_cpc = ".$_POST['id']." and estado=0 order by razonsocial,nomsede, ciudad";
			$bd->xConsulta($sql);
			break;
		case 40101: // get idus_cpc from idusuario
			$sql="select idus_cpc as d1 from us_cpc where idusuario=".$_POST['id'];			
			$rpt = $bd->xDevolverUnDato($sql);
			print $rpt;
			break;
		case 40102: // cambiar las variables de sesion
			$_SESSION['ido'] = $_POST['ido'];
			$_SESSION['idsede'] = $_POST['ids'];
			print '1';
			break;
		case 402:// num usuario contador
			$sql="SELECT COUNT(idusuario)+1 as d1 from usuario where rol=2 and estado=0";
			$rpt = $bd->xDevolverUnDato($sql);
			print $rpt;
			break;
		case 403: // registrar usuario contador
			$sql = "insert into usuario (nombres, cargo, usuario, pass, acc, rol, nuevo) 
														values ('".$_POST['n']."', 'CONTADOR', '".$_POST['u']."','123456','D6,',2,0)";
			$id_us = $bd->xConsulta_UltimoId($sql);
			print $id_us;
			break;
		case 404:// load sedes mas contadores
			$sql="
				select s.idsede, s.nombre, s.ciudad, s.direccion, s.telefono, u.nombre_cpc, u.ciudad as u_ciudad, u.telefono as u_telefono, if(IsNull(u.nombre_cpc), 0, 1) as asignado
				FROM sede as s
					left join us_cpc_sedes as us using(idsede)
					left join us_cpc as u USING(idus_cpc)
				where s.idorg=".$_SESSION['ido']." and s.estado=0
				order by s.idsede
			";
			$bd->xConsulta($sql);
			break;
		case 405:// listado de contadores
			$sql="SELECT * from us_cpc where estado=0";
			$bd->xConsulta($sql);
			break;
		case 406: // asignar contador a sede
			$idorg = $_SESSION['ido'];
			$idsede = $_POST['idsede'];
			$idus_cpc = $_POST['id'];
			$sql="CALL procedure_cpc_sedes(".$idus_cpc.",".$idorg.",".$idsede.");";
			$bd->xConsulta_NoReturn($sql);
			break;
		case 5:// load companies apifac
			$get_data = callAPI('GET', 'http://apifac.papaya.com.pe:3719/api/companies', false);
			$response = json_decode($get_data, true);
			print json_encode($response);
			// $errors = $response['response']['errors'];
			// $data = $response['response']['data'][0];
			break;
		case 501: // guarda establecimiento asignado
			$arrItem=addslashes(json_encode($_POST['item']));
			$sql = "CALL procedure_asignar_companies_contador('".$arrItem."')";
			$bd->xConsulta($sql);
			break;
		
		// configuracion app
		case 6: // cargar categorias
			$sql = "select * from sede_categoria where estado = 0";
			$bd->xConsulta($sql);
			break;
		case 601: // cargar SUBCategorias
			$sql = "select * from sede_subcategoria where estado = 0";
			$bd->xConsulta($sql);
			break;
		case 602: // cargar SUBCategorias suscritas por la sede
			$idsede = $_POST['idsede'];
			$sql = "
				SELECT GROUP_CONCAT(sc.idsede_categoria) idsede_categoria, GROUP_CONCAT(ssc.idsede_subcategoria) idsede_subcategoria from sede_subcategoria_suscrito ssc
				inner JOIN sede_subcategoria sc on ssc.idsede_subcategoria = sc.idsede_subcategoria
	   			where ssc.idsede = ".$idsede." and ssc.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 603: // guardar subcategorias suscritas
			$idsede = $_POST['idsede'];
			$items = $_POST['arrItems'];

			$sql = "update sede_subcategoria_suscrito set estado = 1 where idsede=".$idsede;
			$bd->xConsulta_NoReturn($sql);

			$sql_d = '';
			foreach ( $items as $item ) {
				$sql_d = $sql_d."(".$idsede.", ".$item['idsede_subcategoria']."),";
			}

			$sql_d=substr($sql_d, 0, -1);

			$sql_d = 'insert into sede_subcategoria_suscrito (idsede, idsede_subcategoria) values '.$sql_d;
			$bd->xConsulta($sql_d);
			break;
		case 604: // img
			$idsede = $_POST['idsede'];
			$img = $_POST['d'];
			$sql = "update sede set pwa_delivery_img = '".$img. "' where idsede = ".$idsede;
			$bd->xConsulta($sql);
			break;
		case 60401: // img-mini
			$idsede = $_POST['idsede'];
			$img = $_POST['d'];
			$sql = "update sede set img_mini = '".$img. "' where idsede = ".$idsede;
			$bd->xConsulta($sql);
			break;
		
		case 700: //horario de trabajo
			$sql = "insert into sede_horario_trabajo (idsede, de, a, numdia, desdia) values (".$_POST['idsede'].", '".$_POST['de']."', '".$_POST['a']."', '".$_POST['dias']."', '".$_POST['desdias']."')";
			$bd->xConsulta($sql);
			break;
		case 701: // load //horario de trabajo
			$sql = "select * from sede_horario_trabajo where idsede = ".$_POST['idsede']." and estado=0";
			$bd->xConsulta($sql);
			break;
		

		// seccion icon
		case 8: // update icon seccion
			if ( $_POST['opIcon'] === '0' ) { // seccion
				$sql = "update seccion set img = '".$_POST['img']."' where idseccion=".$_POST['i'];
			} else {
				// producto familia
				$sql = "update producto_familia set img = '".$_POST['img']."' where idproducto_familia='".$_POST['i']."'";
			}
			$bd->xConsulta($sql);
			break;		
		
		case 9: //load repartidores
			$sql = "SELECT r.*, s.nombre as nom_sede from repartidor r left join sede s on s.idsede = r.idsede_suscrito";
			$bd->xConsulta($sql);
			break;
		
		// cambios en el sistema
		case 10:
			$sql = "SELECT * from notificacion_cambios_sistema where estado=0 order by idnotificacion_cambios_sistema desc";
			$bd->xConsulta($sql);
			break;
		case 1001: // guardar registro actualizacion
			$item = $_POST['item'];
			$sql = "insert into notificacion_cambios_sistema (fecha, titulo, descripcion, imagen) values (curdate(), '".$item['titulo']."', '".$item['descripcion']."', '".$item['imagen']."')";			
			$bd->xConsulta($sql);
			break;

		case 11: // metodos de pago aceptados
			$sql = "select idtipo_pago, descripcion from tipo_pago where upper(descripcion) != 'APLICACION'";
			$bd->xConsulta($sql);
			break;
		case 1101: // metodos de pago aceptados
			$ids = $_POST["ids"];
			$sql = "update sede set metodo_pago_aceptados='$ids' where idsede=$g_idsede";
			$bd->xConsulta($sql);
			break;

		case 12:
			$sql="select psd.idprint_server_detalle, psd.fecha, psd.hora, psd.descripcion_doc, u.nombres usuario, u.idusuario from print_server_detalle psd  
			inner join usuario u on u.idusuario =psd.idusuario 
			where psd.idusuario = ".$_SESSION['idusuario']." and psd.idprint_server_estructura = 4
				and HOUR(TIMEDIFF(STR_TO_DATE(concat(psd.fecha, ' ', psd.hora), '%d/%m/%Y %H:%i:%s'), NOW())) < 72
			order by psd.idprint_server_detalle desc limit 5";
			$bd->xConsulta($sql);
			break;
		
		case 12001: // revisa si hay pendiente cierre para ofrecer el consolidado
			$sql = "select u.nombres from registro_pago rp 
					inner join usuario u on rp.idusuario = u.idusuario 
				where  DATE_SUB(CURDATE(), INTERVAL 2 DAY)  < STR_TO_DATE(rp.fecha, '%d/%m/%Y %H:%i:%s') 
					and rp.idsede=$g_idsede and rp.cierre =0 
				GROUP by rp.idusuario";
			$bd->xConsulta($sql);
			break;

		case 12002: // devuele las fecha de incio y fin del consolidado
			$sql = "call procedure_get_fechas_consolidado($g_idsede)";
			$bd->xConsulta($sql);
			break;
		case 13: // compras
			$pagination = $_POST['pagination'];
            $fecha = $pagination['pageFecha'];
            $filtroFecha = $fecha === '' ? '' : " HAVING c.f_registro = '".$fecha."'";
            $filtroFechaCount = $fecha === '' ? '' : " and (c.f_registro = '".$fecha."')";
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(tp.descripcion,c.f_registro,a.descripcion,COALESCE(p.descripcion, '')) LIKE '%".$pagination['pageFilter']."%' ";
            
            
			$sql="select c.idcompra, c.idalmacen, c.f_registro, c.f_compra, c.f_pago, c.total, tp.descripcion des_tipo_pago, a.descripcion des_almacen, COALESCE(p.descripcion, '') des_proveedor 				
			from compra c
			inner join tipo_pago tp on c.idtipo_pago = tp.idtipo_pago 
			inner join almacen a on c.idalmacen = a.idalmacen 
			left join proveedor p on c.idproveedor = p.idproveedor
			where c.idsede = ".$_SESSION['idsede']." and c.estado = 0 ".$filtro."
			order by idcompra desc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];
                			

            $sqlCount="
                SELECT count(c.idcompra) as d1 from compra as c   
				inner join tipo_pago tp on c.idtipo_pago = tp.idtipo_pago 
				inner join almacen a on c.idalmacen = a.idalmacen 
				left join proveedor p on c.idproveedor = p.idproveedor                 
                where (c.idsede=".$_SESSION['idsede']." and c.estado=0) ".$filtro." ".$filtroFechaCount;            
            


            $rowCount = $bd->xDevolverUnDato($sqlCount);

            $rpt = $bd->xConsulta($sql);            
            print $rpt."**".$rowCount;
			break;

		case 1301:
			$idcompra = $_POST['id'];
			$sql = "select ci.cantidad, p.descripcion producto, format(ci.ptotal, 2) precio from compra_items ci
				inner join producto p on ci.idproducto = p.idproducto 
				where ci.idcompra = $idcompra";
			$bd->xConsulta($sql);  
			break;
		
		case 14: // guardar cliente_sede
			$data = $_POST['data'];
			// $sql = "insert into cliente_sede (idsede, idcliente) values (".$g_idsede.",".$data['idcliente'].")";
			// echo $sql;
			$sql= "call procedure_save_new_modifica_cliente(".$data['idcliente'].",".$g_idsede.",'".$data['telefono']."')";
			$bd->xConsulta($sql); 
			break;

		case 15: // caja adelantos ordenes de pedidos	
			$sql = "select opa.idorden_pedido_adelanto, opa.idorden_pedido, opa.fecha_hora, opa.concepto, opa.importe, tp.descripcion destp, u.usuario
				,op.cliente_nom, op.numero
			from orden_pedido_adelanto opa
				inner join orden_pedido op on op.idorden_pedido = opa.idorden_pedido 
				inner join usuario u on u.idusuario = opa.idusuario 
				inner join tipo_pago tp on tp.idtipo_pago = opa.idtipo_pago 
			where op.idsede = $g_idsede and opa.isprocesado_caja = '0' and tp.idtipo_pago = 1";
			$bd->xConsulta($sql);
			break;

		case 1501: // aceptar pago adelanto orden pago
			$data = $_POST['data'];
			$sql = "update orden_pedido_adelanto set isprocesado_caja = '1' where idorden_pedido_adelanto=".$data['idorden_pedido_adelanto'];
			$bd->xConsulta_NoReturn($sql);

			// insertamos como ingreso a caja
			$motivo = "Orden de pago #".$data['numero']." ".$data['cliente_nom']." ".$data['concepto'];
			$sql_c = "insert into ie_caja(idorg,idsede,idusuario,tipo,motivo,fecha,monto,fecha_cierre)
					values($g_idorg, $g_idsede, $g_idusuario,1,'".$motivo."','".$data['fecha_guardar']."',".$data['importe'].",'')";

			$bd->xConsulta($sql_c);
			break;

		case 16:  // verifica la disponibilidad de link carta
			$nomCarta = $_POST['link_carta'];
			$sql = "select idsede from sede where link_carta = '$nomCarta' and estado = 0";
			$bd->xConsulta($sql);
			break;
		
		case 17: // recibir tickes entrada caja
			$sql = "select tt.fecha, tt.idusuario, tt.usuario, total total, CAST(CONCAT('[',GROUP_CONCAT(tt.lista),']') as json) as lista, '' fecha_guardar 
			from (
				select DATE_FORMAT(tr.fecha, '%d/%m/%Y') fecha, tr.idusuario, u.nombres usuario, sum(tr.total) total
				,JSON_OBJECT('descripcion', i.descripcion , 'cantidad', sum(trd.cantidad), 'total', format(sum(trd.total),2)) lista
				from ticket_rapido tr 
					inner join ticket_rapido_detalle trd on tr.idticket_rapido = trd.idticket_rapido 
					inner join item i on trd.iditem = i.iditem
					inner join usuario u on tr.idusuario = u.idusuario				
				where tr.idsede = $g_idsede and tr.asignada_caja = '0' and trd.iditem 
				GROUP by DATE_FORMAT(tr.fecha, '%d/%m/%Y'), tr.idusuario, trd.iditem
			) as tt
			GROUP by tt.fecha, tt.usuario";
			$bd->xConsulta($sql);
			break;

		case 1701: // aceptar pago tickes entrada
			$data = $_POST['data'];
			$sql = "update ticket_rapido set asignada_caja = '1' 
					where DATE_FORMAT(fecha, '%d/%m/%Y')='".$data['fecha']."' and idusuario=".$data['idusuario'];
			$bd->xConsulta_NoReturn($sql);

			// insertamos como ingreso a caja
			$motivo = "Tickets de Entrada - ".$data['usuario'];
			$sql_c = "insert into ie_caja(idorg,idsede,idusuario,tipo,motivo,fecha,monto,fecha_cierre)
					values($g_idorg, $g_idsede, $g_idusuario,1,'".$motivo."','".$data['fecha_guardar']."','".$data['total']."','')";

			$bd->xConsulta($sql_c);
			// echo $sql;
			break;
		
		case 1702: // lista ingresos varios
			$sql="select DATE_FORMAT(iv.fecha, '%d/%m/%Y %H:%i:%s') fecha, tp.descripcion tipo_pago, iv.nom_cliente, iv.importe, iv.concepto
			from ingreso_varios iv 
				inner join tipo_pago tp on tp.idtipo_pago = iv.idtipo_pago 	
			where iv.idusuario =$g_idusuario and iv.cierre=0 and iv.estado=0";
			$bd->xConsulta($sql);
			break;
		 case 1800: // update igv desde configuracion admin}
			$data = $_POST['data'];
			$idsede = $data['idsede'];
			$igv = $data['igv'];
			$activo = $data['activo'];
			$sql = "update conf_print_detalle set porcentaje = $igv, activo = $activo  where idsede = $idsede and descripcion = 'I.G.V'";
			$bd->xConsulta($sql);
			break;
		case 1801: //verifica si igv esta activo
			$idsede = $_POST['idsede'];
			$sql = "select activo, porcentaje from conf_print_detalle where idsede = $idsede and descripcion = 'I.G.V'";
			$bd->xConsulta($sql);
			break;
		case 1802: // suscripcion
			$data = $_POST['data'];

			$ultimo_pago = date('Y-m', strtotime($data['ultimo_pago']));
			// tomar como ultimo pago el ultimo dia del mes
			$ultimo_pago = date('Y-m-t', strtotime($ultimo_pago));
			$idsede_selected = $data['idsede'];

			// buscar si ya esta suscrito
			$sql = "select idsede_suscripcion from sede_suscripcion where idsede = $idsede_selected and activo = 0 and estado=0";
			$rpt = $bd->xDevolverUnDato($sql);

			if (isset($rpt)) {
				$idsede_suscripcion = $rpt;
				$sql = "update sede_suscripcion set idsede_plan_contratado = ".$data['idsede_plan_contratado'].", frecuencia = '".$data['frecuencia']. "', fecha_inicio = '".$data['fecha_inicio']."', ultimo_pago = '".$ultimo_pago."', nombre_contacto = '".$data['nombre_contacto']."', telefono_contacto = '".$data['telefono_contacto']."' where idsede_suscripcion = $idsede_suscripcion";
			} else {
				$sql = "insert into sede_suscripcion (idsede, idsede_plan_contratado, frecuencia, fecha_inicio, ultimo_pago, nombre_contacto, telefono_contacto) values 
				(".$data['idsede'].", ".$data['idsede_plan_contratado'].", '".$data['frecuencia']."', '".$data['fecha_inicio']."', '".$ultimo_pago."', '".$data['nombre_contacto']."', '".$data['telefono_contacto']."')";
			}

			$bd->xConsulta($sql);
			// echo $data;

			break;
		case 1803: // cargar datos sede_suscripcion
			$idsede = $_POST['idsede'];
			$sql = "select * from sede_suscripcion ss
				where ss.idsede = $idsede and ss.activo = 0 and ss.estado=0";
			$bd->xConsulta($sql);
			break;
		case 1804: // registrar pago suscripcion
			$data = $_POST['data'];
			$mes_pagado = date('Y-m', strtotime($data['ultimo_pago']));
			// $ultimo_pago = date('Y-m', strtotime($data['ultimo_pago']));

			// tomar como ultimo pago el ultimo dia del mes
			$ultimo_pago = date('Y-m-t', strtotime($mes_pagado));

			$idsede_selected = $data['idsede'];

			$sql = "select idsede_suscripcion from sede_suscripcion where idsede = $idsede_selected";
			$rpt = $bd->xDevolverUnDato($sql);
			$idsede_suscripcion = $rpt;

			// actualizamos fecha de pago suscripcion
			$sql = "update sede_suscripcion set activo='0', idsede_plan_contratado = " . $data['idsede_plan_contratado'] . ", frecuencia = '" . $data['frecuencia'] . "', ultimo_pago = '" . $ultimo_pago . "' where idsede_suscripcion = $idsede_suscripcion";
			$bd->xConsulta_NoReturn($sql);

			// cambiar estados de sede
			$sql = "update sede_estado set is_bloqueado = 0, is_bloqueo_contador = 0 where idsede = $idsede_selected";
			$bd->xConsulta_NoReturn($sql);

			// actualizamos el historial de pagos
			$sql = "insert into sede_detalle_pago (idsede, fecha, importe, descripcion) value ($idsede_selected, curdate(), '". $data['importe_pagado']."', 'PAGO DE SUSCRIPCION ".$mes_pagado."')";
			$bd->xConsulta($sql);
			break;
		case 1805: //guardar en sede_estado			
			$idsede = $data['idsede'];
			$sql = "insert into sede_estado (idsede,is_bloqueado, is_baja) values ($idsede, 0, 0)";
			$bd->xConsulta_NoReturn($sql);
			break;
		case 19: // dar de baja sede			
			$idsede = $_POST['idsede'];
			$motivo = $_POST['motivo_baja_sede'];
			$fecha = $_POST['fecha_baja_sede'];
			
			// sede_suscripcion
			$sql = "update sede_suscripcion set activo = 1 where idsede = $idsede";
			$bd->xConsulta_NoReturn($sql);

			//sede_estado
			$sql = "update sede_estado set is_bloqueado = 1, is_baja = 1, motivo_baja = '$motivo', fecha_baja = '$fecha' where idsede = $idsede";
			$bd->xConsulta_NoReturn($sql);

			echo 'ok';
			break;
		case 1901: //retablecer el servicio:
			$idsede = $_POST['idsede'];

			// sede_suscripcion
			$sql = "update sede_suscripcion set activo = 0 where idsede = $idsede";
			$bd->xConsulta_NoReturn($sql);

			$sql = "update sede_estado set is_bloqueado = 0, is_baja = 0, motivo_baja = '', fecha_baja = '' where idsede = $idsede";
			$bd->xConsulta_NoReturn($sql);

			echo 'ok';
			break;
		
	}

?>