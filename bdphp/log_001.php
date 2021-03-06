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
	

	// echo 'p_from | antes = '.$x_from.' | ----';

	
	// lo vamos a hacer con sockets
	// if ( strrpos($x_from, "re-resta") !== false ) { $x_from = str_replace('re-resta','',$x_from); reservarStockItemsPedido(); return;} // reserva stock desde mipedio
	if ( strrpos($x_from, "re-suma") !== false ) { $x_from = str_replace('re-suma','',$x_from); restauraStockItemsPedido(); return;} // reserva stock desde mipedio

	if ( strrpos($x_from, "a") !== false ) { $x_from = str_replace('a','',$x_from); cocinar_pedido(); }
	if ( strrpos($x_from, "d") !== false ) { $x_from = str_replace('d','',$x_from); cocinar_registro_cliente(); }	
	
	if ( strrpos($x_from, "b") !== false ) { $x_from = str_replace('b','',$x_from); cocinar_pago_total(); }
	if ( strrpos($x_from, "c") !== false ) { $x_from = str_replace('c','',$x_from); cocinar_pago_parcial(); }
	
	if ( strrpos($x_from, "f") !== false ) { $x_from = str_replace('f','',$x_from); getCorrelativoComprobante(); }
	if ( strrpos($x_from, "e") !== false ) { $x_from = str_replace('e','',$x_from); setComprobantePagoARegistroPago(); }
	// if ( strrpos($x_from, "h") !== false ) { $x_from = str_replace('h','',$x_from); cocinar_registro_cliente_sede(); }

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
		$x_array_pedido_body_homologacion = $_POST['p_body_h']; // 151020 homologacion con papaya express
		$x_array_subtotales = $_POST['p_subtotales'];
		
		$x_array_pedido_header = is_object($x_array_pedido_header) || is_array($x_array_pedido_header) ? $x_array_pedido_header : json_decode($x_array_pedido_header, true);
		$x_array_pedido_body = is_object($x_array_pedido_body) || is_array($x_array_pedido_body) ? $x_array_pedido_body : json_decode($x_array_pedido_body, true);
		$x_array_subtotales = is_object($x_array_subtotales) || is_array($x_array_subtotales) ? $x_array_subtotales : json_decode($x_array_subtotales, true);

		// echo 'cocinar_pedido | idcategoria = '. $x_array_subtotales.' | ----';
		// print_r($x_array_pedido_header);
		
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

		 $is_delivery = 0; // 1 es delivery

		 		 
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
				$cantidad=$subitem['cantidad'];

				if ( $cantidad == 0 ) { // si cantidad es igual 0 entonces no guarda lo quita
					$indexRemove = $subitem['iditem'];
					// echo $indexRemove; // error loading // se queda cargando porque no espera este resultado
					unset($x_array_pedido_body[$indexRemove]);
					continue;
				}


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
				$idItem2 = isset($subitem['iditem2']) ? $subitem['iditem2'] : $subitem['iditem'];
				$pwa = isset($subitem['pwa']) ? $subitem['pwa'] : 0;				
				$subItemSelect = json_encode($subitem['subitems_view'], true);
				// $subItemSelect = null;
				
				//  -- 29/07/2020 SI TIENE SUBITEMS ENTONCES EN EL DETALLE DESGLOSA
				// -- ESTO PARA CONTROL DE PEDIDOS

				$lisSubItemsSelect = isset($subitem['subitems_view']) ? $subitem['subitems_view'] : null;
				$isExistSubitemsSelect = isset($lisSubItemsSelect) ? true : false; 
				if ( $isExistSubitemsSelect == true ) {
					$isExistSubitemsSelect  = isset($lisSubItemsSelect[0]) ? true : false;										
				}
				
				// if ( $lisSubItemsSelect == null ) {
				// if ( !isset($lisSubItemsSelect[0]) ) {
				if ( $isExistSubitemsSelect == false ) {
					$desItemInsert = addslashes($subitem['des'].$indicaciones_p);

					// print $subItemSelect;					
					$sql_pedido_detalle=$sql_pedido_detalle."(?,".$tipo_consumo.",".$categoria.",".$subitem['iditem'].",".$idItem2.",'".$subitem['idseccion']."','".$subitem['cantidad']."','".$subitem['cantidad']."','".$subitem['precio']."','".$precio_total."','".$precio_total."','".$desItemInsert."',".$viene_de_bodega.",".$tabla_procede.",".$pwa.",'".$subItemSelect."'),";

				} else {					
					$pUnitarioItem = $subitem['precio'];
					$DesItemUp = $subitem['des'];

					// cuando la cantidad del item es mas que los subitems seleccionados					
					$lenghSubItem = count($lisSubItemsSelect);
					$cantItemSeleccionda = $subitem['cantidad_seleccionada'];
					$PrecioTotalItemSeleccionda = $subitem['precio_total'];

					// $cantItemSeleccionda = $subitem['cantidad'] - $lenghSubItem;

					$subItemSelect = addslashes(json_encode($subitem['subitems_view']));				
					// $subItemSelect = json_encode($subItemSelect);					
					

					foreach ($lisSubItemsSelect as $sub) {						

						$pUnitario = $sub['precio'];
						$desItem = addslashes($DesItemUp.' ('.$sub['des'].')');						

						$cantSeleccionadaSubItem = $sub['cantidad_seleccionada'];

						if ( $pUnitario == 0 ) {
							$pUnitario = $pUnitarioItem;	
							$pTotal = $pUnitario * $cantSeleccionadaSubItem;
						}
						else { 
							$pUnitario = $pUnitario / $cantSeleccionadaSubItem + $pUnitarioItem;
							$pTotal = $pUnitario * $cantSeleccionadaSubItem;
						}

						$cantItemSeleccionda = $cantItemSeleccionda - $cantSeleccionadaSubItem;

						$subitem['des'] = $desItem;
						$subitem['cantidad'] = $cantSeleccionadaSubItem;
						$subitem['precio'] = number_format($pUnitario, 2);
						$precio_total = number_format($pTotal, 2);
						

						// print $subItemSelect;												
						$sql_pedido_detalle=$sql_pedido_detalle."(?,".$tipo_consumo.",".$categoria.",".$subitem['iditem'].",".$idItem2.",'".$subitem['idseccion']."','".$subitem['cantidad']."','".$subitem['cantidad']."','".$subitem['precio']."','".$precio_total."','".$precio_total."','".$subitem['des']."',".$viene_de_bodega.",".$tabla_procede.",".$pwa.",'".$subItemSelect."'),";


						$PrecioTotalItemSeleccionda = $PrecioTotalItemSeleccionda - $precio_total;

					}

					// si los subitems son menores a la cantidad total seleccionada entonces guarda la diferencia
					if ($cantItemSeleccionda > 0) {
						$PrecioTotalItemSeleccionda = number_format($PrecioTotalItemSeleccionda, 2);						
						$desItemInsert = addslashes($DesItemUp.$indicaciones_p);
						$sql_pedido_detalle=$sql_pedido_detalle."(?,".$tipo_consumo.",".$categoria.",".$subitem['iditem'].",".$idItem2.",'".$subitem['idseccion']."','".$cantItemSeleccionda."','".$cantItemSeleccionda."','".$subitem['precio']."','".$PrecioTotalItemSeleccionda."','".$PrecioTotalItemSeleccionda."','".$desItemInsert."',".$viene_de_bodega.",".$tabla_procede.",".$pwa.",'".$subItemSelect."'),";
					}
				}

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
			$tachado =  isset($sub_total['tachado']) ? $sub_total['tachado'] === "true" ? 1 : 0 : 0;   
			$importe_row = $tachado === 1 ? $sub_total['importe_tachado'] : $sub_total['importe'];
			// $importe_total = $sub_total['importe'];
			$importe_total = number_format((float)$sub_total['importe'], 2, '.', '');
			$importe_row = number_format((float)$importe_row, 2, '.', '');
			$sql_subtotales = $sql_subtotales."(?,".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$sub_total['descripcion']."','".$importe_row."',".$tachado."),";
		}

        //guarda primero pedido para obtener el idpedio
		if(!isset($_POST['estado_p'])){$estado_p=0;}else{$estado_p=$_POST['estado_p'];}//para el caso de venta rapida si ya pago no figura en control de pedidos
		if(!isset($_POST['idpedido'])){$id_pedido=0;}else{$id_pedido=$_POST['idpedido'];}//si se agrea en un pedido / para control de pedidos al agregar		
		
        if($id_pedido==0){ //  nuevo pedido
			//num pedido
			$numpedido=array_key_exists('num_pedido', $x_array_pedido_header) ? $x_array_pedido_header['num_pedido'] : '';
			if ( $numpedido == '' ) { // cuando es nuevo, si ya lo manda des control de pedidos adjunta al numpedido raiz
				$numpedido = 1;
				// $sql="select count(idpedido) as d1 from pedido where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'];
				// $sql="SELECT numpedido FROM pedido WHERE idsede = ".$_SESSION['idsede']." order by idpedido desc limit 1";
				
				// $numpedido=$bd->xDevolverUnDato($sql);
				// $numpedido++;
			}

			//numcorrelativo segun fecha
			// $correlativo_dia=array_key_exists('correlativo_dia', $x_array_pedido_header) ? $x_array_pedido_header['correlativo_dia'] : '';
						
			$sql = "call porcedure_get_correlativo(".$_SESSION['idsede'].")";
			$correlativo_dia=$bd->xDevolverUnDatoSP($sql);

			
			// $correlativo_dia++;
			
			$arrDeliveryPedido = isset( $x_array_pedido_header['arrDatosDelivery'] ) ? $x_array_pedido_header['arrDatosDelivery'] : null;
			// si es delivery y si trae datos adjuntos -- json-> direccion telefono forma pago
			$json_datos_delivery='';
			$is_delivery = 0;
			
			// if ( array_key_exists('arrDatosDelivery', $x_array_pedido_header) ) {
				
			if ( count($arrDeliveryPedido) > 0 ) {
				$arrD = $x_array_pedido_header['arrDatosDelivery'];
				$isComercioAppDeliveryMapa = isset($x_array_pedido_header['isComercioAppDeliveryMapa']) ? $x_array_pedido_header['isComercioAppDeliveryMapa'] : 0;

				//071220 a la referencia le quitamos los caracteres especiales
				$ref_cocinada = addslashes($arrD['referencia']);
				$x_array_pedido_header['arrDatosDelivery']['referencia'] = $ref_cocinada;
				if ( isset($x_array_pedido_header['arrDatosDelivery']['direccionEnvioSelected'])) {
					$x_array_pedido_header['arrDatosDelivery']['direccionEnvioSelected']['referencia'] = $ref_cocinada;
				}
				
				// desde 03/08/2020 -- omologacion con papaya express
				// $x_array_pedido_header['delivery'] = array_key_exists('pasoRecoger', $arrD) ? 1 : 0;
				$is_delivery = array_key_exists('pasoRecoger', $arrD) ? 1 : 0;
				$x_array_pedido_header['delivery'] = $is_delivery;
								
				if ( $is_delivery == 1 && $isComercioAppDeliveryMapa == 1) {									
					$x_array_pedido_body = $x_array_pedido_body_homologacion;					
				}
					
				// $json_datos_delivery = json_encode(array('p_body' => $x_array_pedido_body, 'p_header' => $x_array_pedido_header,'p_subtotales' => $x_array_subtotales)); // , 'p_body' => $x_array_pedido_body, 'p_subtotales' => $x_array_subtotales
				// $is_delivery = 1;

				// solo guarda los encabezados si es solo delivery y no este habilitado en el app express
				$json_body = addslashes($x_array_pedido_body);				
				$json_body = str_replace("\\n", "", $json_body); // eliminar los saltos de pagina
				$json_body = json_decode(stripslashes($json_body), JSON_UNESCAPED_UNICODE);

				$json_datos_delivery = json_encode(array('p_body' => $json_body, 'p_header' => $x_array_pedido_header,'p_subtotales' => $x_array_subtotales), JSON_UNESCAPED_UNICODE); // , 'p_body' => $x_array_pedido_body, 'p_subtotales' => $x_array_subtotales
			}
				// solo para pruebas
				// $json_body = addslashes($x_array_pedido_body);				
				// $json_body = json_decode(stripslashes($json_body), JSON_UNESCAPED_UNICODE);
				// $json_datos_delivery = json_encode(array('p_body' => $json_body, 'p_header' => $x_array_pedido_header,'p_subtotales' => $x_array_subtotales)); // , 'p_body' => $x_array_pedido_body, 'p_subtotales' => $x_array_subtotales
			

            // guarda pedido
            $sql="insert into pedido (idorg, idsede, idcliente, fecha,hora,fecha_hora,nummesa,numpedido,correlativo_dia,referencia,total,total_r,solo_llevar,idtipo_consumo,idcategoria,reserva,idusuario,subtotales_tachados,estado,json_datos_delivery, pwa_is_delivery, is_from_client_pwa)
					values(".$_SESSION['ido'].",".$_SESSION['idsede'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y'),DATE_FORMAT(now(),'%H:%i:%s'),now(),'".$x_array_pedido_header['mesa']."','".$numpedido."','".$correlativo_dia."','".$x_array_pedido_header['referencia']."','".$importe_subtotal."','".$importe_total."',".$solo_llevar.",".$tipo_consumo.",".$x_array_pedido_header['idcategoria'].",".$x_array_pedido_header['reservar'].",".$_SESSION['idusuario'].",'". $x_array_pedido_header['subtotales_tachados'] ."',".$estado_p.",'".$json_datos_delivery."', ".$is_delivery.", ".$is_delivery.")";
			
			// echo $sql;
			$id_pedido=$bd->xConsulta_UltimoId($sql);
			
			// 291220
			// actualizamos el numpedido colocando el ultimo idpedido
			if ( $numpedido == 1 ) {
				$sql = "update pedido set numpedido = $id_pedido where idpedido = $id_pedido";
				$bd->xConsulta_NoReturn($sql);
			}

                
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
		$sql_pedido_detalle='insert into pedido_detalle (idpedido,idtipo_consumo,idcategoria,idcarta_lista,iditem,idseccion,cantidad,cantidad_r,punitario,ptotal,ptotal_r,descripcion,procede,procede_tabla, pwa, subitems) values '.$sql_pedido_detalle;
		// $sql_pedido_detalle = addslashes($sql_pedido_detalle);
		// echo $sql_pedido_detalle;
		
		//pedido_subtotales
		$sql_subtotales='insert into pedido_subtotales (idpedido,idorg,idsede,descripcion,importe, tachado) values '.$sql_subtotales;
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

	function filter_by_value ($array, $index, $value){
		$newarray = [];
        if(is_array($array) && count($array)>0) 
        {
            foreach(array_keys($array) as $key){
                $temp[$key] = $array[$key][$index];
                
                if ($temp[$key] == $value){
                    $newarray[$key] = $array[$key];
                }
            }
          }
      return $newarray;
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

		$x_array_pedido_header = is_object($x_array_pedido_header) || is_array($x_array_pedido_header) ? $x_array_pedido_header : json_decode($x_array_pedido_header, true);
		$x_array_tipo_pago = is_object($x_array_tipo_pago) || is_array($x_array_tipo_pago) ? $x_array_tipo_pago : json_decode($x_array_tipo_pago, true);
		$x_array_subtotales = is_object($x_array_subtotales) || is_array($x_array_subtotales) ? $x_array_subtotales : json_decode($x_array_subtotales, true);
		$x_array_comprobante = is_object($x_array_comprobante) || is_array($x_array_comprobante) ? $x_array_comprobante : json_decode($x_array_comprobante, true);
		
		$id_pedido = $x_idpedido ? $x_idpedido : $x_array_pedido_header['idPedidoSeleccionados'];

		$tipo_consumo = $x_array_pedido_header['tipo_consumo'];
		$idc=$x_array_pedido_header['idclie'] == ''? ($x_idcliente == '' ? 0 : $x_idcliente) : $x_array_pedido_header['idclie'];
		// $tt=$x_array_pedido_header['ImporteTotal'];
		

		// subtotales
		$sql_subtotales = '';
		$importe_total=0; // la primera fila es subtotal o total si no hay adicionales
		$importe_subtotal = $x_array_subtotales[0]['importe'];
		foreach ( $x_array_subtotales as $sub_total ) {
			$tachado = isset($sub_total['tachado']) ? $sub_total['tachado'] === "true" ? 1 : 0 : 0; 
			$importe_row = $tachado === 1 ? $sub_total['importe_tachado'] : $sub_total['importe'];
			$importe_total = number_format((float)$sub_total['importe'], 2, '.', '');
			$importe_row = number_format((float)$importe_row, 2, '.', '');
			$sql_subtotales = $sql_subtotales."(?,".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$sub_total['descripcion']."','".$importe_row."',".$tachado."),";			
		}

		/// buscamos el ultimo correlativo
		/// buscamos el ultimo correlativo
		// $correlativo_comprobante = returnCorrelativo($x_array_comprobante);
		// $correlativo_comprobante = 0; 
		$idtipo_comprobante_serie = $x_array_comprobante['idtipo_comprobante_serie'];
		if ($x_array_comprobante['idtipo_comprobante'] != "0"){ // 0 = ninguno | no imprimir comprobante

			
		// 	$sql_doc_correlativo="select correlativo + 1  from tipo_comprobante_serie where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
		// 	$correlativo_comprobante = $bd->xDevolverUnDato($sql_doc_correlativo);		
			
			$sql_update_value_correlativo = "update tipo_comprobante_serie set facturacion_correlativo_api=1 where idtipo_comprobante_serie=".$idtipo_comprobante_serie;
			$bd->xConsulta_NoReturn($sql_update_value_correlativo);
			
		// 		// $sql_doc_correlativo = "update tipo_comprobante_serie set correlativo = ".$correlativo_comprobante." where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
		// 		// $bd->xConsulta_NoReturn($sql_doc_correlativo);			
		// } else {
		// 	$correlativo_comprobante='0';
		}

		// $sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo, idtipo_comprobante_serie, correlativo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.",".$idtipo_comprobante_serie.",'".$correlativo_comprobante."');";
		$correlativo_comprobante = '';		
		$sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.");";
		$idregistro_pago=$bd->xConsulta_UltimoId($sqlrp);
		

                
		//registro tipo de pago // efectivo / tarjeta / etc
		// si solo tiene un item entonces guarda con la cantidad del total -- para evitar errores
		$cadena_tp='';
		if ( count($x_array_tipo_pago) == 1 ) {
			$importe_detalle_pago = $importe_total;
			$cadena_tp=$cadena_tp."(".$idregistro_pago.",".$x_array_tipo_pago[0]['id'].",'".$importe_detalle_pago."'),";
		} else {
			foreach($x_array_tipo_pago as $item){
				$importe_detalle_pago = $item['importe'] == 'NaN' ? $importe_total : $item['importe'];
				$cadena_tp=$cadena_tp."(".$idregistro_pago.",".$item['id'].",'".$importe_detalle_pago."'),";
			}
		}

        $cadena_tp=substr($cadena_tp,0,-1);
		$cadena_tp="insert into registro_pago_detalle (idregistro_pago,idtipo_pago,importe) values ".$cadena_tp."; ";
		// echo $cadena_tp;
		
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

		// echo $cadena_tp;
		
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

		$x_array_pedido_header = is_object($x_array_pedido_header) || is_array($x_array_pedido_header) ? $x_array_pedido_header : json_decode($x_array_pedido_header, true);
		$x_array_tipo_pago = is_object($x_array_tipo_pago) || is_array($x_array_tipo_pago) ? $x_array_tipo_pago : json_decode($x_array_tipo_pago, true);
		$array_items = is_object($array_items) || is_array($array_items) ? $array_items : json_decode($array_items, true);
		$x_array_subtotales = is_object($x_array_subtotales) || is_array($x_array_subtotales) ? $x_array_subtotales : json_decode($x_array_subtotales, true);
		$x_array_comprobante = is_object($x_array_comprobante) || is_array($x_array_comprobante) ? $x_array_comprobante : json_decode($x_array_comprobante, true);

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
			// $tachado = $sub_total['tachado'] === "true" ? 1 : 0; 
			$tachado = isset($sub_total['tachado']) ? $sub_total['tachado'] === "true" ? 1 : 0 : 0; 
			$importe_row = $tachado === 1 ? $sub_total['importe_tachado'] : $sub_total['importe'];
			$importe_total = $sub_total['importe'];
			$sql_subtotales = $sql_subtotales."(?,".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$sub_total['descripcion']."','".$importe_row."',".$tachado."),";			
		}

		/// buscamos el ultimo correlativo
		// $correlativo_comprobante = returnCorrelativo($x_array_comprobante);
		// $correlativo_comprobante = 0; 
		$idtipo_comprobante_serie = $x_array_comprobante['idtipo_comprobante_serie'];
		if ($x_array_comprobante['idtipo_comprobante'] != "0"){ // 0 = ninguno | no imprimir comprobante

	
		// 	$sql_doc_correlativo="select correlativo + 1  from tipo_comprobante_serie where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
		// 	$correlativo_comprobante = $bd->xDevolverUnDato($sql_doc_correlativo);		

			$sql_update_value_correlativo = "update tipo_comprobante_serie set facturacion_correlativo_api=1 where idtipo_comprobante_serie=".$idtipo_comprobante_serie;
			$bd->xConsulta_NoReturn($sql_update_value_correlativo);
			
		// 	// if ($x_array_comprobante['codsunat'] == "0") { // si no es factura electronica
		// 		// guardamos el correlativo //
		// 		$sql_doc_correlativo = "update tipo_comprobante_serie set correlativo = ".$correlativo_comprobante." where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
		// 		$bd->xConsulta_NoReturn($sql_doc_correlativo);
		// 	// } 
		// 	// si es factura elctronica guarda despues tigger ce 
		// } else {
		// 	$correlativo_comprobante='0';
		}

		// $sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo, idtipo_comprobante_serie, correlativo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.",".$idtipo_comprobante_serie.",'".$correlativo_comprobante."');";
		$correlativo_comprobante='';
		$sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.");";
		$idregistro_pago=$bd->xConsulta_UltimoId($sqlrp);
		// echo $sqlrp;

		// $sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$importe_total."',".$tipo_consumo.");";
        // $idregistro_pago=$bd->xConsulta_UltimoId($sqlrp);

                
        //registro tipo de pago // efectivo / tarjeta / etc
        $cadena_tp='';
        // foreach($x_array_tipo_pago as $item){
		// 	$importe_detalle_pago = $item['importe'] == 'NaN' ? $importe_total : $item['importe'];
        //     $cadena_tp=$cadena_tp."(".$idregistro_pago.",".$item['id'].",'".$importe_detalle_pago."'),";
		// }
		
		//registro tipo de pago // efectivo / tarjeta / etc
		// si solo tiene un item entonces guarda con la cantidad del total -- para evitar errores
		$cadena_tp='';
		if ( count($x_array_tipo_pago) == 1 ) {
			$importe_detalle_pago = $importe_total;
			$cadena_tp=$cadena_tp."(".$idregistro_pago.",".$x_array_tipo_pago[0]['id'].",'".$importe_detalle_pago."'),";
		} else {
			foreach($x_array_tipo_pago as $item){
				$importe_detalle_pago = $item['importe'] == 'NaN' ? $importe_total : $item['importe'];
				$cadena_tp=$cadena_tp."(".$idregistro_pago.",".$item['id'].",'".$importe_detalle_pago."'),";
			}
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
		$datos_cliente = is_object($datos_cliente) || is_array($datos_cliente) ? $datos_cliente : json_decode($datos_cliente, true);

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

				// insertar en cliente_sede
				$sql = "call procedure_registrar_cliente_sede(".$_SESSION['idsede'].",".$idclie.")";				
				$bd->xConsulta_NoReturn($sql);
				
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

	// registra el cliente en la sede
	function cocinar_registro_cliente_sede() {
		$idclie = $_POST['idcliente'];
		$sql = "call procedure_registrar_cliente_sede(".$_SESSION['idsede'].",".$idclie.")";				
		$bd->xConsulta_NoReturn($sql);
		// echo $sql;
	}

	// devuelve el correlativo del comprobante
	function getCorrelativoComprobante() {
		global $bd;
		global $x_correlativo_comprobante;
		/// buscamos el ultimo correlativo
		$x_array_comprobante = $_POST['p_comprobante'];
		$x_array_comprobante = is_object($x_array_comprobante) || is_array($x_array_comprobante) ? $x_array_comprobante : json_decode($x_array_comprobante, true);

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
		$x_array_comprobante = is_object($x_array_comprobante) || is_array($x_array_comprobante) ? $x_array_comprobante : json_decode($x_array_comprobante, true);

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