<?php
	//log - adm contador
	session_start();	
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
			$u=$_SESSION['idusuario'];
			$sql="
			SELECT s.* 
				from us_cpc_sedes as s
					INNER join us_cpc as u on s.idus_cpc=u.idus_cpc
				where u.idusuario=$u and s.estado=0
				ORDER BY razonsocial, nomsede, ciudad
			";
			$bd->xConsulta($sql);
			break;
		case '2': // descargar reporte
			$arrItem=$_POST['item'];
			$arrItem_establecimiento = $arrItem['establecimiento'];
			$data_array = array(
				"id" => $arrItem_establecimiento['userid'],
				"s" => $arrItem_establecimiento['serie'],
				"m" => $arrItem['m'],
				"y" => $arrItem['y']
			);

			// echo json_encode($data_array);

			$get_data = callAPI('GET-BODY-JSON', 'http://apifac.papaya.com.pe:3719/api/documents', json_encode($data_array));
			$response = json_decode($get_data);
			print json_encode($response);
			
			break;
	}

?>