<?php
date_default_timezone_set('America/Lima');	

// header('Access-Control-Allow-Origin: *');  


$uriLogo64 = file_get_contents("logo.txt");

// $arrData = $_POST['arrData'];
$item = $_POST['arrData'];

// foreach ($arrData as $item) {
	
	$data=$item['data'];	
	$nom_file=$item['nom_documento'].".txt";
	$nom_us=$item['nomUs'];
	$hora_actual=$item['hora'];

	// ob_start();	
	$estructura = file_get_contents($nom_file);
	eval($estructura);	
	
// }



?>