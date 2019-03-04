<?php	
	require __DIR__ . '/autoload.php';
	//include 'autoload.php';
	use Mike42\Escpos\Printer;
	use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
	/* Most printers are open on port 9100, so you just need to know the IP 
	 * address of your receipt printer, and then fsockopen() it on that port.
	 */
	try {
	    $connector = new NetworkPrintConnector("192.168.1.80");	    	   
	    $printer = new Printer($connector);
	    $printer -> text("HOLA COLITAS POTO\n");
	    $printer -> cut();
	    
	    /* Close printer */
	    $printer -> close();
	} catch (Exception $e) {
	    echo "Couldn't print to this printer: " . $e -> getMessage() . "\n";
	}
?>