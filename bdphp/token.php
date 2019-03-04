<?php
    include_once  "ManejoBD.php";    
    class xAuth
    {   
        public static function getData()
        {
            $bd = new xManejoBD("restobar");
            $sql="select * from org where idorg=".$_SESSION['ido'];
            $org = $bd->xConsulta3($sql);

            $sql="select * from sede where idsede=".$_SESSION['idsede'];
			$sede = $bd->xConsulta3($sql);
            return static::encode($org.$sede);
        }

        public static function encode($data)
        {
            return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
        }

        public static function Verify($data, $dataMatch)
        {
            $data = str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
            if ($data === $dataMatch){
                return true;
            } else {return false;}
            
        }
    }
?>