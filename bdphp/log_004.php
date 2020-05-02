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
	
    switch ($op) {
		case '1': //lista sede
			$sql="
				SELECT o.idorg, s.idsede, o.ruc, o.telefono, o.nombre as razonsocial, s.nombre as nomsede, s.ciudad, s.tipo, s.finicio
				FROM org as o 
					LEFT JOIN sede as s on s.idorg=o.idorg
				WHERE o.estado=0 
			";
			$bd->xConsulta($sql);
			break;		
		case '101': //lista sede sergun org
			$idorg = $_POST['idorg'];
			$sql="SELECT * FROM sede WHERE idorg=".$idorg." and estado=0";
			$bd->xConsulta($sql);
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
						VALUES(".$idorg.", ".$idsede.", 'CONSUMIR EN EL LOCAL', 'LOCAL', 0), (".$idorg.", ".$idsede.", 'PARA LLEVAR', '', 0);

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
			$sql="select idus_cpc,idus_cpc_sedes,razonsocial,nomsede,serie,ciudad from us_cpc_sedes where idus_cpc = ".$_POST['id']." and estado=0 order by razonsocial,nomsede, ciudad";
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
		
		case 700: //horario de trabajo
			$sql = "insert into sede_horario_trabajo (idsede, de, a) values (".$_POST['idsede'].", '".$_POST['de']."', '".$_POST['a']."')";
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
	}

?>