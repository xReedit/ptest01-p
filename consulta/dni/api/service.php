<?php
    
	namespace DatosPeru;
	class Peru
	{
		function __construct()
		{
			$this->jne = new \Jne\jne();
			$this->reniec = new \Reniec\Reniec(); 
			$this->essalud = new \EsSalud\EsSalud();
			$this->mintra = new \MinTra\mintra();			
			$this->token = new \Reniec\Token();
		}

		function search( $dni, $token )
		{
			if ( !$this->token->Check($token) ) { 
				$rpt = (object)array(
					"success" 		=> false,
					"source" 		=> "Autorizacion",
					"msg" 		    => "Usuario no autorizado",
					"error" 		=> "01"
				);
				return $rpt;
			}

						

			// echo "service a jne";
			$response = $this->jne->check( $dni );
			if($response->success == true)
			{
				$rpt = (object)array(
					"success" 		=> true,
					"source" 		=> "jne",
					"haydatos"    => $response->result->nombre == '' ? false : true,
					"result" 		=> $response->result
				);
				if ($rpt->haydatos) {
				 return $rpt;   
				} 
			}

			// token uno
			$token_a = '9032a50cc5152873fe7c0d1485ade12b09b050b01b5cdf3235d370665d9b41ab';
			$response = $this->jne->check( $dni, $token_a, true );
			if($response->success == true)
			{
				$rpt = (object)array(
					"success" 		=> true,
					"source" 		=> "apidev",
					"haydatos"    => true,
					"result" 		=> $response->data
				);

				return $rpt;
				
			} 
			
			$response = $this->reniec->search( $dni );
			if($response->success == true)
			{
				$rpt = (object)array(
					"success" 		=> true,
					"source" 		=> "reniec",
					"haydatos"    => $response->result->Nombres === "" ? false : true,
					"result" 		=> $response->result
				);
				if ($rpt->haydatos) {
				 return $rpt;   
				}
			}

			$response = $this->essalud->check( $dni );
			if($response->success == true)
			{
				$rpt = (object)array(
					"success" 		=> true,
					"haydatos"    => $response->result->Nombres === "" ? false : true,
					"source" 		=> "essalud",
					"result" 		=> $response->result
				);
				if ($rpt->haydatos) {
				 return $rpt;   
				}
			}
						
			$response = $this->mintra->check( $dni );
			if( $response->success == true )
			{
				$rpt = (object)array(
					"success" 		=> true,
					"haydatos"    => $response->result->Nombres === "" ? false : true,
					"source" 		=> "mintra",
					"result" 		=> $response->result
				);
				if ($rpt->haydatos) {
				 return $rpt;   
				}
			}
			
			$rpt = (object)array(
				"success" 		=> true,
				"haydatos"    => false,
				"result" 		=> [],
				"msg" 			=> "No se encontraron datos"
			);
			return $rpt;
		}
	}
	

	require_once("../src/autoload.php");
	$token = $_GET['token'];
	$dni = $_GET['ndni'];
	
	$response = new \DatosPeru\Peru();
	
	header('Access-Control-Allow-Origin: *');
	header('Content-Type: text/plain');
	
	$dni = ( isset($dni))? $dni : false;	
	echo json_encode( $response->search( $dni, $token ) );


?>
