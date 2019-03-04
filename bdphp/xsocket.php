<?php
session_start();	
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
date_default_timezone_set('America/Lima');	

include "ManejoBD.php";
$bd=new xManejoBD("restobar");
//$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//$socket = fsockopen ( $host );
		$sql="SELECT count(*) as d1 FROM pedido where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].")";			
			$numero_pedidos_actual=$bd->xDevolverUnDato($sql);						
			echo "retry: 10000\n"."data:".$numero_pedidos_actual."\n\n";
			//ob_flush();			
			//flush();
			//socket_write($numero_pedidos_actual);
			break;


/*log("Handshaking..."); 
list($resource,$host,$origin) = getheaders($buffer); 
$upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" . "Upgrade: WebSocket\r\n" . "Connection: Upgrade\r\n" . "WebSocket-Origin: " . 
$origin . "\r\n" . "WebSocket-Location: ws://" . 
$host . $resource . "\r\n" . "\r\n"; 
$handshake = true; 
socket_write($socket,$upgrade.chr(0),strlen($upgrade.chr(0)));
*/
?>