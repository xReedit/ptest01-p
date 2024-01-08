<?php
session_start();
$op=$_GET['op'];
$timeSpan = '';

$fecha = new DateTime();
$timeSpan = $fecha->getTimestamp();

switch ($op) {
    case '1': // img de carta
		$path = '../../file/';
        // $timeSpan = '';
		break;	
	case '4': // logo comercio desde plantilla correo que viene en formato base64
        $path = '../../repositorio/img_correo/';
		break;
    case '5':
        $path = '../../repositorio/gif_update/';
        break;
    case '6': // sube imagen de promocion
        $path = '../../repositorio/img_promo/';
        $timeSpan = '';
        break;
}


    
		$nomfile = $_POST['nomfile'];
		$fileName = $_SESSION['ido'].$_SESSION['idsede'].$nomfile;		

        $data = $_POST['img'];
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $type = explode('/', $type);
        $type = strtolower($type[1]); // jpg, png, gif
        
        $data = base64_decode($data);

		$fileName = $fileName.$timeSpan.'.'.$type;
		
		file_put_contents($path.$fileName, $data);
        chmod($path.$fileName, 0777);
        
		echo $fileName;
