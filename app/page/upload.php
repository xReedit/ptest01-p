<?php
session_start();
$op=$_GET['op'];


if (array_key_exists('HTTP_X_FILE_NAME', $_SERVER) && array_key_exists('CONTENT_LENGTH', $_SERVER)) {
	$fileName = $_SESSION['ido'].$_SESSION['idsede'].$_SERVER['HTTP_X_FILE_NAME'];
	$contentLength = $_SERVER['CONTENT_LENGTH'];
} else throw new Exception("Error retrieving headers");


switch ($op) {
	case '1':
		$path = '../../file/';
		break;	
	case '2':
		$path = '../../print/logo/';
		break;
	case '3': // logo comercio app para la aplicacion delivery
		$fileName = $_SERVER['HTTP_X_FILE_NAME'];
		$path = '../../print/logo/';
		break;
	case '5':
		$path = '../../repositorio/gif_update/';
		break;		
}

if (!$contentLength > 0) {
    throw new Exception('No file uploaded!');
}

file_put_contents(
    $path . $fileName,
    file_get_contents("php://input")
);

chmod($path.$fileName, 0777);
?>