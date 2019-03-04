<?php	
	// version de estructura cliente
    $listEstructuras = $_POST['arrEstructura'];
    $pase = true;
    $mensaje='inicia';
    $contador=1;
    foreach ($listEstructuras as $item) {
        $v = $item['v'];
        echo "contador: ".$contador;
        $contador++;
        $nombre_archivo = "logs.txt"; 
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


        // update estructuras
        if ($pase) {
            echo "pase a crear estructura ";
            $nom_file = $item['nom_documento'].".txt";
            $estructura = $item['estructura_json'];

            if(file_exists($nom_file)) {
                echo "modifica estructura ".$nom_file;
                //modificamos escribimos nueva version                
                file_put_contents($nom_file, $estructura);
                
            } else {            
                //creamos nuevo archivo
                echo "crea estructura ".$nom_file;
                if($archivo = fopen($nom_file, "a")) {
                    fwrite($archivo, $estructura);
                    fclose($archivo);
                }

            }
        }
    }


    // descarga logo
    $logo = $_POST['logo'];
    $nomFileLogo = "logo.txt";
    if(file_exists($nomFileLogo)) {
        echo "modifica logo ".$nomFileLogo;
        //modificamos escribimos nueva version                
        file_put_contents($nomFileLogo, $logo);
        
    } else {            
        //creamos nuevo archivo
        echo "crea logo ".$nomFileLogo;
        if($archivo = fopen($nomFileLogo, "a")) {
            fwrite($archivo, $logo);
            fclose($archivo);
        
        }
    }

    echo $mensaje;

?>