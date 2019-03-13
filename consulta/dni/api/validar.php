<?php
   namespace DatosPeru;
	class Validar
	{

        function __construct()
		{
			$this->token = new \Reniec\Token();
        }
        
        function validar($token){
            if ( !$this->token->Check($token) ) { 
                $rpt = (object)array(
                    "success" 		=> false,
                    "source" 		=> "Autorizacion",
                    "msg" 		    => "No autorizado",
                    "error" 		=> "01"
                );
            } else {
                $rpt = (object)array(
                    "success" 		=> true,
                    "msg" 		    => "Ok",
                );
            }
    
            return $rpt;
        }
    }

    require_once("../src/autoload.php");
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: text/plain');

    $token = $_GET['token'];
    $response = new \DatosPeru\Validar();
    echo json_encode( $response->validar( $token ) );
?>