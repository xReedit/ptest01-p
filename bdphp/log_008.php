<?php
	//log registrar peidod y pago
	// session_set_cookie_params('4000'); // 1 hour
	// session_regenerate_id(true); 
    session_start();
	//header("Cache-Control: no-cache,no-store");
	header('Content-Type: application/json;charset=utf-8');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");


    // $aa = file_get_contents('php://input');
    $aa = $_POST['p_from'];
    $aa = isset($aa) ? $aa : json_decode(file_get_contents('php://input'));
    $data = [ 'name' => $aa->p_from ];
    // echo json_encode($data);


    $sql = "select * from usuario where idusuario=1";
	$bd->xConsulta($sql);
    
    // $fecha_actual=date('Y').'-'.date('m').'-'.date('d');
	// $hora_actual=date('H').':'.date('i').':'.date('s');

	// echo $fecha_actual.'|'.$hora_actual;


    // $data = [ 'name' => 'God', 'age' => -1 ];
    // echo json_encode( $data );

?>