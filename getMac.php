<?php
/*$ip=getenv("REMOTE_ADDR");
 
//Client MAC Addresse
echo"
IP-Adresse:$ip<br />
MAC-Adresse:";
$cmd = "arp $ip | grep $ip | awk '{ print $3 }'";
system($cmd);
*/
$ip='';
if (isset($_SERVER["HTTP_CLIENT_IP"]))
        {
            $ip=$_SERVER["HTTP_CLIENT_IP"];
        }
        elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
        {
            $ip= $_SERVER["HTTP_X_FORWARDED_FOR"];
        }
        elseif (isset($_SERVER["HTTP_X_FORWARDED"]))
        {
            $ip= $_SERVER["HTTP_X_FORWARDED"];
        }
        elseif (isset($_SERVER["HTTP_FORWARDED_FOR"]))
        {
            $ip= $_SERVER["HTTP_FORWARDED_FOR"];
        }
        elseif (isset($_SERVER["HTTP_FORWARDED"]))
        {
            $ip= $_SERVER["HTTP_FORWARDED"];
        }
        else
        {
            $ip= $_SERVER["REMOTE_ADDR"];
        }

//$nombre_host = gethostbyaddr($ip);

echo $ip; //.'  nombre_host: '.$nombre_host;

?>