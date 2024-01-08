<?php
date_default_timezone_set('America/Lima');	

header('Access-Control-Allow-Origin: *');  

//require_once('https://papaya.com.pe/p.php'); 
//$file = file_get_contents('pruebas2.txt'); 
//echo $file;

// $uriLogo64 = file_get_contents("logo.txt");

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

// eval($estructura);

//$aa = $_POST['ip_print'];
//echo json_encode($aa); 
//eval(file_get_contents('https://papaya.com.pe/p.txt'));


?>