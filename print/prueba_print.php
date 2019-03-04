<?php	
	require __DIR__ . '/autoload.php';
	use Mike42\Escpos\Printer;
	use Mike42\Escpos\EscposImage;
	use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;	
	use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
	//include 'autoload.php';
	$val_op=$_GET['op'];
	//$val_imp=$_GET['i'];
	//$pos_=strrpos($val_imp,'//');
	//if($pos_==false){$val_op=1;}else{$val_op=2;}
	if($val_op==1){		
		/* Most printers are open on port 9100, so you just need to know the IP 
		 * address of your receipt printer, and then fsockopen() it on that port.
		 */
		try {
		    $connector = new NetworkPrintConnector($val_imp);	 
		    $profile = DefaultCapabilityProfile::getInstance();   	   
		    $printer = new Printer($connector,$profile);
		    $printer -> text("HOLA COLITAS POTO\n");
		    $printer -> cut();
		    
		    /* Close printer */
		    $printer -> close();
		} catch (Exception $e) {
		    echo "Couldn't print to this printer: " . $e -> getMessage() . "\n";
		}
	}

	if($val_op==2){		
		try {					

		    // Enter the share name for your USB printer here
		    //$connector = null;
		    $connector = new WindowsPrintConnector("smb://desa1/print_a");			    										
		    //$connector = new WindowsPrintConnector($val_imp);		    		    
		    //$connector = new WindowsPrintConnector("Receipt Printer");
		    /* Print a "Hello world" receipt" */
		    /* Underline */
			/*$fonts = array(
				Printer::FONT_A,
				Printer::FONT_B,
				Printer::FONT_C);
			for($i = 0; $i < count($fonts); $i++) {
				$printer -> setFont(Printer::FONT_B);
				$printer -> text("The quick brown fox jumps over the lazy dog\n");
			}*/

			$printer = new Printer($connector);			
			//$printer -> setFont(Printer::FONT_B);
			//$printer -> setTextSize(0,1);

			//margin
			//$connector->write(Printer::GS.'L'.intLowHigh(64, 2));
			//

			//$connector->write(Printer::ESC."%".chr(1));
			
			//$connector->write(Printer::GS.'SP'.chr(255));
			//$printer -> setFont(Printer::FONT_A);			
			//To reduce the left margin
			//$connector->write(Printer::GS.'L'.chr(2).chr(0));
			//$connector->write(Printer::GS.'L'.intLowHigh(0, 2));			
			
			//$connector->write(self::ESC . "W".self::NUL.self::NUL." ".self::NUL.self::NUL." \xBa".self::NUL." \x2c\x01");
			//$connector->write(self::GS."\ ".self::DLE.self::NUL);

			//$connector->write(EscPos::CTL_ESC . "@");

			//$var_margen_iz=50;
			//$var_size_font=1;
			//$var_margen_iz=intLowHigh($var_margen_iz, 2);			

			//$connector->write(Printer::GS.'L'.$var_margen_iz);			
			$printer -> setFont(Printer::FONT_A);			

						
			$printer -> text("------------------------------------------------\n");						
			$printer -> text("The quick brown fox jumps over the lazy dog\n");			
			
			$printer -> cut();
			//$printer -> close();

		} catch (Exception $e) {
		    print "Couldn't print to this printer: aAAAAA " . $e -> getMessage() . "\n";
		}
	}

function intLowHigh($input, $length)
{
    // Function to encode a number as two bytes. This is straight out of Mike42\Escpos\Printer
    $outp = "";
    for ($i = 0; $i < $length; $i++) {
        $outp .= chr($input % 256);
        $input = (int)($input / 256);
    }
    return $outp;
}

?>