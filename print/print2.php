<?php
require __DIR__ . '/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

///$logo_post=$_POST['logo'];
$ArrayItem=$_POST['ArrayItem'];
$logo_post='logo_sancarlos.jpg';
$connector = new NetworkPrintConnector("192.168.1.80");	    	   
$printer = new Printer($connector);

$items = array(
    new item("Example item #1", "4.00"),
    new item("Another thing", "3.50"),
    new item("Something else", "1.00"),
    new item("A final item", "4.45"),
);
$subtotal = new item('Subtotal', '12.95');
$tax = new item('A local tax', '1.30');
$total = new item('Total', '14.25', true);
/* Date is kept the same for testing */
// $date = date('l jS \of F Y h:i:s A');
$date = "Monday 6th of April 2015 02:56:25 PM";

/* Start the printer */
$printer = new Printer($connector);

/* Print top logo */
if($logo_post!=''){
	$logo = EscposImage::load($logo_post, false);
	$printer -> setJustification(Printer::JUSTIFY_CENTER);
	$printer -> graphics($logo);
}

/* ENCABEZADO */
$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer -> text("MESA 01\n");
$printer -> selectPrintMode();
$printer -> text("------------------------------------------------\n");
$printer -> text("DR. AAAA\n");
$printer -> feed();

/* BODY - TITULO TIPO CONSUMO*/
$printer -> setEmphasis(true);
$printer -> text("CONSUMIR EN EL LOCAL\n");
$printer -> text("------------------------------------------------\n");
$printer -> setEmphasis(false);

/* Items */
$printer -> setJustification(Printer::JUSTIFY_LEFT);
$printer -> setEmphasis(true);
$printer -> text(new item('', '$'));
$printer -> setEmphasis(false);
foreach ($items as $item) {
    $printer -> text($item);
}
$printer -> setEmphasis(true);
$printer -> text($subtotal);
$printer -> setEmphasis(false);
$printer -> feed();

/* Tax and total */
$printer -> text($tax);
$printer -> selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
$printer -> text($total);
$printer -> selectPrintMode();

/* Footer */
$printer -> feed(2);
$printer -> setJustification(Printer::JUSTIFY_CENTER);
$printer -> text("Thank you for shopping at ExampleMart\n");
$printer -> text("For trading hours, please visit example.com\n");
$printer -> feed(2);
$printer -> text($date . "\n");

/* Cut the receipt and open the cash drawer */
$printer -> cut();
$printer -> pulse();

$printer -> close();

/* A wrapper to do organise item names & prices into columns */
class item
{
    private $name;
    private $price;
    private $dollarSign;

    public function __construct($name = '', $price = '', $dollarSign = false)
    {
        $this -> name = $name;
        $this -> price = $price;
        $this -> dollarSign = $dollarSign;
    }

    public function __toString()
    {
        $rightCols = 10;
        $leftCols = 38;
        if ($this -> dollarSign) {
            $leftCols = $leftCols / 2 - $rightCols / 2;
        }
        $left = str_pad($this -> name, $leftCols) ;

        $sign = ($this -> dollarSign ? 'S/. ' : '');
        $right = str_pad($sign . $this -> price, $rightCols, ' ', STR_PAD_LEFT);
        return "$left$right\n";
    }
}
?>