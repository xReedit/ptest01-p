<?php

	
	//log registrar peidod y pago
	// session_set_cookie_params('4000'); // 1 hour
	// session_regenerate_id(true); 
    session_start();
	//header("Cache-Control: no-cache,no-store");
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');
	
	$x_from = $_POST['p_from']; // a = registro pedido | d=registro cliente | b=registro pago total | c=registro pago parcial
	$x_idpedido;
	$x_correlativo_comprobante;
	$x_idcliente = '';
	

	
	// lo vamos a hacer con sockets
	// if ( strrpos($x_from, "re-resta") !== false ) { $x_from = str_replace('re-resta','',$x_from); reservarStockItemsPedido(); return;} // reserva stock desde mipedio
	if ( strrpos($x_from, "re-suma") !== false ) { $x_from = str_replace('re-suma','',$x_from); restauraStockItemsPedido(); return;} // reserva stock desde mipedio

	if ( strrpos($x_from, "a") !== false ) { $x_from = str_replace('a','',$x_from); cocinar_pedido(); }
	if ( strrpos($x_from, "d") !== false ) { $x_from = str_replace('d','',$x_from); cocinar_registro_cliente(); }
	
	if ( strrpos($x_from, "b") !== false ) { $x_from = str_replace('b','',$x_from); cocinar_pago_total(); }
	if ( strrpos($x_from, "c") !== false ) { $x_from = str_replace('c','',$x_from); cocinar_pago_parcial(); }
	
	if ( strrpos($x_from, "f") !== false ) { $x_from = str_replace('f','',$x_from); getCorrelativoComprobante(); }
	if ( strrpos($x_from, "e") !== false ) { $x_from = str_replace('e','',$x_from); setComprobantePagoARegistroPago(); }

	if ( strrpos($x_from, "z") !== false ) { $x_from = str_replace('z','',$x_from); getFechaServer(); }
	if ( strrpos($x_from, "y") !== false ) { $x_from = str_replace('y','',$x_from); setidExternalComprobanteElectronico(); }
	if ( strrpos($x_from, "x") !== false ) { $x_from = str_replace('x','',$x_from); saveComprobanteElectronicoError(); }	
	
	// REGISTRA PEDIDO (a)
	//registra pedidos, pedidodetalle, actualiza stock si es necesario //
	function cocinar_pedido() {
		global $bd;
		global $x_from;
		global $x_idpedido;
		global $x_idcliente;
		
		$x_array_pedido_header = $_POST['p_header'];
    	$x_array_pedido_body = $_POST['p_body'];
		$x_array_subtotales = $_POST['p_subtotales'];
		
		$idc=$x_array_pedido_header['idclie'] == '' ? ($x_idcliente == '' ? 0 : $x_idcliente) : $x_array_pedido_header['idclie'];
		// $idc = cocinar_registro_cliente();
		// $x_array_pedido_footer = $_POST['p_footer'];
		// $x_array_tipo_pago = $_POST['p_tipo_pago'];

		 //sacar de arraypedido || tipo de consumo || local || llevar ... solo llevar
		 $count_arr=0;
		 $count_items=0;
		 $item_antes_solo_llevar=0;
		 $solo_llevar=0;
		 $tipo_consumo;
		 $categoria;
		 
		 $sql_pedido_detalle='';
		 $sql_sub_total='';

		 $numpedido='';
		 $correlativo_dia='';
		 $viene_de_bodega=0;// para pedido_detalle
		 $id_pedido;

		 		 
		 // cocina datos para pedidodetalle
		 foreach ($x_array_pedido_body as $i_pedido) {
			if($i_pedido==null){continue;}
			// tipo de consumo
			//solo llevar
			$pos = strrpos(strtoupper($i_pedido['des']), "LLEVAR");
			
			
			//subitems // detalles
			foreach ($i_pedido as $subitem) {
				if(is_array($subitem)==false){continue;}
				$count_items++;
				if($pos!=false){$solo_llevar=1;$item_antes_solo_llevar=$count_items;}
				$tipo_consumo=$subitem['idtipo_consumo'];
				$categoria=$subitem['idcategoria'];

				$tabla_procede=$subitem['procede']; // tabla de donde se descuenta

				$viene_de_bodega=0;
				if($tabla_procede===0){$viene_de_bodega=$subitem['procede_index'];}

				//armar sql pedido_detalle con arrPedido
				$precio_total=$subitem['precio_print'];
				if($precio_total==""){$precio_total=$subitem['precio_total'];}

				//concatena descripcion con indicaciones
				$indicaciones_p='';
				$indicaciones_p= array_key_exists('indicaciones', $subitem) ? $subitem['indicaciones'] : '';
				if($indicaciones_p!==''){$indicaciones_p=" (".$indicaciones_p.")";$indicaciones_p=strtolower($indicaciones_p);}
				
				//
				$idItem2 = $subitem['iditem2'] ? $subitem['iditem2'] : $subitem['iditem'];
				$sql_pedido_detalle=$sql_pedido_detalle.'(?,'.$tipo_consumo.','.$categoria.','.$subitem['iditem'].','.$idItem2.',"'.$subitem['idseccion'].'","'.$subitem['cantidad'].'","'.$subitem['cantidad'].'","'.$subitem['precio'].'","'.$precio_total.'","'.$precio_total.'","'.$subitem['des'].$indicaciones_p.'",'.$viene_de_bodega.','.$tabla_procede.'),';                                        

			}

			$count_arr++;
		}		
		
		if($count_items==0){return false;}//si esta vacio

		if($item_antes_solo_llevar>1){$solo_llevar=0;} // >1 NO solo es para llevar

		//armar sql pedido_subtotales con arrTotales		
		// $importe_total=0; // la primera fila es subtotal o total si no hay adicionales
		// $importe_subtotal = $x_array_subtotales[0]['importe'];
		// for ($z=0; $z < count($x_array_subtotales); $z++) {	
		// 	$importe_total = $x_array_subtotales[$z]['importe'];		
		// 	$sql_sub_total=$sql_sub_total.'(?,"'.$x_array_subtotales[$z]['descripcion'].'","'.$x_array_subtotales[$z]['importe'].'"),';
		// }
		
		// subtotales		
		$sql_subtotales = '';
		$importe_total=0; // la primera fila es subtotal o total si no hay adicionales
		$importe_subtotal = $x_array_subtotales[0]['importe'];
		foreach ( $x_array_subtotales as $sub_total ) {
			$tachado = $sub_total['tachado'] === "true" ? 1 : 0;  
			$importe_row = $tachado === 1 ? $sub_total['importe_tachado'] : $sub_total['importe'];
			$importe_total = $sub_total['importe'];
			$sql_subtotales = $sql_subtotales."(?,".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$sub_total['descripcion']."','".$importe_row."',".$tachado."),";
		}

        //guarda primero pedido para obtener el idpedio
		if(!isset($_POST['estado_p'])){$estado_p=0;}else{$estado_p=$_POST['estado_p'];}//para el caso de venta rapida si ya pago no figura en control de pedidos
		if(!isset($_POST['idpedido'])){$id_pedido=0;}else{$id_pedido=$_POST['idpedido'];}//si se agrea en un pedido / para control de pedidos al agregar		

        if($id_pedido==0){ //  nuevo pedido
			//num pedido
			$numpedido=array_key_exists('num_pedido', $x_array_pedido_header) ? $x_array_pedido_header['num_pedido'] : '';
			if ( $numpedido == '' ) { // cuando es nuevo, si ya lo manda des control de pedidos adjunta al numpedido raiz
				$sql="select count(idpedido) as d1 from pedido where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'];
				$numpedido=$bd->xDevolverUnDato($sql);
				$numpedido++;
			}

			//numcorrelativo segun fecha
			// $correlativo_dia=array_key_exists('correlativo_dia', $x_array_pedido_header) ? $x_array_pedido_header['correlativo_dia'] : '';
						
			$sql="SELECT count(fecha) AS d1 FROM pedido WHERE (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and STR_TO_DATE(fecha,'%d/%m/%Y')=curdate()";
			$correlativo_dia=$bd->xDevolverUnDato($sql);
			$correlativo_dia++;
			

			// si es delivery y si trae datos adjuntos -- json-> direccion telefono forma pago
			$json_datos_delivery=array_key_exists('arrDatosDelivery', $x_array_pedido_header) ? json_encode($x_array_pedido_header['arrDatosDelivery']) : '';
			

            // guarda pedido
            $sql="insert into pedido (idorg, idsede, idcliente, fecha,hora,fecha_hora,nummesa,numpedido,correlativo_dia,referencia,total,total_r,solo_llevar,idtipo_consumo,idcategoria,reserva,idusuario,subtotales_tachados,estado,json_datos_delivery)
					values(".$_SESSION['ido'].",".$_SESSION['idsede'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y'),DATE_FORMAT(now(),'%H:%i:%s'),now(),'".$x_array_pedido_header['mesa']."','".$numpedido."','".$correlativo_dia."','".$x_array_pedido_header['referencia']."','".$importe_subtotal."','".$importe_total."',".$solo_llevar.",".$tipo_consumo.",".$x_array_pedido_header['idcategoria'].",".$x_array_pedido_header['reservar'].",".$_SESSION['idusuario'].",'". $x_array_pedido_header['subtotales_tachados'] ."',".$estado_p.",'".$json_datos_delivery."')";
			
			// echo $sql;
            $id_pedido=$bd->xConsulta_UltimoId($sql);
                
		}else{
			//actualiza monto
			$sql="update pedido set total=FORMAT(total+".$xarr['ImporteTotal'].",2), subtotales_tachados = '".$x_array_pedido_header['subtotales_tachados']."' where idpedido=".$id_pedido;
			$bd->xConsulta_NoReturn($sql);
        }

        //armar sql completos
		//remplazar ? por idpedido
		$sql_subtotales = str_replace("?", $id_pedido, $sql_subtotales);
		$sql_pedido_detalle = str_replace("?", $id_pedido, $sql_pedido_detalle);

		//saca el ultimo caracter ','
		$sql_subtotales=substr ($sql_subtotales, 0, -1);
		$sql_pedido_detalle=substr ($sql_pedido_detalle, 0, -1);

		//pedido_detalle
		$sql_pedido_detalle='insert into pedido_detalle (idpedido,idtipo_consumo,idcategoria,idcarta_lista,iditem,idseccion,cantidad,cantidad_r,punitario,ptotal,ptotal_r,descripcion,procede,procede_tabla) values '.$sql_pedido_detalle;
		//pedido_subtotales
		$sql_subtotales='insert into pedido_subtotales (idpedido,idorg,idsede,descripcion,importe, tachado) values '.$sql_subtotales;
		// echo $sql_pedido_detalle;
		//ejecutar
        //$sql_ejecuta=$sql_pedido_detalle.'; '.$sql_sub_total.';'; // guarda pedido detalle y pedido subtotal
        $bd->xConsulta_NoReturn($sql_pedido_detalle.';');
		$bd->xConsulta_NoReturn($sql_subtotales.';');
		
		// $x_array_pedido_header['id_pedido'] = $id_pedido; // si viene sin id pedido
		$x_idpedido = $id_pedido;

		// SI ES PAGO TOTAL
		if ( strrpos($x_from, "b") !== false ) { $x_from = str_replace('b','',$x_from); cocinar_pago_total(); }

		
		// $x_respuesta->idpedido = $id_pedido; 
		// $x_respuesta->numpedido = $numpedido; 
		// $x_respuesta->correlativo_dia = $correlativo_dia; 
		
		$x_respuesta = json_encode(array('idpedido' => $id_pedido, 'numpedido' => $numpedido, 'correlativo_dia' => $correlativo_dia));
		print $x_respuesta.'|';
		// $x_respuesta = ['idpedido' => $idpedido];
		// print $id_pedido.'|'.$numpedido.'|'.$correlativo_dia;
		
	}


	
	// REGISTRA PAGO (b)
	// PAGO TOTAL
	// REGISTRO DE PAGO, REGISTRO_DETALLE , REGISTRO_PAGO_PEDIDO
	// registro, detalle, tipo de pago total
	function cocinar_pago_total() {
		global $bd;
		global $x_idpedido;
		global $x_idcliente;
		
		
		$x_array_pedido_header = $_POST['p_header'];
		$x_array_tipo_pago = $_POST['p_tipo_pago'];
		$x_array_subtotales=$_POST['p_subtotales'];
		$x_array_comprobante=$_POST['p_comprobante'];
		
		$id_pedido = $x_idpedido ? $x_idpedido : $x_array_pedido_header['idPedidoSeleccionados'];

		$tipo_consumo = $x_array_pedido_header['tipo_consumo'];
		$idc=$x_array_pedido_header['idclie'] == ''? ($x_idcliente == '' ? 0 : $x_idcliente) : $x_array_pedido_header['idclie'];
		// $tt=$x_array_pedido_header['ImporteTotal'];
		

		// subtotales
		$sql_subtotales = '';
		$importe_total=0; // la primera fila es subtotal o total si no hay adicionales
		$importe_subtotal = $x_array_subtotales[0]['importe'];
		foreach ( $x_array_subtotales as $sub_total ) {
			$tachado = $sub_total['tachado'] === "true" ? 1 : 0; 
			$importe_row = $tachado === 1 ? $sub_total['importe_tachado'] : $sub_total['importe'];
			$importe_total = $sub_total['importe'];
			$sql_subtotales = $sql_subtotales."(?,".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$sub_total['descripcion']."','".$importe_row."',".$tachado."),";			
		}

		/// buscamos el ultimo correlativo
		/// buscamos el ultimo correlativo
		// $correlativo_comprobante = returnCorrelativo($x_array_comprobante);
		// $correlativo_comprobante = 0; 
		// $idtipo_comprobante_serie = $x_array_comprobante['idtipo_comprobante_serie'];
		// if ($x_array_comprobante['idtipo_comprobante'] != "0"){ // 0 = ninguno | no imprimir comprobante

			
		// 	$sql_doc_correlativo="select correlativo + 1  from tipo_comprobante_serie where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
		// 	$correlativo_comprobante = $bd->xDevolverUnDato($sql_doc_correlativo);		
			
		// 	$sql_update_value_correlativo = "update tipo_comprobante_serie set facturacion_correlativo_api=1 where idtipo_comprobante_serie=".$idtipo_comprobante_serie;
		// 	$bd->xConsulta_NoReturn($sql_update_value_correlativo);
			
		// 		// $sql_doc_correlativo = "update tipo_comprobante_serie set correlativo = ".$correlativo_comprobante." where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
		// 		// $bd->xConsulta_NoReturn($sql_doc_correlativo);			
		// } else {
		// 	$correlativo_comprobante='0';
		// }

		// $sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo, idtipo_comprobante_serie, correlativo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.",".$idtipo_comprobante_serie.",'".$correlativo_comprobante."');";
		$correlativo_comprobante = '';		
		$sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.");";
		$idregistro_pago=$bd->xConsulta_UltimoId($sqlrp);
		

                
        //registro tipo de pago // efectivo / tarjeta / etc
        $cadena_tp='';
        foreach($x_array_tipo_pago as $item){
            $cadena_tp=$cadena_tp."(".$idregistro_pago.",".$item['id'].",'".$item['importe']."'),";
        }

        $cadena_tp=substr($cadena_tp,0,-1);
		$cadena_tp="insert into registro_pago_detalle (idregistro_pago,idtipo_pago,importe) values ".$cadena_tp."; ";
		
        // registro pago pedido - detalle
		$sql_idpd="select idpedido,idpedido_detalle, cantidad,ptotal from pedido_detalle where idpedido in (".$id_pedido.") and (estado=0 and pagado=0)";
		$rows_pedido_detalle=$bd->xConsulta2($sql_idpd);
		$sql_pago_pedido='';
		//echo $sql_idpd;
		foreach($rows_pedido_detalle as $fila){ // sacamos el idpedido_detalle y los demas datos
			$sql_pago_pedido=$sql_pago_pedido."(".$idregistro_pago.",".$fila['idpedido'].",".$fila['idpedido_detalle'].",'".$fila['cantidad']."','".$fila['ptotal']."'),";
        }
                
        $sql_pago_pedido=substr($sql_pago_pedido,0,-1);
        $sql_pago_pedido='insert into registro_pago_pedido(idregistro_pago,idpedido,idpedido_detalle,cantidad,total) values '.$sql_pago_pedido.'; ';
		
		// subtotal // primero se obtiene $idregistro_pago
		$sql_subtotales = str_replace("?", $idregistro_pago, $sql_subtotales);
		$sql_subtotales = substr($sql_subtotales,0,-1);
		$sql_subtotales = 'insert into registro_pago_subtotal (idregistro_pago,idorg,idsede,descripcion,importe,tachado) values '.$sql_subtotales.'; '; 
	

		// comprobante de pago | datos
		// $sql_devolver_correlativo = "update tipo_comprobante_serie set correlativo=correlativo+1 where (idrog=".$_SESSION['ido']." and idsede=".$_SESSION['ido'].") and idtipo_comprobante=".$x_array_comprobante['idtipo_comprobante']." and estado=0;";


		// echo $sql_pago_pedido;
        $bd->xConsulta_NoReturn($sql_pago_pedido);
		$bd->xConsulta_NoReturn($cadena_tp);
		$bd->xConsulta_NoReturn($sql_subtotales);
		
		// print $correlativo_comprobante."|";

		// $x_respuesta->b = $correlativo_comprobante;
		// $x_respuesta = ['correlativo_comprobante' => $correlativo_comprobante];
		$x_respuesta = json_encode(array('correlativo_comprobante' => $correlativo_comprobante, 'idregistro_pago' => $idregistro_pago));
		print $x_respuesta.'|';
		//+++++ info+++++++++ el update pedido idregistropago es un triggers en la tabla registro_pago_pedido

	}

	function cocinar_pago_parcial() {
		global $bd;
		global $x_idpedido;
		global $x_idcliente;
		
		$x_array_pedido_header = $_POST['p_header'];
		$x_array_tipo_pago = $_POST['p_tipo_pago'];
		$array_items=$_POST['p_items_seleccionados'];
		$x_array_subtotales=$_POST['p_subtotales'];
		$x_array_comprobante=$_POST['p_comprobante'];

		// $id_pedido = $x_idpedido;
		$id_pedido = $x_idpedido ? $x_idpedido : $x_array_pedido_header['idPedidoSeleccionados']; // de venta rapida el idpedido lo manda elcliente

		$tipo_consumo = $x_array_pedido_header['tipo_consumo'];
		$idc=$x_array_pedido_header['idclie'] == ''? ($x_idcliente == '' ? 0: $x_idcliente) : $x_array_pedido_header['idclie'];
		// $tt=$x_array_pedido_header['ImporteTotal'];
		
		// subtotales
		$sql_subtotales = '';
		$importe_total=0; // la primera fila es subtotal o total si no hay adicionales
		$importe_subtotal = $x_array_subtotales[0]['importe'];
		foreach ( $x_array_subtotales as $sub_total ) {
			$tachado = $sub_total['tachado'] === "true" ? 1 : 0; 
			$importe_row = $tachado === 1 ? $sub_total['importe_tachado'] : $sub_total['importe'];
			$importe_total = $sub_total['importe'];
			$sql_subtotales = $sql_subtotales."(?,".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$sub_total['descripcion']."','".$importe_row."',".$tachado."),";			
		}

		/// buscamos el ultimo correlativo
		// $correlativo_comprobante = returnCorrelativo($x_array_comprobante);
		// $correlativo_comprobante = 0; 
		// $idtipo_comprobante_serie = $x_array_comprobante['idtipo_comprobante_serie'];
		// if ($x_array_comprobante['idtipo_comprobante'] != "0"){ // 0 = ninguno | no imprimir comprobante

	
		// 	$sql_doc_correlativo="select correlativo + 1  from tipo_comprobante_serie where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
		// 	$correlativo_comprobante = $bd->xDevolverUnDato($sql_doc_correlativo);		

		// 	$sql_update_value_correlativo = "update tipo_comprobante_serie set facturacion_correlativo_api=1 where idtipo_comprobante_serie=".$idtipo_comprobante_serie;
		// 	$bd->xConsulta_NoReturn($sql_update_value_correlativo);
			
		// 	// if ($x_array_comprobante['codsunat'] == "0") { // si no es factura electronica
		// 		// guardamos el correlativo //
		// 		$sql_doc_correlativo = "update tipo_comprobante_serie set correlativo = ".$correlativo_comprobante." where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
		// 		$bd->xConsulta_NoReturn($sql_doc_correlativo);
		// 	// } 
		// 	// si es factura elctronica guarda despues tigger ce 
		// } else {
		// 	$correlativo_comprobante='0';
		// }

		// $sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo, idtipo_comprobante_serie, correlativo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.",".$idtipo_comprobante_serie.",'".$correlativo_comprobante."');";
		$correlativo_comprobante='';
		$sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.");";
		$idregistro_pago=$bd->xConsulta_UltimoId($sqlrp);
		// echo $sqlrp;

		// $sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.");";
        // $idregistro_pago=$bd->xConsulta_UltimoId($sqlrp);

                
        //registro tipo de pago // efectivo / tarjeta / etc
        $cadena_tp='';
        foreach($x_array_tipo_pago as $item){
            $cadena_tp=$cadena_tp."(".$idregistro_pago.",".$item['id'].",'".$item['importe']."'),";
        }

        $cadena_tp=substr($cadena_tp,0,-1);
		$cadena_tp="insert into registro_pago_detalle (idregistro_pago,idtipo_pago,importe) values ".$cadena_tp."; ";


		//esta parte se diferencia de pago total
        //obtiene idpedido e idpedidodetalle
		$sql_pago_pedido='';
		foreach ($array_items as $sub_item){
			//$sql_pago_pedido=$sql_pago_pedido."(".$idregistro_pago.",".$sub_item['idpedido'].",".$sub_item['idpedido_detalle'].",'".$sub_item['cantidad']."','".$sub_item['total']."'),";
			$idp_d=$sub_item['idpedido_detalle'];
			$pos = strpos($idp_d, ',');
			if($pos===false){
				$idp_d=explode('|',$idp_d);
				$idp_d=$idp_d[0];
				$sql_pago_pedido=$sql_pago_pedido."(".$idregistro_pago.",".$sub_item['idpedido'].",".$idp_d.",'".$sub_item['cantidad']."','".$sub_item['total']."'),";
			}else{ // vienen varios item agrupados
				$idp_d=explode(",",$idp_d);
				$cantp_d=explode(",",$sub_item['cantidad']);
				$totalp_d=explode(",",$sub_item['total']);
				//foreach($idp_d as $i){
				foreach ($idp_d as $i => $clave) {
					$idp_d_idp=explode('|',$idp_d[$i]);
					$sql_pago_pedido=$sql_pago_pedido."(".$idregistro_pago.",".$idp_d_idp[1].",".$idp_d_idp[0].",'".$cantp_d[$i]."','".$totalp_d[$i]."'),";
					}
			}
		}

		$sql_pago_pedido=substr($sql_pago_pedido,0,-1);
		$sql_pago_pedido='insert into registro_pago_pedido(idregistro_pago,idpedido,idpedido_detalle,cantidad,total) values '.$sql_pago_pedido.'; ';

		
		// // subtotales
		// $sql_subtotales = '';
		// foreach ( $x_array_subtotales as $sub_total ) {
		// 	$sql_subtotales = $sql_subtotales."(".$idregistro_pago.",".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$sub_total['descripcion']."','".$sub_total['importe']."'),";			
		// }

		// subtotal // primero se obtiene $idregistro_pago
		$sql_subtotales = str_replace("?", $idregistro_pago, $sql_subtotales);
		$sql_subtotales = substr($sql_subtotales,0,-1);
		$sql_subtotales = 'insert into registro_pago_subtotal (idregistro_pago,idorg,idsede,descripcion,importe,tachado) values '.$sql_subtotales.'; '; 
	

		// $sql_subtotales = substr($sql_subtotales,0,-1);
		// $sql_subtotales = 'insert into registro_pago_subtotal (idregistro_pago,idorg,idsede,descripcion,importe) values '.$sql_subtotales.'; '; 
        
		
		// resta el importe total de pedidos
		// $sql_pedido_update='update pedido set total=total-'.$tt.' where idpedido in ('++')';
				
		// echo $sql_subtotales;
        $bd->xConsulta_NoReturn($sql_pago_pedido);
		$bd->xConsulta_NoReturn($cadena_tp);
		$bd->xConsulta_NoReturn($sql_subtotales);
		// print $correlativo_comprobante;

		$x_respuesta = json_encode(array('correlativo_comprobante' => $correlativo_comprobante, 'idregistro_pago' => $idregistro_pago));
		print $x_respuesta.'|';
	}

	//REGISTRAR CLIENTE (d)	
	//REGISTRA NUEVO CLIENTE SI ES NECESARIO 
	//registra el idcliente en pedidos
	function cocinar_registro_cliente() {
		global $bd;
		global $x_idcliente;
		global $x_idpedido;

		
		// $x_arr_cliente = $_POST['p_cliente'];
		// $datos_cliente = $x_arr_cliente['cliente'];
		$datos_cliente = $_POST['p_cliente'];

		$nomclie=$datos_cliente['nombres'];
		$idclie=$datos_cliente['idcliente'];
		$num_doc=$datos_cliente['num_doc'];
		$direccion=$datos_cliente['direccion'];
		$f_nac=$datos_cliente['f_nac'];
		$telefono=array_key_exists('telefono', $datos_cliente) ? $datos_cliente['telefono'] : '';
		$update_telefono = $telefono != '' ? ", telefono = '".$telefono."'" : '';
		// $idpedidos=$x_arr_cliente['i'] == '' ? $x_idpedido : $x_arr_cliente['i'];

		if($idclie==''){
			if($nomclie==''){//publico general
				$idclie=0;
			}else{
				$sql="insert into cliente (idorg,nombres,direccion,ruc,f_nac, f_registro,telefono)values(".$_SESSION['ido'].",'".$nomclie."','".$direccion."','".$num_doc."','".$f_nac."',DATE_FORMAT(now(),'%d/%m/%Y'),'".$telefono."')";
				$idclie=$bd->xConsulta_UltimoId($sql);
			}
		} else {
			// update cliente
			$sql="update cliente set nombres='".$nomclie."',ruc='".$num_doc."',direccion='".$direccion."'".$update_telefono." where idcliente = ".$idclie;
			$bd->xConsulta_NoReturn($sql);
		}

		// $bd->xConsulta_NoReturn($sql);
		// $sql="update pedido set idcliente=".$idclie." where idpedido in (".$idpedidos.")";
		
		$x_idcliente = $idclie;
		$x_idpedido = $idpedidos;

		echo $idclie;

		// $rptclie = json_encode(array('idcliente' => $idclie));
		// print $rptclie.'|';

		// 031218 // cambio: ahora se graba primero el cliente se devuelve el idcliete, 

		// $GLOBALS['x_idcliente'] = $idclie;
		// return $x_idcliente;
		// echo $idclie;
	}

	// devuelve el correlativo del comprobante
	function getCorrelativoComprobante() {
		global $bd;
		global $x_correlativo_comprobante;
		/// buscamos el ultimo correlativo
		$x_array_comprobante = $_POST['p_comprobante'];
		$correlativo_comprobante = 0; 
		$idtipo_comprobante_serie = $x_array_comprobante['idtipo_comprobante_serie'];
		if ($x_array_comprobante['idtipo_comprobante'] != "0"){ // 0 = ninguno | no imprimir comprobante

	
			$sql_doc_correlativo="select (correlativo + 1) as d1  from tipo_comprobante_serie where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;		
			$correlativo_comprobante = $bd->xDevolverUnDato($sql_doc_correlativo);

			// if ($x_array_comprobante['codsunat'] === "0") { // si no es factura electronica
				// guardamos el correlativo //
				$sql_doc_correlativo = "update tipo_comprobante_serie set correlativo = ".$correlativo_comprobante." where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
				$bd->xConsulta_NoReturn($sql_doc_correlativo);
			// } 
			// si es factura elctronica guarda despues tigger ce 
		} else {
			$correlativo_comprobante='0';
		}

		// SI TAMBIEN MODIFICA EN REGISTRO PAGO
		$x_correlativo_comprobante = $correlativo_comprobante;
		if ( strrpos($x_from, "e") !== false ) { $x_from = str_replace('e','',$x_from); setComprobantePagoARegistroPago(); }

		// print $correlativo_comprobante;
		$x_respuesta = json_encode(array('correlativo_comprobante' => $correlativo_comprobante));
		print $x_respuesta.'|';
	}

	// viene desde registro de pagos > imprimir comprobante
	function setComprobantePagoARegistroPago() {
		global $bd;		
		global $x_correlativo_comprobante;
		$correlatico_comprobante = $x_correlativo_comprobante;
		$x_array_comprobante = $_POST['p_comprobante'];
		$id_registro_pago = $_POST['idregistro_pago'];

		$idtipo_comprobante_serie = $x_array_comprobante['idtipo_comprobante_serie'];
		
		$sqlrp="update registro_pago set idtipo_comprobante_serie=".$idtipo_comprobante_serie." where idregistro_pago=".$id_registro_pago;
		$idregistro_pago=$bd->xConsulta_NoReturn($sqlrp);
	}


	// devolver fecha del servidor // comprobante electronico
	function getFechaServer() {
		$fecha_actual=date('Y').'-'.date('m').'-'.date('d');
		$hora_actual=date('H').':'.date('i').':'.date('s');

		print $fecha_actual.'|'.$hora_actual;
	}


	// grabar id_external_comprobante electronico
	function setidExternalComprobanteElectronico() {
		global $bd;	
		$idregistro_pago = $_POST['idregistro_pago'];
		$idexternal = $_POST['idexternal'];
		$sql= "update registro_pago set external_id_comprobante = '".$idexternal."' where idregistro_pago=".$idregistro_pago;		
		$bd->xConsulta_NoReturn($sql);

		// echo $sql;
	}

	// guarda los comprobantes electronicos que por algun error no fueron enviados al servicio
	// puede que la conexion con el servicio fallo o no tiene internet
	function saveComprobanteElectronicoError() {
		global $bd;	
		$idregistro_pago = $_POST['idregistro_pago'];
		$jsonxml = $_POST['jsonxml'];

		$sql="insert into registro_pago_cpe_error (idregistro_pago, jsonxml) values (".$idregistro_pago.", ".$jsonxml.")";
		$bd->xConsulta_NoReturn($sql);
		// echo $sql;
	}

	// reserva stock -- mi pedido para no salir volando con stock
	function reservarStockItemsPedido() {		
		$data = $_POST['i'];
		$cantidad = $data['cantidad'];
		if ($data['procede']==0){
			$sql="update producto_stock set stock=stock-".$cantidad." where idproducto_stock=".$data['id'];
		} elseif ($data['procede']==1) {
			$sql="update carta_lista set cantidad=if(cantidad!='ND',cantidad-".$cantidad.",'ND') where idcarta_lista=".$data['id'];
		} else {
			$sql="
			UPDATE porcion AS p
			LEFT JOIN item_ingrediente AS ii using (idporcion)
			SET p.stock=p.stock - (".$cantidad."*(ii.cantidad))
			WHERE ii.iditem=".$data['id'];
		}
		$bd->xConsulta($sql);
	}

	// restaura stock -- mi pedido
	function restauraStockItemsPedido() {
		$data = $_POST['i'];
			$_sql="";
			foreach ($data as $sub_item){
				$cantidad = $sub_item['stock'];
				$id = $sub_item['id'];
				if ($data['procede']==1){
					$sql="update producto_stock set stock=stock-".$cantidad." where idproducto_stock=".$id.";";
				} elseif ($data['procede']==0) {
					$sql="update carta_lista set cantidad=if(cantidad!='ND',cantidad-".$cantidad.",'ND') where idcarta_lista=".$id.";";
				} else {
					$sql="
					UPDATE porcion AS p
					LEFT JOIN item_ingrediente AS ii using (idporcion)
					SET p.stock=p.stock - (".$cantidad."*(ii.cantidad))
					WHERE ii.iditem=".$id.";";
				}

				$_sql = $_sql.$sql;
				
			
			}

			$bd->xMultiConsulta($_sql);
	}


    
?>