<?php
class xManejoBD extends mysqli //extends SQLite3
{
	var $bd;
    function xManejoBD($BaseDatos)
    {
		//$this->bd = new Sqlite3($BaseDatos) or die('no se pudo conectar');
		$this->bd = new mysqli('127.0.0.1', 'adm_restobar', '159159159', $BaseDatos);
		if (mysqli_connect_errno()) {
			printf("Fallo la conexión: %s\n", mysqli_connect_error());
			exit();
		}
		mysqli_set_charset($this->bd,"utf8");//caracteres especiales
		//if ($this->bd->connect_error) {die('Error de Conexión (' . $mysqli->connect_errno . ') '. $mysqli->connect_error);}
	}

	function xConsulta($Consulta){
		$error="";
		$pasa=false;
		$rows = array();
		$pos1 = strpos(strtoupper($Consulta), 'INSERT');
		$pos2 = strpos(strtoupper($Consulta), 'DELETE');
		$pos3 = strpos(strtoupper($Consulta), 'UPDATE');

		//$this->bd->set_charset("utf8");
		$results = $this->bd->query($Consulta);

		if ($results) {
			$this->bd->commit();
			//no insert
			if($pos1===false and $pos2===false and $pos3===false){while($row = $results->fetch_object()){$rows[]=$row;}}else{$rows[]="";}
			$pasa=true;
			}
			else {$error=$this->bd->error; $this->bd->rollBack(); $pasa=false;}

		$js = json_encode(array(
				"success" => $pasa,
				"datos" => $rows,
				"sentencia" => $Consulta,
				"error" => $error,
				"info"=> $this->bd->info
				));

		print $js;
		}


	function xConsulta2($Consulta){
		//$error="";
		$results = $this->bd->query($Consulta);
		if ($results) {$this->bd->commit();	}else{$results='error_'.$this->bd->error; $this->bd->rollBack();}
		return $results;
		}
	
	// retorna resultados no imprime
	function xConsulta3($Consulta){
		$results = $this->bd->query($Consulta);
		
		$rows = [];
		if ($results) {
			$this->bd->commit();
			while($row = $results->fetch_object()){$rows[]=$row;}
		}
		
		return json_encode($rows);
		}

	function xConsultaSucess($Consulta){
		//$error="";
		$results = $this->bd->query($Consulta);
		if ($results) {$this->bd->commit(); return true;}else{return 'error_'.$this->bd->error;}		
		}

	function xConsulta_NoReturn($Consulta){
		//$error="";
		$results = $this->bd->query($Consulta);
		}

	function xConsulta_UltimoId($Consulta){
		//$error="";
		$results = $this->bd->query($Consulta);
		//print $this->bd->insert_id;
		if ($results) {return $this->bd->insert_id;}else{$this->bd->rollBack(); return $results='error_'.$this->bd->error;}
		}

	function xMultiConsulta($Consulta){
		$error="";
		$UltimoId="";
		$pasa=false;
		$results = $this->bd->multi_query($Consulta);


		if ($results) {
			$UltimoId=$this->bd->insert_id;
			$this->bd->commit();
			$pasa=true;
			}
			else {$error=$this->bd->error; $this->bd->rollBack(); $pasa=false;}


		$js = json_encode(array(
				"success" => $pasa,
				"sentencia" => $Consulta,
				"error" => $error,
				"info"=> $this->bd->info,
				"UltimoId"=>$UltimoId
				));

		print $js;
		}

	function xMultiConsultaNoReturn($Consulta){
		$error="";
		$UltimoId="";
		$pasa=false;
		$results = $this->bd->multi_query($Consulta);


		if ($results) {
			$UltimoId=$this->bd->insert_id;
			$this->bd->commit();
			$pasa=true;
			}
			else {$error=$this->bd->error; $this->bd->rollBack(); $pasa=false;}
		}

	function xDevolverUnDato($Consulta){
		$results = $this->bd->query($Consulta);
		while ($fila = $results->fetch_row()) {
			return $fila[0]; // la fila a devolver es d1
		}
		}

		//para graficos // json con nombre ej. .cantidad .nombres .edad
	function xListaDatosJSONGrafico($Consulta){
		$result = $this->bd->query($Consulta);
		//$i=0;
		$rows = array();
		while($row = $result->fetch_object())
		{
		    $rows[]=$row;
			//$i=$i+1;
		}
		print json_encode($rows);
		}

	function xListaDatosJSON($Consulta){
		$result = $this->bd->query($Consulta);
		$i=0;
		$rows = array();
		while($row = $result->fetch_row())
		{
		    $rows[]=$row;
			$i=$i+1;
		}
		//$rows=$rows+$i;
		$Resultado = array();
		$Resultado['count'] = $i;
		$Resultado['row'] = $rows;
		print json_encode($Resultado,JSON_FORCE_OBJECT);
		}

	function xCerrarSesion(){
		$this->bd->close();
		session_id(uniqid());
		//@session_start();
		//@session_destroy();
		session_unset();
		session_destroy();
		setcookie("PHPSESSID", "", 1);
		}

	function xRespuesta($result){
		$js = json_encode ( array (
				"data" => "ejemplo",
				"success" => $result,
				"error" => $stm->errorInfo ()
				));
		}


	function loguear_us($user,$password,&$result){
		$Consulta="SELECT s.idsede, s.nombre AS nom_sede, s.ciudad, u.* FROM usuario as u INNER JOIN sede AS s using(idsede) WHERE u.estado=0 and u.usuario = '".$user."' and u.pass = '".$password."'";
		$count=1;
		$result = $this->bd->query($Consulta);
        $count = 0;
        while($row = $result->fetch_object()){$count=1;$rows[]=$row;}
		if($count==1){$result=json_encode($rows);}else{$result="";}
		return $count;
		}
	/*
	function xListaJTable($Consulta,$SQLConpagina){
		//try{
		//Funciona para plugin jtabla
		if($SQLConpagina!=""){
			//cuenta los datos en la tabla
			$resultado = $this->bd->query($SQLConpagina);
			while ($fila = $resultado->fetch_row()) {
			 $recordCount = $fila[0];
			}
		}
		$result = $this->bd->query($Consulta);

		$rows = array();
		while($row=odbc_fetch_object($result))
		{
			//$row = utf8_encode($row);
			foreach($row as $key => $value) {
			  $convertedArray[$key] = mb_convert_encoding($value, 'HTML-ENTITIES');
	    	}
		    $rows[] = $convertedArray;
		}
		/*while($row = $result->fetch_row())
		{
		    $rows[] = $row;
		}*/

		//resultado jTable
		/*$jTableResult = array();
		$jTableResult['Result'] = "OK";
		if($SQLConpagina!=""){$jTableResult['TotalRecordCount'] = $recordCount;}
		$jTableResult['Records'] = $rows;
		return json_encode($jTableResult);
		//}
		/*catch(Exception $ex)
		{
			//Return error message
			$jTableResult = array();
			$jTableResult['Result'] = "ERROR";
			$jTableResult['Message'] = $ex->getMessage();
			print json_encode($jTableResult);
		}
		*/
		/*}



*/

}


?>
