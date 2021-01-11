<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\{SafeParam, SafeParams};
require_once(ROOT."/core/exceptions/Exceptions.php");

if (!file_exists(ROOT."/core/libraries/PHPMailer/src/PHPMailer.php")) die("Missing library: PHPMailer - git submodule init/update?\n");
require_once(ROOT."/core/libraries/PHPMailer/src/PHPMailer.php"); use \PHPMailer\PHPMailer;
require_once(ROOT."/core/libraries/PHPMailer/src/Exception.php");
require_once(ROOT."/core/libraries/PHPMailer/src/SMTP.php");

class MailSendException extends Exceptions\ServerException          { public $message = "MAIL_SEND_FAILURE"; }
class EmptyRecipientsException extends Exceptions\ServerException   { public $message = "NO_RECIPIENTS_GIVEN"; }
class InvalidMailTypeServerException extends Exceptions\ServerException { public $message = "INVALID_MAILER_TYPE"; }
class InvalidMailTypeClientException extends Exceptions\ClientErrorException { public $message = "INVALID_MAILER_TYPE"; }

class EmailRecipient
{
    private ?string $name; 
    private string $address;
    
    public function GetName() : ?string { return $this->name; }
    public function GetAddress() : string { return $this->address; }
    public function __construct(string $address, ?string $name = null) {
        $this->address = $address; $this->name = $name; }
        
    public function ToString() : string
    {
        $addr = $this->GetAddress(); $name = $this->GetName();        
        if ($name === null) return $addr;
        else return "$name <$addr>";
    }
}

class Emailer extends StandardObject
{    
    private PHPMailer\PHPMailer $mailer;
    
    const SMTP_TIMEOUT = 15;
    
    const TYPE_PHPMAIL = 0; const TYPE_SENDMAIL = 1; const TYPE_QMAIL = 2; const TYPE_SMTP = 3;
    
