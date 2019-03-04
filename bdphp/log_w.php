<?php
    
    $user = "viudaneg_papayaR";
    $pass = "159159resto";
    $host = "viudanegra.com.pe";
    $bdx = "viudaneg_papaya.resto";


    $mysqli = new mysqli($host, $user, $pass, $bdx);
    if ($mysqli->connect_errno) {
        echo "Fallo al conectar a MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    } 
    echo $mysqli->host_info . "\n";

    // $mysqli = new mysqli("127.0.0.1", "usuario", "contraseña", "basedatos", 3306);
    // if ($mysqli->connect_errno) {
    //     echo "Fallo al conectar a MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    // }

    // $connection = mysqli_connect($host, $user, $pass);

    // if ($connection) {
    //     echo "me conecte";
    // } else {
    //     echo "error ".mysql_error();
    // }

    echo "si llega";
?>

