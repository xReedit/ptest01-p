<?php
//session_set_cookie_params('4000'); // 1 hour
//session_regenerate_id(true); 
session_start();	
//$NomTabla=$_SESSION['Tabla'];
//$IdUs=$_SESSION['IdUs'];


		//$IdProvincia=$_SESSION['IdProvincia'];
//$IdValTabla=$_SESSION['IdValTabla'];
//$NextPagina=$_SESSION['Pasar_A'];
$IdValTabla="";
$NextPagina="";
$UltimoId="";
$Campo_comparar="";
$val_Campo_comparar="";

//if($NextPagina=="DevolverUltimoIdJavaScript"){
	//$IdValTabla=$_POST('xUltimoIdJavaScript');
	//$NomTabla=$_POST('xTablaJavaScript');
	//}


include "ManejoBD.php";
$bd=new xManejoBD("restobar");

//especifica tabla en script
$xGet_tb=$_GET['tb'];
$NomTabla=$xGet_tb;
//if($xGet_tb!=""){$NomTabla=$xGet_tb;}
//busca el id
foreach($_POST as $nombre_campo => $valor){ if(strtoupper($nombre_campo) == strtoupper("Id".$NomTabla)){$IdValTabla=$valor; continue;} };//obtiene el id del registro (nuevo o modificar)


//obtiene los datos enviados por post
//or strpos($nombre_campo, 'JavaScript')==false
$NomCampos="";$ValorCampo="";$coma="";
foreach($_POST as $nombre_campo => $valor){
	if($nombre_campo == "no_post" or $nombre_campo == "log"){continue;}//no considera //el log es solo por la huella
	if(strtoupper($nombre_campo) == strtoupper("Id".$NomTabla)){continue;} //obtiene el id del registro (nuevo o modificar)
	//si es para comparar en la bd, si ya existe registro parecido como dni ej: @dni - dni se compara
	if(substr($nombre_campo,0,1)=='@'){
		$Campo_comparar=substr($nombre_campo,1);
		$nombre_campo=$Campo_comparar;
		$val_Campo_comparar=$valor;
		}

	if($NomCampos!=""){$coma=", ";}
	if($IdValTabla=="")//nuevo
	{
		$NomCampos = $NomCampos.$coma.$nombre_campo;
		$ValorCampo = $ValorCampo.$coma."'".$valor."'";
	}
	else
	{
		$NomCampos=$NomCampos.$coma.$nombre_campo."='".$valor."'";
	}
}

if($Campo_comparar!="") //caparar
{
	$resultado=$bd->xDevolverUnDato("select ".$Campo_comparar." from ".$NomTabla." where ".$Campo_comparar."=".$val_Campo_comparar);
	if($resultado!="")
	{
			echo '<script>alert("'.$Campo_comparar.': '.$resultado.' YA EXISTE. NO SE REGISTRO. VERIFIQUE LOS DATOS."); history.back();</script>';
			//regresar a la pagina anterior
			//header('Location:' . getenv('HTTP_REFERER'));
			return;
		}
	}


	if($IdValTabla=="")//nuevo
	{
		$xSql="Insert Into ".$NomTabla ."(".$NomCampos.") values (". $ValorCampo .")";
		$bd->xConsulta2($xSql);
		$UltimoId=$bd->xDevolverUnDato("SELECT LAST_INSERT_ID()");
		$_SESSION['UltimoId']=$UltimoId;


		//mod 17/04/14
		print $UltimoId;
	}
	else //Actualiza
	{
		$xSql="Update ".$NomTabla." set ".$NomCampos." where Id".$NomTabla."=".$IdValTabla;
		$bd->xConsulta2($xSql);

		//mod 17/04/14
		print $IdValTabla;
	}


	//$_SESSION['IdValTabla']="";

	//print $xSql;
	//$bd->xCerrarSesion();
	//print $xSql;

	if($NextPagina!="")
	{
		//if($NextPagina=="DevolverUltimoIdJavaScript"){ print $UltimoId; exit();}

		header("Location:".$NextPagina,true);
		exit();

		//header("Location: ".$_SERVER[HTTP_REFERER]);

		}


?>
