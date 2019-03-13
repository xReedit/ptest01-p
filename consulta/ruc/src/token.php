<?php
namespace Sunat;
use Firebase\JWT\JWT;
class Auth
{
    private static $secret_key = 'millave';
    private static $encrypt = ['HS256'];
    private static $aud = null;
    
    

    // function __construct($token) {
    //     echo "llego a token";
    //     // Check($token)
    // }
    // public static function SignIn($data)
    // {
    //     $time = time();
        
    //     $token = array(
    //         'exp' => $time + (60*60),
    //         // 'aud' => self::Aud(),
    //         'data' => $data
    //     );

    //     return JWT::encode($token, self::$secret_key);
    // }
    
    public static function Check($token)
    {
        if(empty($token))
        {
            throw new Exception("Invalid token supplied.");
        }
        
        $decode = JWT::decode(
            $token,
            self::$secret_key,
            self::$encrypt
        );
        
        if(!$decode)
        {
            throw new Exception("Invalid user logged in.");
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
    
    // private static function Aud()
    // {
    //     $aud = '';
        
    //     if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    //         $aud = $_SERVER['HTTP_CLIENT_IP'];
    //     } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    //         $aud = $_SERVER['HTTP_X_FORWARDED_FOR'];
    //     } else {
    //         $aud = $_SERVER['REMOTE_ADDR'];
    //     }
        
    //     $aud .= @$_SERVER['HTTP_USER_AGENT'];
    //     $aud .= gethostname();
        
    //     return sha1($aud);
    // }
}