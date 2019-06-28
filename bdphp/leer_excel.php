<?php
session_start(); 
header("Cache-Control: no-cache,no-store"); 
include 'ManejoBD.php';
include 'excel_reader.php';     // include the class

$excel = new PhpExcelReader;
/*switch ($_GET['op']) {
  case 1:
    $filexls="../file/".$_POST['f'];
    $excel->read($filexls);    
    sheetDataHabitaciones($excel->sheets[0]);
    break;  
  case 2://productos
    $filexls="../file/".$_POST['f'];
    $excel->read($filexls);    
    sheetDataProductos($excel->sheets[0]);
    break;  
}
*/
 $filexls="../file/".$_POST['f'];
    $excel->read($filexls);    
    sheetDataProductos($excel->sheets[0]);

function sheetDataProductos($sheet) {      
  $bdP=new xManejoBD('restobar');
  $x = 4;    
  $re='';
  $reDP='';
  $fecha_actual="'".$_POST['fecha']."'";
  $IdAlmacen=$_POST['a'];
  
  $sqlProductoUpdate="";
  $sqlProductoDetalleUpdate="";
  $sqlProductoUpdateRow='';
  $sqlProductoDetalle='';

  $idorg = $_SESSION['ido'];
  $idsede = $_SESSION['idsede'];

  while($x <= $sheet['numRows']) {      
    $y = 1;
    $re='';
    $xrow="";    
    $row_cant_almacem="";
    $xrow_producto_new=0;        
    while($y <= $sheet['numCols']) {    
      //descripcion
      $cell = isset($sheet['cells'][$x][$y]) ? $sheet['cells'][$x][$y] : '';                  
      $cell=trim(strtoupper($cell));
      if($cell!=''){        
        $cell=utf8_decode($cell);
        $cell=str_replace('?','N',$cell);        
      }      
      //verificar producto si existe actualiza, en almacen central
      if($y==1){
        $sql="select idproducto as d1 from producto where descripcion='".$cell."' and estado=0 and (idorg=".$idorg." and idsede=".$idsede.")";        
        $idProNewUp=$bdP->xDevolverUnDato($sql);  
        //echo $y.' | '.$idProNewUp;        
        //nuevo
        if($idProNewUp==''){
          $xrow_producto_new=0;
        }else{
          $xrow_producto_new=1;
          $sql="select idproducto as d1 from producto where idproducto=".$idProNewUp;
          $idProDtNewUp=$bdP->xDevolverUnDato($sql);
        }

          //echo $cell.' | '.$idProNewUp.' | '.$xrow_producto_new;
      //echo 'productonew'.$xrow_producto_new;
      }      
      //verificar familia
      if($y==2){        
        $sql="select idproducto_familia as d1 from producto_familia where descripcion='".$cell."' and estado=0 and (idorg=".$idorg." and idsede=".$idsede.")";                
        $idt=$bdP->xDevolverUnDato($sql);        
        //echo ' | verificar_famimila :'.$sql."  |  rspt".$idt;
        if($idt==''){
          $sql="insert into producto_familia(descripcion,idorg,idsede)value('".$cell."',".$idorg.",".$idsede.")";                    
          $bdP->xConsulta_NoReturn($sql); 
	  //$idt=$bdP->xConsulta_NoReturn($sql);    
          // $idt=$bdP->xConsulta_UltimoId($sql);     
          
          // 191118 -- el id es char ej: f1
          $sql="select idproducto_familia as d1 from producto_familia where descripcion='".$cell."' and estado=0 and (idorg=".$idorg." and idsede=".$idsede.")";                
          $idt=$bdP->xDevolverUnDato($sql);  
          
          $cell = $idt;
          //echo ' | insert familia :'.$sql."  |  rspt".$idt;
        } else {
          $cell = $idt;
        }
        
      //echo 'categoria'.$idt;
      }

      //para insertar en almancen_items      
      /*if($y==4){//cantidad
        $row_cant_almacem=$row_cant_almacem."'".$cell."',";
        $xcant_y=$cell;
      }*/
      
      $yapaso=false;
      if($y>=3 || $y<=7){
        if($y==4){//cantidad
          $row_cant_almacem=$row_cant_almacem."'".$cell."',";
          $xcant_y=$cell;
          $yapaso=true;
        }
        if($y==6){//precio x unidad
          $xpu=round(($cell/$xcant_y),3);
          $xrow=$xrow."'".$cell."','".$xpu."',"; 
          $yapaso=true;
        }
        if($yapaso==false){
          $xrow=$xrow."'".$cell."',";  
        }        
      }
      
      


      //si es update producto      
      if($xrow_producto_new==1){
        switch ($y) {
          case 2:$sqlProductoUpdateRow=$sqlProductoUpdateRow." idproducto_familia='".$idt;break."'";                  
          case 3:$sqlProductoUpdateRow=$sqlProductoUpdateRow.", codigo_barra='".$cell."'";break; 
          //case 4:$sqlProductoUpdateRow=$sqlProductoUpdateRow.", canitdad='".$cell."'";break;                  
          case 5:$sqlProductoUpdateRow=$sqlProductoUpdateRow.", stock_minimo='".$cell."'";break;
          case 6:$sqlProductoUpdateRow=$sqlProductoUpdateRow.", precio='".$cell."'";$sqlProductoUpdateRow=$sqlProductoUpdateRow.", precio_unitario='".$xpu."'";break;                  
          case 7:$sqlProductoUpdateRow=$sqlProductoUpdateRow.", precio_venta=".$cell;break;
        }         
      }

      $y++;
    }        
    if($xrow==""){$x++;continue;}
    
    $xrow=substr($xrow, 0, -1);        
    $row_cant_almacem=substr($row_cant_almacem, 0, -1);        

    $re='('.$xrow.','.$idorg.','.$idsede.')';        
    //guardar producto
    //echo 'new:'.$xrow_producto_new;
    if($xrow_producto_new==0){
      $sqlProducto="insert into producto (descripcion,idproducto_familia,codigo_barra,stock_minimo,precio,precio_unitario,precio_venta, idorg, idsede) values ".$re;            
      //echo ' | sql:'.$sqlProducto;
      //echo 'add producto '.$sqlProducto;
      $idProducto=$bdP->xConsulta_UltimoId($sqlProducto);
      $reDP=$reDP.'('.$IdAlmacen.','.$idProducto.','.$row_cant_almacem.','.$fecha_actual.'),';        
      $sqlProductoDetalle=$sqlProductoDetalle."INSERT INTO producto_stock (idproducto, idalmacen, stock ) VALUES (".$idProducto.",".$IdAlmacen.",".$row_cant_almacem."); ";
    }else{
      $sqlProductoUpdate=$sqlProductoUpdate."update producto set ".$sqlProductoUpdateRow." where idproducto=".$idProNewUp."; ";
      //if($idProDtNewUp==''){//si no existe en el alamcen indicado ingresa nuevo        
        //$reDP=$reDP.'('.$IdAlmacen.','.$idProNewUp.','.$row_cant_almacem.','.$fecha_actual.'),';
        $sqlProductoDetalle=$sqlProductoDetalle."INSERT INTO producto_stock (idproducto_stock,idproducto, idalmacen, stock ) VALUES ((SELECT ps.idproducto_stock FROM producto_stock AS ps WHERE ps.idproducto = ".$idProNewUp." AND ps.idalmacen = ".$IdAlmacen."),".$idProNewUp.",".$IdAlmacen.",".$row_cant_almacem.") ON DUPLICATE KEY UPDATE stock=stock+".$row_cant_almacem."; ";
        //$sqlProductoDetalle=$sqlProductoDetalle."UPDATE producto_stock SET stock = stock + ".$row_cant_almacem." WHERE idproducto = ".$IdAlmacen." and idalmacen=".$IdAlmacen."; ";
      //}else{
        //$sqlProductoUpdate=$sqlProductoUpdate."update producto_detalle set ".$sqlProductoDetalleUpdateRow." where idproducto_detalle=".$idProDtNewUp."; ";
      //}
    }
            
    $x++;
  }  
  //$re=substr($re, 0, -1);   
  $reDP=substr($reDP, 0, -1); 
  //$sqlProducto="insert into producto (descripcion,idproducto_categoria,stock_min,costo,pventa,idorg,idsede) values ".$re;  
  //$sqlProductoDetalle='';
  /*if($reDP!=''){
    //$sqlProductoDetalle="insert into almacen_items (idalmacen,idproducto,stock,f_ingreso) values ".$reDP;
    //$sql=$sql."INSERT INTO producto_stock (idproducto_stock,idproducto, idalmacen, stock ) VALUES ((SELECT ps.idproducto_stock FROM producto_stock AS ps WHERE ps.idproducto = ".$item['idproducto']." AND ps.idalmacen = ".$item['idalmacen_a']."),".$item['idproducto'].",".$item['idalmacen_a'].",".$item['cantidad'].") ON DUPLICATE KEY UPDATE stock=stock+".$item['cantidad']."; ";
  } */

  $bdP->xMultiConsulta($sqlProductoUpdate.' '.$sqlProductoDetalle);  
  echo $sqlProductoUpdate.' | '.$sqlProductoDetalle;
}

?>

