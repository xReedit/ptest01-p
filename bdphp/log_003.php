<?php
	//log registrar el print server
	session_start();	
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');

	$op = $_GET['op'];
	
	$g_ido = $_SESSION['ido'];
	$g_idsede = $_SESSION['idsede'];
	$g_us = $_SESSION['idusuario'];
	$fecha_now = date("d/m/Y");
	$hora_now = date("H:i:s");


	

    switch ($op) {
		case '1': //registrar impresion
			$detalle_json = addslashes($_POST['datos']); //addslashes para caracteres especiales 
			$idprint_server_estructura = $_POST['idprint_server_estructura'];
			$tipo = $_POST['tipo'];
			$sql="INSERT INTO print_server_detalle (idorg, idsede, idusuario, idprint_server_estructura, descripcion_doc, fecha, hora, detalle_json) 
											values (".$g_ido.",".$g_idsede.",".$g_us.",".$idprint_server_estructura.", '".$tipo."','".$fecha_now."','".$hora_now."','".$detalle_json."')";
						
			$ultimoID = $bd->xConsulta_UltimoId($sql);


			// $port = 5819; // Port the node app listens to
			// $address = 'http://localhost'; // IP the node app is on

			// $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			// $result = socket_connect($socket, $address, $port);

			// // $data = array('itemid' => '1234567', 'steamid' => '769591951959', 'otherinfo' => 'hi there');
			// // $encdata = json_encode($data);
			// // socket_write($socket, $encdata, strlen($encdata));
			// socket_close($socket);

			print $ultimoID;
			
			break;
		case '101': // verificar si registro se imprimio correctamente
			$sql="select error from print_server_detalle where idprint_server_detalle = ".$_POST['id'];
			$estadoPrint = $bd->xDevolverUnDato($sql);
			print $estadoPrint;
		break;
		case '2': // buscar documentos no imprimidos
			// pse.estructura_json,
			$UltimoId=$_POST['ultimoId'];
			if ( $UltimoId!='' ) { $UltimoId=' and psd.idprint_server_detalle>'.$UltimoId.' '; }
			$sql="SELECT psd.*, pse.nom_documento, u.nombres as nomUs
						FROM print_server_detalle as psd
							INNER JOIN print_server_estructura as pse on pse.idprint_server_estructura = psd.idprint_server_estructura
							INNER JOIN usuario as u on u.idusuario = psd.idusuario
					WHERE (psd.idorg=".$g_ido." and psd.idsede=".$g_idsede." and psd.impreso=0) ".$UltimoId." ORDER BY psd.idprint_server_detalle DESC";
			$bd->xConsulta($sql);
			break;
		case '201': //verificar si hay nuevos registros
			$UltimoId=$_POST['ultimoId'];
			if ( $UltimoId!='' ) { $UltimoId=' and idprint_server_detalle > '.$UltimoId.' '; }

			$sql="SELECT MAX(idprint_server_detalle) FROM print_server_detalle
						where (idorg=".$g_ido." and idsede=".$g_idsede." and impreso=0)".$UltimoId;
			
			$numero_pedidos_actual=$bd->xDevolverUnDato($sql);
			echo "retry: 2000\n"."data:".$numero_pedidos_actual."\n\n";
			ob_flush();
			flush();
			break;
		case '3': //guardar impreso=1
			$sql="update print_server_detalle set impreso=1 where idprint_server_detalle=".$_POST['id'];
			$bd->xConsulta_NoReturn($sql);
			break;
		case '4': // list estructuras
			$sql="SELECT nom_documento, v, estructura_json FROM print_server_estructura where estado=0";
			$bd->xConsulta($sql);			
			break;
		case '5':// logo bits
			$sql = "SELECT logo64 FROM sede where idsede=".$g_idsede;
			$logo = $bd->xDevolverUnDato($sql);	
			echo $logo;
			break;
		case '6': // update is_precuenta pedido
			$id = $_POST['id'];
			$sql='update pedido set is_precuenta = 1 where idpedido = '.$id;
			$bd->xConsulta($sql);			
			break;
	}

?>