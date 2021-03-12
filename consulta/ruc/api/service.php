<?php
    header('Access-Control-Allow-Origin: *'); 
	header('Content-Type: text/plain');
	
	require "../src/autoload.php";
	
	$token = $_GET['token'];
	$ruc = $_GET['ruc'];

	$cliente = new \Sunat\Sunat(true,true,$token);
	
	
	$ruc = (isset($ruc))? $ruc : false;
	// echo $cliente->search( $ruc, true );
	$resp = $cliente->search( $ruc, true );

	$respDecode = json_decode($resp, true);
	$pos = strrpos($respDecode["result"]["RazonSocial"], "****");	
	$hayDatos = $respDecode["haydatos"];

	// 110321 // si no hay datos tambien manda a consultar en temporal
	if ( !$pos &&  $hayDatos ) {
		echo $resp;		
	} else {
		// CONSULTA RUC TEMPORAL 080819
		// se soluciono al 041019
		
		$url = 'http://appx.papaya.com.pe/consultaruc-tempo/consultaruc.tempo.php?ruc='.$ruc;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);

		ob_start();
		return curl_exec ($ch);
		ob_end_clean();
		curl_close ($ch);
	
    	// CONSULTA RUC TEMPORAL 080819
	}

	// echo $resp;



	// 110121 si delvuele *** la razon socual busca en ruta temporañ
	// if (!$pos) {
	// 	echo $resp;
	// } else {

	// 	// CONSULTA RUC TEMPORAL 080819
	// 	// se soluciono al 041019
	
	// 	$url = 'http://appx.papaya.com.pe/consultaruc-tempo/consultaruc.tempo.php?ruc='.$ruc;

	// 	$ch = curl_init();
	// 	curl_setopt($ch, CURLOPT_URL,$url);

	// 	ob_start();
	// 	return curl_exec ($ch);
	// 	ob_end_clean();
	// 	curl_close ($ch);
    // 	// CONSULTA RUC TEMPORAL 080819

	// }

	// CONSULTA RUC TEMPORAL 080819
	// se soluciono al 041019
	
	// $url = 'http://appx.papaya.com.pe/consultaruc-tempo/consultaruc.tempo.php?ruc='.$ruc;

	// $ch = curl_init();
 	// curl_setopt($ch, CURLOPT_URL,$url);

 	// ob_start();
    // return curl_exec ($ch);
    // ob_end_clean();
    // curl_close ($ch);
	
    // CONSULTA RUC TEMPORAL 080819
	
?>
