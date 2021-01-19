<?php
session_start();
$op=$_GET['op'];
$timeSpan = '';

switch ($op) {
	case '4': // logo comercio desde plantilla correo que viene en formato base64
        $path = '../../repositorio/img_correo/';
        $fecha = new DateTime();
        $timeSpan = $fecha->getTimestamp();
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
