<?php	

    header('Access-Control-Allow-Origin: *'); 

	// version de estructura cliente
    $listEstructuras = $_POST['arrEstructura'];
    $pase = true;
    $mensaje='inicia';
    $contador=1;

    // version
    $nombre_archivo = "logs.txt"; 
    $v = $listEstructuras[0]['v'];
    if(file_exists($nombre_archivo))
    {            
        // comparamos la version
        $_v = file_get_contents($nombre_archivo);            
        if ($v==$_v) {                
            $pase = false;
            $mensaje="misma version "; 
        } else {
            //modificamos escribimos nueva version                
            $mensaje="modifica version ";
            file_put_contents($nombre_archivo, $v);
        }
    }
    else
    {            
        //creamos nuevo archivo
        $mensaje="crea version ";
        if($archivo = fopen($nombre_archivo, "a")) {
            fwrite($archivo, $v);
            fclose($archivo);
        }
    }


    foreach ($listEstructuras as $item) {
        $v = $item['v'];
        echo "contador: ".$contador;
        $contador++;

        // update estructuras
        if ($pase) {
            // $nom_file = $item['nom_documento'].".txt";
            $nom_file = $item['nom_documento'].".php";
            $estructura = $item['estructura_json'];
            echo "pase a crear estructura ".$nom_file;
            
            if(file_exists($nom_file)) {
                echo "modifica estructura ".$nom_file;
                //modificamos escribimos nueva version                
                file_put_contents($nom_file, $estructura);
                
            } else {            
                //creamos nuevo archivo
                echo "crea estructura ".$nom_file;
                if($archivo = fopen($nom_file, "a".$contador)) {
                    fwrite($archivo, $estructura);
                    fclose($archivo);
                }

            }
        }
    }


    // descarga logo
    $logo = $_POST['logo'];
    // to png
    $data_logo = $logo;
    $data_logo = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data_logo));
    file_put_contents('logo.png', $data_logo);
    echo "modifica logo";

    // $nomFileLogo = "logo.txt";
    // if(file_exists($nomFileLogo)) {
    //     echo "modifica logo ".$nomFileLogo;
    //     //modificamos escribimos nueva version                
    //     file_put_contents($nomFileLogo, $logo);
        
    // } else {            
    //     //creamos nuevo archivo
    //     echo "crea logo ".$nomFileLogo;
    //     if($archivo = fopen($nomFileLogo, "a")) {
    //         fwrite($nomFileLogo, $logo);
    //         fclose($nomFileLogo);
        
    //     }
    // }

    echo $mensaje;

?>