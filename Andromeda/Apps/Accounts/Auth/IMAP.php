<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Apps/Accounts/Auth/External.php");
require_once(ROOT."/Apps/Accounts/Auth/Manager.php");

/** Exception indicating the IMAP extension does not exist */
class IMAPExtensionException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("IMAP_EXTENSION_MISSING", $details);
    }
}

/** Exception indicating IMAP encountered an error */
class IMAPErrorException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("IMAP_EXTENSION_ERROR", $details);
    }
}

/** Uses an IMAP server for authentication */
class IMAP extends External
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'protocol' => new FieldTypes\IntType(),
            'hostname' => new FieldTypes\StringType(),
            'port' => new FieldTypes\IntType(),
            'implssl' => new FieldTypes\BoolType(), // true to use implicit SSL, false for explicit or none
            'secauth' => new FieldTypes\BoolType() // true to use secure authentication
        ));
    }
    
    private const PROTOCOL_IMAP = 1; 
    private const PROTOCOL_POP3 = 2; 
    private const PROTOCOL_NNTP = 3;
    
    private const PROTOCOLS = array('imap'=>self::PROTOCOL_IMAP,'pop3'=>self::PROTOCOL_POP3,'nntp'=>self::PROTOCOL_NNTP);
    
    public static function GetPropUsage() : string { return "--protocol imap|pop3|nntp --hostname hostname [--port ?uint16] [--implssl bool] [--secauth bool]"; }
    
    public static function Create(ObjectDatabase $database, SafeParams $params) : self
    {
        $protocol = $params->GetParam('protocol')->FromWhitelist(array_keys(self::PROTOCOLS));

        return parent::Create($database, $params)->SetScalar('protocol', self::PROTOCOLS[$protocol])
            ->SetScalar('hostname', $params->GetParam('hostname')->GetHostname())
            ->SetScalar('port', $params->GetOptParam('port',null)->GetNullUint16())
            ->SetScalar('implssl', $params->GetOptParam('implssl',false)->GetBool())
            ->SetScalar('secauth', $params->GetOptParam('secauth',false)->GetBool());
    }
    
    public function Edit(SafeParams $params) : self
    {
        if ($params->HasParam('hostname')) $this->SetScalar('hostname',$params->GetParam('hostname')->GetHostname());
        if ($params->HasParam('port')) $this->SetScalar('port',$params->GetParam('port')->GetNullUint16());
        if ($params->HasParam('implssl')) $this->SetScalar('implssl',$params->GetParam('implssl')->GetBool());
        if ($params->HasParam('secauth')) $this->SetScalar('secauth',$params->GetParam('secauth')->GetBool());
        
        return $this;
    }
    
    private function GetProtocol() : string { return array_flip(self::PROTOCOLS)[$this->GetScalar('protocol')]; }
    
    /**
     * Returns a printable client object for this IMAP
     * @return array `{protocol:enum, hostname:string, port:int, implssl:bool, secauth:bool}`
     */
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
    
    /** Checks for the existence of the IMAP extension */
    public function SubConstruct() : void
    {
        if (!function_exists('imap_open')) throw new IMAPExtensionException();
    }
    
    public function VerifyUsernamePassword(string $username, string $password) : bool
    {
        $hostname = $this->GetScalar('hostname'); 
        
        if (($port = $this->TryGetScalar('port')) !== null) $hostname .= ":$port";
        
        $implssl = null; if ($this->GetScalar('implssl')) $implssl = 'ssl';
        $secauth = null; if ($this->GetScalar('secauth')) $secauth = 'secure';
        
        $connectstr = implode("/",array_filter(array($hostname, $this->GetProtocol(), $implssl, $secauth)));

        try 
        { 
            $imap = imap_open("{{$connectstr}}", $username, $password, OP_HALFOPEN); 
            
            $retval = (bool)($imap); imap_close($imap); return $retval;
        }
        catch (Exceptions\PHPError $e) 
        {
            $errman = ErrorManager::GetInstance(); 
            $errman->LogException($e);
            
            foreach (imap_errors() as $err) 
                $errman->LogException(new IMAPErrorException($err)); 
            
            return false; 
        }
    }
}
