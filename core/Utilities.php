<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;

class JSONException extends Exceptions\ServerException {
    public function __construct() {
        $this->code = json_last_error();
        $this->details = json_last_error_msg(); } }
        
class JSONEncodingException extends JSONException { public $message = "JSON_ENCODE_FAIL"; }
class JSONDecodingException extends JSONException { public $message = "JSON_DECODE_FAIL"; }

class Utilities
{   
    public static function Random(?int $length = null) : string
    {
        if ($length === null) $length = BaseObject::IDLength;
        $chars = "0123456789abcdefghijkmnopqrstuvwxyz_"; $string = ""; $range = strlen($chars)-1;
        for ($i = 0; $i < $length; $i++) { $string .= $chars[random_int(0, $range)]; }; return $string;        
    }
    
    public static function JSONEncode($data) : string
    {
        if (!($data = json_encode($data, JSON_NUMERIC_CHECK))) {
            throw new JSONEncodingException(); };
        return $data;
    }
    
    public static function JSONDecode(string $data)
    {
        if (!($data = json_decode($data, true))) {
            throw new JSONDecodingException(); };
        return $data;
    }
}
