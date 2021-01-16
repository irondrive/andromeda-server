<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/apps/accounts/auth/Manager.php");

class IMAPExtensionException extends Exceptions\ServerException { public $message = "IMAP_EXTENSION_MISSING"; }

Manager::RegisterAuthType(IMAP::class);

class IMAP extends External
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'protocol' => null,
            'hostname' => null,
            'port' => null,
            'implssl' => null,
            'secauth' => null
        ));
    }
    
    const PROTOCOL_IMAP = 1; const PROTOCOL_POP3 = 2; const PROTOCOL_NNTP = 3;
    
    private const PROTOCOLS = array('imap'=>self::PROTOCOL_IMAP,'pop3'=>self::PROTOCOL_POP3,'nntp'=>self::PROTOCOL_NNTP);
    
    public static function GetPropUsage() : string { return "--protocol imap|pop3|nntp --hostname alphanum [--port int] [--implssl bool] [--secauth bool]"; }
    
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        $protocol = $input->GetParam('protocol', SafeParam::TYPE_ALPHANUM,
            function($val){ return array_key_exists($val, self::PROTOCOLS); });

        return parent::Create($database, $input)->SetScalar('protocol', self::PROTOCOLS[$protocol])
            ->SetScalar('hostname', $input->GetParam('hostname', SafeParam::TYPE_HOSTNAME))
            ->SetScalar('port', $input->TryGetParam('port', SafeParam::TYPE_INT))
            ->SetScalar('implssl', $input->TryGetParam('implssl', SafeParam::TYPE_BOOL) ?? false)
            ->SetScalar('secauth', $input->TryGetParam('secauth', SafeParam::TYPE_BOOL) ?? false);
    }
    
    public function Edit(Input $input) : self
    {
        $hostname = $input->TryGetParam('hostname', SafeParam::TYPE_HOSTNAME);
        $port = $input->TryGetParam('port', SafeParam::TYPE_INT);
        $implssl = $input->TryGetParam('implssl', SafeParam::TYPE_BOOL);
        $secauth = $input->TryGetParam('secauth', SafeParam::TYPE_BOOL);
        
        if ($hostname !== null) $this->SetScalar('hostname', $hostname);
        if ($port !== null) $this->SetScalar('port', $port);
        if ($implssl !== null) $this->SetScalar('implssl', $implssl);
        if ($secauth !== null) $this->SetScalar('secauth', $secauth);
        
        return $this;
    }
    
    private function GetProtocol() : string { return array_flip(self::PROTOCOLS)[$this->GetScalar('protocol')]; }
    
    public function GetClientObject() : array
    {
        return array(
            'protocol' => $this->GetProtocol(),
            'hostname' => $this->GetScalar('hostname'),
            'port' => $this->TryGetScalar('port'),
            'implssl' => $this->GetScalar('implssl'),
            'secauth' => $this->GetScalar('secauth')
        );
    }
    
    public function SubConstruct() : void
    {
        if (!function_exists('imap_open')) throw new IMAPExtensionException();
    }
    
    public function Activate() : self { return $this; }
    
    public function VerifyPassword(string $username, string $password) : bool
    {
        $hostname = $this->GetScalar('hostname'); 
        
        if (($port = $this->TryGetScalar('port')) !== null) $hostname .= ":$port";
        
        $implssl = null; if ($this->GetScalar('implssl')) $implssl = 'ssl';
        $secauth = null; if ($this->GetScalar('secauth')) $secauth = 'secure';
        
        $connectstr = implode("/",array_filter(array($hostname, $this->GetProtocol(), $implssl, $secauth)));

        try { $imap = imap_open("{{$connectstr}}", $username, $password, OP_HALFOPEN); }
        catch (Exceptions\PHPError $e) { 
            foreach (imap_errors() as $err) Main::GetInstance()->PrintDebug($err); return false; }            
        
        $success = boolval($imap);
            
        try { imap_close($imap); } catch (Exceptions\PHPError $e) { return false; } 
        
        return $success;
    }
}
