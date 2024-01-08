<?php

namespace Jne;

// use GuzzleHttp\Client;
// use GuzzleHttp\RequestOptions;

class jne 
{
    private const URL_CONSULT = 'https://aplicaciones007.jne.gob.pe/srop_publico/Consulta/api/AfiliadoApi/GetNombresCiudadano';
    private const REQUEST_TOKEN = 'Dmfiv1Unnsv8I9EoXEzbyQExSD8Q1UY7viyyf_347vRCfO-1xGFvDddaxDAlvm0cZ8XgAKTaWclVFnnsGgoy4aLlBGB5m-E8rGw_ymEcCig1:eq4At-H2zqgXPrPnoiDGFZH0Fdx5a-1UiyVaR4nQlCvYZzAhzmvWxLwkUk6-yORYrBBxEnoG5sm-Hkiyc91so6-nHHxIeLee5p700KE47Cw1';

    
    function __construct()
    {
        $this->cc = new \CURL\cURL();
        $this->cc->setReferer('https://aplicaciones007.jne.gob.pe/');
    }

    function getDataJne( $dni )
		{
			if(strlen(trim($dni))==8)
			{
			               
                $url = self::URL_CONSULT;   
                $ch = curl_init($url);
                $postdata = json_encode(['CODDNI' => $dni]);
                
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                  
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
                    'Content-Type: application/json',                                                                                
                    'Requestverificationtoken: ' . self::REQUEST_TOKEN)                                                                       
                );
                
                $res = curl_exec($ch);
                curl_close($ch);                                           
        
                $result = json_decode($res);

                // return $res;
                
                if (!$result) {
                    return null;
                }

                // echo $result->data;
        
                $cadena = $result->data;
                $arr = explode('|', $cadena);
                $ApePat = $arr[0];
                $ApeMat = $arr[1];
                $Nombre = $arr[2];
                $MsjPadronElec = $arr[3];

                // print_r($arr);
        
                $rtn = array(
                    "DNI" 			=>(string)$dni,
                    "apellidos"     =>(string)$ApePat.' '.$ApeMat,
                    "paterno" 		=>(string)$ApePat,
                    "materno" 		=>(string)$ApeMat,
                    "nombre" 		=>(string)$Nombre,
                    "Nombres" 		=>(string)$Nombre,
                    "sexo" 			=>'',
                    "nacimiento" 	=>'',
                    "gvotacion" 	=>''
                );
                return $rtn;

			}
			return false;
		}

		function check( $dni, $token = '', $fromApi = 0, $inJSON = false )
		{            
			if( strlen($dni) == 8 )
			{

                if ( $fromApi == 1 ) {

                    // $_url = 'https://apiperu.dev/api/dni/'.$number;
        
        
                    // $httpClient = new Client();
        
                    // $response = $httpClient->get(
                    //     $_url,
                    //     [
                    //         RequestOptions::HEADERS => [
                    //             'Accept' => 'application/json',
                    //             'Authorization' => 'Bearer ' . $token,
                    //         ]
                    //     ]
                    // );

                    // $authorization = "Authorization: Bearer ".$token;
                     
                    // $ch = curl_init($_url);                    
                    
                    // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");                    
                    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                      
                    // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));                                     
                    
                    // $res = curl_exec($ch);
                    // curl_close($ch); 


                    $url = "https://apiperu.dev/api/dni/".$dni;
                    $headers = array(
                        "Accept: application/json",
                        "Authorization: Bearer 9032a50cc5152873fe7c0d1485ade12b09b050b01b5cdf3235d370665d9b41ab",
                        );

                    $curl = curl_init($url);
                    // curl_setopt($curl, CURLOPT_URL,$url);
                    curl_setopt($curl, CURLOPT_URL, $url);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                    //for debug only!
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

                    $resp = curl_exec($curl);
                    curl_close($curl);

                    // $result = var_dump($resp);

                    // $result = $resp;

                    $result = json_decode($resp);
        
                    // $result = json_decode($response->getBody()->getContents());
                    if ( $result->success ) {
                        $Nombre = $result->data->nombres;
                        $ApePat = $result->data->apellido_paterno;
                        $ApeMat = $result->data->apellido_materno;
                        $NombreCompleto = $result->data->nombre_completo;
                        $Fnac = isset($result->data->fecha_nacimiento) ? $result->data->fecha_nacimiento : '';

                        $info = array(
                            "DNI" 			=>(string)$number,
                            "apellidos"     =>(string)$ApePat.' '.$ApeMat,
                            "paterno" 		=>(string)$ApePat,
                            "materno" 		=>(string)$ApeMat,
                            "nombre" 		=>(string)$Nombre,
                            "Nombres" 		=>(string)$Nombre,
                            "sexo" 			=>'',
                            "nacimiento" 	=>(string)$Fnac,
                            "gvotacion" 	=>''
                        );
                        

                        $res_api = (object)array(
                            "success" 	=> true,
                            "data" 	=> $info
                        );

                        return $res_api;
                        
                    }  else {
        
                        return [
                            'success' => false,
                            'dni' => $number,
                            'source' => 'apidev',
                            'message' => 'Datos no encontrados.',
                            "data" 	=> $result
                        ];
        
                    } 
        
        
        
                }



				$info = $this->getDataJne( $dni );
				if( $info!=false )
				{
					$rtn = (object)array(
						"success" 	=> true,
						"result" 	=> $info
					);
				}
				else
				{
					$rtn = (object)array(
						"success" 	=> false,
						"msg" 		=> "No se ha encontrado resultados.",
                        "result" 	=> $info
					);
				}
				return ($inJSON==true) ? json_encode($rtn,JSON_PRETTY_PRINT):$rtn;
			}

			$rtn = (object)array(
				"success" 	=> false,
				"msg" 		=> "Nro de DNI no valido."
			);
			return ($inJSON==true) ? json_encode( $rtn, JSON_PRETTY_PRINT ) : $rtn;
		}

    
}