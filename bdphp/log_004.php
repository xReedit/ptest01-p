<?php
	//log - adm
	session_start();	
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');

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
	}

?>