<?php
// header('Content-Type: application/json;charset=utf-8');
$op = $_GET['op'];



switch ($op) {
	case '1': // get token
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api-seguridad.sunat.gob.pe/v1/clientesextranet/90a45b17-46dc-4efd-9fa9-74232d2fe0e0/oauth2/token/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials&scope=https%3A%2F%2Fapi.sunat.gob.pe%2Fv1%2Fcontribuyente%2Fcontribuyentes&client_id=90a45b17-46dc-4efd-9fa9-74232d2fe0e0&client_secret=jO7pVUNVxH/zIc8fe6rkyA==',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            // 'Cookie: TS019e7fc2=014dc399cbc566cc1589a4824a1901f3e5a76f739a0e9f4ad52600d066bba7c2cb48b72622c6375634f13e3a584b299aedfc48e505'
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;
        break;

    case '2': // consultar
        $postBody = json_decode(file_get_contents('php://input'));
        // $data = $POST['data'];
        $_header = $postBody->header;
        $ruc_receptor = '20610029923'; // PAPAYA INC //ZAMATEX // $_header['ruc_receptor'];
        $token = $_header->access_token;
        $body = json_encode($postBody->body);

        // echo json_encode($body);

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes/'.$ruc_receptor.'/validarcomprobante',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.$token,
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        echo $response;

        break;
}            