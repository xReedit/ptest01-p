<?php
session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

switch($_GET['op']){
    case '1': //

        $url = $_POST['url'];
        $header = $_POST['header'];
        $jsonData = $_POST['data'];

        $jsonDataEncoded = json_encode($jsonData);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

        //Agregamos los encabezados del contenido
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $result = curl_exec($ch);
        print "a: ".$result;
        break;
}