    private const MAIL_TYPES = array('phpmail'=>self::TYPE_PHPMAIL, 'sendmail'=>self::TYPE_SENDMAIL, 'qmail'=>self::TYPE_QMAIL, 'smtp'=>self::TYPE_SMTP);
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'type' => null,
            'hosts' => new FieldTypes\JSON(),
            'username' => null,
            'password' => null,
            'from_address' => null,
            'from_name' => null,
            'features__reply' => null
        ));
    }    
    
    public static function GetCreateUsage() : string { return "--type ".implode('|',array_keys(self::MAIL_TYPES))." --from_address email [--from_name name] [--use_reply bool]"; }
    public static function GetCreateUsages() : array { return array("--type smtp ((--host alphanum [--port int] [--proto ssl|tls]) | --hosts json[]) [--username text] [--password raw]"); }
    
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        $mailer = parent::BaseCreate($database);
        
        $type = $input->GetParam('type', SafeParam::TYPE_ALPHANUM);
        if (!array_key_exists($type, self::MAIL_TYPES)) throw new InvalidMailTypeClientException();
        $type = self::MAIL_TYPES[$type];
        
        $mailer->SetScalar('type',$type)
            ->SetScalar('from_address',$input->GetParam('from_address',SafeParam::TYPE_EMAIL))
            ->SetScalar('from_name',$input->TryGetParam('from_name',SafeParam::TYPE_NAME))
            ->SetFeature('reply',$input->TryGetParam('use_reply',SafeParam::TYPE_BOOL));
        
        if ($type == self::TYPE_SMTP)
        {
            $mailer->SetScalar('username',$input->TryGetParam('username',SafeParam::TYPE_TEXT));
            $mailer->SetScalar('password',$input->TryGetParam('password',SafeParam::TYPE_RAW));
            
            if ($input->HasParam('hosts'))
            {
                $hosts = $input->TryGetParam('hosts',SafeParam::TYPE_OBJECT | SafeParam::TYPE_ARRAY);
                $hosts = array_map(function($i){ return self::BuildHostFromParams($i); }, $hosts);
            }
            else $hosts = array(self::BuildHostFromParams($input->GetParams()));
                
            $mailer->SetScalar('hosts',$hosts);
        }
        
        return $mailer;
    }
    
    private static function BuildHostFromParams(SafeParams $input) : string
    {
        $host = $input->GetParam('host',SafeParam::TYPE_ALPHANUM);
        $port = $input->TryGetParam('port',SafeParam::TYPE_INT);
        $proto = $input->TryGetParam('proto',SafeParam::TYPE_ALPHANUM,
            function($d){ return in_array($d,array('tls','ssl')); });
        
        if ($port) $host .= ":$port";
        if ($proto) $host = "$proto://$host";        
        return $host;
    }    
    
    public function GetClientObject() : array
    {
        return array(
            'type' => array_flip(self::MAIL_TYPES)[$this->GetScalar('type')],
            'hosts' => $this->TryGetScalar('hosts'),
            'username' => $this->TryGetScalar('username'),
            'password' => boolval($this->TryGetScalar('password')),
            'from_address' => $this->GetScalar('from_address'),
            'from_name' => $this->TryGetScalar('from_name'),
            'features' => $this->GetAllFeatures(),
            'dates' => $this->GetAllDates()
        );        
    }
    
    public function Activate() : self
    {        
        $mailer = new PHPMailer\PHPMailer(true);
        
        $mailer->Timeout = self::SMTP_TIMEOUT;
        
        $type = $this->GetScalar('type');
        switch ($type)
        {
            case self::TYPE_PHPMAIL: $mailer->isMail(); break;
            case self::TYPE_SENDMAIL: $mailer->isSendmail(); break;
            case self::TYPE_QMAIL: $mailer->isQmail(); break;
            case self::TYPE_SMTP: $mailer->isSMTP(); break;
            default: throw new InvalidMailTypeServerException();
        }
        
        $api = Main::GetInstance();
        $mailer->SMTPDebug = $api->GetDebugState() >= Config::LOG_DEVELOPMENT ? PHPMailer\SMTP::DEBUG_CONNECTION : 0;        
        $mailer->Debugoutput = function($str, $level)use($api){ $api->PrintDebug("PHPMailer $level: ".Utilities::MakePrintable($str)); };
        
        $mailer->setFrom($this->GetScalar('from_address'), $this->TryGetScalar('from_name') ?? 'Andromeda');
        
        if ($type == self::TYPE_SMTP)
        {
            $mailer->Username = $this->TryGetScalar('username');
            $mailer->Password = $this->TryGetScalar('password');
            if ($mailer->Username !== null) $mailer->SMTPAuth = true;

            $mailer->Host = implode(';', $this->TryGetScalar('hosts'));
        }
    
        $this->mailer = $mailer;
        return $this;
    }
    
    public function SendMail(string $subject, string $message, array $recipients, ?EmailRecipient $from = null, bool $isHtml = false, bool $usebcc = false) : void
    {
        if (count($recipients) == 0) throw new EmptyRecipientsException();
        
        $mailer = $this->mailer;
        
        if ($from === null && $this->TryGetFeature('reply') ?? false)
            $mailer->addReplyTo($this->GetScalar('from_address'), $this->TryGetScalar('from_name') ?? 'Andromeda');        
        else if ($from !== null) $mailer->addReplyTo($from->GetAddress(), $from->GetName());
        
        foreach ($recipients as $recipient) 
        {
            if ($usebcc) $mailer->addBCC($recipient->GetAddress(), $recipient->GetName());
            else $mailer->addAddress($recipient->GetAddress(), $recipient->GetName());          
        }
        
        $mailer->Subject = $subject; 
        
        if ($isHtml) $mailer->msgHTML($message);
        else $mailer->Body = $message;
        
        try { if (!$mailer->send()) throw new MailSendException($mailer->ErrorInfo); }
        catch (\Throwable $e) { throw MailSendException::Copy($e); }
        
        $mailer->clearAddresses(); $mailer->clearAttachments();
    }
}
