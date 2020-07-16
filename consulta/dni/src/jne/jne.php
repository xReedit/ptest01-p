<?php

namespace Jne;
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
                    "DNI"           =>(string)$dni,
                    "apellidos"     =>(string)$ApePat.' '.$ApeMat,
                    "paterno"       =>(string)$ApePat,
                    "materno"       =>(string)$ApeMat,
                    "nombre"        =>(string)$Nombre,
                    "Nombres"       =>(string)$Nombre,
                    "sexo"          =>'',
                    "nacimiento"    =>'',
                    "gvotacion"     =>''
                );
                return $rtn;

            }
            return false;
        }
        function check( $dni, $inJSON = false )
        {            
            if( strlen($dni) == 8 )
            {
                $info = $this->getDataJne( $dni );
                if( $info!=false )
                {
                    $rtn = (object)array(
                        "success"   => true,
                        "result"    => $info
                    );
                }
                else
                {
                    $rtn = (object)array(
                        "success"   => false,
                        "msg"       => "No se ha encontrado resultados."
                    );
                }
                return ($inJSON==true) ? json_encode($rtn,JSON_PRETTY_PRINT):$rtn;
            }

            $rtn = (object)array(
                "success"   => false,
                "msg"       => "Nro de DNI no valido."
            );
            return ($inJSON==true) ? json_encode( $rtn, JSON_PRETTY_PRINT ) : $rtn;
        }

    
}