<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/apps/accounts/auth/Local.php");
require_once(ROOT."/apps/accounts/Group.php"); use Andromeda\Apps\Accounts\Group;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

class IMAPExtensionException extends Exceptions\ServerException   { public $message = "IMAP_EXTENSION_MISSING"; }

class IMAP extends External implements ISource
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'protocol' => null,
            'secure' => null,
            'hostname' => null,
            'port' => null
        ));
    }
    
    const PROTOCOL_IMAP = 1; const PROTOCOL_POP3 = 2;
    
    public function GetProtocolString() : string 
    { 
        switch ($this->GetScalar('protocol'))
        {
            case self::PROTOCOL_IMAP: return 'imap'; break;
            case self::PROTOCOL_POP3: return 'pop3'; break;
            default: return 'imap';
        }
    }
    
    public function GetHostname() : string { return $this->GetScalar('hostname'); }
    public function GetPort() : int { return $this->GetScalar('port'); }
    public function GetUseSSL() : bool { return $this->GetScalar('secure'); }
    
    public function GetAccountGroup() : ?Group { return $this->TryGetObject('default_group'); }
    
    public function __construct(ObjectDatabase $database, array $data)
    {
        parent::__construct($database, $data);
        
        if (!function_exists('imap_open')) throw new IMAPExtensionException();
    }
    
    public function VerifyPassword(string $username, string $password) : bool
    {
        if (strlen($username) == 0 || strlen($password) == 0) return false;

        $hostname = $this->GetHostname(); $port = $this->GetPort(); $proto = $this->GetProtocolString();
        
        $secure = null; if ($this->GetUseSSL()) $secure = "ssl";
        
        $connectstr = implode("/",array_filter(array("$hostname:$port", $proto, $secure)));

        $imap = null; $good = false; try 
        { 
            $good = imap_open("{{$connectstr}}", $username, $password, OP_HALFOPEN) !== false;
        }
        catch (Exceptions\PHPException $e) { return false; } 
        
        try { imap_close($imap); } catch (Exceptions\PHPException $e) { return false; } 
        
        return $good;
    }
}
