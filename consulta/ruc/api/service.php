<?php
    header('Access-Control-Allow-Origin: *'); 
	header('Content-Type: text/plain');
	
	require "../src/autoload.php";
	
	$token = $_GET['token'];
	$ruc = $_GET['ruc'];

	$cliente = new \Sunat\Sunat(true,true,$token);
	
	
	$ruc = (isset($ruc))? $ruc : false;
	echo $cliente->search( $ruc, true );
?>
