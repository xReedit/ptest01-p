<?php
// header('Content-Type: application/json;charset=utf-8');
$op = $_GET['op'];



switch ($op) {
	case '1': // get token
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api-seguridad.sunat.gob.pe/v1/clientesextranet/10d010a9-84bd-4f58-91f7-fba1e34c39ab/oauth2/token/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials&scope=https%3A%2F%2Fapi.sunat.gob.pe%2Fv1%2Fcontribuyente%2Fcontribuyentes&client_id=10d010a9-84bd-4f58-91f7-fba1e34c39ab&client_secret=EK4SWJ%2BAtfhDqyMxkOlCKg%3D%3D',
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
        $ruc_receptor = '20600161050'; //ZAMATEX // $_header['ruc_receptor'];
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