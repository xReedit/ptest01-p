<?php
	// session_set_cookie_params('4000'); // 1 hour
	// session_regenerate_id(true); 
	session_start();	
	//header("Cache-Control: no-cache,no-store");
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	include "token.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');


	//$_SESSION['ido']=1;
	//$_SESSION['idsede']=1;
	//$_SESSION['idusuario']=1;
	//$_SESSION['idterminal']=3;

	$g_ido = $_SESSION['ido'];
	$g_idsede = $_SESSION['idsede'];

	switch($_GET['op'])
	{
		case 102://verificar log
			if(isset($_SESSION['uid']) && $_SESSION['uid']==''){
				//header('Location: ../registro.html');
				print 0;
				}else{print 1;}
			break;
		case 101://borrar tabla un solo criterio id
			$sql="delete from ".$_POST['t']." where id".$_POST['t']."=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 10101://borrar tabla 2 criterio tabla y id ej: en carta borrar de carta_lista la seccion
			$sql="delete from ".$_POST['t']." where ".$_POST['campo']."=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 103://borrado logico
			$sql="update ".$_POST['t']." set estado=1 where id".$_POST['t']."=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 104://borrado logico en anulado
			$sql="update ".$_POST['t']." set anulado=1 where id".$_POST['t']."=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 105://borrado logico en check
			$sql="update ".$_POST['t']." set estado=".$_POST['c']." where id".$_POST['t']."=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 106://borrado logico en check
			$sql="update ".$_POST['t']." set ".$_POST['campo']."=".$_POST['c']." where id".$_POST['t']."=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		//case 104://devuelve el ultimo idgenerado
		//	$bd->xConsulta_UltimoId($_POST['sql']);
		//	break;
		case 100://multiconsulta
			$bd->xMultiConsulta($_POST['xsql']);
			break;
		case -304: // cambiar clave usuario			
			$sql = "SELECT pass as d1 from usuario where idusuario=".$_SESSION['idusuario'];
			$passA = $bd->xDevolverUnDato($sql);
			echo 'passA: '.$passA;
			if ($passA === $_POST['pa']) {
				$sql="update usuario set pass = '".$_POST['pn']."' where idusuario=".$_SESSION['idusuario'];
				$bd->xConsulta_NoReturn($sql);
				print 1; // ok
			} else {
				print 0; // no pasa
			}
			break;
		case -3041: // cambiar clave usuario			
			$sql="update usuario set nuevo=1, pass = '".$_POST['pn']."' where idusuario=".$_SESSION['idusuario'];
			$bd->xConsulta($sql);
			break;
		
		case -303:// load tipo usuarios
			$sql="SELECT * FROM usuario_tipo order by idusuario_tipo";
			$bd->xConsulta($sql);
			break;
		case -302://load usuariosdesde config
			$sql="
				SELECT ut.descripcion, u.idusuario,u.nombres,u.apellidos,u.usuario, '*******' AS pass
				FROM usuario AS u
					INNER JOIN usuario_tipo as ut using(idusuario_tipo)
				WHERE u.idorg=".$_SESSION['ido']." AND (u.idsede=".$_SESSION['idsede']." OR u.idsede=0) and u.estado=0
				";
			$bd->xConsulta($sql);
			break;
		case -301://guardar usuario
			$sql="select max(idorg)+1 from org";
			$xorg=$bd->xDevolverUnDato($sql);
			$sql="insert into usuario(idorg, idsede, usuario, pass,correo, acc) value (".$xorg.",1,'".$_POST['u']."','".$_POST['p']."','".$_POST['c']."','a;b;')";
			$bd->xConsulta($sql);
			break;
		case -3://verificar disponibilidad de usuario
			$sql="select usuario from usuario where usuario='".$_POST['u']."'";
			$xu=$bd->xDevolverUnDato($sql);
			if(strlen($xu)>0){print 1;}else{print 0;}
			break;
		case -2://ejecutar consula ej: insert detalle
			if($_POST['NomCampoPadre']!=""){$bd->xConsulta2('delete from '.$_POST['NomTablaHijo'].' where '.$_POST['NomCampoPadre'].'='.$_POST['idPadre']);}
			$bd->xConsulta($_POST['xsql']);
			break;
		case -108://verificar si tiene acceso a esta pagina //si no encuentra regresa a la pagina anterior
			//verifica si el usuario tiene permiso para acceder a esta pagina
				if(!isset($_SESSION['u_pas_rl'])){
					$sql="SELECT GROUP_CONCAT(url_pas) AS d1 FROM us_home_opciones WHERE estado=0";
					$_SESSION['u_pas_rl']=$bd->xDevolverUnDato($sql);
				}

				$u_per=$_POST['p'];
				$u_per=explode('/', $u_per);
				$u_per=$u_per[1];

				$u_per=explode('?', $u_per);
				$u_per=$u_per[0];

				$pos=strpos($_SESSION['u_pas_rl'],$u_per);
				if ($pos=== false) {
					print 0;
				}else{//si existe en tabla verifica permiso

					$sql="select codigo as d1 from us_home_opciones where url_pas LIKE '%".$u_per."%'";
					$cod_p=$bd->xDevolverUnDato($sql);

					$pos=strpos($_SESSION['acc'],$cod_p);
					if($pos===false){print 1;}else{print 0;}
				}
			break;
		case -107://obtener us_home_opciones //menu
			if(!isset($_SESSION['u_pas_rl'])){
				$sql="SELECT GROUP_CONCAT(url_pas) AS d1 FROM us_home_opciones WHERE estado=0";
				$_SESSION['u_pas_rl']=$bd->xDevolverUnDato($sql);
			}

			$sql="select * from us_home_opciones where estado=0 order by idgrupo";
			$bd->xConsulta($sql);
			break;
		case -1061://guardar impresora local, si la descripcion es la misma que existe actualiza registro
			$margen_iz=$_POST['margen'];
			$font=$_POST['font'];
			$des=$_POST['des'];
			$ip_p=$_POST['ip'];
			$copia_local = $_POST['copia_local'];
			$num_copias = $_POST['num_copias'];
			$papel_size = $_POST['papel_size'];

			$sql="SELECT idimpresora as d1 FROM impresora WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND descripcion='".$_POST['des']."' AND local=1";
			$id_print_bus=$bd->xDevolverUnDato($sql);
			if($id_print_bus!=''){
				$sql="UPDATE impresora SET var_margen_iz=".$margen_iz.",var_size_font=".$font.",ip='".$ip_p."', descripcion='".$des."', copia_local=".$copia_local.", num_copias=".$num_copias.", papel_size=".$papel_size.", estado=0 where idimpresora=".$id_print_bus;
			}else{
				$sql="INSERT INTO impresora (idorg,idsede,ip,descripcion,var_margen_iz,var_size_font,copia_local,num_copias,papel_size,local)VALUES(".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$ip_p."','".$des."',".$margen_iz.",".$font.",".$copia_local.",".$num_copias.",".$papel_size.",'1')";
			}
			//print $sql;
			$bd->xConsulta($sql);
			break;
		case -106://cargar imprimir otros
			$sql="SELECT * FROM conf_print_otros WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") and estado=0";
			$bd->xConsulta($sql);
			break;
		case -105://cargar impresoras
			$sql="SELECT * FROM impresora WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") and estado=0";
			$bd->xConsulta($sql);
			break;
		case -1051: //actualiza el copia_local a todas las impresoras locales
			$copial_local = $_POST['copia_local'];
			$sql="update impresora set copia_local=".$copial_local." where (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") and local=1 and estado=0";
			$bd->xConsulta($sql);
			break;
		case -104://verificar session
			//session_start();
			//session_destroy();
			//echo session_status() == PHP_SESSION_ACTIVE ? 'activa'.$_SESSION['ido']:'desactivada';
			if(!isset($_SESSION['idusuario'])){
				print 1;
			}else{
				print 0;
			}
			break;
		case -103://cerrar session
			$bd->xCerrarSesion();
			session_id(uniqid());
			session_start();
			$_SESSION['uid']==null;
			unset($_SESSION["uid"]); 
	
			session_destroy();
			session_commit();
			setcookie("PHPSESSID", "", 1);
			return true;
			break;
		case -102://verificar usuario
			$sql="select idusuario, acc,per from usuario where (idorg=".$_SESSION['ido'].") and usuario='".$_POST['u']."' and pass='".$_POST['p']."' and estado=0";
			$bd->xConsulta($sql);
			break;
		case -101:// devolver datos de session
			$sql="select '".$_SESSION['ido']."' as ido,  '".$_SESSION['idsede']."' as idse, '".$_SESSION['idusuario']."' as idu, '".$_SESSION['acc']."' as acc, '".$_SESSION['nomU']."' as nomU, '".$_SESSION['cargoU']."' as cargoU, '".$_SESSION['nomUs']."' as nomUs";
			$bd->xConsulta($sql);
			break;
		case -1113://get dataUS
			echo $_SESSION['dataUs'];
			break;
		case -1112://verifica que los datos us no hayan sido modificado
			
			$data_cliente=$_POST['d'];
			$rpt="0";
			$dataUs = !empty($_SESSION['dataUs']) ? $_SESSION['dataUs'] : "-1";

			if(isset($_SESSION['usuario'])){//SI HAY UNA SESSION ABIERTA
				switch ($data_cliente) {//evalua el data del cliente
					case "undefined": //si esta iniciando session
							$rpt="0";
						break;
					case "":
								//si $dataUs -1 no hay datos de session, se corto los datos de session
								//data del cliente vacio, y si los datos de session existen devuelve data de session, si no manda a loguearse
								if($dataUs=="-1"){$rpt="2";}else{$rpt=$dataUs;}
						break;
					default:
								//si $dataUs -1 no hay datos de session, se termino la session // problemas con celulares // se corto la conexion evalua data del cliente
								//la data del cliente esta llena comprueba si es un array valido
								if($dataUs=="-1"){ //si se perdio la conexion
									try{ //evalua data del cliente
										$json_data=base64_decode($data_cliente);
										$obj = (array)json_decode($json_data);
										$_SESSION['ido']=$obj["us"]->ido;
										$_SESSION['idsede']=$obj["us"]->idsede;
										$_SESSION['idusuario']=$obj["us"]->idus;
										$_SESSION['acc']=$obj["us"]->acc;
										$_SESSION['nomU']=$obj["us"]->nomus;
										$_SESSION['nomUs']=$obj["us"]->nombre;
										$_SESSION['cargoU']=$obj["us"]->cargo;
										$_SESSION['nom_sede']=$obj["us"]->nom_sede;
										$_SESSION['rol']=$obj["us"]->rol;
										$_SESSION['ciudad']=$obj["us"]->ciudad;
										$_SESSION['nuevo']=$obj["us"]->nuevo;
										$_SESSION['dataUs']=$data_cliente;
										$rpt="1";
									}
									catch(Exception $e){//si no es valido manda a loguearse
										$rpt="2";
									}
								}
								else{//si existe data session devuelve esta al cliente
									if($dataUs===$data_cliente){ // comprueba que los datos del cliente sean iguales al los datos de session
										$rpt="1";//nada normal sigue
									}else{
										$rpt=$dataUs;
									}
								}
						break;
				}
			}
			else{//si no hay session activa
				$rpt="0"; //mandar a cargar //inicia
			}
			
			echo $rpt;
			break;
		case -1111://pruebas logg //encode // log ini
			$rpt=encode_dataUS();
			echo $rpt;
			break;
		case -1://log
			//if(($_SESSION['uid']==''))
			//{			
				// echo file_get_contents( 'php://input' );
				$reconex = false;
				if (!empty($_u = $_POST['u'])) {
					$_u = $_POST['u'];
					$_p = $_POST['p'];
				} else {
					$reconex = true;					
					$payload = $_POST['sys_data'];
					// $data  =  json_decode ($payload); 
					// echo var_dump($data);
					// echo "ini ". $payload;
					// $data = $data->_sys_id;
					
					// echo $payload;

					$_sys_id = base64_decode($payload);
					// echo "decodificado ".$_sys_id;
					$_sys_id = explode(':', $_sys_id);
					$_u = $_sys_id[1];
					$_p = base64_decode($_sys_id[2]);

					$idOrg = $_sys_id[3];
					$idSede = $_sys_id[4];
				}
					if($bd->loguear_us($_u,$_p,$result) == 1)
					{
						$obj = json_decode($result);

						if ( !$reconex ) { // si no es reconexion chapa los datos del result query
							$idOrg=$obj[0]->idorg;
							$idSede=$obj[0]->idsede;
						}

						$_SESSION['ido']=$idOrg;
						$_SESSION['idsede']=$idSede;
						$_SESSION['idusuario']=$obj[0]->idusuario;
						$_SESSION['acc']=$obj[0]->acc;
						$_SESSION['nomU']=$obj[0]->usuario;
						$_SESSION['nomUs']=$obj[0]->nombres;
						$_SESSION['cargoU']=$obj[0]->cargo;
						$_SESSION['nom_sede']=$obj[0]->nom_sede;
						$_SESSION['rol']=$obj[0]->rol;
						$_SESSION['ciudad']=$obj[0]->ciudad;
						$_SESSION['nuevo']=$obj[0]->nuevo;
						$_SESSION['_sys_PHPSESSID']=base64_encode('XZCfnb-o@:'.$_u.':'.base64_encode($_p).':'.$idOrg.':'.$idSede); // restaurar
						$_SESSION['dataUs']="";

						//session_start();
						print 1;
					}else{
						print 0;
					}
			//};
			break;
		case -1001://cambiar sede
			if (!empty($_u = $_POST['o'])) {$_SESSION['idorg'] = $_POST['o'];}		
			$_SESSION['idsede'] = $_POST['i'];
			break;
		case -1002: // obtener sede y rol antes de cargar componentes
			echo $_SESSION['idsede'].','.$_SESSION['rol'];
			break;
		case 0://ruta1 uid utipo unom unomcompleto udireccion uemail departamento costo_envio(precio envio) importe_minimo(para envio)
			//$_SESSION['uPromoDirigidoA']=$bd->xDevolverUnDato("select tipous as d1 from webpromocion as wp where now() between wp.fini and wp.ffin and wp.estado=0 limit 1");
			if(!isset($_SESSION['uid'])){$_SESSION['uid']='';}
			if(!isset($_SESSION['utipo'])){$_SESSION['utipo']=0;}
			if(!isset($_SESSION['unom'])){$_SESSION['unom']='';}
			if(!isset($_SESSION['unomCompleto'])){$_SESSION['unomCompleto']='';}
			if(!isset($_SESSION['udireccion'])){$_SESSION['udireccion']='';}
			if(!isset($_SESSION['uemail'])){$_SESSION['uemail']='';}
			if(!isset($_SESSION['udepartamento'])){$_SESSION['udepartamento']='';}
			if(!isset($_SESSION['ucostoenvio'])){$_SESSION['ucostoenvio']='';}
			if(!isset($_SESSION['uPromoDirigidoA'])){$_SESSION['uPromoDirigidoA']=$bd->xDevolverUnDato("select tipous as d1 from webpromocion as wp where now() between wp.fini and wp.ffin and wp.estado=0 limit 1");}

			// echo 'http://www.viudanegra.com.pe/tienda/viuda/app/file/|'.$_SESSION['uid'].'|'.$_SESSION['utipo'].'|'.$_SESSION['unom'].'|'.$_SESSION['unomCompleto'].'|'.$_SESSION['udireccion'].'|'.$_SESSION['uemail'].'|'.$_SESSION['udepartamento'].'|'.$_SESSION['ucostoenvio'].'|'.$_SESSION['uPromoDirigidoA'];
			break;


		//elaboracion de carta
		case 1://load categoria
			$sql="SELECT idcategoria, descripcion FROM categoria WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			$bd->xConsulta($sql);
			break;
		case 2://load items
			/*$sql="
			SELECT i.iditem,i.idseccion,c.descripcion AS des_seccion, i.descripcion AS des_item, i.precio
			FROM item AS i
				INNER JOIN seccion AS c using(idseccion)
			WHERE (i.idorg=".$_SESSION['ido']." AND i.idsede=".$_SESSION['idsede'].") AND i.estado=0 ORDER BY i.descripcion
			";			*/
			$sql="SELECT iditem as value,descripcion as label,precio FROM item WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0 ORDER BY descripcion";
			$bd->xConsulta($sql);
			break;
		case 201://load seccion
			$sql="SELECT idseccion as value,descripcion as label FROM seccion WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0 ORDER BY descripcion";
			$bd->xConsulta($sql);
			break;
		case 2011://load seccion orden
			$sql="
				SELECT s.idseccion,s.descripcion,s.sec_orden, s.sec_orden
				FROM seccion AS s
					INNER JOIN carta_lista AS cl using(idseccion)
				WHERE (s.idorg=".$_SESSION['ido']." AND s.idsede=".$_SESSION['idsede'].") AND s.estado=0
				GROUP BY s.idseccion
				ORDER BY s.sec_orden
			";
			$bd->xConsulta($sql);
			break;
		case 2012://guardar seccion orden
			$arr_orden=$_POST['arr_orden'];
			$sql_seccion='';
			foreach ($arr_orden as $sec_orden) {
				$sql_seccion=$sql_seccion.'update seccion set sec_orden='.$sec_orden['val'].' where idseccion='.$sec_orden['id'].'; ';
			}
			//print $sql_seccion;
			$bd->xMultiConsulta($sql_seccion);
			break;
		case 202://load item
			$sql="";
			$bd->xConsulta($sql);
			break;
		case 203://guardar seccion
			$id=$_POST['i'];
			//if($id=='undefined'){$id='';}
			$des_seccion=$_POST['des'];
			if($id==''){
				//comprueba nombre que coicidan en la bd si no selecciono item con id
				$sql="select idseccion as d1 from seccion where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and descripcion='".$des_seccion."'";
				$id=$bd->xDevolverUnDato($sql);
				if($id==''){
					//guarda nuevo
					$sql="insert into seccion (idorg, idsede,descripcion,imprimir) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$des_seccion."',".$_POST['p'].")";
					$id=$bd->xConsulta_UltimoId($sql);
				}
			}
			print $id;
			break;
		case 2031://guardar item
			$id=$_POST['i'];
			//$idsec=$_POST['ise'];
			//if($id=='undefined'){$id='';}
			$des_item=$_POST['des'];
			$precio_item=$_POST['p'];
			$det_item=$_POST['d'];
			$img_item=$_POST['img'];
			if($id==''){
				//comprueba nombre que coicidan en la bd si no selecciono item con id
				$sql="select iditem as d1 from item where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and descripcion='".$des_item."'";
				$id=$bd->xDevolverUnDato($sql);
				if($id==''){
					//guarda nuevo
					$sql="insert into item (idorg, idsede,descripcion,precio,detalle,img) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$des_item."','".$precio_item."','".$det_item."','".$img_item."')";
					$id=$bd->xConsulta_UltimoId($sql);
				}
				else{
					$sql="update item set detalle='".$det_item."', precio='".$precio_item."', img='".$img_item."' where iditem=".$id;
					$bd->xConsulta_NoReturn($sql);
				}
			}
			print $id;
			break;
		case 2032://update seccion print
			$id=$_POST['i'];
			if($id!=''){
				$sql="update seccion set imprimir=".$_POST['p']." where idseccion=".$id;
				$bd->xConsulta_NoReturn($sql);
			}
			break;
		case 2033://update seccion print
			$id=$_POST['i'];
			if($id!=''){
				$sql="update seccion set ver_stock_cero=".$_POST['p']." where idseccion=".$id;
				$bd->xConsulta_NoReturn($sql);
			}
			break;
		case 204:// id ultima carta // guardar cambios de carta
			$idCategoria=$_POST['idc'];
			$id_carta_anterior=$_POST['id_carta_anterior']; // es idcarta
			$id_carta=$_POST['id_carta_anterior']; // es idcarta
			$sql_array=$_POST['sql_array'];
			$fecha=$_POST['f'];
			$sql_carta_historial="";
			$sql_carta_lista_anterior="";
			$sql_update_carta = '';

			//obtiene ultima carta segun fecha
			// $sql="SELECT idcarta as d1 FROM carta WHERE (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede']." and idcategoria=".$idCategoria.") AND STR_TO_DATE(fecha, '%d/%m/%Y')=curdate()";
			// $id_carta=$bd->xDevolverUnDato($sql);
			if($id_carta==''){ //nueva carta
				$sql_carta="insert into carta (idorg, idsede, idcategoria,fecha) value (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$idCategoria.", DATE_FORMAT(now(),'%d/%m/%Y'))";
				$id_carta=$bd->xConsulta_UltimoId($sql_carta);

				// if($id_carta_anterior!=""){
				// 	$sql_carta_lista_anterior="delete from carta_lista where idcarta=".$id_carta_anterior.";";
				// }
			}else{
				//si ya existe carta guarda y pasa carta actual al historial
				//$sql_carta_historial="delete from carta_lista_historial where idcarta=".$id_carta."; INSERT INTO carta_lista_historial (idcarta_lista,idcarta,idseccion,iditem,precio,cantidad,cant_preparado,sec_orden,estado) SELECT * from carta_lista WHERE idcarta=".$id_carta.";";

				// 121118 | si ya existe actualiza la fecha de modificacion
				$sql_update_carta = "update carta set fecha = DATE_FORMAT(now(),'%d/%m/%Y') where idcarta=".$id_carta."; ";

				// elimina el contenido antererior para guardar todo nueva mente con las filas modificadas o agregadas
				// $sql_carta_lista_anterior="delete from carta_lista where idcarta=".$id_carta.";";
			}

			$sql_carta_lista="";
			/// cuando la carta es muy extensa guarda en partes
			$sql_object=[];
			$index_row=0;
			$contador_row=0;
			///
			// echo json_decode($sql_array);
			foreach ($sql_array as $seccion) {
				

				//seccion				
				$id_seccion=$seccion['id_seccion'];
				if($id_seccion==''){
					//comprueba nombre que coicidan en la bd si no selecciono seccion con id
					$sql="select idseccion as d1 from seccion where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and descripcion='".$seccion['des_seccion']."'";
					$id_seccion=$bd->xDevolverUnDato($sql);
					if($id_seccion==''){
						//nuevo seccion
						$sql_seccion="insert into seccion (idorg,idsede,descripcion,sec_orden,idimpresora)values(".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$seccion['des_seccion']."',".$seccion['sec_orden'].",".$seccion['idimpresora'].")";
						$id_seccion=$bd->xConsulta_UltimoId($sql_seccion);
					}
				} else if ( $seccion['modificado'] ) {
					$sql_update_seccion = "update seccion set descripcion='".$seccion['des_seccion']."' where idseccion=".$id_seccion;
					$bd->xConsulta_NoReturn($sql_update_seccion);
				}
				// echo $id_seccion.",".$seccion['des_seccion']." * ".$contador_row." | ";
				

				//item
				foreach ($seccion as $item) {
					if(is_array($item)==false){continue;}
						// if ( !array_key_exists('id_item', $item) ) {continue;}
						
						$id_item=$item['id_item'];
						if($id_item==''){
							//comprueba nombre que coicidan en la bd si no selecciono item con id
							$sql="select iditem as d1 from item where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and descripcion='".$item['des_item']."'";
							// echo json_encode($item);
							// echo $sql;
							$id_item=$bd->xDevolverUnDato($sql);
							if($id_item==''){
								//guarda nuevo item
								$sql="insert into item (idorg, idsede,descripcion,precio,detalle,img) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$item['des_item']."','".$item['precio_item']."','".$item['det_item']."','".$item['img_item']."')";
								//echo $sql;
								echo $sql;
								$id_item=$bd->xConsulta_UltimoId($sql);
							}
							else{//actualizar
								$sql_update_item="update item set descripcion='".$item['des_item']."', detalle='".$item['det_item']."', precio='".$item['precio_item']."', img='".$item['img_item']."' where iditem=".$id_item;
								// echo $sql_update_item;
								$bd->xConsulta_NoReturn($sql_update_item);
							}
						} else {//actualizar
								$sql_update_item="update item set descripcion='".$item['des_item']."', detalle='".$item['det_item']."', precio='".$item['precio_item']."', img='".$item['img_item']."' where iditem=".$id_item.";";
								$bd->xConsulta_NoReturn($sql_update_item);
								// echo $sql_update_item;
							}

						$id_carta_lista=$item['id_carta_lista'];
						// if($id_carta_lista==""){$id_carta_lista=$_SESSION['ido'].$_SESSION['idsede'].$id_item;}						
						if($id_carta_lista==""){$id_carta_lista=$_SESSION['ido'].$_SESSION['idsede'].$id_carta.$id_seccion.$id_item;}

						$sql_carta_lista=$sql_carta_lista."('".$id_carta_lista."',".$id_carta.",".$id_seccion.",".$id_item.",'".$item['precio_item']."','".$item['cant_item']."','".$item['cant_item']."',".$item['sec_orden']."),";

						$contador_row ++;
						
						// idea para no eliminar - pero y si item se elimina?
						// if ($item['idcarta'] === "undefined") {
						// } else { // modifica
						// 	$sql_carta_lista_update_item = "update carta_lista set "
						// }					
				}



				// if($contador_row>40) {
				// 	$contador_row=0;

				// 	$sql_carta_lista = substr($sql_carta_lista, 0, -1);
				// 	$sql_carta_lista_insert_update = "insert into carta_lista (idcarta_lista,idcarta,idseccion,iditem,precio,cantidad,cant_preparado,sec_orden) values ".$sql_carta_lista." ON DUPLICATE KEY UPDATE 
				// 								idcarta_lista=values(idcarta_lista),
				// 								idcarta=values(idcarta),
				// 								idseccion=values(idseccion),
				// 								iditem=values(iditem),
				// 								precio=values(precio),
				// 								cantidad=values(cantidad),
				// 								cant_preparado=values(cant_preparado),
				// 								sec_orden=values(sec_orden)";

				// 	$sql_object[$index_row] = $sql_carta_lista_insert_update;

				// 	$sql_carta_lista='';
				// 	$sql_carta_lista_insert_update='';

				// 	$index_row++;
				// }
			}



			// $index_row++;
			// $sql_carta_lista = substr($sql_carta_lista, 0, -1);					
			// $sql_carta_lista_insert_update = "insert into carta_lista (idcarta_lista,idcarta,idseccion,iditem,precio,cantidad,cant_preparado,sec_orden) values ".$sql_carta_lista." ON DUPLICATE KEY UPDATE 
			// 									idcarta_lista=values(idcarta_lista),
			// 									idcarta=values(idcarta),
			// 									idseccion=values(idseccion),
			// 									iditem=values(iditem),
			// 									precio=values(precio),
			// 									cantidad=values(cantidad),
			// 									cant_preparado=values(cant_preparado),
			// 									sec_orden=values(sec_orden)";

			// $sql_object[$index_row] = $sql_carta_lista_insert_update;



			//armar carta lista
			$sql_carta_lista = substr($sql_carta_lista, 0, -1);

			//$sql_carta_lista="delete from carta_lista where idcarta=".$id_carta."; insert into carta_lista (idcarta_lista,idcarta,idseccion,iditem,precio,cantidad,cant_preparado,sec_orden) values ".$sql_carta_lista.";";
			$sql_carta_lista_insert_update = "insert into carta_lista (idcarta_lista,idcarta,idseccion,iditem,precio,cantidad,cant_preparado,sec_orden) values ".$sql_carta_lista." ON DUPLICATE KEY UPDATE 
												idcarta_lista=values(idcarta_lista),
												idcarta=values(idcarta),
												idseccion=values(idseccion),
												iditem=values(iditem),
												precio=values(precio),
												cantidad=values(cantidad),
												cant_preparado=values(cant_preparado),
												sec_orden=values(sec_orden)";

			// $sql_carta_lista="delete from carta_lista where idcarta=".$id_carta."; insert into carta_lista (idcarta_lista,idcarta,idseccion,iditem,precio,cantidad,cant_preparado,sec_orden) values ".$sql_carta_lista.";";

			// $sql_ejecuta=$sql_update_carta.$sql_carta_lista;
			$sql_ejecuta=$sql_update_carta.$sql_carta_lista_insert_update;
			// $bd->xConsulta($sql_carta_lista_insert_update.';');
			// $bd->xConsulta($sql_update_carta.';');

			// $sql_ejecuta=$sql_carta_historial.$sql_carta_lista_anterior.$sql_carta_lista;
			// echo $sql_carta_lista_insert_update;

			$bd->xMultiConsultaNoReturn($sql_ejecuta);
			
			echo $id_carta;
			break;
		//MI PEDIDO APP ANFITRION CLIENTE
		//MI PEDIDO APP ANFITRION CLIENTE
		case 2041://solo secciones en mi pedido
			/*$sql="
				SELECT * FROM(
				SELECT lcase(GROUP_CONCAT(i.descripcion ORDER BY i.descripcion)) AS all_items, s.idseccion,concat('1',s.idseccion) AS idseccion_index, s.descripcion AS des_seccion,s.idimpresora, IF(cl.cantidad='SP','porcion','carta_lista') AS procede
				FROM seccion AS s
					INNER JOIN carta_lista AS cl using(idseccion)
					INNER JOIN item AS i using(iditem)
					INNER JOIN carta AS c using(idcarta)
					INNER JOIN categoria AS cat using(idcategoria)
				WHERE (s.idorg=".$_SESSION['ido']." AND s.idsede=".$_SESSION['ido'].") AND (cat.idcategoria=1) AND s.estado=0
				GROUP BY s.idseccion
				ORDER BY cl.sec_orden) a
				UNION ALL
				SELECT * FROM(
				SELECT '' as all_items, pf.idproducto_familia, concat('2',pf.idproducto_familia) AS idseccion_index, pf.descripcion AS des_seccion, cp.idimpresora,'almacen_items' AS procede
				FROM producto_familia AS pf
					INNER JOIN producto AS p using(idproducto_familia)
					INNER JOIN almacen_items AS ai using(idproducto)
					INNER JOIN almacen AS a using(idalmacen)
					LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ai.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
				WHERE (pf.idorg=".$_SESSION['ido']." AND pf.idsede=".$_SESSION['ido'].") AND a.bodega=1 AND pf.estado=0
				GROUP by pf.idproducto_familia
				ORDER BY pf.descripcion)b

				LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ps.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
			";*/

			$idcategoria = isset($_POST['idcategoria']) ? $_POST['idcategoria'] : 1;
			if ($idcategoria=='undefined' || $idcategoria==null) {$idcategoria=1;}
			$sql="
				SELECT * FROM(
				SELECT lcase(GROUP_CONCAT(i.descripcion ORDER BY i.descripcion)) AS all_items, s.idseccion,concat('1',s.sec_orden,'.',s.idseccion) AS idseccion_index, s.descripcion AS des_seccion,s.idimpresora, IF(cl.cantidad='SP',2,1) AS procede
				FROM seccion AS s
					INNER JOIN carta_lista AS cl using(idseccion)
					INNER JOIN item AS i using(iditem)
					INNER JOIN carta AS c using(idcarta)
					INNER JOIN categoria AS cat using(idcategoria)
				WHERE (s.idorg=".$_SESSION['ido']." AND s.idsede=".$_SESSION['idsede'].") AND (cat.idcategoria=".$idcategoria.") AND s.estado=0
				GROUP BY s.idseccion
				ORDER BY s.sec_orden) a
				UNION ALL
				SELECT * FROM(
				SELECT '' as all_items, pf.idproducto_familia, concat('2',pf.idproducto_familia,'.0') AS idseccion_index, pf.descripcion AS des_seccion, pf.idimpresora,0 AS procede
				FROM producto_familia AS pf
					INNER JOIN producto AS p using(idproducto_familia)
					INNER JOIN producto_stock AS ps using(idproducto)
					INNER JOIN almacen AS a using(idalmacen)					
				WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['idsede'].") AND a.bodega=1 AND ps.estado=0 AND p.estado=0
				GROUP by pf.idproducto_familia
				ORDER BY pf.descripcion)b
			";
			$bd->xConsulta($sql);

			//WHERE (pf.idorg=".$_SESSION['ido']." AND pf.idsede=".$_SESSION['idsede'].") AND a.bodega=1 AND ps.stock>0 AND pf.estado=0
			break;
		case 2042://submenu items //solo esto no lleva decimal en el sec_index, por que ordena cadena al reves
			$idCatProcede=$_POST['p'];
			$idcategoria = $_POST['i'];
			$idseccion = $_POST['s'];
			$sql="CALL procedure_submenu_items_2042(".$idCatProcede.",".$idcategoria.",'".$idseccion."',".$g_ido.",".$g_idsede.");";
			// if($idCatProcede!=0){
			// 	$sql="
			// 		SELECT cl.idcarta_lista,s.idseccion,concat('1',s.sec_orden) as idseccion_index,s.descripcion AS des_seccion,s.idimpresora,i.iditem,i.descripcion AS des_item,cl.precio , IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad) AS cantidad,s.sec_orden
			// 			,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP',2,1) AS procede, '0' AS procede_index,'1' AS imprimir_comanda,'' as codigo_barra, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar, '0' AS idalmacen_items
			// 		FROM carta_lista AS cl
			// 			INNER JOIN item AS i using(iditem)
			// 			INNER JOIN seccion AS s using(idseccion)
			// 			INNER JOIN carta AS c using(idcarta)
			// 			LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
			// 											FROM item_ingrediente AS ii
			// 											INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
			// 											WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
			// 		WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") AND cl.estado=0 AND c.idcategoria=".$idcategoria." AND s.idseccion=".$_POST['s']." AND IF(IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad)=0,s.ver_stock_cero,0)=0 ORDER BY i.descripcion
			// 	";
			// }else{
			// 	$sql="
			// 		SELECT '' as all_items, ps.idproducto_stock AS idcarta_lista,pf.idproducto_familia,pf.idproducto_familia as idseccion, concat('2',pf.idproducto_familia) AS idseccion_index, p.idproducto AS iditem, pf.descripcion AS des_seccion, pf.idimpresora
			// 			,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, ps.stock AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, ps.stock AS cant_preparado, 0 AS imprimir,p.idproducto AS idprocede, 0 AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,p.codigo_barra,1 AS cant_descontar,ps.idproducto_stock AS idalmacen_items
			// 		FROM producto AS p
			// 			INNER JOIN producto_stock AS ps using(idproducto)
			// 			INNER JOIN almacen AS a using(idalmacen)
			// 			INNER JOIN producto_familia AS pf using(idproducto_familia)						
			// 		WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['idsede'].") AND pf.idproducto_familia='".$_POST['s']."' AND a.bodega=1 AND ps.estado=0 AND p.estado=0
			// 		GROUP by p.idproducto
			// 		ORDER BY pf.descripcion, p.descripcion
			// 	";
			// }
			$bd->xConsulta($sql);
			break;
		case 2043://stock en tiempo real
			$idCatProcede=$_POST['p'];
			if($idCatProcede!=0){//'almacen_items'
				$sql="
					SELECT IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad) AS d1
					FROM carta_lista AS cl
					WHERE cl.idcarta_lista=".$_POST['i']."
				";
			}
			else{
				/*$sql="
					SELECT sum(ai.stock) AS cantidad
					FROM almacen_items AS ai
						INNER JOIN almacen AS a using(idalmacen)
					WHERE ai.idproducto=".$_POST['i']." AND ai.estado=0 AND a.bodega=1
					GROUP by ai.idproducto
				";*/
				$sql="SELECT stock AS cantidad FROM producto_stock WHERE idproducto_stock=".$_POST['i'];
			}
			print $bd->xDevolverUnDato($sql);
			break;
		//MI PEDIDO APP ANFITRION CLIENTE
		//MI PEDIDO APP ANFITRION CLIENTE
		case 205://load listado carta actual + bodega si hay // PARA AGREGAR EB PANEL DE CONTROL DE PEDIDOS
			//cambiamos los caracteres de procede por numeros (0=almacen_items o producto 1=carta lista 2=porcion)
			// $sql="
			// 	SELECT * FROM(
			// 		SELECT lcase(ii_detalle.descripcion) AS all_items,cl.idcarta_lista,s.idseccion,concat('1',s.sec_orden,'.',s.idseccion) AS idseccion_index, i.iditem,s.descripcion AS des_seccion,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad) AS cantidad,s.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP',2,1) AS procede, '0' AS procede_index,'1' AS imprimir_comanda,'' as codigo_barra, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar, '0' AS idalmacen_items
			// 		FROM carta_lista AS cl
			// 			INNER JOIN carta AS c using(idcarta)
			// 			INNER JOIN seccion AS s using(idseccion)
			// 			INNER JOIN item AS i using(iditem)
			// 			LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
			// 			LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
			// 						FROM item_ingrediente AS ii
			// 						INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
			// 						WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
			// 			LEFT JOIN (SELECT sa.idseccion, GROUP_CONCAT(ia.descripcion) AS descripcion FROM item AS ia INNER JOIN carta_lista AS cla using(iditem) INNER JOIN seccion AS sa using(idseccion) GROUP BY sa.idseccion) AS ii_detalle ON ii_detalle.idseccion=s.idseccion
			// 		WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta AND IF(IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad)=0,s.ver_stock_cero,0)=0
			// 		order by s.sec_orden
			// 	) a
			// 	UNION ALL
			// 		SELECT * FROM(
			// 		SELECT '' as all_items, ps.idproducto_stock AS idcarta_lista,pf.idproducto_familia AS idseccion, concat('2',pf.idproducto_familia,'.0') AS idseccion_index, p.idproducto AS iditem, pf.descripcion AS des_seccion, cp.idimpresora
			// 			,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, ps.stock AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, ps.stock AS cant_preparado,'' as f_ingreso, 0 AS imprimir,p.idproducto AS idprocede, 0 AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,p.codigo_barra,1 AS cant_descontar,ps.idproducto_stock AS idalmacen_items
			// 		FROM producto AS p
			// 			INNER JOIN producto_stock AS ps using(idproducto)
			// 			INNER JOIN almacen AS a using(idalmacen)
			// 			INNER JOIN producto_familia AS pf using(idproducto_familia)
			// 			LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ps.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
			// 		WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['idsede'].") AND a.bodega=1 AND ps.estado=0 AND p.estado=0
			// 		GROUP by p.idproducto
			// 		ORDER BY pf.descripcion, p.descripcion
			// 		) b
			// ";

			//LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ps.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
			$idCategoria = $_POST['idcategoria'];
			$sql="CALL procedure_loadcarta_205(".$idCategoria.",".$g_ido.",".$g_idsede.");";
			// --
			// $sql = "
			// SELECT * FROM(
			// 	SELECT lcase(ii_detalle.descripcion) AS all_items,cl.idcarta_lista,s.idseccion,concat('1',s.sec_orden,'.',s.idseccion) AS idseccion_index, i.iditem,s.descripcion AS des_seccion,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad) AS cantidad,s.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP',2,1) AS procede, '0' AS procede_index,'1' AS imprimir_comanda,'' as codigo_barra, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar, '0' AS idalmacen_items
			// 	FROM carta_lista AS cl
			// 		INNER JOIN carta AS c using(idcarta)
			// 		INNER JOIN seccion AS s using(idseccion)
			// 		INNER JOIN item AS i using(iditem)						
			// 		LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
			// 					FROM item_ingrediente AS ii
			// 					INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
			// 					WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
			// 		LEFT JOIN (SELECT sa.idseccion, GROUP_CONCAT(ia.descripcion) AS descripcion FROM item AS ia INNER JOIN carta_lista AS cla using(iditem) INNER JOIN seccion AS sa using(idseccion) GROUP BY sa.idseccion) AS ii_detalle ON ii_detalle.idseccion=s.idseccion
			// 	WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and (c.idcategoria=".$_POST['idcategoria']." and cl.estado=0) AND IF(IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad)=0,s.ver_stock_cero,0)=0
			// 	order by s.sec_orden, i.descripcion
			// ) a
			// UNION ALL
			// 	SELECT * FROM(
			// 	SELECT '' as all_items, ps.idproducto_stock AS idcarta_lista,pf.idproducto_familia AS idseccion, concat('2',pf.idproducto_familia,'.0') AS idseccion_index, p.idproducto AS iditem, pf.descripcion AS des_seccion, pf.idimpresora
			// 		,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, ps.stock AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, ps.stock AS cant_preparado,'' as f_ingreso, 0 AS imprimir,p.idproducto AS idprocede, 0 AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,p.codigo_barra,1 AS cant_descontar,ps.idproducto_stock AS idalmacen_items
			// 	FROM producto AS p
			// 		INNER JOIN producto_stock AS ps using(idproducto)
			// 		INNER JOIN almacen AS a using(idalmacen)
			// 		INNER JOIN producto_familia AS pf using(idproducto_familia)					
			// 	WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['idsede'].") AND a.bodega=1 AND ps.estado=0 AND p.estado=0
			// 	GROUP by p.idproducto
			// 	ORDER BY pf.descripcion, p.descripcion
			// 	) b
			// ";
			// --

			/*$sql="
			SELECT * FROM(
					SELECT lcase(ii_detalle.descripcion) AS all_items,cl.idcarta_lista,s.idseccion,concat('1',s.idseccion) AS idseccion_index, i.iditem,s.descripcion AS des_seccion,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad) AS cantidad,cl.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP','porcion','carta_lista') AS procede, '0' AS procede_index,'1' AS imprimir_comanda,'' as codigo_barra, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar, '0' AS idalmacen_items
					FROM carta_lista AS cl
						INNER JOIN carta AS c using(idcarta)
						INNER JOIN seccion AS s using(idseccion)
						INNER JOIN item AS i using(iditem)
						LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
						LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
									FROM item_ingrediente AS ii
									INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
									WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
						LEFT JOIN (SELECT sa.idseccion, GROUP_CONCAT(ia.descripcion) AS descripcion FROM item AS ia INNER JOIN carta_lista AS cla using(iditem) INNER JOIN seccion AS sa using(idseccion) GROUP BY sa.idseccion) AS ii_detalle ON ii_detalle.idseccion=s.idseccion
					WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta AND IF(IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad)=0,s.ver_stock_cero,0)=0
					order by cl.sec_orden
				) a
				UNION ALL
					SELECT * FROM(
					SELECT '' as all_items, ps.idproducto_stock AS idcarta_lista,pf.idproducto_familia, concat('2',pf.idproducto_familia) AS idseccion_index, p.idproducto AS iditem, pf.descripcion AS des_seccion, cp.idimpresora
						,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, ps.stock AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, ps.stock AS cant_preparado,'' as f_ingreso, 0 AS imprimir,p.idproducto AS idprocede, 'almacen_items' AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,p.codigo_barra,1 AS cant_descontar,ps.idproducto_stock AS idalmacen_items
					FROM producto AS p
						INNER JOIN producto_stock AS ps using(idproducto)
						INNER JOIN almacen AS a using(idalmacen)
						INNER JOIN producto_familia AS pf using(idproducto_familia)
						LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ps.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
					WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['idsede'].") AND a.bodega=1 AND ps.estado=0 AND p.estado=0
					GROUP by p.idproducto
					ORDER BY pf.descripcion, p.descripcion
					) b
			";
			/*$sql="
			SELECT * FROM(
					SELECT lcase(ii_detalle.descripcion) AS all_items,cl.idcarta_lista,s.idseccion,concat('1',s.idseccion) AS idseccion_index, i.iditem,s.descripcion AS des_seccion,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad) AS cantidad,cl.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP','porcion','carta_lista') AS procede, '0' AS procede_index,'1' AS imprimir_comanda,'' as codigo_barra, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar, '0' AS idalmacen_items
					FROM carta_lista AS cl
						INNER JOIN carta AS c using(idcarta)
						INNER JOIN seccion AS s using(idseccion)
						INNER JOIN item AS i using(iditem)
						LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
						LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
									FROM item_ingrediente AS ii
									INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
									WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
						LEFT JOIN (SELECT sa.idseccion, GROUP_CONCAT(ia.descripcion) AS descripcion FROM item AS ia INNER JOIN carta_lista AS cla using(iditem) INNER JOIN seccion AS sa using(idseccion) GROUP BY sa.idseccion) AS ii_detalle ON ii_detalle.idseccion=s.idseccion
					WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta
					order by cl.sec_orden
				) a
				UNION ALL
					SELECT * FROM(
					SELECT '' as all_items, ai.idalmacen_items AS idcarta_lista,pf.idproducto_familia, concat('2',pf.idproducto_familia) AS idseccion_index, ai.idproducto AS iditem, pf.descripcion AS des_seccion, cp.idimpresora
						,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, sum(ai.stock) AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, sum(ai.stock) AS cant_preparado, ai.f_ingreso, 0 AS imprimir,ai.idproducto AS idprocede, 'almacen_items' AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,p.codigo_barra,1 AS cant_descontar,ai.idalmacen_items
					FROM almacen_items AS ai
						INNER JOIN almacen AS a using(idalmacen)
						INNER JOIN producto AS p using(idproducto)
						INNER JOIN producto_familia AS pf using(idproducto_familia)
						LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ai.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
					WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['idsede'].") AND a.bodega=1 AND ai.estado=0 AND p.estado=0
					GROUP by ai.idproducto
					ORDER BY pf.descripcion, p.descripcion
					) b
			";
			/*$sql="
				SELECT * FROM(
					SELECT cl.idcarta_lista,s.idseccion,concat('1',s.idseccion) AS idseccion_index, i.iditem,s.descripcion AS des_seccion,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),cl.cantidad) AS cantidad,cl.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP','porcion','carta_lista') AS procede, '0' AS procede_index,'1' AS imprimir_comanda,'' as codigo_barra
					FROM carta_lista AS cl
						INNER JOIN carta AS c using(idcarta)
						INNER JOIN seccion AS s using(idseccion)
						INNER JOIN item AS i using(iditem)
						LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
						LEFT JOIN(SELECT i.iditem, po.idporcion, i.descripcion,po.stock
								FROM item AS i
									INNER JOIN item_ingrediente AS ii using(iditem)
									INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
								GROUP BY i.iditem
							 ) AS it_p using(iditem)
					WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta AND c.idcategoria=1
					order by cl.sec_orden
				) a
				UNION ALL
					SELECT * FROM(
					SELECT ai.idalmacen_items AS idcarta_lista,pf.idproducto_familia, concat('2',pf.idproducto_familia) AS idseccion_index, ai.idproducto AS iditem, pf.descripcion AS des_seccion, cp.idimpresora
						,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, sum(ai.stock) AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, sum(ai.stock) AS cant_preparado, ai.f_ingreso, 0 AS imprimir,ai.idproducto AS idprocede, 'almacen_items' AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,p.codigo_barra
					FROM almacen_items AS ai
						INNER JOIN almacen AS a using(idalmacen)
						INNER JOIN producto AS p using(idproducto)
						INNER JOIN producto_familia AS pf using(idproducto_familia)
						LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ai.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
					WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['idsede'].") AND a.bodega=1 AND ai.estado=0 AND p.estado=0
					GROUP by ai.idproducto
					ORDER BY pf.descripcion, p.descripcion
				) b
			";
			/*$sql="
			SELECT cl.idcarta_lista,s.idseccion, i.iditem,s.descripcion AS des_seccion, i.descripcion AS des_item, cl.precio, cl.cantidad,cl.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir
			FROM carta_lista AS cl
				INNER JOIN carta AS c using(idcarta)
				INNER JOIN seccion AS s using(idseccion)
				INNER JOIN item AS i using(iditem)
				LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
			WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta AND c.idcategoria=".$_POST['i']." order by cl.sec_orden";
			*/

			$bd->xConsulta($sql);
			break;
		case 2051://load listado de carta actual solo item de carta, para elaborar carta
			// $sql="
			// SELECT c.idcarta, cl.idcarta_lista,s.idseccion, i.iditem,s.descripcion AS des_seccion, i.descripcion AS des_item, cl.precio, cl.cantidad,s.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,s.ver_stock_cero
			// FROM carta_lista AS cl
			// 	INNER JOIN carta AS c using(idcarta)
			// 	INNER JOIN seccion AS s using(idseccion)
			// 	INNER JOIN item AS i using(iditem)
			// 	LEFT JOIN ( SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta where idcategoria=".$_POST['i']." ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
			// WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta AND c.idcategoria=".$_POST['i']." order by s.sec_orden,i.descripcion
			// ";

			// conciderar
			// $sql = "
			// SELECT c.idcarta, cl.idcarta_lista,s.idseccion, i.iditem,s.descripcion AS des_seccion, i.descripcion AS des_item, cl.precio, cl.cantidad,s.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,s.ver_stock_cero
			// FROM carta_lista AS cl
			// 	INNER JOIN carta AS c using(idcarta)
			// 	INNER JOIN seccion AS s using(idseccion)
			// 	INNER JOIN item AS i using(iditem)                
			// WHERE (c.idorg=1 AND c.idsede=1) and cl.estado=0 and (c.idcategoria=1) order by s.sec_orden,i.descripcion
			// ";

			$sql = "
			SELECT c.idcarta, cl.idcarta_lista,s.idseccion, i.iditem,s.descripcion AS des_seccion, i.descripcion AS des_item, cl.precio, cl.cantidad,s.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,s.ver_stock_cero
			FROM carta_lista AS cl
				INNER JOIN carta AS c using(idcarta)
				INNER JOIN seccion AS s using(idseccion)
				INNER JOIN item AS i using(iditem)
                LEFT JOIN ( SELECT idorg, idsede, idcarta FROM carta where idcategoria=".$_POST['i']." ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
			WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 and (c.idcarta=uc.idcarta) order by s.sec_orden,s.descripcion,i.descripcion
			";
			$bd->xConsulta($sql);
			break;
		case 2052://load sub totales del pedido para mostrar en mi pedido al momento de pedir la cuenta o para imprimir precuenta
			$sql="
				SELECT pds.descripcion, sum(importe)AS importe
				FROM pedido_subtotales AS pds
					INNER JOIN pedido AS p using(idpedido)
				WHERE (p.nummesa=".$_POST['m']." AND p.estado=0) AND pds.estado=0 AND pds.descripcion!='sub total' AND (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].")
				GROUP BY pds.descripcion
				ORDER BY sum(importe) desc
			";
			$bd->xConsulta($sql);
			break;
		case 2053: // cargar items de carta lista para busqueda submenu mi pedido
		// LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ps.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
			$parametro = $_POST['parametro'];
			$idCategoria = $_POST['i'];
			$sql="CALL procedure_bus_submenu_2053(".$idCategoria.",'".$parametro."',".$g_ido.",".$g_idsede.");";
			// $sql="
			// SELECT * FROM(
			// 	SELECT cl.idcarta_lista,s.idseccion,concat('1',s.sec_orden) AS idseccion_index, i.iditem,s.descripcion AS des_seccion,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad) AS cantidad,s.sec_orden,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir,IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP',2,1) AS procede, '0' AS procede_index,'1' AS imprimir_comanda,'' as codigo_barra, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar, '0' AS idalmacen_items
			// 	FROM carta_lista AS cl
			// 		INNER JOIN carta AS c using(idcarta)
			// 		INNER JOIN seccion AS s using(idseccion)
			// 		INNER JOIN item AS i using(iditem)						
			// 		LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
			// 					FROM item_ingrediente AS ii
			// 					INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
			// 					WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)					
			// 	WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and (c.idcategoria=".$_POST['i']." and cl.estado=0 and CONCAT(s.descripcion,i.descripcion) like '%".$parametro."%') AND IF(IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad)=0,s.ver_stock_cero,0)=0
			// 	order by  cl.count_seleccionado desc, i.descripcion limit 15
			// ) a
			// UNION ALL
			// 	SELECT * FROM(
			// 	SELECT ps.idproducto_stock AS idcarta_lista,pf.idproducto_familia AS idseccion, concat('2',pf.idproducto_familia) AS idseccion_index, p.idproducto AS iditem, pf.descripcion AS des_seccion, pf.idimpresora
			// 		,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, ps.stock AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, ps.stock AS cant_preparado,'' as f_ingreso, 0 AS imprimir,p.idproducto AS idprocede, 0 AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,p.codigo_barra,1 AS cant_descontar,ps.idproducto_stock AS idalmacen_items
			// 	FROM producto AS p
			// 		INNER JOIN producto_stock AS ps using(idproducto)
			// 		INNER JOIN almacen AS a using(idalmacen)
			// 		INNER JOIN producto_familia AS pf using(idproducto_familia)					
			// 	WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['idsede'].") and CONCAT(pf.descripcion,p.descripcion) like '%".$parametro."%' AND a.bodega=1 AND ps.estado=0 AND p.estado=0
			// 	GROUP by p.idproducto
			// 	ORDER BY pf.idproducto_familia, p.descripcion limit 6
			// 	) b
			// ";

			$bd->xConsulta($sql);
			break;
		case 206://guardar detalleitem
			$sql="update item set detalle='".$_POST['d']."' where iditem=".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 207://guardar foto
			$sql="update item set img='".$_POST['d']."' where iditem=".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 208://guardar logo print
			$sql="update conf_print set logo='".$_POST['d']."' where idconf_print=".$_POST['i'];
			$sqlLogo64 = "; update sede set logo64 = '".$_POST['logo']."' where idsede=".$_SESSION['idsede'];
			$bd->xMultiConsulta($sql.$sqlLogo64);
			break;
		//pedido
		case 3: //amar array con tipo de consumo
			$sql="SELECT * FROM tipo_consumo WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			$bd->xConsulta($sql);
			break;
		case 301: //load seccion
			//0 procede carta 1 procede bodega
			$idCatProcede=$_POST['p'];
			if($idCatProcede!=0){//'almacen_items'
				$sql="
				SELECT cl.idcarta_lista,s.idseccion,concat('1',s.sec_orden,'.',s.idseccion) as idseccion_index, i.iditem,s.descripcion AS des_seccion ,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),cl.cantidad) AS cantidad,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir, IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP',2,1) as procede, '0' AS procede_index, '1' AS imprimir_comanda, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar
				FROM carta_lista AS cl
					INNER JOIN carta AS c using(idcarta)
					INNER JOIN seccion AS s using(idseccion)
					INNER JOIN item AS i using(iditem)
					LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
					LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
									FROM item_ingrediente AS ii
									INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
									WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
				WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta AND c.idcategoria=".$_POST['i']." AND s.idseccion=".$_POST['s']."
				ORDER BY i.descripcion
				";
			}
			else{
				$sql="
				SELECT ai.idalmacen_items AS idcarta_lista, pf.idproducto_familia AS idseccion,concat('2',pf.idproducto_familia,'.0') AS idseccion_index, ai.idproducto AS iditem, pf.descripcion AS des_seccion, pf.idimpresora
					,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, sum(ai.stock) AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, sum(ai.stock) AS cant_preparado, ai.f_ingreso, 0 AS imprimir,ai.idproducto AS idprocede, 0 AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,1 AS cant_descontar
				FROM almacen_items AS ai
					INNER JOIN almacen AS a using(idalmacen)
					INNER JOIN producto AS p using(idproducto)
					INNER JOIN producto_familia AS pf using(idproducto_familia)					
				WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['ido'].") AND pf.idproducto_familia='".$_POST['s']."' AND ai.estado=0 AND p.estado=0
				GROUP by ai.idproducto
				ORDER BY pf.descripcion, p.descripcion
				";
			}

			/*$sql="
				SELECT cl.idcarta_lista,s.idseccion, i.iditem,s.descripcion AS des_seccion ,s.idimpresora, i.descripcion AS des_item, cl.precio, cl.cantidad,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir
				FROM carta_lista AS cl
					INNER JOIN carta AS c using(idcarta)
					INNER JOIN seccion AS s using(idseccion)
					INNER JOIN item AS i using(iditem)
					LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
				WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta AND c.idcategoria=".$_POST['i']." AND s.idseccion=".$_POST['s']."
				ORDER BY i.descripcion
			";*/
			$bd->xConsulta($sql);
			break;
		case 3011://item carta en Ul add de pedido
			$sql="
				SELECT cl.idcarta_lista,s.idseccion, i.iditem,s.descripcion AS des_seccion ,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),cl.cantidad) AS cantidad, IF(cl.cantidad='SP',it_p.idporcion,0) AS idporcion
				FROM carta_lista AS cl
					INNER JOIN carta AS c using(idcarta)
					INNER JOIN seccion AS s using(idseccion)
					INNER JOIN item AS i using(iditem)
					LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
					LEFT JOIN(SELECT i.iditem, po.idporcion, i.descripcion,po.stock
								FROM item AS i
									INNER JOIN item_ingrediente AS ii using(iditem)
									INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
								GROUP BY i.iditem
							 ) AS it_p using(iditem)
				WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta
				ORDER BY i.descripcion
			";
			/*$sql="
				SELECT cl.idcarta_lista,s.idseccion,s.idimpresora, i.iditem,s.descripcion AS des_seccion, i.descripcion AS des_item, cl.precio, cl.cantidad
				FROM carta_lista AS cl
					INNER JOIN carta AS c using(idcarta)
					INNER JOIN seccion AS s using(idseccion)
					INNER JOIN item AS i using(iditem)
					LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
				WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta
				ORDER BY s.idseccion, i.descripcion
				";*/
			$bd->xConsulta($sql);
			break;
		case 3012://verificar suma de peddido si aumenta se ingreso o modifico row // actualizar
			$sql="SELECT sum(total) AS d1 FROM pedido WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") and fecha=DATE_FORMAT(now(),'%d/%m/%Y')";
			echo $bd->xDevolverUnDato($sql);
			break;
		case 302: //load categoria
			// $sql="SELECT * FROM categoria WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			$sql = "SELECT * FROM categoria WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0 and (time(now()) BETWEEN time(if(hora_ini='',now(),hora_ini)) and time(if(hora_fin = '',now(), hora_fin)))";
			$bd->xConsulta($sql);
			break;
		case 303: //load item carta, mi pedido
			/*$sql="
			SELECT cl.idcarta_lista,s.idseccion, i.iditem,s.descripcion AS des_seccion, i.descripcion AS des_item, cl.precio, cl.cantidad,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir
				FROM carta_lista AS cl
					INNER JOIN carta AS c using(idcarta)
					INNER JOIN seccion AS s using(idseccion)
					INNER JOIN item AS i using(iditem)
					LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
				WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") AND c.idcarta=uc.ultima_carta AND cl.idcarta_lista='".$_POST['i']."'
				ORDER BY i.descripcion
			";*/


			$idCatProcede=$_POST['p'];
			if($idCatProcede!=0){//'carta !=0'
				// $sql="
				// SELECT cl.idcarta_lista,s.idseccion,concat('1',s.sec_orden,'.',s.idseccion) as idseccion_index, i.iditem,s.descripcion AS des_seccion ,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),cl.cantidad) AS cantidad,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir, IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP',2,1) as procede, '0' AS procede_index, '1' AS imprimir_comanda, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar
				// FROM carta_lista AS cl
				// 	INNER JOIN carta AS c using(idcarta)
				// 	INNER JOIN seccion AS s using(idseccion)
				// 	INNER JOIN item AS i using(iditem)
				// 	LEFT JOIN (SELECT idorg, idsede, max(idcarta) AS ultima_carta FROM carta ) AS uc ON uc.idorg=c.idorg AND uc.idsede=c.idsede
				// 	LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
				// 					FROM item_ingrediente AS ii
				// 					INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
				// 					WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
				// WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 AND c.idcarta=uc.ultima_carta and cl.idcarta_lista='".$_POST['i']."'
				// ORDER BY i.descripcion
				// ";

				$sql="
				SELECT cl.idcarta_lista,s.idseccion,concat('1',s.sec_orden,'.',s.idseccion) as idseccion_index, i.iditem,s.descripcion AS des_seccion ,s.idimpresora, i.descripcion AS des_item, cl.precio, IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),cl.cantidad) AS cantidad,i.detalle,i.img,cl.cant_preparado,c.fecha,s.imprimir, IF(cl.cantidad='SP',it_p.idporcion,cl.idcarta_lista) AS idprocede, IF(cl.cantidad='SP',2,1) as procede, '0' AS procede_index, '1' AS imprimir_comanda, IF(cl.cantidad='SP',it_p.cant_porcion,1) AS cant_descontar
				FROM carta_lista AS cl
					INNER JOIN carta AS c using(idcarta)
					INNER JOIN seccion AS s using(idseccion)
					INNER JOIN item AS i using(iditem)					
					LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
									FROM item_ingrediente AS ii
									INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
									WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
				WHERE (c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede'].") and cl.estado=0 and cl.idcarta_lista='".$_POST['i']."'
				ORDER BY i.descripcion
				";
			}
			else{
				/*$sql="
				SELECT ai.idalmacen_items AS idcarta_lista, pf.idproducto_familia AS idseccion,concat('2',pf.idproducto_familia,'.0') AS idseccion_index, ai.idproducto AS iditem, pf.descripcion AS des_seccion, cp.idimpresora
					,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, sum(ai.stock) AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, sum(ai.stock) AS cant_preparado, ai.f_ingreso, 0 AS imprimir,ai.idproducto AS idprocede, 0 AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,1 AS cant_descontar
				FROM almacen_items AS ai
					INNER JOIN almacen AS a using(idalmacen)
					INNER JOIN producto AS p using(idproducto)
					INNER JOIN producto_familia AS pf using(idproducto_familia)
					LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ai.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
				WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['ido'].") and cl.idcarta_lista='".$_POST['i']."' AND ai.estado=0 AND p.estado=0
				GROUP by ai.idproducto
				ORDER BY pf.descripcion, p.descripcion
				";
				LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ps.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
				*/

				$sql="
					SELECT ps.idproducto_stock AS idcarta_lista,pf.idproducto_familia as idseccion,concat('2',pf.idproducto_familia) AS idseccion_index,p.idproducto AS iditem, pf.descripcion AS des_seccion, pf.idimpresora,p.descripcion AS des_item, format(p.precio_venta,2) AS precio, ps.stock AS cantidad, 0 AS sec_orden,'' AS detalle,'' AS img, ps.stock AS cant_preparado, 0 AS imprimir,p.idproducto AS idprocede, 0 AS procede, pf.idproducto_familia AS procede_index,a.imprimir_comanda,1 AS cant_descontar,ps.idproducto_stock AS idalmacen_items
					FROM producto AS p
						INNER JOIN producto_stock AS ps using(idproducto)
						INNER JOIN almacen AS a using(idalmacen)
						INNER JOIN producto_familia AS pf using(idproducto_familia)						
					WHERE (a.idorg=".$_SESSION['ido']." AND a.idsede=".$_SESSION['ido'].") AND ps.idproducto_stock='".$_POST['i']."' AND a.bodega=1 AND ps.estado=0 AND p.estado=0
					GROUP by p.idproducto
					ORDER BY pf.descripcion, p.descripcion
				";
			}

			$bd->xConsulta($sql);
			break;
		case 3031: // get stock item antes de confirmar el pedido comprobar stock
			$productos = $_POST['p'];

			if ( $productos !=="" ) {
				$sql = "
				SELECT cl.idcarta_lista, IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),cl.cantidad) AS cantidad
				FROM carta_lista AS cl
					INNER JOIN carta AS c using(idcarta)													
					LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
									FROM item_ingrediente AS ii
									INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
									WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
				WHERE (c.idorg=1 AND c.idsede=1) and cl.estado=0 and cl.idcarta_lista in (".$_POST['i'].") 								
				UNION ALL
				SELECT ps.idproducto_stock AS idcarta_lista, ps.stock AS cantidad
				FROM producto AS p
						INNER JOIN producto_stock AS ps using(idproducto)
						INNER JOIN almacen AS a using(idalmacen)
						INNER JOIN producto_familia AS pf using(idproducto_familia)
						LEFT JOIN (SELECT idorg, idsede, idtipo_otro, idimpresora FROM conf_print_otros WHERE esalmacen=1) AS cp ON idtipo_otro=ps.idalmacen AND (cp.idorg=a.idorg AND cp.idsede=a.idsede)
				WHERE (a.idorg=1 AND a.idsede=1) AND ps.idproducto_stock in (".$_POST['p'].") and a.bodega=1 AND ps.estado=0 AND p.estado=0
				GROUP by p.idproducto		
				";
			} else {
				$sql = "
				SELECT cl.idcarta_lista, IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),cl.cantidad) AS cantidad
				FROM carta_lista AS cl
					INNER JOIN carta AS c using(idcarta)													
					LEFT JOIN (SELECT ii.iditem, GROUP_CONCAT(ii.idporcion) AS idporcion,GROUP_CONCAT(ii.cantidad) AS cant_porcion,po.stock
									FROM item_ingrediente AS ii
									INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
									WHERE ii.idporcion!=0 GROUP BY ii.iditem) AS it_p using(iditem)
				WHERE (c.idorg=1 AND c.idsede=1) and cl.estado=0 and cl.idcarta_lista in (".$_POST['i'].")				
				";
			}

			
			$bd->xConsulta($sql);
			break;
		case 304://guardar pedido
			//num pedido
			$sql="select count(idpedido) as d1 from pedido where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'];
			$numpedido=$bd->xDevolverUnDato($sql);
			$numpedido++;

			$sql="SELECT count(fecha) AS d1 FROM pedido WHERE (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and STR_TO_DATE(fecha,'%d/%m/%Y')=curdate()";
			$correlativo_dia=$bd->xDevolverUnDato($sql);
			$correlativo_dia++;

			if(!isset($_POST['estado_p'])){$estado_p=0;}else{$estado_p=$_POST['estado_p'];}//para el caso de venta rapida si ya pago no figura en control de pedidos
			$sql="insert into pedido (idorg, idsede,fecha,hora,fecha_hora,nummesa,numpedido,correlativo_dia,referencia,total,total_r,solo_llevar,idtipo_consumo,idcategoria,reserva,idusuario,estado)values(".$_SESSION['ido'].",".$_SESSION['idsede'].",DATE_FORMAT(now(),'%d/%m/%Y'),DATE_FORMAT(now(),'%H:%i:%s'),now(),'".$_POST['mesa']."','".$numpedido."','".$correlativo_dia."','".$_POST['ref']."','".$_POST['t']."','".$_POST['t']."',".$_POST['sl'].",".$_POST['idtpc'].",".$_POST['idcat'].",".$_POST['r'].",".$_SESSION['idusuario'].",".$estado_p.")";
			$id_pedido=$bd->xConsulta_UltimoId($sql);

			$xsql_p=$_POST['sql_p'];
			$xsql_p_subt=$_POST['sql_p_subt'];

			$xsql_p=str_replace("?p",$id_pedido,$xsql_p);
			$xsql_p_subt=str_replace("?p",$id_pedido,$xsql_p_subt);
			$xsql_descontar=$_POST['sql_descontar'];

			$xsql_ejecutar=$xsql_p.'; '.$xsql_p_subt.'; '.$xsql_descontar;
			$bd->xMultiConsultaNoReturn($xsql_ejecutar);

			print $id_pedido.'|'.$numpedido.'|'.$correlativo_dia;
			//print $bd->xConsulta_UltimoId($sql).'|'.$numpedido.'|'.$correlativo_dia;
		break;
		case 30401://
			$xarr=$_POST['arr'];
			$xarr_pedido=$xarr['arrPedido'];
			$xarr_sub_totales=$xarr['arrSubTotales'];

			//sacar de arraypedido || tipo de consumo || local || llevar ... solo llevar
			$count_arr=0;
			$count_items=0;
			$item_antes_solo_llevar=0;
			$solo_llevar=0;
			$tipo_consumo;
			$categoria;

			$sql_pedido_detalle='';
			//$sql_porcion='';
			//$sq_carta_lista='';
			//$sql_almacen='';
			$sql_sub_total='';

			$numpedido='';
			$correlativo_dia='';
			$viene_de_bodega=0;// para pedido_detalle
			foreach ($xarr_pedido as $i_pedido) {
				if($i_pedido==null){continue;}
				//solo llevar
				$pos = strrpos(strtoupper($i_pedido['des']), "LLEVAR");

				//subitems
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
					$indicaciones_p="";
					$indicaciones_p=$subitem['indicaciones'];
					if($indicaciones_p!==""){$indicaciones_p=" (".$indicaciones_p.")";$indicaciones_p=strtolower($indicaciones_p);}
					$sql_pedido_detalle=$sql_pedido_detalle.'(?,'.$tipo_consumo.','.$categoria.','.$subitem['iditem'].','.$subitem['iditem2'].','.$subitem['idseccion'].',"'.$subitem['cantidad'].'","'.$subitem['cantidad'].'","'.$subitem['precio'].'","'.$precio_total.'","'.$precio_total.'","'.$subitem['des'].$indicaciones_p.'",'.$viene_de_bodega.','.$tabla_procede.'),';

				}

				$count_arr++;
			}
			if($count_items==0){return false;}//si esta vacio

			if($item_antes_solo_llevar>1){$solo_llevar=0;}

			//armar sql pedido_subtotales con arrTotales
			for ($z=0; $z < count($xarr_sub_totales); $z++) {
				$sql_sub_total=$sql_sub_total.'(?,"'.$xarr_sub_totales[$z]['descripcion'].'","'.$xarr_sub_totales[$z]['importe'].'"),';
			}

			//guarda primero pedido para obtener el idpedio
			if(!isset($_POST['estado_p'])){$estado_p=0;}else{$estado_p=$_POST['estado_p'];}//para el caso de venta rapida si ya pago no figura en control de pedidos
			if(!isset($_POST['idpedido'])){$id_pedido=0;}else{$id_pedido=$_POST['idpedido'];}//si se agrea en un pedido / para control de pedidos al agregar
			if($id_pedido==0){
					//num pedido
				$sql="select count(idpedido) as d1 from pedido where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'];
				$numpedido=$bd->xDevolverUnDato($sql);
				$numpedido++;

				//numcorrelativo segun fecha
				$sql="SELECT count(fecha) AS d1 FROM pedido WHERE (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and STR_TO_DATE(fecha,'%d/%m/%Y')=curdate()";
				$correlativo_dia=$bd->xDevolverUnDato($sql);
				$correlativo_dia++;

				$sql="insert into pedido (idorg, idsede,fecha,hora,fecha_hora,nummesa,numpedido,correlativo_dia,referencia,total,total_r,solo_llevar,idtipo_consumo,idcategoria,reserva,idusuario,estado)values(".$_SESSION['ido'].",".$_SESSION['idsede'].",DATE_FORMAT(now(),'%d/%m/%Y'),DATE_FORMAT(now(),'%H:%i:%s'),now(),'".$xarr['mesa']."','".$numpedido."','".$correlativo_dia."','".$xarr['referencia']."','".$xarr['ImporteTotal']."','".$xarr['ImporteTotal']."',".$solo_llevar.",".$tipo_consumo.",".$xarr['idcategoria'].",".$xarr['reservar'].",".$_SESSION['idusuario'].",".$estado_p.")";
				$id_pedido=$bd->xConsulta_UltimoId($sql);
				//print $sql;
			}else{
				//actualiza monto
				$sql="update pedido set total=FORMAT(total+".$xarr['ImporteTotal'].",2) where idpedido=".$id_pedido;
				$bd->xConsulta_NoReturn($sql);
			}
			//armar sql completos
			//remplazar ? por idpedido
			$sql_sub_total = str_replace("?", $id_pedido, $sql_sub_total);
			$sql_pedido_detalle = str_replace("?", $id_pedido, $sql_pedido_detalle);

			//saca el ultimo caracter ','
			$sql_sub_total=substr ($sql_sub_total, 0, -1);
			$sql_pedido_detalle=substr ($sql_pedido_detalle, 0, -1);

			//pedido_detalle
			$sql_pedido_detalle='insert into pedido_detalle (idpedido,idtipo_consumo,idcategoria,idcarta_lista,iditem,idseccion,cantidad,cantidad_r,punitario,ptotal,ptotal_r,descripcion,procede,procede_tabla) values '.$sql_pedido_detalle;
			//pedido_subtotales
			$sql_sub_total='insert into pedido_subtotales (idpedido,descripcion,importe) values '.$sql_sub_total;

			//ejecutar
			//$sql_ejecuta=$sql_pedido_detalle.'; '.$sql_sub_total.';'; //.$sql_porcion.$sq_carta_lista.$sql_almacen;
			//$bd->xMultiConsultaNoReturn($sql_ejecuta);

			
			$bd->xConsulta_NoReturn($sql_pedido_detalle.';');
            $bd->xConsulta_NoReturn($sql_sub_total.';');
			//print $sql_porcion.' || '.$sq_carta_lista.' || '.$sql_almacen;
			print $id_pedido.'|'.$numpedido.'|'.$correlativo_dia;
			break;
		case 3041://add pedido desde control de pedido
			$sql="update pedido set total=FORMAT(total+".$_POST['t'].",2) where idpedido=".$_POST['i']."; ".$_POST['xsql'];
			$bd->xMultiConsulta($sql);
			break;
		case 3042://aumentar stock item anulado // anula de 1 en 1
			// no aumenta porque borramos el procedimiento almacenado
			// 19102018 -- estara en la pagina pedido borrados - para restablecer o no stock
			$arrIE=$_POST['xarr'];

			$tabla_procede=$arrIE['procede'];
			$idpedido_detalle=$arrIE['idpedido_detalle'];
			$idpedido=$arrIE['idpedido'];
			// $iditem=$arrIE['idprocede'];
			$iditem=$arrIE['iditem'];
			$idcarta_lista=$arrIE['idcarta_lista'];
			$precio_total_item=$arrIE['precio_total'];
			$precio_unitario_item=$arrIE['precio_unitario'];

			$sql_pedido='';
			$sql_pedido_detalle='';

			//registra pedido borrado
			$sqlpedido_borrado="insert into pedido_borrados (idpedido,idpedido_detalle,iditem,idcarta_lista,idusuario,idusuario_permiso,importe,fecha,hora,procede_tabla) values(".$idpedido.",".$idpedido_detalle.",".$iditem.",".$idcarta_lista.",".$_POST["u"].",".$_SESSION['idusuario'].",".$precio_unitario_item.",DATE_FORMAT(now(),'%d/%m/%Y'),DATE_FORMAT(now(),'%H:%i:%s'),".$tabla_procede."); ";

			//descuenta en pedido_detalle
			$campo_precio='';
			if($precio_total_item>0){//si es cero solo descuenta en pedido detalle
				$campo_precio=', ptotal=format(ptotal-punitario,2)';
				//descuneta importe en pedido
				$sql_pedido="update pedido set total=format(total-".$precio_unitario_item.",2),estado=if(total<=0,3,0) where idpedido=".$idpedido."; update pedido_subtotales set importe=format(importe-".$precio_unitario_item.",2) where idpedido=".$idpedido." and descripcion='TOTAL'; ";
				//descuenta en subtotal
			}
			$sql_pedido_detalle='update pedido_detalle set cantidad=cantidad-1, ptotal=format(ptotal-'.$precio_unitario_item.',2), estado=if(cantidad<=0,1,0), modificado=1 where idpedido_detalle='.$idpedido_detalle.'; ';

			//descuenta
			//ejecutar
			//$sql_ejecuta=$sql_pedido.$sql_pedido_detalle.$sql_porcion.$sq_carta_lista.$sql_almacen;
			$sql_ejecuta=$sql_pedido.$sql_pedido_detalle.$sqlpedido_borrado; //.$sql_porcion.$sq_carta_lista.$sql_almacen;
			//print $sql_ejecuta;
			$bd->xMultiConsulta($sql_ejecuta);

			break;
		case 305:	//load pedido desde mi pedido
			$sql="
			SELECT p.idpedido, pd.idpedido_detalle, p.referencia,pd.idtipo_consumo, tp.descripcion AS des_tp, pd.idseccion,s.descripcion AS des_seccion, pd.iditem, pd.cantidad, pd.punitario, pd.ptotal, pd.descripcion
			FROM pedido AS p
				INNER JOIN pedido_detalle AS pd using(idpedido)
				INNER JOIN seccion AS s using(idseccion)
				INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=p.idtipo_consumo
			WHERE p.nummesa='".$_POST['i']."' and (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") AND p.estado IN (0)
			ORDER BY pd.idtipo_consumo,pd.idseccion, pd.idpedido_detalle
			";
			$bd->xConsulta($sql);
			break;
		case 3051:	//load pedido control de pedidos /load pedido desde mi pedido
			$nummesa=$_POST['m'];
			$numpedido=$_POST['p'];
			
			$sql="CALL procedure_bus_pedido_bd_3051(".$nummesa.",'".$numpedido."',".$g_ido.",".$g_idsede.");";
			
			// $condicion='p.nummesa='.$nummesa;
			// if($nummesa==0){
			// 	$condicion='p.numpedido='.$numpedido;
			// }

			// //idpedido_detalle_r, cantidad_r, total_r // es para control_mesas, para datos de pago
			// $sql="
			// SELECT * FROM(
			// 	SELECT DISTINCT p.idpedido, pd.idpedido_detalle,pd.idcarta_lista,pd.idcategoria, p.referencia,pd.idtipo_consumo, tp.descripcion AS des_tp, pd.idseccion,concat('1',s.sec_orden,'.',s.idseccion) AS idseccion_index,s.descripcion AS des_seccion, pd.iditem, pd.cantidad, pd.punitario, pd.ptotal, pd.descripcion, 0 as visible, pd.procede, '0' AS procede_index , IF(cl.cantidad='SP',2,1) AS descontar_en, IF(cl.cantidad='SP',ii.idporcion,pd.idcarta_lista)AS iddescontar, IF(cl.cantidad='SP',ii.cant_porcion,pd.cantidad) AS cant_descontar, pd.cantidad AS cantidad_r, ptotal AS total_r,concat(pd.idpedido_detalle,'|',pd.idpedido) AS idpedido_detalle_r, p.subtotales_tachados
			// 	FROM pedido AS p
			// 		INNER JOIN pedido_detalle AS pd using(idpedido)
			// 		INNER JOIN seccion AS s using(idseccion)
			// 		INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
			// 		INNER JOIN carta_lista AS cl using(idcarta_lista)
			// 		LEFT JOIN (SELECT iditem, GROUP_CONCAT(idporcion) AS idporcion,GROUP_CONCAT(cantidad) AS cant_porcion FROM item_ingrediente WHERE idporcion!=0 GROUP BY iditem) AS ii ON pd.iditem=ii.iditem
			// 	WHERE ".$condicion." and (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") AND p.estado IN (0) AND pd.pagado=0 AND pd.estado=0
			// 	ORDER BY pd.idtipo_consumo,s.sec_orden, pd.descripcion
			// ) a
			// UNION all
			// 	SELECT * FROM(
			// 	SELECT DISTINCT p.idpedido, pd.idpedido_detalle,pd.idcarta_lista,pd.idcategoria, p.referencia,pd.idtipo_consumo, tp.descripcion AS des_tp, pd.idseccion, concat('2',pd.idseccion,'.0') AS idseccion_index, pf.descripcion AS des_desccion, pd.iditem, pd.cantidad, pd.punitario, pd.ptotal, pd.descripcion, 0 as visible, pd.procede , pf.idproducto_familia AS procede_index, 0 AS descontar_en,pd.iditem AS iddescontar, pd.cantidad AS cant_descontar, pd.cantidad AS cantidad_r, ptotal AS total_r,concat(pd.idpedido_detalle,'|',pd.idpedido) AS idpedido_detalle_r, p.subtotales_tachados
			// 	FROM pedido AS p
			// 		INNER JOIN pedido_detalle AS pd using(idpedido)
			// 		INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
			// 		JOIN (SELECT idproducto_familia,descripcion FROM producto_familia WHERE idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AS pf ON pd.idseccion=pf.idproducto_familia
			// 	WHERE ".$condicion." and (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") AND pd.procede_tabla=0 AND p.estado IN (0) AND pd.pagado=0 AND pd.estado=0
			// 	ORDER BY pd.idtipo_consumo,pd.idseccion, pd.descripcion) b
			// ";

			
			/*$sql="
			SELECT p.idpedido, pd.idpedido_detalle,pd.idcarta_lista,pd.idcategoria, p.referencia,pd.idtipo_consumo, tp.descripcion AS des_tp, pd.idseccion,s.descripcion AS des_seccion, pd.iditem, pd.cantidad, pd.punitario, pd.ptotal, pd.descripcion, 0 as visible
			FROM pedido AS p
				INNER JOIN pedido_detalle AS pd using(idpedido)
				INNER JOIN seccion AS s using(idseccion)
				INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
			WHERE ".$condicion." and (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") AND p.estado IN (0,1) AND pd.pagado=0
			ORDER BY pd.idtipo_consumo,pd.idseccion, pd.idpedido_detalle
			";*/
			$bd->xConsulta($sql);
			break;
		case 306://reglas de la carta
			/*$sql="
			SELECT r.idregla_carta,r.idseccion,rd.idseccion AS idseccion_detalle
			FROM regla_carta AS r INNER JOIN regla_carta_detalle AS rd using(idregla_carta)
			WHERE (r.idorg=".$_SESSION['ido']." AND r.idsede=".$_SESSION['idsede'].") AND (r.estado=0 AND rd.estado=0)
			";*/
			$sql="
			SELECT idregla_carta, idseccion, idseccion_detalle
			FROM regla_carta
			WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND (idcategoria='".$_POST['i']."' and estado=0)
			";
			$bd->xConsulta($sql);
			break;
		case 307://load datos print //encabezado, logo slogan agradecimiento
			$sql="
			SELECT cp.ip_print, cp.num_copias, cp.pie_pagina, cp.logo, IFNULL(cp_d.descripcion,'') AS des_detalle, IFNULL(cp_d.porcentaje,'') AS porcentaje, s.nombre AS des_sede, s.eslogan, IFNULL(cp_a.descripcion,'') as ad_descripcion, IFNULL(cp_a.idtipo_consumo,'') as ad_idtp_consumo,IFNULL(cp_a.idseccion,'') as ad_idseccion, IFNULL(cp_a.importe,'') as ad_importe, s.mesas
			FROM conf_print AS cp
				LEFT JOIN (select idconf_print,descripcion,porcentaje from conf_print_detalle where estado=0) as cp_d ON cp.idconf_print=cp_d.idconf_print
			        LEFT JOIN (select idconf_print,idtipo_consumo,group_concat(idseccion) as idseccion,descripcion,importe from conf_print_adicionales where estado=0 group by descripcion,idtipo_consumo)  as cp_a ON cp.idconf_print=cp_a.idconf_print
				LEFT JOIN sede AS s ON s.idorg=cp.idorg AND s.idsede=cp.idsede
			WHERE (cp.idorg=".$_SESSION['ido']." AND cp.idsede=".$_SESSION['idsede'].")
			";
			$bd->xConsulta($sql);
			break;
		case 308://otros datos de la sede
			$sql="select maximo_pedidos_x_hora from sede where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['ido'];
			$bd->xConsulta($sql);
			break;
		case 309://cambiar maximo pedidos x hora
			$sql="update sede set maximo_pedidos_x_hora=".$_POST['p']." where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['ido'];
			$bd->xConsulta($sql);
			break;
		/////////////
		//regla
		case 4://load reglas
			$sql="
			SELECT r.idregla_carta,c.descripcion AS des_categoria,s.descripcion AS des_seccion,s2.descripcion AS des_con
			FROM regla_carta AS r
				INNER JOIN categoria AS c using(idcategoria)
				INNER JOIN seccion AS s using(idseccion)
				INNER JOIN seccion AS s2 ON r.idseccion_detalle=s2.idseccion
			WHERE (r.idorg=".$_SESSION['ido']." AND r.idsede=".$_SESSION['idsede'].") AND r.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 401://conf print
			$sql="SELECT * FROM conf_print WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			$bd->xConsulta($sql);
			break;
		case 402://load usuarios
			$sql="SELECT * FROM usuario WHERE idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede']." AND estado=0 and super=0";
			$bd->xConsulta($sql);
			break;
		case 403://load usuarios modificar
			$sql="SELECT * FROM usuario WHERE idusuario=".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 4031://resetera clave
			$sql="update usuario set pass='123456', nuevo=0 WHERE idusuario=".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 404:// organizacion
			$sql="
				SELECT o.*, s.idsede, s.nombre AS nom_sede, s.direccion as dir_sede, s.ciudad,s.eslogan,s.mesas, s.ubigeo, s.codigo_del_domicilio_fiscal
				FROM org AS o
					INNER JOIN sede AS s using(idorg)
				WHERE (o.idorg=".$_SESSION['ido'].") and s.estado=0
				";
			$bd->xConsulta($sql);
			break;
		case 4041://generar token
			// $sql="select * from org where idorg=".$_SESSION['ido'];
			// $org = $bd->xConsulta3($sql);

			// $sql="select * from sede where idsede=".$_SESSION['idsede'];
			// $sede = $bd->xConsulta3($sql);
				
			
			$xTk = new xAuth();
			$dataTK = $xTk->getData();
			// $dataTK = $xTk->Asign($dataTK);

			print $dataTK;
			//$sql="update sede set token='".$dataTK."' where idsede=".$_SESSION['idsede'];
			//print $bd->xConsultaSucess($sql);
			break;
		case 405://categoria
			$sql="SELECT * FROM categoria WHERE idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede']." AND estado=0";
			$bd->xConsulta($sql);
			break;
		case 406://load tipos de consumo
			$sql="select * from tipo_consumo where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede']." and estado=0";
			$bd->xConsulta($sql);
			break;
		case 407://load conf print detalle
			// $sql="select * from conf_print_detalle where idconf_print=".$_POST['i']." and estado=0";
			$sql="select * from conf_print_detalle where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede']." and estado=0";
			$bd->xConsulta($sql);
			break;
		case 408://load conf print adicionales
			$sql="select cpa.*, tp.descripcion as des_tipo_consumo,s.descripcion as des_seccion from conf_print_adicionales as cpa inner join tipo_consumo as tp using(idtipo_consumo) inner join seccion as s on cpa.idseccion=s.idseccion where cpa.idconf_print=".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 409:// load secciones con impresoras
			$sql="
				SELECT s.idseccion, s.descripcion AS plato,ifnull(i.idimpresora,0)AS idimpresora, ifnull(i.descripcion,'Ninguno') as descripcion FROM seccion AS s
				left JOIN impresora AS i using(idimpresora)
				WHERE (s.idorg=".$_SESSION['ido']." AND s.idsede=".$_SESSION['idsede'].")
				ORDER BY s.idseccion
				";
			$bd->xConsulta($sql);
			break;
		case 40901:// load secciones con impresoras BODEGA
			$sql="
				SELECT pf.idproducto_familia as idseccion,pf.descripcion AS plato,pf.idimpresora,ifnull(i.descripcion,'Ninguno') as descripcion
				FROM producto_stock AS ps
					INNER JOIN producto AS p using(idproducto)
					INNER JOIN producto_familia AS pf using(idproducto_familia)
					INNER JOIN almacen AS a using(idalmacen)
					LEFT JOIN impresora AS i on pf.idimpresora=i.idimpresora
				WHERE (pf.idorg=".$_SESSION['ido']." AND pf.idsede=".$_SESSION['idsede'].") AND a.bodega=1 AND a.imprimir_comanda=1
				GROUP BY pf.idproducto_familia
				";
			$bd->xConsulta($sql);
			break;
		case 4010://guardar seccion impresora
			$sql="update seccion set idimpresora=".$_POST['idp']." where idseccion=".$_POST['ids'];
			$bd->xConsulta($sql);
			break;
		case 40101://guardar seccion impresora bodega
			$sql="update producto_familia set idimpresora=".$_POST['idp']." where idproducto_familia='".$_POST['ids']."'";
			$bd->xConsulta($sql);
			break;
		case 40102://guardar papel_size de impresora
			$sql="update impresora set papel_size=".$_POST['papel']." where idimpresora=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 4011://load config otros documentos
			// $sql="SELECT s.conf_print_otros, s.idtipo_otro, ifnull(i.idimpresora,0)AS idimpresora,ifnull(i.descripcion,'Ninguno') as descripcion FROM conf_print_otros AS s left JOIN impresora AS i using(idimpresora) WHERE (s.idorg=".$_SESSION['ido']." AND s.idsede=".$_SESSION['idsede'].") AND s.idtipo_otro<0";
			$sql="
				SELECT ifnull(conf.conf_print_otros,0) as conf_print_otros, tpo.idtipo_otro, tpo.descripcion as nomdoc, ifnull(conf.idimpresora,0)AS idimpresora,ifnull(conf.descripcion,'Ninguno') as descripcion
				from tipo_otro as tpo
					left join (
						SELECT cpo.conf_print_otros, cpo.idtipo_otro, i.idimpresora, i.descripcion
						from conf_print_otros as cpo
							left join impresora as i on cpo.idimpresora=i.idimpresora
						where (cpo.idorg=".$_SESSION['ido']." and cpo.idsede=".$_SESSION['idsede'].") and cpo.estado=0
					) as conf on conf.idtipo_otro = tpo.idtipo_otro
				ORDER BY tpo.idtipo_otro DESC
			";
			$bd->xConsulta($sql);
			break;
		case 4012://guardar config otros documentos
			$id_conf_otro_doc=$_POST['ids'];
			$id_doc=$_POST['iddoc'];			
			$id_print=$_POST['idp'];
			if($id_conf_otro_doc=='0'){//nuevo
				$sql="insert into conf_print_otros(idorg,idsede,idtipo_otro,idimpresora) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$id_doc.",".$id_print.")";
			}else{
				$sql="update conf_print_otros set idimpresora=".$id_print." where conf_print_otros=".$id_conf_otro_doc;
			}
			$bd->xConsulta($sql);
			break;
		///control de mesas
		///control de mesas
		case 5://load mesas
			$sql="select mesas as d1 from sede where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede']." and estado=0";
			print $bd->xDevolverUnDato($sql);
			break;
		case 501:// pedidos
			// $sql="
			// 	SELECT p.idpedido,p.idcategoria, count(p.nummesa) AS num_pedidos ,p.idtipo_consumo,p.reserva,p.nummesa, p.numpedido,p.correlativo_dia, p.referencia, sum(p.total) AS importe, p.hora
			// 	FROM pedido AS p
			// 	WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and p.estado IN (0) and p.nummesa>0
			// 	GROUP BY p.nummesa
			// 	UNION ALL
			// 	SELECT p.idpedido,p.idcategoria, count(p.nummesa) AS num_pedidos ,p.idtipo_consumo,p.reserva,p.nummesa, p.numpedido,p.correlativo_dia, p.referencia, sum(p.total) AS importe, p.hora
			// 	FROM pedido AS p
			// 	WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and p.estado IN (0) and p.nummesa=0
			// 	GROUP BY p.idpedido
			// ";
			// $sql = "
			// 	SELECT p.idpedido,p.idcategoria, count(p.nummesa) AS num_pedidos ,p.idtipo_consumo, p.subtotales_tachados,
			// 		GROUP_CONCAT(DISTINCT concat(pd.idtipo_consumo,':', pd.idseccion,':', pd.cantidad)) as secciones,p.reserva,p.nummesa, p.numpedido,p.correlativo_dia, p.referencia, sum(pd.ptotal) AS importe, p.hora,
			// 		if (POSITION('0' in GROUP_CONCAT(p.despachado))=0,1,0) as despachado, tpc.titulo as tipo_consumo_titulo
			// 		FROM pedido AS p
			// 			inner join tipo_consumo as tpc on p.idtipo_consumo = tpc.idtipo_consumo
			// 			inner join (
			// 						SELECT pdt.idpedido, pdt.idtipo_consumo, pdt.idseccion, sum(pdt.cantidad) as cantidad, sum(pdt.ptotal) as ptotal
			// 						from pedido_detalle as pdt where pdt.pagado=0 and pdt.estado=0 
			// 						GROUP BY pdt.idpedido, pdt.idtipo_consumo, pdt.idseccion order by pdt.idpedido desc
			// 						) as pd on p.idpedido=pd.idpedido
			// 	WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and (p.estado=0) and p.nummesa>0
			// 	GROUP BY p.nummesa
			// 	UNION ALL
			// 	SELECT p.idpedido,p.idcategoria, count(p.nummesa) AS num_pedidos ,p.idtipo_consumo, p.subtotales_tachados,
			// 		GROUP_CONCAT(DISTINCT concat(pd.idtipo_consumo,':', pd.idseccion,':', pd.cantidad)) as secciones,p.reserva,p.nummesa, p.numpedido,p.correlativo_dia, p.referencia, sum(pd.ptotal) AS importe, p.hora,
			// 		if (POSITION('0' in GROUP_CONCAT(p.despachado))=0,1,0) as despachado, tpc.titulo as tipo_consumo_titulo
			// 		FROM pedido AS p
			// 			inner join tipo_consumo as tpc on p.idtipo_consumo = tpc.idtipo_consumo
			// 			inner join (
			// 						SELECT pdt.idpedido, pdt.idtipo_consumo, pdt.idseccion, sum(pdt.cantidad) as cantidad, sum(pdt.ptotal) as ptotal
			// 						from pedido_detalle as pdt where pdt.pagado=0 and pdt.estado=0 
			// 						GROUP BY pdt.idpedido, pdt.idtipo_consumo, pdt.idseccion order by pdt.idpedido desc
			// 						) as pd on p.idpedido=pd.idpedido
			// 	WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and (p.estado=0) and p.nummesa=0
			// 	GROUP BY p.idpedido
			// ";

			$sql="CALL procedure_refresh_mesas_501(".$g_ido.",".$g_idsede.");";
			$bd->xConsulta($sql);
			break;
		case 50101://count cantidad de pedidos para actualizar
			$sql="SELECT count(idpedido) as d1 FROM pedido WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado IN (0,1)";
			print $bd->xDevolverUnDato($sql);
			break;
		case 502://detalle pedido
			$sql="
				SELECT p.idpedido, pd.idpedido_detalle, p.referencia,pd.idtipo_consumo, tp.descripcion AS des_tp, pd.idseccion,s.descripcion AS des_seccion, pd.iditem, pd.cantidad, pd.punitario, pd.ptotal, pd.descripcion
				FROM pedido AS p
				    INNER JOIN pedido_detalle AS pd using(idpedido)
					INNER JOIN seccion AS s using(idseccion)
					INNER JOIN tipo_consumo AS tp using(idtipo_consumo)
				WHERE (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") and p.nummesa=".$_POST['i']." AND (p.estado=0 OR p.estado=1)
				ORDER BY pd.idtipo_consumo,pd.idseccion, pd.idpedido_detalle
			";
			$bd->xConsulta($sql);
			break;
		case 503:// registrar borrar item
			//procednecia 0:
			$sql="insert into pedido_borrados (idpedido_detalle,idusuario,idusuario_permiso,fecha,hora) values(".$_POST["i"].",".$_POST["u"].",".$_SESSION['idusuario'].",DATE_FORMAT(now(),'%d/%m/%Y'),DATE_FORMAT(now(),'%H:%i:%s'))";
			$bd->xMultiConsulta($sql);
			break;
		case 504:// registrar cambiar plato
			$sql="insert into pedido_cambios (idpedido_detalle,idusuario,idusuario_permiso,fecha,hora) values(".$_POST["i"].",".$_POST["u"].",".$_SESSION['idusuario'].",DATE_FORMAT(now(),'%d/%m/%Y'),DATE_FORMAT(now(),'%H:%i:%s')); update pedido_detalle set secambio=secambio+1 where idpedido_detalle=".$_POST["i"];
			$bd->xMultiConsulta($sql);
			break;
		case 505:// modificar importe en bd de pedido
			$cant=$_POST["c"];
			$icl_de=$_POST["icl_de"];
			$icl_a=$_POST["icl_a"];
			$iddescontar=$_POST["iddescontar"];//id segun tabla descontar
			$tabla_descontar=$_POST["tabladescontar"];//tabla descontar /carta_lista/porcion/almacen_items
			$campo_descontar='stock=stock+1';
			if($tabla_descontar===1){$campo_descontar="cantidad=if(cantidad='ND','ND',cantidad+1)";}

			//elimina de uno en uno //aumenta stock
			$sql_change_de="update ".$tabla_descontar." set ".$campo_descontar." where id".$tabla_descontar."=".$iddescontar."; ";

			if($icl_a!=''){//si cambia
				$sql_change_de=" update carta_lista set cantidad=cantidad+".$cant." where idcarta_lista=".$icl_de."; ";
				$sql_change_a=" update carta_lista set cantidad=cantidad-".$cant." where idcarta_lista=".$icl_a."; ";
			}else{$sql_change_a="";}


			$sql_cant="update pedido_detalle set cantidad=cantidad-'".$cant."', estado=if((cantidad)<=1,1,0), ptotal='".$_POST["mpd"]."' where idpedido_detalle=".$_POST["ipd"]."; ";
			$sql="update pedido set total='".$_POST["m"]."' where idpedido=".$_POST["i"]."; update pedido_subtotales set importe='".$_POST["m"]."' where idpedido=".$_POST["i"]." and descripcion='TOTAL'; ";
			$bd->xMultiConsulta($sql.$sql_cant.$sql_change_a.$sql_change_de);
			break;
		case 506://listar items ultima carta para cambiar
			$sql="
				select cl.idcarta_lista,cl.idseccion, i.iditem,concat(s.descripcion,' | ', i.descripcion) as des_item, cl.precio, cl.cantidad
				from carta as c
					inner join carta_lista as cl using (idcarta)
				    inner join seccion as s using(idseccion)
					inner join item as i using(iditem)
				        join (select max(idcarta) as idcarta, idorg,idsede from carta) as uc on (uc.idorg=c.idorg and uc.idsede=c.idsede)
				where (c.idorg=".$_SESSION['ido']." and c.idsede=".$_SESSION['idsede'].") and (cl.cantidad>0 or cl.cantidad='ND') and c.idcarta=uc.idcarta and cl.estado=0
				order by s.sec_orden, i.descripcion
			";
			$bd->xConsulta($sql);
			break;
		case 507:// load detalle del pedido
			$nummesa=$_POST['m'];
			$numpedido=$_POST['p'];

			$condicion='p.nummesa='.$nummesa;
			if($nummesa==0){
				$condicion='p.numpedido='.$numpedido;
			}

			$sql="
				SELECT p.idpedido,p.nummesa,p.numpedido,p.correlativo_dia,p.reserva, p.idtipo_consumo,
					SUBSTRING_INDEX(u.nombres, ' ', 1) AS nom_usuario,p.referencia, p.subtotales_tachados,
					concat('P',LPAD(p.correlativo_dia,5,'0')) AS des_pedido,TIMESTAMPDIFF(MINUTE , STR_TO_DATE(concat(p.fecha,' ',p.hora),'%d/%m/%Y %H:%i:%s'), CURRENT_TIMESTAMP() ) AS min_transcurridos
					, p.tiempo_atencion, p.val_color_despachado, p.total
				FROM pedido AS p
					INNER JOIN usuario AS u using(idusuario)
				WHERE ".$condicion." and (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") AND p.estado IN(0,1)
				";

			$bd->xConsulta($sql);
			break;
		case 508://registrar cliente
			$nomclie=$_POST['nom'];
			$idclie=$_POST['idclie'];
			$idpedidos=$_POST['i'];

			if($idclie==''){
				if($nomclie==''){//publico general
					$idclie=0;
				}else{
					$sql="insert into cliente (idorg,nombres)values(".$_SESSION['ido'].",'".$nomclie."')";
					$idclie=$bd->xConsulta_UltimoId($sql);
				}
			}

			$sql="update pedido set idcliente=".$idclie." where idpedido in (".$idpedidos.")";
			$bd->xConsulta_NoReturn($sql);
			print $idclie;
			break;
		case 5081://registrar pago solo items seleccionados
			$array_items=$_POST['xArrayItems'];
			$sql_pago_pedido='';

			//guardar pagos
			$ArrayTP=$_POST['ArrayPago']; //tipos de pago // contado credito tarjeta
			$idc=$_POST['idclie'];
			$tt=$_POST['tt']; // total que esta pagando
			$idtipo_consumo=$_POST['idtipo_consumo'];
			//$idpedidos=$_POST['idp'];

			//registro unico de pago
			$sqlPago="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$tt."',".$idtipo_consumo.")";
			$idregistro_pago=$bd->xConsulta_UltimoId($sqlPago);

			//registra los tipos de pago // efectivo tarjeta
			$cadena_tp='';
			foreach($ArrayTP as $itemP){
				$cadena_tp=$cadena_tp."(".$idregistro_pago.",".$itemP['id'].",'".$itemP['importe']."'),";
			}
			$cadena_tp=substr($cadena_tp,0,-1);
			$cadena_tp="insert into registro_pago_detalle (idregistro_pago,idtipo_pago,importe) values ".$cadena_tp."; ";

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
				}else{
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

			//registro_pago_pedido
			$sql_pago_pedido=substr($sql_pago_pedido,0,-1);
			$sql_pago_pedido='insert into registro_pago_pedido(idregistro_pago,idpedido,idpedido_detalle,cantidad,total) values '.$sql_pago_pedido.'; ';
			//print $sql_pago_pedido;

			$bd->xMultiConsulta($sql_pago_pedido.$cadena_tp);
			break;
		case 509://registrar pago // completo
			$ArrayTP=$_POST['ArrayPago'];
			$idc=$_POST['idclie'];
			$tt=$_POST['tt'];
			$idpedidos=$_POST['idp'];
			$array_items=$_POST['xArrayItems'];
			$idtipo_consumo=$_POST['idtipo_consumo'];
			$sql_pago_pedido='';

			//guardar registro_pago
			$sqlrp="insert into registro_pago(idorg,idsede,idusuario,idcliente,fecha,total,idtipo_consumo) values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$idc.",DATE_FORMAT(now(),'%d/%m/%Y %H:%i:%s'),'".$tt."',".$idtipo_consumo.")";
			$idregistro_pago=$bd->xConsulta_UltimoId($sqlrp);

			//tipo de pago // efectivo / tarjeta / etc
			$cadena_tp='';
			foreach($ArrayTP as $item){
				$cadena_tp=$cadena_tp."(".$idregistro_pago.",".$item['id'].",'".$item['importe']."'),";
			}

			$cadena_tp=substr($cadena_tp,0,-1);
			$cadena_tp="insert into registro_pago_detalle (idregistro_pago,idtipo_pago,importe) values ".$cadena_tp."; ";

			//obtiene idpedido e idpedidodetalle
			$sql_pago_pedido='';
			if(is_array($array_items)){foreach ($array_items as $sub_item){
					$idp_d=$sub_item['idpedido_detalle'];
					$pos = strpos($idp_d, ',');
					if($pos===false){
						$idp_d=explode('|',$idp_d);
						$idp_d=$idp_d[0];
						$sql_pago_pedido=$sql_pago_pedido."(".$idregistro_pago.",".$sub_item['idpedido'].",".$idp_d.",'".$sub_item['cantidad']."','".$sub_item['total']."'),";
					}else{
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
			}
			else{ // viene de venta rapida
				//en venta rapida no tengo el idpedido_detalle.
				//obtner el idpedido_detalle
				$sql_idpd="select idpedido,idpedido_detalle, cantidad,ptotal from pedido_detalle where idpedido=".$idpedidos;
				$rows_pedido_detalle=$bd->xConsulta2($sql_idpd);
				$sql_pago_pedido='';
				foreach($rows_pedido_detalle as $fila){
					$sql_pago_pedido=$sql_pago_pedido."(".$idregistro_pago.",".$fila['idpedido'].",".$fila['idpedido_detalle'].",'".$fila['cantidad']."','".$fila['ptotal']."'),";
				}
				//$sql_pago_pedido=$sql_pago_pedido."(".$idregistro_pago.",".$idpedidos.",'',".$tt."),";
			}

			$sql_pago_pedido=substr($sql_pago_pedido,0,-1);
			
			if ($sql_pago_pedido == ''){
				// si no hay nada en (pedidodetalle) , significa que hubo un problema al grabar los detalles pero los datos de este pedido si se grabaron en (pedido)
				// hay un problema o error con los items detalle de pedidodetalle 
				// se procede a cambiar de estado al pedido
				// al cobiarlo no figurara en registro de pagos pero si sumara a caja
				$sql_pago_pedido='update pedido set total=total-'.$tt.', estado=if(total=0,2,estado),idregistro_pago=if(total=0,'.$idregistro_pago.',idregistro_pago) where idpedido in ('.$idpedidos.');';
			}else {
				$sql_pago_pedido= 'insert into registro_pago_pedido(idregistro_pago,idpedido,idpedido_detalle,cantidad,total) values '.$sql_pago_pedido.'; ';
			}

			//$bd->xConsulta($sql);
			//echo $sql_pago_pedido;
			echo $sql_pago_pedido;
			$bd->xMultiConsulta($sql_pago_pedido.$cadena_tp);
			break;
		case 5010://cambiar mesa
			$sql="update pedido set nummesa='".$_POST['n']."' where idpedido in (".$_POST['i'].")";
			$bd->xConsulta($sql);
			break;
		case 5011://anular pedidos
			//armar sql item anular devolver stock
			$xarray_pe_anular=$_POST['ArrayPeAnular'];
			$sql_change_de='';
			$sql_historial_rp='';
			$sqlpedido_borrado='';
			$sql_todos='';
			$sql_pdt='';
			$id_pedidos_anular='';
			$motivo_anular=$_POST['xMotivo'];

			//$count_filas_item=count($xarray_pe_anular);
			if($xarray_pe_anular==='' || $xarray_pe_anular == []){//anular todos los pedidos
				$id_pedidos_anular=$_POST['xPedidos'];
				$sql_todos="update pedido set estado=3, motivo_anular='".$motivo_anular."' where idpedido in (".$id_pedidos_anular."); ";
			}else{//si solo estan algunos pedidos seleccionados
				$id_pedidos_anular=$xarray_pe_anular[0]['idpedidos'];
				$motivo_anular=$xarray_pe_anular[0]['m_a'];

				$sql_pdt="update pedido_detalle set estado=1 where idpedido in (".$id_pedidos_anular."); update pedido set estado=3, motivo_anular='".$motivo_anular."' where idpedido in (".$id_pedidos_anular.");";
			}

			$condicion_pdb="idpedido IN (".$id_pedidos_anular.") and pagado=0";//si no viene de registro elimina todo los item que no esten pagados // desde control de pedido
			if(isset($_POST['viene_historial'])){
				$idregistro_pago_desde_h=$_POST['viene_historial'];
				//si viene de historial_registro_pago quiere decir que se eliminara una cobranza por lo tanto los datos lo guardara tambien en registro_pago
				$condicion_pdb="idregistro_pago=".$idregistro_pago_desde_h;//todos los item que correspondan a este iregistropago
				$sql_historial_rp="update registro_pago set estado=1, motivo_anular='".$motivo_anular."', idusuario_permiso=".$_POST['u']." where idregistro_pago=".$idregistro_pago_desde_h."; ";
			}

			//registrar en pedidos_borrados.// en este caso los sub item se borran de los pedidos seleccionados
			$sqlpedido_borrado="insert into pedido_borrados (idpedido,idpedido_detalle,iditem,idcarta_lista,idusuario,idusuario_permiso,importe,fecha,hora,procede_tabla) SELECT idpedido,idpedido_detalle,iditem,idcarta_lista,".$_SESSION["idusuario"].",".$_POST['u'].",IF(ptotal*1=0,ptotal_r,ptotal),DATE_FORMAT(now(),'%d/%m/%Y'),DATE_FORMAT(now(),'%H:%i:%s'), procede_tabla FROM pedido_detalle WHERE ".$condicion_pdb."; ";

			//print $sql_todos.$sql_change_de.$sql_pdt.$sqlpedido_borrado.$sql_historial_rp;
			$bd->xMultiConsulta($sql_todos.$sql_change_de.$sql_pdt.$sqlpedido_borrado.$sql_historial_rp);
			/////////
			break;
		///////////////
		///////////////

		// webcomponent pago
		// webcomponent pago
		case 6://tipos de pago
			$sql="SELECT * FROM tipo_pago WHERE estado=0";
			$bd->xConsulta($sql);
			break;
		case 601://load clientes
			$sql="SELECT * FROM cliente where (idorg=".$_SESSION['ido'].") AND estado=0 order by nombres";
			$bd->xConsulta($sql);
			break;
		case 602://buscar cliente		
			$sql="SELECT * FROM cliente where (idorg=".$_SESSION['ido'].") AND estado=0 and ruc='".$_POST['doc']."' order by nombres";
			$bd->xConsulta($sql);
			break;
		case 603://Load tipo comprobante		
			// $sql="SELECT * FROM tipo_comprobante where estado=0";
			$sql = "SELECT tps.idtipo_comprobante_serie, tps.idtipo_comprobante, tp.descripcion, tp.codsunat, tps.serie, tp.requiere_cliente, tp.inicial FROM tipo_comprobante_serie tps 
				inner join tipo_comprobante as tp using(idtipo_comprobante) 
				WHERE (tps.idorg=".$_SESSION['ido']." and tps.idsede=".$_SESSION['idsede'].") and tps.estado = 0";
			$bd->xConsulta($sql);
			break;
		///////////////
		///////////////

		//cierre de caja
		//cierre de caja
		case 7://resumen ventas
			$ido=$_SESSION['ido'];
			$idsede=$_SESSION['idsede'];
			$idus=$_SESSION['idusuario'];
			$sql="
				SELECT tp.descripcion AS descripcion,'' as t1, '' as t2, format(SUM(rpd.importe),2) AS t3
				FROM registro_pago AS rp
					INNER JOIN registro_pago_detalle AS rpd using(idregistro_pago)
					INNER JOIN tipo_pago AS tp using(idtipo_pago)
				WHERE (rp.idorg=".$ido." AND rp.idsede=".$idsede." AND rp.idusuario=".$idus.") AND (rp.estado=0 AND rpd.estado=0) AND rp.cierre=0
				GROUP BY rpd.idtipo_pago
				";
			/*$sql="
				SELECT tp.descripcion AS descripcion, SUM(rpd.importe) AS importe, ('+') AS operacion1, IF(tp.idtipo_pago=1,'+','') AS operacion2
				FROM registro_pago AS rp
					INNER JOIN registro_pago_detalle AS rpd using(idregistro_pago)
					INNER JOIN tipo_pago AS tp using(idtipo_pago)
				WHERE (rp.idorg=".$ido." AND rp.idsede=".$idsede." AND rp.idusuario=".$idus.") AND (rp.estado=0 AND rpd.estado=0) AND rp.cierre=0
				GROUP BY rpd.idtipo_pago
				UNION all
				SELECT IF(ie.tipo=1,'INGRESO','SALIDA') AS descripcion,sum(monto)  AS importe, ('')AS operacion1 , (IF(ie.tipo=1,'+','-')) AS operacion2
				FROM ie_caja AS ie
				WHERE (ie.idorg=".$ido." AND ie.idsede=".$idsede." AND ie.idusuario=".$idus.") AND ie.estado=0 AND ie.cierre=0
				GROUP BY ie.tipo
				";*/
			$bd->xConsulta($sql);
			break;
		case 7001://verificar caja
			$ido=$_SESSION['ido'];
			$idsede=$_SESSION['idsede'];
			$idus=$_SESSION['idusuario'];
			$sql="
				SELECT SUM(rpd.importe) as d1
				FROM registro_pago AS rp
					INNER JOIN registro_pago_detalle AS rpd using(idregistro_pago)
					INNER JOIN tipo_pago AS tp using(idtipo_pago)
				WHERE (rp.idorg=".$ido." AND rp.idsede=".$idsede." AND rp.idusuario=".$idus.") AND (rp.estado=0 AND rpd.estado=0) AND rp.cierre=0 AND tp.idtipo_pago=1
				GROUP BY rpd.idtipo_pago
			";
			$ef1=$bd->xDevolverUnDato($sql);

			$sql="
				SELECT sum(monto) AS d1
				FROM ie_caja AS ie
				WHERE (ie.idorg=".$ido." AND ie.idsede=".$idsede." AND ie.idusuario=".$idus.") AND ie.estado=0 AND ie.cierre=0 AND ie.tipo=1
			";
			$val_ingreso=$bd->xDevolverUnDato($sql);

			$sql="
				SELECT sum(monto) AS d1
				FROM ie_caja AS ie
				WHERE (ie.idorg=".$ido." AND ie.idsede=".$idsede." AND ie.idusuario=".$idus.") AND ie.estado=0 AND ie.cierre=0 AND ie.tipo=2
			";
			$val_salida=$bd->xDevolverUnDato($sql);

			$rpt=($ef1+$val_ingreso)-$val_salida;
			print $rpt;
			break;
		case 70012://cerrar seccion = cierre=1 pedido, registro_pago, ie_caja //el ultimo es para cerrar pedidos anulados
			//UPDATE pedido AS p INNER JOIN registro_pago AS rp ON p.idregistro_pago=rp.idregistro_pago SET p.cierre=1 WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and (p.cierre=0 AND rp.idusuario=".$_POST['i'].");
			$sql="
				UPDATE ie_caja SET cierre=1 WHERE idusuario=".$_POST['i']." AND cierre=0 and (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].");
				UPDATE registro_pago SET cierre=1, fecha_cierre=DATE_FORMAT(now(),'%H:%i:%s') WHERE idusuario=".$_POST['i']." and cierre=0 and (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].");
				UPDATE pedido AS p INNER JOIN registro_pago AS rp ON p.idregistro_pago=rp.idregistro_pago SET p.cierre=1 WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND rp.idusuario=".$_SESSION['idusuario']." AND p.cierre=0 ;
				UPDATE pedido SET cierre=1 WHERE estado=3 AND (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].");
				update pedido_borrados set fecha_cierre=now() where fecha_cierre='' and idusuario=".$_SESSION['idusuario'].";
				DELETE FROM print_server_detalle WHERE (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and (impreso=1 or estado=1 or error=1);
			";
			//update pedido_borrados set cierre=1 where idusuario=".$_POST['i']." and cierre=0 and (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].");
			//UPDATE pedido AS p SET p.cierre=1 WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and (p.cierre=0 AND p.idusuario=".$_POST['i'].");
			//UPDATE pedido SET cierre=1 WHERE estado=3 AND (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].");
			$bd->xMultiConsulta($sql);
			break;
		case 701:// detalle ie caja ingreso
			$sql="
				SELECT ie.motivo AS descripcion ,'' as t1, '' as t2, format(ie.monto,2) as t3
				FROM ie_caja AS ie WHERE (ie.idorg=".$_SESSION['ido']." AND ie.idsede=".$_SESSION['idsede']." AND ie.idusuario=".$_SESSION['idusuario'].") AND ie.estado=0 AND ie.cierre=0 and ie.tipo=1
				ORDER BY ie.tipo
			";
			$bd->xConsulta($sql);
			break;
		case 7002:// detalle ie caja salida
			$sql="
				SELECT ie.motivo AS descripcion ,'' as t1,'' as t2, format(ie.monto,2) as t3
				FROM ie_caja AS ie WHERE (ie.idorg=".$_SESSION['ido']." AND ie.idsede=".$_SESSION['idsede']." AND ie.idusuario=".$_SESSION['idusuario'].") AND ie.estado=0 AND ie.cierre=0 and ie.tipo=2
				ORDER BY ie.tipo
			";
			$bd->xConsulta($sql);
			break;
		case 702:// ventas al credito
			$sql="
				SELECT c.nombres AS descripcion, '' as t1,count(rpd.idregistro_pago) as t2, format(sum(rpd.importe),2) AS t3
					FROM registro_pago AS rp
					INNER JOIN registro_pago_detalle AS rpd using(idregistro_pago)
					INNER JOIN tipo_pago AS tp using(idtipo_pago)
					INNER JOIN cliente AS c using(idcliente)
				WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede']." AND rp.idusuario=".$_SESSION['idusuario'].") AND rpd.idtipo_pago=3 AND (rp.estado=0 AND rpd.estado=0) AND rp.cierre=0
				GROUP BY rp.idcliente
			";
			$bd->xConsulta($sql);
			break;
		case 703:// resumen tipo de consumo
			/*$sql="
				SELECT tpc.descripcion, count(p.idtipo_consumo) AS t1, format(sum(p.total),2) AS t2
				FROM pedido AS p
					INNER JOIN tipo_consumo AS tpc using(idtipo_consumo)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.cierre=0 AND p.estado !=3
				GROUP BY p.idtipo_consumo
				";-*/
			$sql="
			SELECT tpc.descripcion,'' as t1, count(rp.idregistro_pago) AS t2, format(sum(rp.total),2) AS t3
				FROM registro_pago AS rp
				INNER JOIN tipo_consumo AS tpc using(idtipo_consumo)
			WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede']." AND rp.idusuario=".$_SESSION['idusuario'].") AND rp.cierre=0 AND rp.estado=0
			GROUP BY rp.idtipo_consumo
			";
			$bd->xConsulta($sql);
			break;
		case 704://resumen estado de pedidos
			$sql="
				SELECT (CASE p.estado WHEN '0' THEN 'POR DESPACHAR' WHEN '1' THEN 'DESPACHADOS' WHEN '2' THEN 'PAGADOS' WHEN '3' THEN 'ANULADO' END) AS descripcion, count(p.idpedido) as importe
				FROM pedido AS p
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.cierre=0
				GROUP BY p.estado
			";
			$bd->xConsulta($sql);
			break;
		case 705://resumen de platos vendidos
			/*$sql="
				SELECT s.descripcion AS des_seccion, i.descripcion AS des_item, count(idpedido_detalle) AS cantidad
				FROM pedido AS p
					INNER JOIN pedido_detalle AS pd using(idpedido)
					INNER JOIN carta_lista AS cl using(idcarta_lista)
					INNER JOIN seccion AS s ON s.idseccion=cl.idseccion
					INNER JOIN item AS i ON i.iditem=pd.iditem
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede']." AND p.cierre=0) AND p.estado IN (2)
				GROUP BY cl.idseccion, pd.iditem
				ORDER BY cl.idseccion,cl.sec_orden,count(idpedido_detalle) desc
			";*/
			/*$sql="
			SELECT i.descripcion,IF(cl.cant_preparado='SP',sum(pd.cantidad)+(IFNULL(it_p.stock,0)),IF(cl.cant_preparado='ND','',cl.cant_preparado)) AS t1, sum(pd.cantidad) AS t2,IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),IF(cl.cantidad='ND','',cl.cantidad)) AS t3
			FROM pedido AS p
				INNER JOIN registro_pago AS rp using(idregistro_pago)
				INNER JOIN pedido_detalle AS pd using(idpedido)
				LEFT JOIN carta_lista AS cl using(idcarta_lista)
				LEFT JOIN item AS i ON cl.iditem=i.iditem
				LEFT JOIN(SELECT i.iditem, po.idporcion, i.descripcion,CAST((po.stock/ii.cantidad) AS SIGNED) AS stock
							FROM item AS i
								INNER JOIN item_ingrediente AS ii using(iditem)
								INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
							GROUP BY i.iditem
							) AS it_p ON pd.iditem=it_p.iditem
			WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND (p.cierre=0 and rp.idusuario=".$_SESSION['idusuario'].") AND p.estado=2 AND (pd.procede=0 AND pd.estado=0 and pd.secambio=0 AND pd.borrado=0)
			GROUP BY pd.iditem
			ORDER BY pd.idseccion, pd.descripcion
			";*/

			// $sql="
			// 	SELECT i.descripcion,'' AS t1,sum(rpp.cantidad) AS t2,IF(cl.cantidad='SP',(IFNULL(it_p.stock,0)),IF(cl.cantidad='ND','ND',cl.cantidad)) AS t3
			// 	FROM registro_pago_pedido AS rpp
			// 		INNER JOIN registro_pago AS rp ON rpp.idregistro_pago=rp.idregistro_pago
			// 		INNER JOIN pedido_detalle AS pd ON rpp.idpedido_detalle=pd.idpedido_detalle
			// 		INNER JOIN item AS i ON pd.iditem=i.iditem
			// 		INNER JOIN carta_lista AS cl ON i.iditem=cl.iditem
			// 		LEFT JOIN(SELECT i.iditem, CAST((po.stock/ii.cantidad) AS SIGNED) AS stock
			// 					FROM item AS i
			// 						INNER JOIN item_ingrediente AS ii using(iditem)
			// 						INNER JOIN porcion AS po ON ii.idporcion=po.idporcion
			// 						GROUP BY i.iditem
			// 					) AS it_p ON pd.iditem=it_p.iditem				
			// 	WHERE rp.cierre=0 AND rp.idusuario=".$_SESSION['idusuario']." AND pd.procede_tabla!=0 and (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].")
			// 	GROUP BY pd.iditem
			// 	ORDER BY t2 desc
			// ";

			$sql = "
				SELECT i.descripcion, sum(rpp.cantidad) as t1,
				if (cl.cantidad = 'SP', 
					(SELECT CAST((po.stock/ii.cantidad) AS SIGNED) AS stock 
					FROM item AS i1 
					INNER JOIN item_ingrediente AS ii using(iditem) 
					INNER JOIN porcion AS po ON ii.idporcion=po.idporcion 
					WHERE i1.iditem = i.iditem
					GROUP BY i1.iditem)
				, cl.cantidad) as t2, format(sum(rpp.total),2) as t3
				FROM registro_pago_pedido as rpp
				inner join registro_pago as rp ON rpp.idregistro_pago = rp.idregistro_pago
				inner join pedido_detalle as pd on rpp.idpedido_detalle = pd.idpedido_detalle
				inner join item as i on pd.iditem = i.iditem
				INNER JOIN carta_lista AS cl ON i.iditem=cl.iditem
				where (rp.cierre = 0 AND rp.idusuario=".$_SESSION['idusuario']." AND rp.idsede=".$_SESSION['idsede'].") AND pd.procede_tabla!=0 
				GROUP BY i.iditem
				order BY sum(rpp.total) desc
			";

			$bd->xConsulta($sql);
			break;
		case 706://productos vendidos de bodega
			/*$sql="
			SELECT pd.descripcion,'' as t1, sum(pd.cantidad) AS t2,ai.stock AS t3
			FROM pedido AS p
				INNER JOIN pedido_detalle AS pd using(idpedido)
				LEFT JOIN(SELECT idproducto, sum(stock) AS stock FROM almacen_items WHERE estado=0 GROUP BY idproducto) AS ai ON pd.iditem=ai.idproducto
			WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.cierre=0 AND p.estado=2 AND (pd.procede!=0 AND pd.estado=0 and pd.secambio=0 AND pd.borrado=0)
			GROUP BY ai.idproducto
			ORDER BY pd.idseccion, pd.descripcion
			";*/
			//solo vendidos
			/*$sql="
				SELECT pd.descripcion,'' as t1, sum(pd.cantidad) AS t2, pos.stock AS t3
				FROM pedido AS p
					INNER JOIN pedido_detalle AS pd using(idpedido)
					INNER JOIN registro_pago AS rp ON rp.idregistro_pago=pd.idregistro_pago
					INNER JOIN producto AS pro ON pd.iditem=pro.idproducto
					INNER JOIN producto_stock AS pos ON pos.idproducto_stock=pd.idcarta_lista
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede']." AND rp.idusuario=".$_SESSION['idusuario'].")  AND pd.procede!=0 AND p.cierre=0 AND p.estado=2 AND (pd.estado=0 and pd.secambio=0 AND pd.borrado=0)
				GROUP BY pro.idproducto
				ORDER BY pd.descripcion
			";*/
			$sql="
				SELECT pd.descripcion,'' AS t1,sum(rpp.cantidad) AS t2,pos.stock AS t3
				FROM registro_pago_pedido AS rpp
					INNER JOIN registro_pago AS rp ON rpp.idregistro_pago=rp.idregistro_pago
					INNER JOIN pedido_detalle AS pd ON rpp.idpedido_detalle=pd.idpedido_detalle
					INNER JOIN producto AS pro ON pd.iditem=pro.idproducto
					INNER JOIN producto_stock AS pos ON pos.idproducto_stock=pd.idcarta_lista
				WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND rp.cierre=0 AND rp.idusuario=".$_SESSION['idusuario']."  AND pd.procede_tabla=0
				GROUP BY pd.iditem
				ORDER BY t2 desc
			";
			$bd->xConsulta($sql);
			break;
		case 707://inventario de porciones vendidas
			/*$sql="
				SELECT p.descripcion, '' AS t1, sum(pd.cantidad*ii.cantidad) AS t2, p.stock AS t3
				FROM pedido_detalle AS pd
					INNER JOIN pedido AS pro using(idpedido)
					INNER JOIN item AS i using(iditem)
					INNER JOIN item_ingrediente AS ii ON i.iditem=ii.iditem
					INNER JOIN porcion AS p using(idporcion)
				WHERE (i.idorg=".$_SESSION['ido']." AND i.idsede=".$_SESSION['idsede'].") AND (pro.cierre=0 AND pro.estado!=3 AND pd.estado=0)
				GROUP BY p.idporcion
				ORDER BY p.descripcion
			";*/
			$sql="
				SELECT por.descripcion, '' AS t1, sum(rpp.cantidad*ii.cantidad) AS t2, por.stock AS t3
				FROM registro_pago_pedido AS rpp
					INNER JOIN registro_pago AS rp ON rpp.idregistro_pago=rp.idregistro_pago
					INNER JOIN pedido_detalle AS pd ON rpp.idpedido_detalle=pd.idpedido_detalle
						INNER JOIN item AS i ON pd.iditem=i.iditem
						INNER JOIN carta_lista AS cl ON i.iditem=cl.iditem
						INNER JOIN item_ingrediente AS ii ON i.iditem=ii.iditem
						INNER JOIN porcion AS por ON ii.idporcion=por.idporcion
				WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND rp.cierre=0 AND rp.idusuario=".$_SESSION['idusuario']." AND cl.cantidad='SP'
				GROUP BY por.idporcion
				ORDER BY por.descripcion
			";
			$bd->xConsulta($sql);
			break;
		case 708://productos de bodega con stock minimo
			$sql="
			SELECT pro.descripcion, '' AS t1, '' AS t2, a.stock AS t3
			FROM producto AS pro
				INNER JOIN(SELECT sum(a1.stock) AS stock, a1.idproducto
							FROM almacen_items AS a1
								INNER JOIN almacen AS am using(idalmacen)
							WHERE a1.estado=0 AND am.bodega=1 GROUP BY a1.idproducto) AS a using(idproducto)
			WHERE (pro.idorg=".$_SESSION['ido']." AND pro.idsede=".$_SESSION['idsede'].") and (a.stock<=pro.stock_minimo AND pro.stock_minimo!='')
			GROUP BY pro.idproducto
			";
			$bd->xConsulta($sql);
			break;
		case 709://pedidos anulados
			$sql="
			SELECT 'PEDIDOS ANULADOS' AS descripcion,'' as t1,'' as t2, count(idpedido) as t3 FROM pedido WHERE estado=3 and cierre=0 AND (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].")
			UNION all
			SELECT 'ITEMS ANULADOS' AS descripcion, '' as t1,'' as t2, count(idpedido_detalle) as t3 FROM pedido_detalle AS pd INNER JOIN pedido using(idpedido) WHERE pd.estado=1 AND (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede']." AND cierre=0)
			";
			$bd->xConsulta($sql);
			break;
		case 7091://item anulados - cuadre
			$sql="
			SELECT * FROM (SELECT pd.descripcion,'' AS t1, count(pb.idpedido) AS t2, format(sum(pb.importe),2) AS t3
				FROM pedido_borrados AS pb
					INNER JOIN pedido AS p ON pb.idpedido=p.idpedido
					INNER JOIN pedido_detalle AS pd ON pb.idpedido_detalle=pd.idpedido_detalle
				where (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede']." AND pb.idusuario=".$_SESSION['idusuario'].") AND pb.fecha_cierre='' AND pd.procede_tabla!=0
				GROUP BY pd.iditem
				ORDER BY pd.descripcion
			) a
			UNION all
			SELECT * FROM (SELECT prod.descripcion, '' AS t1, count(*) AS t2, format(sum(pb.importe),2) AS t3
						FROM pedido_borrados AS pb
							INNER JOIN pedido AS p using(idpedido)
							INNER JOIN producto AS prod ON prod.idproducto=pb.iditem
						where (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede']." AND pb.idusuario=".$_SESSION['idusuario'].") AND pb.fecha_cierre='' AND pb.procede_tabla=0
						GROUP BY pb.iditem
						ORDER BY prod.descripcion) b
			";
			$bd->xConsulta($sql);
			break;
		case 7092://pedidos anulados
			$sql="
				SELECT concat(pb.hora,'|',u.usuario,'|',p.motivo_anular) AS descripcion,'' AS t1,'' AS t2, format(sum(pb.importe),2) AS t3
				FROM pedido_borrados AS pb
					INNER JOIN pedido AS p using(idpedido)
					INNER JOIN usuario AS u ON pb.idusuario_permiso=u.idusuario
				where (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede']." AND pb.idusuario=".$_SESSION['idusuario'].") AND pb.fecha_cierre=''
				GROUP BY pb.hora
			";
			$bd->xConsulta($sql);
			break;
		case 70010://cantidades vendidas por seccion
			/*$sql="
				SELECT * FROM (SELECT s.descripcion AS descripcion, '' as t1, sum(pd.cantidad) AS t2, format(sum(pd.ptotal),2) AS t3
				FROM pedido_detalle pd
					INNER JOIN pedido AS p using(idpedido)
					INNER JOIN seccion AS s using(idseccion)
					INNER JOIN registro_pago AS rp ON rp.idregistro_pago=pd.idregistro_pago
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND (p.estado=2 AND pd.procede=0 AND p.cierre=0 AND rp.idusuario=".$_SESSION['idusuario'].")
				GROUP BY pd.idseccion
				ORDER BY s.descripcion) a
				UNION ALL
				SELECT * FROM (
				SELECT pf.descripcion AS descripcion,'' as t1, sum(pd.cantidad) AS t2, format(sum(pd.ptotal),2) AS t3
				FROM pedido_detalle AS pd
					INNER JOIN pedido AS p using(idpedido)
					INNER JOIN registro_pago AS rp ON rp.idregistro_pago=pd.idregistro_pago
					INNER JOIN producto_familia AS pf ON pd.idseccion=pf.idproducto_familia
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and pd.procede>0 AND (p.estado=2 AND p.cierre=0 AND rp.idusuario=".$_SESSION['idusuario'].")
				GROUP BY pd.procede
				ORDER BY pf.descripcion ) b
			";*/
			$sql="
				SELECT * FROM (SELECT s.descripcion AS descripcion, '' AS t1, sum(rpp.cantidad) AS t2, format(sum(rpp.total),2) AS t3
					FROM registro_pago_pedido AS rpp
					INNER JOIN registro_pago AS rp ON rpp.idregistro_pago=rp.idregistro_pago
					INNER JOIN pedido_detalle AS pd ON rpp.idpedido_detalle=pd.idpedido_detalle
					INNER JOIN seccion AS s ON pd.idseccion=s.idseccion
					WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND pd.procede_tabla!=0 AND rp.cierre=0 AND rp.idusuario=".$_SESSION['idusuario']."
					GROUP BY pd.idseccion
					ORDER BY sum(rpp.total) DESC) a
					UNION all
				SELECT * FROM (
					SELECT pf.descripcion AS descripcion, '' AS t1, sum(rpp.cantidad) AS t2, format(sum(rpp.total),2) AS t3
					FROM registro_pago_pedido AS rpp
					INNER JOIN registro_pago AS rp ON rpp.idregistro_pago=rp.idregistro_pago
					INNER JOIN pedido_detalle AS pd ON rpp.idpedido_detalle=pd.idpedido_detalle
					INNER JOIN producto_familia AS pf ON pd.idseccion=pf.idproducto_familia
					WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND pd.procede_tabla=0 AND rp.cierre=0 AND rp.idusuario=".$_SESSION['idusuario']."
					GROUP BY pd.idseccion
					ORDER BY sum(rpp.total) DESC) b
			";
			$bd->xConsulta($sql);
			break;
		case 70011://resumne de ingresos y salidas de caja no cerradas
			$sql="
				SELECT  fecha, IF(tipo=1,'INGRESO','SALIDA') AS des_tipo,tipo,motivo,monto
					FROM ie_caja
				WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND idusuario=".$_SESSION['idusuario']." AND cierre=0
				ORDER BY idie_caja desc
			";
			$bd->xConsulta($sql);
			break;
		///////////////////
		///////////////////
		case 15://modificar importe total del pedido
			$sql="update pedido set total=FORMAT(total+".$_POST['t'].",2) where idpedido=".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		////////
		case 16: //load productos y familia
			$sql="
				SELECT p.idproducto as value, p.descripcion as label,p.precio_venta,p.precio,p.precio_unitario,pf.descripcion as familia, p.idproducto_familia,p.enlazar
				FROM producto AS p
					INNER JOIN producto_familia AS pf using(idproducto_familia)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 16001: //load productos + familia y familia
			/*$sql="
				SELECT alm.idalmacen_items,p.idproducto as value, concat(pf.descripcion ,' | ',p.descripcion) as label, p.precio,p.precio_unitario,pf.descripcion as familia, p.idproducto_familia, alm.stock
				FROM producto AS p
					INNER JOIN producto_familia AS pf using(idproducto_familia)
					INNER JOIN almacen_items AS alm using(idproducto)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND (alm.idalmacen=".$_POST['i']." and alm.stock>0) AND p.estado=0
			";*/
			$sql="
				SELECT ps.idproducto_stock ,p.idproducto as value, concat(pf.descripcion ,' | ',p.descripcion) as label, p.precio,p.precio_unitario,pf.descripcion as familia, p.idproducto_familia, ps.stock
				FROM producto AS p
					INNER JOIN producto_stock AS ps using(idproducto)
					INNER JOIN producto_familia AS pf using(idproducto_familia)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND (ps.idalmacen=".$_POST['i'].") AND p.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 16002://almacen
			/*$sql="
				SELECT alm.idalmacen_items,p.idproducto as value, concat(al.descripcion,' | ', pf.descripcion ,' | ',p.descripcion) as label, p.precio,p.precio_unitario,pf.descripcion as familia, p.idproducto_familia, alm.stock,p.enlazar
				FROM producto AS p
					INNER JOIN producto_familia AS pf using(idproducto_familia)
					INNER JOIN almacen_items AS alm using(idproducto)
					INNER JOIN almacen AS al using (idalmacen)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND (alm.stock>0) AND p.estado=0
			";*/
			$sql="
				SELECT ps.idproducto_stock ,p.idproducto as value, concat(al.descripcion,' | ', pf.descripcion ,' | ',p.descripcion ) as label, p.precio,p.precio_unitario,pf.descripcion as familia, p.idproducto_familia, ps.stock,p.enlazar
				FROM producto AS p
					INNER JOIN producto_stock AS ps using(idproducto)
					INNER JOIN producto_familia AS pf using(idproducto_familia)
					INNER JOIN almacen AS al using (idalmacen)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND ps.stock>0 AND p.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 16003://productos porcionados
			$sql="
				SELECT idporcion AS value, descripcion AS label FROM porcion
				WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 16004://update stock en almacen producto porcionado
			//$sql="update almacen_items set stock=stock-".$_POST['s']." where idalmacen_items=".$_POST['i']."; update producto set enlazar=".$_POST['e']." where idproducto=".$_POST['idpro_de'];
			$sql="update producto_stock set stock=stock - ".$_POST['s']." where idproducto_stock=".$_POST['i']." ; update producto set enlazar=".$_POST['e']." where idproducto=".$_POST['idpro_de'];
			$bd->xMultiConsulta($sql);
			break;
		case 16005://guardar x enlazado
			$xArray_enlazado=$_POST['array_enlaze'];
			$sql='';
			foreach ($xArray_enlazado as $enlazado) {
				$sql=$sql."update porcion set stock=stock+".$enlazado['cantidad']." where idproducto_de=".$enlazado['idpro_sel']."; ";
			}
			$bd->xMultiConsulta($sql);
			break;
		case 16006://guardar distribuicion
			$array_db=$_POST['array_db'];
			$sql='';
			foreach ($array_db as $item) {
				//verificar si existe en el almacen que va a ingresar
				$sql=$sql."UPDATE producto_stock SET stock = stock - ".$item['cantidad']." WHERE idproducto_stock = ".$item['idproducto_stock']."; INSERT INTO producto_stock (idproducto_stock,idproducto, idalmacen, stock ) VALUES ((SELECT ps.idproducto_stock FROM producto_stock AS ps WHERE ps.idproducto = ".$item['idproducto']." AND ps.idalmacen = ".$item['idalmacen_a']."),".$item['idproducto'].",".$item['idalmacen_a'].",".$item['cantidad'].") ON DUPLICATE KEY UPDATE stock=stock+".$item['cantidad']."; ";
			}
			$bd->xMultiConsulta($sql);
			break;
		case 1601://cargar familias
			$sql="select idproducto_familia as value, descripcion as label from producto_familia where (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") and estado=0";
			$bd->xConsulta($sql);
			break;
		case 1602://guardar familia
			$sql="SELECT idproducto_familia AS d1 FROM producto_familia WHERE descripcion='".$_POST['f']."' and (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			$xidFam=$bd->xDevolverUnDato($sql);
			if($xidFam!=0){print $xidFam;}
			else{
				$sql="insert into producto_familia (idproducto_familia,idorg,idsede,descripcion) values (0,".$_SESSION['ido'].",".$_SESSION['idsede'].",'".$_POST['f']."')";
				$bd->xConsulta_NoReturn($sql);
				// print $bd->xConsulta_UltimoId($sql);

				// idproducto_familia es char -> f1
				$sql="SELECT idproducto_familia AS d1 FROM producto_familia WHERE descripcion='".$_POST['f']."' and (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
				$xidFam=$bd->xDevolverUnDato($sql);

				print $xidFam;
			}
			break;
		case 1603://load sel proveedores
			$sql="select idproveedor as value, descripcion as label, direccion, dni from proveedor where (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			$bd->xConsulta($sql);
			break;
		case 1604://load almacenes
			$sql="select * from almacen where (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			$bd->xConsulta($sql);
			break;
		case 1605://guardar cambios en config almacen segun chek
			$sql="update almacen set ".$_POST['campo']."=".$_POST['valor']." where idalmacen=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 1606://load motivo de entrada o salida almacen
			$sql="select * from almacen_motivo_es where estado=0";
			$bd->xConsulta($sql);
			break;
		case 1607:// load emision de comprobantes de pago - config
			$sql = "
			SELECT tpcs.*, tp.descripcion as dsc_comprobante, s.nombre as dsc_sede, s.ciudad
			from tipo_comprobante_serie tpcs
				inner join tipo_comprobante tp using(idtipo_comprobante)
				inner join sede s on s.idsede = tpcs.idsede
			where (tpcs.idorg=".$_SESSION['ido'].") and tpcs.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 17://recetas y costos  //load items platos de la carta
			$sql="
				SELECT i.iditem, concat(IFNULL(s.descripcion,'----'),' | ',i.descripcion) AS descripcion, i.precio, i.costo, format(i.precio-i.costo,2) as rentabilidad
				FROM item as i
					left JOIN carta_lista AS cl using(iditem)
					left JOIN seccion AS s using(idseccion)
				WHERE (i.idorg=".$_SESSION['ido']." AND i.idsede=".$_SESSION['idsede'].") and i.estado=0
				ORDER BY IFNULL(s.descripcion,'----'),i.descripcion
			";
			$bd->xConsulta($sql);
			break;
		case 1701://listado de porciones para ingredientes
			$sql="
			SELECT p.idporcion AS value, p.descripcion AS label, ifnull(format(pr.precio_unitario*p.peso,2),0) AS precio_unitario
			FROM porcion AS p
				left join (SELECT p1.idproducto, p1.precio_unitario FROM producto AS p1 WHERE p1.estado=0 AND (p1.idorg=".$_SESSION['ido']." AND p1.idsede=".$_SESSION['idsede'].")) AS pr ON pr.idproducto=p.idproducto_de
			WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and  p.estado=0
			ORDER BY descripcion
			";
			$bd->xConsulta($sql);
			break;
		case 1702://load detalles de ingredientes
			$sql="SELECT iditem,descripcion,cantidad,costo FROM item_ingrediente WHERE iditem=".$_POST['i']." AND estado=0 order by iditem_ingrediente";
			$bd->xConsulta($sql);
			break;
		///productos y porciones
		case 18:///productos x almacen
			/*$sql="
				SELECT alm.idalmacen_items,alm.idproducto, alm.idalmacen,pf.descripcion AS familia, a.descripcion AS almacen, p.descripcion AS producto, sum(alm.stock) AS stock, IF(p.precio_venta='','0.00',format(p.precio_venta,2)) AS precio_venta, IF(p.stock_minimo='',0,p.stock_minimo) AS stock_minimo
				FROM almacen_items AS alm
					INNER JOIN producto AS p using(idproducto)
					INNER JOIN almacen AS a using(idalmacen)
					INNER JOIN producto_familia AS pf using(idproducto_familia)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.estado=0 AND alm.estado=0
				GROUP BY alm.idalmacen, alm.idproducto
				ORDER BY a.descripcion, p.descripcion
			";*/
			$sql="
				SELECT ps.idproducto_stock,p.idproducto, ps.idalmacen,pf.descripcion AS familia, a.descripcion AS almacen, p.descripcion AS producto, ps.stock, IF(p.precio_venta='','0.00',format(p.precio_venta,2)) AS precio_venta, IF(p.stock_minimo='',0,p.stock_minimo) AS stock_minimo
				FROM producto AS p
					INNER JOIN producto_stock AS ps using(idproducto)
					INNER JOIN almacen AS a using(idalmacen)
					INNER JOIN producto_familia AS pf using(idproducto_familia)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.estado=0 AND ps.estado=0
				GROUP BY ps.idalmacen, ps.idproducto
				ORDER BY a.descripcion, p.descripcion
			";
			$bd->xConsulta($sql);
			break;
		case 1801://guardar cambios de productos
			$array_rows=$_POST['array_rows'];
			$opcion_tabla=$_POST['op'];
			$sql_array='';
			foreach ($array_rows as $row) {
				switch ($opcion_tabla) {
					case 1://productos
						if($row['val_stock']!=''){$sql_array=$sql_array.'update producto_stock set stock='.$row['val_stock'].' where idproducto_stock='.$row['idproducto_stock'].'; ';}
						if($row['val_precio']!=''){$sql_array=$sql_array.'update producto set precio_venta='.$row['val_precio'].' where idproducto='.$row['idproducto'].'; ';}
						if($row['val_descripcion']!=''){$sql_array=$sql_array.'update producto set descripcion="'.$row['val_descripcion'].'" where idproducto='.$row['idproducto'].'; ';}
						break;
					case 2://porciones
						if($row['val_stock']!=''){$sql_array=$sql_array.'update porcion set stock='.$row['val_stock'].' where idporcion='.$row['idproducto'].'; ';}
						if($row['val_descripcion']!=''){$sql_array=$sql_array.'update porcion set descripcion="'.$row['val_descripcion'].'" where idporcion='.$row['idproducto'].'; ';}
						break;
				}
			}
			//print $sql_array;
			if($sql_array!=''){$bd->xMultiConsulta($sql_array);}
			break;
		case 1802://borrar  producto seleccionado del alamecen seleccionado
			//$sql="update almacen_items set estado=1 where idproducto=".$_POST['idp']." and idalmacen=".$_POST['ida'];
			$sql="update producto_stock set estado=1 where idproducto_stock=".$_POST['idp'];
			$bd->xConsulta($sql);
			break;
		case 1803://porciones
			$sql="
				SELECT por.idporcion, ifnull(pf.descripcion,'no definido') as familia, por.descripcion as porcion, por.stock FROM porcion AS por
					left JOIN producto AS p ON p.idproducto=por.idproducto_de
					left JOIN producto_familia AS pf using(idproducto_familia)
				WHERE (por.idorg=".$_SESSION['ido']." AND por.idsede=".$_SESSION['idsede'].") AND por.estado=0
				ORDER BY pf.descripcion, por.descripcion
			";
			$bd->xConsulta($sql);
			break;
		case 1804://borrar  producto seleccionado del alamecen seleccionado
			$sql="update porcion set estado=1 where idporcion=".$_POST['idp'];
			$bd->xConsulta($sql);
			break;
		case 1805://porciones mas productos para E/S almacen // entrada
			/*$sql="
				SELECT * FROM(SELECT p.idporcion AS value, concat('PORCION ',' | ', p.descripcion) AS label, '1' as procede FROM porcion AS p WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and p.estado=0 ORDER BY p.descripcion) b
				UNION ALL
				SELECT * FROM(
				SELECT alm.idproducto AS value, concat(pf.descripcion,' | ',p.descripcion) AS label, '0' as procede
								FROM almacen_items AS alm
									INNER JOIN producto AS p using(idproducto)
									INNER JOIN almacen AS a using(idalmacen)
									INNER JOIN producto_familia AS pf using(idproducto_familia)
								WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.estado=0 AND alm.estado=0
								GROUP BY alm.idproducto
								ORDER BY p.idproducto_familia, p.descripcion
				) a
			";*/
			$sql="
				SELECT * FROM(SELECT p.idporcion AS value, concat('PORCION ',' | ', p.descripcion) AS label, '1' as procede FROM porcion AS p WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and p.estado=0 ORDER BY p.descripcion) b
				UNION ALL
				SELECT * FROM(
				SELECT p.idproducto AS value, concat(pf.descripcion,' | ',p.descripcion) AS label, '0' as procede
								FROM producto AS p
									INNER JOIN producto_stock AS ps using(idproducto)
									INNER JOIN almacen AS a using(idalmacen)
									INNER JOIN producto_familia AS pf using(idproducto_familia)
								WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.estado=0 AND ps.estado=0
								GROUP BY ps.idproducto
								ORDER BY p.idproducto_familia, p.descripcion
				) a
			";
			$bd->xConsulta($sql);
			break;
		case 1806://guardar Entrada Salida Almacen
			$array_es=$_POST['xarray_save_ae'];
			$sql_ai_producto='';
			$sql_porcion='';
			$sql_detalle_ie='';
			$idalmacen='';
			$tipo='';
			$motivo='';

			foreach ($array_es as $item) {
				$procede=$item['procede'];
				$tipo=$item['tipo']; // 1entrada o 2salida
				$motivo=$item['motivo'];
				$idmotivo=$item['idmotivo'];
				$idalmacen=$item['idalmacen'];
				$cantidad=$item['cantidad'];

				if($tipo=='2'){//salida add -
					$cantidad='-'.$cantidad;
				}

				/*if($procede=='0'){//producto
					$sql_ai_producto=$sql_ai_producto.'('.$idalmacen.','.$item['id'].','.$cantidad.',DATE_FORMAT(now(),"%d/%m/%Y")),';
				}else{//porcion
					$sql_porcion=$sql_porcion.'update porcion set stock=stock+'.$cantidad.' where idporcion='.$item['id'].'; ';
				}*/

				//detalle de almacen_ei_detalle
				$sql_detalle_ie=$sql_detalle_ie.'(?ie,'.$item['id'].','.$procede.','.$cantidad.'),';
			}

			//guardar en almacen_ie
			$sql_ai='insert into almacen_ie (idorg,idsede,idusuario,idalmacen,tipo,fecha,idproducto_motivo_es,motivo) values ('.$_SESSION['ido'].','.$_SESSION['idsede'].','.$_SESSION['idusuario'].','.$idalmacen.','.$tipo.',DATE_FORMAT(now(),"%d/%m/%Y"),'.$idmotivo.',"'.$motivo.'")';
			$xIdUltimoIE=$bd->xConsulta_UltimoId($sql_ai);

			/*if($sql_ai_producto!=''){
				$sql_ai_producto=substr($sql_ai_producto, 0, -1);
				$sql_ai_producto='insert into almacen_items (idalmacen,idproducto,stock,f_ingreso) values '.$sql_ai_producto.'; ';
			}*/

			if($sql_detalle_ie!=''){
				$sql_detalle_ie=substr($sql_detalle_ie, 0, -1);
				$sql_detalle_ie=str_replace("?ie",$xIdUltimoIE,$sql_detalle_ie);
				$sql_detalle_ie='insert into almacen_ie_detalle(idalmacen_ie,idp,procede,cantidad) values '.$sql_detalle_ie.'; ';
			}

			//$sql_ejecuta=$sql_ai_producto.' '.$sql_porcion.' '.$sql_detalle_ie;
			$sql_ejecuta=$sql_ai_producto.' '.$sql_detalle_ie;
			$bd->xMultiConsulta($sql_ejecuta);
			break;
		case 1807://guardar producto_stock desde
			$sql="INSERT INTO producto_stock( idproducto, idalmacen, stock ) VALUES (".$_POST[p].", ".$_POST['a'].", ".$_POST['c'].")";
			$bd->xConsulta($sql);
		case 19:// monitor de pedidos
			// $sql="
			// 	SELECT cl.idcarta_lista,cl.iditem, cl.cantidad, s.sec_orden, i.descripcion AS plato, ifnull(pd_v.cant_vendido,0) AS cant_vendido, IF(cl.cantidad='SP',(IFNULL((SELECT FLOOR(p1.stock/i1.cantidad) FROM item_ingrediente AS i1 INNER JOIN porcion AS p1 ON i1.idporcion=p1.idporcion WHERE i1.iditem=cl.iditem GROUP BY i1.iditem ORDER BY i1.iditem_ingrediente),0)),cl.cantidad) AS cantidad,s.idseccion, s.descripcion AS des_seccion,IF(cl.cantidad='SP',2,1) AS procede
			// 	FROM carta_lista AS cl
			// 		INNER JOIN carta AS c using(idcarta)
			// 		INNER JOIN item AS i using(iditem)
			// 		INNER JOIN seccion AS s using(idseccion)
			// 		LEFT JOIN (
			// 			SELECT pd1.iditem,sum(pd1.cantidad) AS cant_vendido
			// 			FROM pedido AS p1
			// 				INNER JOIN pedido_detalle AS pd1 using(idpedido)
			// 			WHERE (p1.idorg=".$_SESSION['ido']." AND p1.idsede=".$_SESSION['idsede'].") AND p1.cierre=0 AND pd1.estado=0
			// 			GROUP BY pd1.iditem
			// 			) AS pd_v ON cl.iditem=pd_v.iditem
			// 	WHERE c.idorg=".$_SESSION['ido']." AND c.idsede=".$_SESSION['idsede']." and c.idcategoria=".$_POST['idcategoria']."
			// 	ORDER BY s.sec_orden,s.descripcion,i.descripcion
			// ";
			$idCategoria = $_POST['idcategoria'];
			$sql="CALL procedure_refresh_monitor_19(".$idCategoria.",".$g_ido.",".$g_idsede.");";
			$bd->xConsulta($sql);
			break;
		case 1901://modificacion manual de cantidades , solo monitor de pedido, carta_lista y porcion
			$procede=$_POST['p'];
			$idcarta_lista=$_POST['idcl'];
			$iditem=$_POST['idi'];
			$cant=$_POST['c'];
			if($procede==="1"){
				$sql="update carta_lista set cantidad=".$cant." where idcarta_lista=".$idcarta_lista;
			}else{
				$sql="
					UPDATE porcion AS p
						LEFT JOIN item_ingrediente AS ii using (idporcion)
					SET p.stock=((".$cant.")*(ii.cantidad))
					WHERE ii.iditem=".$iditem.";
				";
			}
			echo $sql;
			$bd->xConsulta($sql);
			break;
		case 1902://pedidos por hora // en el intervalo de 60min
			$sql="
				SELECT count(*) AS cantidad  FROM pedido
				WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND fecha_hora >= NOW() - INTERVAL 45 MINUTE and estado!=3
			";
			$bd->xConsulta($sql);
			break;
		case 1903://tabla usuarios con mas pedidos
			$sql="
				SELECT count(*) AS cantidad, u.nombres
				FROM pedido AS p
					INNER JOIN usuario AS u using (idusuario)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.cierre=0 AND p.estado!=3
				GROUP BY p.idusuario
				ORDER BY cantidad desc
				";
			$bd->xConsulta($sql);
			break;
		//historial de ventas
		case 20://historial de ventas
			$f_historial=$_POST['f'];
			if($f_historial==''){$condicion="rp.cierre=0";}else{$condicion="STR_TO_DATE(rp.fecha, '%d/%m/%Y')=STR_TO_DATE('".$f_historial."', '%d/%m/%Y')";}
			/*$sql="
				SELECT rp.idregistro_pago, GROUP_CONCAT(rpp.idpedido) AS idpedido, GROUP_CONCAT(rpp.idpedido_detalle) AS idpedido_detalle,SUBSTRING_INDEX(rp.fecha,' ',-1) AS hora, rp.total, IFNULL(c.nombres,'PUBLICO EN GENERAL') AS cliente
				FROM registro_pago_pedido AS rpp
					INNER JOIN registro_pago AS rp using(idregistro_pago)
					LEFT JOIN cliente AS c using(idcliente)
					INNER JOIN pedido AS p using(idpedido)
				WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND ".$condicion."
				GROUP BY rpp.idregistro_pago
				ORDER BY rp.idregistro_pago desc
			";*/
			// $sql="
			// SELECT rpp.idregistro_pago,GROUP_CONCAT(DISTINCT rpp.idpedido) AS idpedido,SUBSTRING_INDEX(rp.fecha,' ',-1) AS hora,rp.total,rp.estado,rp.cierre,rp.motivo_anular
			// FROM registro_pago_pedido AS rpp
			// INNER JOIN registro_pago AS rp ON rpp.idregistro_pago=rp.idregistro_pago
			// INNER JOIN pedido AS p ON rpp.idpedido=p.idpedido
			// WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND ".$condicion."
			// GROUP BY rpp.idregistro_pago
			// ORDER BY rp.idregistro_pago desc
			// ";
			$sql = "
			SELECT rpp.idregistro_pago,GROUP_CONCAT(DISTINCT rpp.idpedido) AS idpedido,SUBSTRING_INDEX(rp.fecha,' ',-1) AS hora,rp.total,rp.estado,rp.cierre,rp.motivo_anular,tps.idtipo_comprobante
					,LPAD(p.nummesa,2,'0') AS nummesa, GROUP_CONCAT(DISTINCT LPAD(p.correlativo_dia,5,'0')) as correlativo_dia, p.referencia, u.usuario, tp.idtipo_comprobante, tp.descripcion as comprobante, tpcs.serie, tpcs.correlativo, IFNULL(c.nombres, 'PUBLICO EN GENERAL') AS cliente
					,rp.idce
						FROM registro_pago_pedido AS rpp
						INNER JOIN registro_pago AS rp ON rpp.idregistro_pago=rp.idregistro_pago
						INNER JOIN tipo_comprobante_serie as tps on rp.idtipo_comprobante_serie = tps.idtipo_comprobante_serie
						INNER JOIN pedido AS p ON rpp.idpedido=p.idpedido
						inner JOIN usuario as u on u.idusuario = rp.idusuario
						LEFT join cliente as c on c.idcliente = rp.idcliente
						INNER join tipo_comprobante_serie as tpcs on tpcs.idtipo_comprobante_serie = rp.idtipo_comprobante_serie
						INNER JOIN tipo_comprobante as tp on tp.idtipo_comprobante = tpcs.idtipo_comprobante
			WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND ".$condicion."
			GROUP BY rpp.idregistro_pago
			ORDER BY rp.idregistro_pago desc
			";
			$bd->xConsulta($sql);
			break;
		case 20001:// historial de ventas
			$pagination = $_POST['pagination'];
			$fecha = $pagination['pageFecha'];
			$filtroFecha = $fecha === '' ? ' and cierre=0 ' : " AND SUBSTRING_INDEX(fecha,' ',1) = '".$fecha."' ";
			$filtroFechaCount = $fecha === '' ? '' : " and (SUBSTRING_INDEX(c.fecha,' ',1)= '".$fecha."')";

			$sql = "
				select idregistro_pago, SUBSTRING_INDEX(fecha,' ',-1) AS hora, total, estado, cierre, idce
				from registro_pago
				where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") ".$filtroFecha."
				order by idregistro_pago desc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];				
			
			$sqlCount="
                SELECT count(c.idregistro_pago) as d1 from registro_pago as c                    
                where (c.idorg=".$_SESSION['ido']." and c.idsede=".$_SESSION['idsede'].")".$filtroFechaCount;            
            
			$rowCount = $bd->xDevolverUnDato($sqlCount);
			// echo $sql;
			$rpt = $bd->xConsulta($sql);            
            print $rpt."**".$rowCount;
			break;
		case 20002: // detalles
			$sql = "
				SELECT rpp.idregistro_pago,GROUP_CONCAT(DISTINCT rpp.idpedido) AS idpedido,SUBSTRING_INDEX(rp.fecha,' ',-1) AS hora,rp.total,rp.estado,rp.cierre,rp.motivo_anular,tp.idtipo_comprobante
					,LPAD(p.nummesa,2,'0') AS nummesa, GROUP_CONCAT(DISTINCT LPAD(p.correlativo_dia,5,'0')) as correlativo_dia, p.referencia, u.usuario, tp.idtipo_comprobante, tp.descripcion as comprobante, IFNULL(c.nombres, 'PUBLICO EN GENERAL') AS cliente
					,rp.idce, ce.numero as num_comprobante
						FROM registro_pago_pedido AS rpp
						INNER JOIN registro_pago AS rp ON rpp.idregistro_pago=rp.idregistro_pago										
						INNER JOIN pedido AS p ON rpp.idpedido=p.idpedido
						inner JOIN usuario as u on u.idusuario = rp.idusuario
						LEFT join cliente as c on c.idcliente = rp.idcliente
						LEFT join ce as ce on rp.idce = ce.idce			
						LEFT join tipo_comprobante_serie as tpcs on tpcs.idtipo_comprobante_serie = ce.idtipo_comprobante_serie
						LEFT JOIN tipo_comprobante as tp on tp.idtipo_comprobante = tpcs.idtipo_comprobante
				WHERE rpp.idregistro_pago = ".$_POST['i']."
				GROUP BY rpp.idregistro_pago
				ORDER BY rp.idregistro_pago desc
			";
			$bd->xConsulta($sql);
			break;
		case 2001://resumen total historial de venta x dia
			$f_historial=$_POST['f'];
			if($f_historial==''){$f_historial='curdate()';}else{$f_historial="STR_TO_DATE('".$f_historial."', '%d/%m/%Y')";}
			$sql="
				SELECT tp.descripcion, format(sum(rpd.importe),2)  AS total
				FROM registro_pago AS rp
					INNER JOIN registro_pago_detalle AS rpd using(idregistro_pago)
					INNER JOIN tipo_pago AS tp using(idtipo_pago)
				WHERE (rp.idorg=".$_SESSION['ido']." AND rp.idsede=".$_SESSION['idsede'].") AND rp.estado=0 AND STR_TO_DATE(rp.fecha, '%d/%m/%Y')=".$f_historial."
				GROUP BY tp.idtipo_pago
			";
			$bd->xConsulta($sql);
			break;
		case 2002://detalle encabezado del pedido seleccionado
			// $sql="
			// 	SELECT u.usuario,IF(p.nummesa=0,'-',LPAD(p.nummesa,2,'0')) AS nummesa,concat('CO-',LPAD(p.correlativo_dia,5,'0')) AS correlativo_dia,p.reserva,p.referencia,format(p.total,2) AS total, tp.descripcion AS tipo_consumo, ct.descripcion AS categoria, p.motivo_anular,p.estado,IFNULL(c.nombres,'PUBLICO EN GENERAL') AS cliente
			// 	FROM pedido AS p
			// 		INNER JOIN usuario AS u using(idusuario)
			// 		LEFT JOIN cliente AS c using(idcliente)
			// 		INNER JOIN tipo_consumo AS tp using(idtipo_consumo)
			// 		INNER JOIN categoria AS ct using(idcategoria)
			// 	WHERE p.idpedido IN (".$_POST['i'].") limit 1
			// ";
			$sql = "
				SELECT u.usuario,IF(p.nummesa=0,'-',LPAD(p.nummesa,2,'0')) AS nummesa,concat('CO-',LPAD(p.correlativo_dia,5,'0')) AS correlativo_dia,p.reserva,p.referencia,format(p.total,2) AS total, tp.descripcion AS tipo_consumo, ct.descripcion AS categoria, p.motivo_anular,p.estado,IFNULL(c.nombres,'PUBLICO EN GENERAL') AS cliente, IFNULL(rp.descripcion,'NINGUNO') as dsc_comprobante, IFNULL(rp.serie,'') as serie, IFNULL(rp.correlativo,'') as num_comprobante
				FROM pedido AS p
					INNER JOIN usuario AS u using(idusuario)
					LEFT JOIN cliente AS c using(idcliente)
                    LEFT JOIN (select r.idregistro_pago, r.correlativo, tpc.serie, tpc.idtipo_comprobante_serie, tp.descripcion 
                               from registro_pago as r 
                               	inner join  tipo_comprobante_serie as tpc USING(idtipo_comprobante_serie)
                               	inner join tipo_comprobante as tp on tp.idtipo_comprobante = tpc.idtipo_comprobante
                              ) as rp on rp.idregistro_pago = p.idregistro_pago
					INNER JOIN tipo_consumo AS tp on p.idtipo_consumo = tp.idtipo_consumo
					INNER JOIN categoria AS ct using(idcategoria)
					WHERE p.idpedido IN (".$_POST['i'].") limit 1
			";
			$bd->xConsulta($sql);
			break;
		case 2003://detalle del pedido seleccionado
			/*$sql="
				SELECT * FROM(
					SELECT p.idpedido, pd.idpedido_detalle,pd.idcarta_lista,pd.idcategoria, p.referencia,pd.idtipo_consumo, tp.descripcion AS des_tp, pd.idseccion,concat('1',s.sec_orden,'.',s.idseccion) AS idseccion_index,s.descripcion AS des_seccion, pd.iditem, pd.cantidad, pd.punitario, pd.ptotal_r as ptotal, pd.descripcion, 0 as visible, pd.procede, '0' AS procede_index , IF(cl.cantidad='SP',2,1) AS descontar_en, IF(cl.cantidad='SP',ii.idporcion,pd.idcarta_lista)AS iddescontar, IF(cl.cantidad='SP',ii.cant_porcion,pd.cantidad) AS cant_descontar
					FROM pedido AS p
						INNER JOIN pedido_detalle AS pd using(idpedido)
						INNER JOIN seccion AS s using(idseccion)
						INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
						INNER JOIN carta_lista AS cl using(idcarta_lista)
						LEFT JOIN (SELECT iditem, GROUP_CONCAT(idporcion) AS idporcion,GROUP_CONCAT(cantidad) AS cant_porcion FROM item_ingrediente WHERE idporcion!=0 GROUP BY iditem) AS ii ON pd.iditem=ii.iditem
					WHERE pd.idregistro_pago=".$_POST['i']." and (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") AND pd.estado=0
					ORDER BY pd.idtipo_consumo,s.sec_orden, pd.descripcion
				) a
				UNION all
					SELECT * FROM(
					SELECT p.idpedido, pd.idpedido_detalle,pd.idcarta_lista,pd.idcategoria, p.referencia,pd.idtipo_consumo, tp.descripcion AS des_tp, pd.idseccion, concat('2',pd.idseccion,'.0') AS idseccion_index, pf.descripcion AS des_desccion, pd.iditem, pd.cantidad, pd.punitario, pd.ptotal_r as ptotal, pd.descripcion, 0 as visible, pd.procede , pf.idproducto_familia AS procede_index, 0 AS descontar_en,pd.iditem AS iddescontar, pd.cantidad AS cant_descontar
					FROM pedido AS p
						INNER JOIN pedido_detalle AS pd using(idpedido)
						INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
						JOIN (SELECT idproducto_familia,descripcion FROM producto_familia WHERE idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AS pf ON pd.idseccion=pf.idproducto_familia
					WHERE pd.idregistro_pago=".$_POST['i']." and (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") AND pd.procede_tabla=0 AND pd.estado=0
					ORDER BY pd.idtipo_consumo,pd.idseccion, pd.descripcion) b
			";*/
			$sql="
			SELECT * FROM(
				SELECT rpp.idpedido, rpp.idpedido_detalle, tp.idtipo_consumo, tp.descripcion AS des_tp,concat('1',s.sec_orden,'.',s.idseccion) AS idseccion_index,s.descripcion AS des_seccion, pd.idseccion, rpp.cantidad, pd.descripcion,rpp.total AS ptotal, rpp.total, pd.punitario as precio, pd.iditem
				FROM registro_pago_pedido AS rpp
					INNER JOIN pedido AS p ON rpp.idpedido=p.idpedido
					INNER JOIN pedido_detalle AS pd ON rpp.idpedido_detalle=pd.idpedido_detalle
					INNER JOIN seccion AS s ON pd.idseccion=s.idseccion
					INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
				WHERE rpp.idregistro_pago=".$_POST['i']." AND pd.procede_tabla!=0) a
				UNION ALL
				SELECT * FROM(
				SELECT rpp.idpedido, rpp.idpedido_detalle, tp.idtipo_consumo, tp.descripcion AS des_tp,concat('2',pd.idseccion,'.0') AS idseccion_index,pf.descripcion AS des_seccion, pd.idseccion, rpp.cantidad, pd.descripcion,rpp.total AS ptotal, rpp.total, pd.punitario as precio, pd.iditem
				FROM registro_pago_pedido AS rpp
					INNER JOIN pedido AS p ON rpp.idpedido=p.idpedido
					INNER JOIN pedido_detalle AS pd ON rpp.idpedido_detalle=pd.idpedido_detalle
					INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
					INNER JOIN producto AS pro ON pd.iditem=pro.idproducto
					INNER JOIN producto_familia AS pf ON pd.idseccion=pf.idproducto_familia
				WHERE rpp.idregistro_pago=".$_POST['i']." AND pd.procede_tabla=0)b
			";
			$bd->xConsulta($sql);
			break;
		case 2004:// tipos de pago history
			$sql="
				SELECT rpd.idregistro_pago,tp.descripcion AS tipo_pago, format(rpd.importe,2) as importe
				FROM registro_pago AS rp
					INNER JOIN registro_pago_detalle AS rpd using(idregistro_pago)
					INNER JOIN tipo_pago AS tp using(idtipo_pago)
				WHERE rp.idregistro_pago=".$_POST['i']." AND rpd.estado=0
			";
			$bd->xConsulta($sql);
			break;
		case 2005:// subtotales del registro de pago seleccionado
			$sql = "SELECT * FROM `registro_pago_subtotal` where idregistro_pago = ".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		//////////////////
		////////////////// despacho de pedidos
		case 21:
			$sql="
			SELECT i.descripcion, group_concat(s.idseccion) AS idseccion,i.idimpresora,i.minutos_pedido
			 FROM impresora i
			 INNER JOIN seccion s using(idimpresora)
			WHERE (i.idorg=".$_SESSION['ido']." AND i.idsede=".$_SESSION['idsede'].") and i.estado=0
			GROUP BY i.idimpresora
			";
			$bd->xConsulta($sql);
			break;
		case 2101://cargar ultimo(s) pedido(s)
			$num_pedidos_cola=$_POST['num_pedidos_cola'];
			$arr_filtro=$_POST['arr_filtro'];
			
			$lastIdPedido = $_POST['id'];
			$idSeccion = $arr_filtro['idseccion'];
			$idTipoConsumo = $arr_filtro['tipo_consumo'];

			// $sql="CALL procedure_update_zona_d_2101(".$lastIdPedido.",".$num_pedidos_cola.",'".$idTipoConsumo."','".$idSeccion."',".$g_ido.",".$g_idsede.");";

			// $condicion='';
			// if($num_pedidos_cola!=0){
			// 	$condicion="AND p.idpedido>".$_POST['id'];
			// }

			// $sql="
			// 	SELECT * FROM(
			// 		SELECT p.idpedido,pd.idtipo_consumo,s.idimpresora,p.fecha_hora,p.hora,p.nummesa,p.referencia,p.reserva,p.correlativo_dia,p.numpedido
			// 							,tp.descripcion AS des_tipo_consumo
			// 							,s.idseccion,concat(1,s.sec_orden,'.',s.idseccion) AS idseccion_index,s.descripcion AS des_seccion
			// 							,pd.idpedido_detalle, pd.despachado_tiempo,concat(1,pd.iditem) AS iditem,pd.cantidad,LPAD(pd.cantidad_r,2,'0') as cantidad_r,pd.descripcion
			// 						FROM pedido AS p
			// 							INNER JOIN pedido_detalle AS pd using(idpedido)
			// 							INNER JOIN seccion AS s using(idseccion)
			// 							INNER JOIN tipo_consumo AS tp ON pd.idtipo_consumo=tp.idtipo_consumo
			// 						WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede']." and p.cierre=0) ".$condicion." AND (pd.procede_tabla!=0 AND pd.despachado=0 AND p.despachado=0) and ((pd.idtipo_consumo in (".$arr_filtro['tipo_consumo'].") and s.idimpresora in (".$arr_filtro['idseccion'].")))
			// 						ORDER BY p.idpedido,s.sec_orden,pd.descripcion
			// 	)a
			// 	UNION all
			// 	SELECT * FROM(
			// 		SELECT p.idpedido,pd.idtipo_consumo,pf.idimpresora,p.fecha_hora,p.hora,p.nummesa,p.referencia,p.reserva,p.correlativo_dia,p.numpedido
			// 				,tp.descripcion AS des_tipo_consumo
			// 				,pd.idseccion,concat(2,pd.idseccion,'.0')AS idseccion_index, pf.descripcion AS des_seccion
			// 				,pd.idpedido_detalle, pd.despachado_tiempo,concat(2,pd.iditem) AS iditem,pd.cantidad,LPAD(pd.cantidad_r,2,'0') as cantidad_r,pd.descripcion
			// 		FROM pedido AS p
			// 			INNER JOIN pedido_detalle AS pd using(idpedido)
			// 			INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
			// 			JOIN (SELECT idproducto_familia,descripcion,idimpresora FROM producto_familia WHERE idorg=1 AND idsede=1) AS pf ON pd.idseccion=pf.idproducto_familia
			// 		WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede']." and p.cierre=0) ".$condicion." AND (pd.procede_tabla=0 AND pd.despachado=0 AND p.despachado=0) and ((pd.idtipo_consumo in (".$arr_filtro['tipo_consumo'].") and pf.idimpresora in (".$arr_filtro['idseccion'].")))
			// 		ORDER BY p.idpedido,pf.idproducto_familia ,pd.descripcion
			// 	) b
			// ";

			// print $sql;
			$sql="CALL procedure_refresh_zona_2101(".$lastIdPedido.",".$num_pedidos_cola.",'".$idTipoConsumo."','".$idSeccion."',".$g_ido.",".$g_idsede.");";
			$bd->xConsulta($sql);
			break;
		case 2102://guardar como depachado //despacho=0=despachado 1=por defecto
			$sql_pedido = "";
			if($_POST['op']==0){//depachado
				$hora=date('H:i:s');
				$despachado_hora=$_POST['td'];
				$valEstado=$_POST['valEstado'];
				$sql="
					UPDATE pedido_detalle
						set despachado=1, despachado_hora='".$hora."' , despachado_tiempo='".$despachado_hora."'
					WHERE idpedido_detalle in (".$_POST['id_pd'].");";
				
				// color del estado al despahcar 1=verde 2=ambar 3=alertado
				$sql_pedido=" update pedido set val_color_despachado='".$valEstado."' where idpedido=".$_POST['idp'].";";

			}else{ //retirado
				//$sql="update pedido set estado=4 where idpedido=".$_POST['idp'];
				$sql="update pedido set despachado=2 where idpedido=".$_POST['idp'];
			}
			$bd->xMultiConsulta($sql.$sql_pedido);
			break;
		case 2103://ver pedidos despachados
			$sql="
				SELECT p.idpedido,p.correlativo_dia,p.numpedido,p.nummesa,p.total,IF(p.referencia='','-',p.referencia) AS referencia,pd.despachado_tiempo FROM pedido AS p
					INNER JOIN pedido_detalle AS pd using(idpedido)
					INNER JOIN seccion AS s using(idseccion)
				WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") AND p.cierre=0 AND pd.despachado=1 AND s.idimpresora IN (".$_POST['idzona'].")
				GROUP BY p.idpedido
				ORDER BY p.fecha desc, TIME_FORMAT(pd.despachado_hora, '%H:%i:%s') desc
			";
			$bd->xConsulta($sql);
			break;
		case 2104://ver detalle del pedido despachado
			$sql="
				SELECT * FROM(
					SELECT p.idpedido,pd.idtipo_consumo,s.idimpresora,p.fecha_hora,p.hora,p.nummesa,p.referencia,p.reserva,p.correlativo_dia,p.numpedido
										,tp.descripcion AS des_tipo_consumo
										,s.idseccion,concat(1,s.sec_orden,'.',s.idseccion) AS idseccion_index,s.descripcion AS des_seccion
										,pd.idpedido_detalle, pd.despachado_tiempo,concat(1,pd.iditem) AS iditem,pd.cantidad,LPAD(pd.cantidad_r,2,'0') as cantidad_r,pd.descripcion
									FROM pedido AS p
										INNER JOIN pedido_detalle AS pd using(idpedido)
										INNER JOIN seccion AS s using(idseccion)
										INNER JOIN tipo_consumo AS tp ON pd.idtipo_consumo=tp.idtipo_consumo
									WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and p.idpedido=".$_POST['id']." AND pd.procede_tabla!=0 AND pd.despachado=1
									ORDER BY p.idpedido,s.sec_orden,pd.descripcion
				)a
				UNION all
				SELECT * FROM(
					SELECT p.idpedido,pd.idtipo_consumo,pf.idimpresora,p.fecha_hora,p.hora,p.nummesa,p.referencia,p.reserva,p.correlativo_dia,p.numpedido
							,tp.descripcion AS des_tipo_consumo
							,pd.idseccion,concat(2,pd.idseccion,'.0')AS idseccion_index, pf.descripcion AS des_seccion
							,pd.idpedido_detalle, pd.despachado_tiempo,concat(2,pd.iditem) AS iditem,pd.cantidad,LPAD(pd.cantidad_r,2,'0') as cantidad_r,pd.descripcion
					FROM pedido AS p
						INNER JOIN pedido_detalle AS pd using(idpedido)
						INNER JOIN tipo_consumo AS tp ON tp.idtipo_consumo=pd.idtipo_consumo
						JOIN (SELECT idproducto_familia,descripcion,idimpresora FROM producto_familia WHERE idorg=1 AND idsede=1) AS pf ON pd.idseccion=pf.idproducto_familia
					WHERE (p.idorg=".$_SESSION['ido']." AND p.idsede=".$_SESSION['idsede'].") and p.idpedido=".$_POST['id']." AND pd.procede_tabla=0 AND pd.despachado=1
					ORDER BY p.idpedido,pf.idproducto_familia ,pd.descripcion
				) b
			";
			$bd->xConsulta($sql);
			break;
		case 2105://enviar reserva a despachaho
			$sql="update pedido set reserva=0, hora=TIME_FORMAT(now(), '%H:%i:%s') where idpedido=".$_POST['i'];
			$bd->xConsulta($sql);
			break;
		case 2106:
			$sql="update impresora set ".$_POST['campo']."='".$_POST['valor']."' where idimpresora=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 2107: // guarda porcentaje del impues
			$sql="update conf_print_detalle set porcentaje='".$_POST['valor']."' where idconf_print_detalle=".$_POST['id'];
			$bd->xConsulta($sql);
			break;
		case 2108:
			$sql="update sede set ".$_POST['campo']."='".$_POST['valor']."' where idsede=".$_POST['id'];
			$bd->xConsulta($sql);
			break;	
		case 2109:// despachar todos los pedidos
			$sql="update pedido set despachado=1 where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and despachado=0";
			$bd->xConsulta($sql);
			break;
		case 2200: //recuperar stock pedidos borrados	
			$sql = "
			SELECT pb.*, u.usuario, IFNULL(i.descripcion, '') as dscitem FROM pedido_borrados pb
				inner join usuario as u using(idusuario)
				inner join pedido as p using(idpedido)
				left join item as i on pb.iditem = i.iditem
			where (p.idorg=".$_SESSION['ido']." and p.idsede=".$_SESSION['idsede'].") and pb.estado=0 and pb.fecha_cierre=''
			order by pb.idpedido_borrados desc		
			";
			$bd->xConsulta($sql);
			break;
		case 2201: //recuperar stock pedidos borrados al actualizar estado	
			$arr_recuperar = $_POST['arr_recuperar'];
			$sql_recuperar = '';
			foreach ($arr_recuperar as $item) {
				$sql_recuperar = $sql_recuperar.$item['idpedido_borrados'].',';
			}

			$sql_recuperar=substr($sql_recuperar, 0, -1);
			$sql_recuperar = 'update pedido_borrados set estado=2 where idpedido_borrados in ('.$sql_recuperar.')';

			$bd->xConsulta($sql_recuperar);
			break;
	}


function encode_dataUS(){
	$data = [
		'us' => [
				'ido'=>$_SESSION['ido'],
				'idsede'=>$_SESSION['idsede'],
				'idus'=>$_SESSION['idusuario'],
				'acc'=>$_SESSION['acc'],
				'nombre'=>$_SESSION['nomU'],
				'cargo'=>$_SESSION['cargoU'],
				'nomus'=>$_SESSION['nomUs'],
				'nom_sede'=>$_SESSION['nom_sede'],
				'ciudad'=>$_SESSION['ciudad'],
				'rol'=>$_SESSION['rol'],
				'nuevo'=>$_SESSION['nuevo'],
				'_sys_sessid'=>$_SESSION['_sys_PHPSESSID'],
			],
		'dispositivos'=>[
				'dispositivo'=>xDtUS(3011),
				'otros_print_doc'=>xDtUS(301) //otras impresoras para otros documentos boleta, factura
			],
		'sistema'=>[
				'url'=>xDtUS(309),
				'constantes'=>xDtUS(3014)
			],
		'carta'=>[
					'regla_carta'=>xDtUS(306),
					'categorias'=>xDtUS(302),
					'estructura_pedido'=>xDtUS(3),
					'subtotales'=>xDtUS(3010)
				],
		'sede'=> [					
					'otros_datos'=>xDtUS(308),
					'generales'=> xDtUS(307),
					'datos_org_sede'=> xDtUS(3012), // datos org y sede // facturacion
					'datos_org_all_sede'=> xDtUS(3013) // datos org y sede // facturacion
				]];  /*,
		,
	];/*
	$data = [
		'idus'  => 1,         // Issued at: time when the token was generated
        'idorg'  => 1,          // Json Token Id: an unique identifier for the token
        'idsede'  => 1,       // Issuer
        'acc'  => 'A1,A2,A3,A4,B1',        // Not before
        'data' => [                  // Data related to the logged user you can set your required data
					'id'   => '1111111', // id from the users table
					'name' => 'colis poto', //  name
        			]
            ];*/

	// echo data['us'];
    $json=json_encode($data);
    $json=base64_encode($json);
    $_SESSION['dataUs']=$json;
    return $json;
}

function xDtUS($op_us){
	$bd=new xManejoBD("restobar");
	$sql_us='';
	switch ($op_us) {
		case 3://3: //amar array con tipo de consumo // amar estructura, para pedido ::app3_sys_dta_pe // ::app3_sys_dta_tct //::app3_sys_dta_tct_estructura
			$sql_us="SELECT * FROM tipo_consumo WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			break;
		case 301://cargar imprimir otros ::app3_woIpPrintO
			$sql_us="SELECT * FROM conf_print_otros WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") and estado=0";
			break;
		case 3011://cargar impresoras ::app3_woIpPrint
			$sql_us="SELECT * FROM impresora WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") and estado=0";
			break;
		case 302://302: //load categoria ::app3_sys_dt_mlc
			$sql_us="SELECT * FROM categoria WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND estado=0";
			break;
		case 307://load datos print //encabezado, logo slogan agradecimiento//307://load datos print //encabezado, logo slogan agradecimiento ::app3_sys_dta_prt
			// $sql_us="
			// SELECT cp.ip_print, cp.num_copias, cp.pie_pagina, cp.logo, IFNULL(cp_d.descripcion,'') AS des_detalle, IFNULL(cp_d.porcentaje,'') AS porcentaje, s.nombre AS des_sede, s.eslogan, IFNULL(cp_a.descripcion,'') as ad_descripcion, IFNULL(cp_a.idtipo_consumo,'') as ad_idtp_consumo,IFNULL(cp_a.idseccion,'') as ad_idseccion, IFNULL(cp_a.importe,'') as ad_importe, s.mesas
			// FROM conf_print AS cp
			// 	LEFT JOIN (select idconf_print,descripcion,porcentaje from conf_print_detalle where estado=0) as cp_d ON cp.idconf_print=cp_d.idconf_print
			//         LEFT JOIN (select idconf_print,idtipo_consumo,group_concat(idseccion) as idseccion,descripcion,importe from conf_print_adicionales where estado=0 group by idconf_print,descripcion,idtipo_consumo,importe)  as cp_a ON cp.idconf_print=cp_a.idconf_print
			// 	LEFT JOIN sede AS s ON s.idorg=cp.idorg AND s.idsede=cp.idsede
			// WHERE (cp.idorg=".$_SESSION['ido']." AND cp.idsede=".$_SESSION['idsede'].")";
			$sql_us="
			SELECT cp.var_size_font_tall_comanda, cp.ip_print, cp.num_copias, cp.pie_pagina, cp.pie_pagina_comprobante, cp.logo, s.logo64, s.nombre AS des_sede, s.eslogan, s.mesas, s.ciudad
			FROM conf_print AS cp
            	INNER JOIN sede AS s ON cp.idsede = s.idsede
			WHERE (cp.idorg=".$_SESSION['ido']." AND cp.idsede=".$_SESSION['idsede'].")";

			break;
		case 306://306://reglas de la carta ::app3_sys_dta_rec
			$sql_us="
			SELECT idregla_carta, idseccion, idseccion_detalle
			FROM regla_carta
			WHERE (idorg=".$_SESSION['ido']." AND idsede=".$_SESSION['idsede'].") AND (estado=0)
			";
			break;
		case 308://308://otros datos de la sede // maximo_pedidos_x_hora  tiempo maximo en servir pedido por minutos ::app3_sys_dta_other
			$sql_us="select maximo_pedidos_x_hora from sede where idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'];
			break;
		case 309:
			$sql_us="select * from us_home_opciones where estado=0 order by idgrupo";
			break;
		case 3010: // para calcular monto total // sub totales igv, servicio, otros adicionales taper etc
			$sql_us="
			SELECT * FROM(
				SELECT 'p' as tipo, cpd.idconf_print_detalle as id, cpd.es_impuesto, cpd.descripcion, cpd.porcentaje as monto, 0 as idtipo_consumo, 0 as idseccion, 0 as nivel, cpd.activo
				FROM conf_print_detalle cpd 
					INNER JOIN conf_print as c on cpd.idconf_print=c.idconf_print
				where c.idorg=".$_SESSION['ido']." and c.idsede=".$_SESSION['idsede']." and cpd.estado=0) a 
				UNION ALL
				SELECT * FROM(
				SELECT 'a' as tipo, cpa.idconf_print_adicionales as id, 0 as es_impuesto, cpa.descripcion, cpa.importe as monto, cpa.idtipo_consumo, cpa.idseccion, cpa.nivel, 0 as activo
				FROM conf_print_adicionales as cpa
					INNER JOIN conf_print as c on cpa.idconf_print=c.idconf_print
				where  c.idorg=".$_SESSION['ido']." and c.idsede=".$_SESSION['idsede']." and cpa.estado=0 ) b
			";
			break;
		case 3012: // load datos del org sede 
			$sql_us = "SELECT s.idorg, se.idsede, s.nombre, s.direccion,s.ruc, s.telefono , se.nombre as sedenombre , se.direccion as sededireccion, se.ciudad as sedeciudad, se.telefono as sedetelefono, se.eslogan, se.authorization_api_comprobante, se.id_api_comprobante, se.facturacion_e_activo, se.logo64, se.ubigeo, se.codigo_del_domicilio_fiscal
							,se.sys_local, se.ip_server_local
					from org as s 
					inner JOIN sede as se on s.idorg = se.idorg 
					where se.idorg = ".$_SESSION['ido']." and se.idsede = ".$_SESSION['idsede'];
			break;
		case 3013: // load datos del org sede 
			$sql_us = "SELECT * from sede where idorg = ".$_SESSION['ido']." and estado=0";
			break;
		case 3014: // load sys const
			$sql_us = "SELECT * FROM sys_const where estado=0";
			break;
	}
	$rows = [];
	$results=$bd->xConsulta2($sql_us);
	while($row = $results->fetch_object()){
		$rows[]=$row;
		}
	return $rows;
}

function xGenerarXI($xip){
	$bd=new xManejoBD("restobar");
	$sql="select idterminal from terminal where identificador='".$xip."'";
	$xid=$bd->xDevolverUnDato($sql);
	$_SESSION['idterminal']=$xid;

	$xidGenerado=$_SESSION['ido'].$_SESSION['idsede'].$xid.'MMXVI';

	$sql="update terminal set xi='".$xidGenerado."' where idterminal=".$xid;
	$bd->xConsulta_NoReturn($sql);
	return $xidGenerado;
}
?>
