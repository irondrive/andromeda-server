<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/core/ioformat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/core/exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/core/exceptions/Exceptions.php");

use \PHPMailer\PHPMailer; // via autoloader

/** Exception indicating that sending mail failed */
class MailSendException extends Exceptions\ServerException { public $message = "MAIL_SEND_FAILURE"; }

/** Exception indicating that no recipients were given */
class EmptyRecipientsException extends MailSendException { public $message = "NO_RECIPIENTS_GIVEN"; }

/** Exception indicating that the configured mailer driver is invalid */
class InvalidMailTypeException extends MailSendException { public $message = "INVALID_MAILER_TYPE"; }

/** A name and address pair email recipient */
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

/** 
 * A configured email service stored in the database 
 * 
 * Manages PHPMailer configuration and wraps its usage
 */
class Emailer extends StandardObject
{    
    private PHPMailer\PHPMailer $mailer;
    
    private const SMTP_TIMEOUT = 15;
    
    public const TYPE_PHPMAIL = 0; 
    public const TYPE_SENDMAIL = 1; 
    public const TYPE_QMAIL = 2; 
    public const TYPE_SMTP = 3;
    
    private const MAIL_TYPES = array(
        'phpmail'=>self::TYPE_PHPMAIL, 
        'sendmail'=>self::TYPE_SENDMAIL, 
        'qmail'=>self::TYPE_QMAIL, 
        'smtp'=>self::TYPE_SMTP);
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'type' => null,
            'hosts' => new FieldTypes\JSON(), // array of hosts to try, in-order
            'username' => null,
            'password' => null,
            'from_address' => null,
            'from_name' => null,
            'features__reply' => null // if true, add a Reply-To header
        ));
    }    
    
    /** Returns a string with the CLI usage for creating an emailer */
    public static function GetCreateUsage() : string { return "--type ".implode('|',array_keys(self::MAIL_TYPES))." --from_address email [--from_name name] [--use_reply bool]"; }
    
    /** Returns a array of strings with the CLI usage for each specific driver */
    public static function GetCreateUsages() : array { return array("--type smtp ((--host alphanum [--port int] [--proto ssl|tls]) | --hosts json[]) [--username text] [--password raw]"); }
    
    /** Creates a new email backend in the database with the given input (see CLI usage) */
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        $mailer = parent::BaseCreate($database);
        
        $type = $input->GetParam('type', SafeParam::TYPE_ALPHANUM,
            function($type){ return array_key_exists($type, self::MAIL_TYPES); });
        
        $type = self::MAIL_TYPES[$type];
        
        $mailer->SetScalar('type',$type)
            ->SetScalar('from_address',$input->GetParam('from_address',SafeParam::TYPE_EMAIL))
            ->SetScalar('from_name',$input->GetOptParam('from_name',SafeParam::TYPE_NAME))
            ->SetFeature('reply',$input->GetOptParam('use_reply',SafeParam::TYPE_BOOL));
        
        if ($type == self::TYPE_SMTP)
        {
            $mailer->SetScalar('username',$input->GetOptParam('username',SafeParam::TYPE_TEXT));
            $mailer->SetScalar('password',$input->GetOptParam('password',SafeParam::TYPE_RAW,SafeParams::PARAMLOG_NEVER));
            
            if (($hosts = $input->GetParam('hosts',SafeParam::TYPE_OBJECT | SafeParam::TYPE_ARRAY)) !== null)
            {
                $hosts = array_map(function($i){ return self::BuildHostFromParams($i); }, $hosts);
            }
            else $hosts = array(self::BuildHostFromParams($input->GetParams()));
                
            $mailer->SetScalar('hosts',$hosts);
        }
        
        return $mailer;
    }
    
    /** Build a PHPMailer-formatted host string from an input */
    private static function BuildHostFromParams(SafeParams $input) : string
    {
        $host = $input->GetParam('host',SafeParam::TYPE_HOSTNAME);
        $port = $input->GetOptParam('port',SafeParam::TYPE_UINT);
        $proto = $input->GetOptParam('proto',SafeParam::TYPE_ALPHANUM,
            function($d){ return in_array($d,array('tls','ssl')); });
        
        if ($port) $host .= ":$port";
        if ($proto) $host = "$proto://$host";        
        return $host;
    }    
    
    /**
     * Gets the config as a printable client object
     * @return array `{type:string, hosts:?string, username:?string, password:bool,
         from_address:string, from_name:?string, features:{reply:bool}, dates:{created:float}}`
     */
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
    
    /** Initializes the PHPMailer instance */
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
            default: throw new InvalidMailTypeException();
        }
        
        $mailer->SMTPDebug = Main::GetInstance()->GetDebugLevel() >= Config::ERRLOG_DEVELOPMENT ? PHPMailer\SMTP::DEBUG_CONNECTION : 0;    
        
        $mailer->Debugoutput = function($str, $level){ 
            ErrorManager::GetInstance()->LogDebug("PHPMailer $level: ".Utilities::MakePrintable($str)); };
        
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
    
    /**
     * Send an email
     * @param string $subject the subject line of the message
     * @param string $message the body of the message
     * @param array<EmailRecipient> $recipients name/address pairs to mail to
     * @param EmailRecipient $from a different from to send as
     * @param bool $isHtml true if the body is HTML
     * @param bool $usebcc true if the recipients should use BCC
     * @throws EmptyRecipientsException if no recipients were given
     * @throws MailSendException if sending the message fails
     */
    public function SendMail(string $subject, string $message, bool $isHtml, array $recipients, bool $usebcc, ?EmailRecipient $from = null) : void
    {
        if (!count($recipients)) throw new EmptyRecipientsException();
        
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
