<?php
namespace Reniec;
use Firebase\JWT\JWT;
class Token
{
    private static $secret_key = 'ParaQueTeTraje159159';
	private static $encrypt = ['HS256'];
	private static $aud = null;

    public static function Check($token)
    {        
			if(empty($token))
			{
				throw new Exception("Invalid token supplied.");
				return false;
			}
			
			try {
				
				$decode = JWT::decode(
					$token,
					self::$secret_key,
					self::$encrypt
				);
				
			} catch (\Exception $e) {
				// throw new Exception("Invalid user logged in.");
				return false;
			}
			
			if(!$decode)
			{
				throw new Exception("Invalid user logged in.");
				return false;
			} else {
				return true;
			}
    }
    
    public static function GetData($token)
    {
        return JWT::decode(
            $token,
            self::$secret_key,
            self::$encrypt
        )->data;
    }
    
}