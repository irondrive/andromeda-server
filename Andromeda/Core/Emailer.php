<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/ErrorManager.php"); use Andromeda\Core\Exceptions\ErrorManager;
require_once(ROOT."/Core/Exceptions/Exceptions.php");

use \PHPMailer\PHPMailer; // via autoloader

/** Exception indicating that sending mail failed */
class MailSendException extends Exceptions\ServerException { public $message = "MAIL_SEND_FAILURE"; use Exceptions\Copyable; }

/** Exception indicating that no recipients were given */
class EmptyRecipientsException extends MailSendException { public $message = "NO_RECIPIENTS_GIVEN"; }

/** Exception indicating that the configured mailer driver is invalid */
class InvalidMailTypeException extends MailSendException { public $message = "INVALID_MAILER_TYPE"; }

/** A name and address pair email recipient */
final class EmailRecipient
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
final class Emailer extends BaseObject
{
    use TableNoChildren;
    
    protected const IDLength = 4;
    
    private PHPMailer\PHPMailer $mailer;
    
    private const SMTP_TIMEOUT = 15;
    private const DEFAULT_FROM = 'Andromeda';
    
    public const TYPE_PHPMAIL = 0; 
    public const TYPE_SENDMAIL = 1; 
    public const TYPE_QMAIL = 2; 
    public const TYPE_SMTP = 3;
    
    private const MAIL_TYPES = array(
        'phpmail'=>self::TYPE_PHPMAIL, 
        'sendmail'=>self::TYPE_SENDMAIL, 
        'qmail'=>self::TYPE_QMAIL, 
        'smtp'=>self::TYPE_SMTP);
    
    /** Date of object creation */
    private FieldTypes\Date $date_created;
    /** Type of emailer (see usage) */
    private FieldTypes\IntType $type;
    /** Array of hostnames to try, in order */
    private FieldTypes\NullJsonArray $hosts;
    /** Optional SMTP username */
    private FieldTypes\NullStringType $username;
    /** Optional SMTP password */
    private FieldTypes\NullStringType $password;
    /** From email address */
    private FieldTypes\StringType $from_address;
    /** Optional from email name */
    private FieldTypes\NullStringType $from_name;
    /** If true, add a Reply-To header */
    private FieldTypes\NullBoolType $use_reply;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Date('date_created');
        $this->type = $fields[] =         new FieldTypes\IntType('type');
        $this->hosts = $fields[] =        new FieldTypes\NullJsonArray('hosts');
        $this->username = $fields[] =     new FieldTypes\NullStringType('username');
        $this->password = $fields[] =     new FieldTypes\NullStringType('password');
        $this->from_address = $fields[] = new FieldTypes\StringType('from_address');
        $this->from_name = $fields[] =    new FieldTypes\NullStringType('from_name');
        $this->use_reply = $fields[] =    new FieldTypes\NullBoolType('use_reply');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /** Returns a string with the CLI usage for creating an emailer */
    public static function GetCreateUsage() : string { return "--type ".implode('|',array_keys(self::MAIL_TYPES))." --from_address email [--from_name name] [--use_reply bool]"; }
    
    /** Returns a array of strings with the CLI usage for each specific driver */
    public static function GetCreateUsages() : array { return array("--type smtp ((--host hostname [--port uint16] [--proto ssl|tls]) | --hosts json[]) [--username text] [--password raw]"); }
    
    /** Creates a new email backend in the database with the given input (see CLI usage) */
    public static function Create(ObjectDatabase $database, Input $input) : self
    {
        $mailer = parent::BaseCreate($database);
        
        $type = $input->GetParam('type', SafeParam::TYPE_ALPHANUM, 
            SafeParams::PARAMLOG_ONLYFULL, array_keys(self::MAIL_TYPES));
        
        $type = self::MAIL_TYPES[$type];
        $mailer->type->SetValue($type);
        
        $mailer->from_name->SetValue($input->GetOptParam('from_name',SafeParam::TYPE_NAME));
        $mailer->from_address->SetValue($input->GetParam('from_address',SafeParam::TYPE_EMAIL));
        $mailer->use_reply->SetValue($input->GetOptParam('use_reply',SafeParam::TYPE_BOOL));

        if ($type == self::TYPE_SMTP)
        {
            $mailer->username->SetValue($input->GetOptParam('username',SafeParam::TYPE_TEXT));
            $mailer->password->SetValue($input->GetOptParam('password',SafeParam::TYPE_RAW,SafeParams::PARAMLOG_NEVER));
            
            if (($hosts = $input->GetOptParam('hosts',SafeParam::TYPE_OBJECT | SafeParam::TYPE_ARRAY)) !== null)
            {
                $hosts = array_map(function($i){ return self::BuildHostFromParams($i); }, $hosts);
            }
            else $hosts = array(self::BuildHostFromParams($input->GetParams()));
                
            $mailer->hosts->SetArray($hosts);
        }
        
        return $mailer;
    }
    
    /** Returns all available Emailer objects */
    public static function LoadAll(ObjectDatabase $database) : array
    {
        return $database->LoadObjectsByQuery(static::class, new QueryBuilder());
    }
    
    /** Tries to load an Emailer object by its ID */
    public static function TryLoadByID(ObjectDatabase $database, string $id) : ?self
    {
        return $database->TryLoadByID(self::class, $id);
    }
    
    public function Delete() : void { parent::Delete(); }
    
    /** Build a PHPMailer-formatted host string from an input */
    private static function BuildHostFromParams(SafeParams $input) : string
    {
        $host = $input->GetParam('host',SafeParam::TYPE_HOSTNAME);
        $port = $input->GetOptParam('port',SafeParam::TYPE_UINT16);
        $proto = $input->GetOptParam('proto',SafeParam::TYPE_ALPHANUM,
            SafeParams::PARAMLOG_ONLYFULL, array('tls','ssl'));
        
        if ($port) $host .= ":$port";
        if ($proto) $host = "$proto://$host";        
        return $host;
    }
    
    /** Returns whether or not to use the server from address for reply-to */
    private function GetUseReply() : bool { return $this->use_reply->TryGetValue() ?? false; }
    
    /**
     * Gets the config as a printable client object
     * @return array `{id:string, type:enum, hosts:?string, username:?string, password:bool,
         from_address:string, from_name:?string, use_reply:bool, date_created:float}`
     */
    public function GetClientObject() : array
    {
        return array(
            'id'           => $this->ID(),
            'date_created' => $this->date_created->GetValue(),
            'type' =>         array_flip(self::MAIL_TYPES)[$this->type->GetValue()],
            'hosts' =>        $this->hosts->TryGetArray(),
            'username' =>     $this->username->TryGetValue(),
            'password' =>     (bool)($this->password->TryGetValue()),
            'from_address' => $this->from_address->GetValue(),
            'from_name' =>    $this->from_name->TryGetValue(),
            'use_reply' =>    $this->use_reply->TryGetValue()
        );        
    }
    
    /** Initializes the PHPMailer instance */
    public function Activate() : self
    {        
        $mailer = new PHPMailer\PHPMailer(true);
        
        $mailer->Timeout = self::SMTP_TIMEOUT;
        
        switch ($type = $this->type->GetValue())
        {
            case self::TYPE_PHPMAIL: $mailer->isMail(); break;
            case self::TYPE_SENDMAIL: $mailer->isSendmail(); break;
            case self::TYPE_QMAIL: $mailer->isQmail(); break;
            case self::TYPE_SMTP: $mailer->isSMTP(); break;
            default: throw new InvalidMailTypeException();
        }
        
        $mailer->SMTPDebug = Main::GetInstance()->GetDebugLevel() >= Config::ERRLOG_DETAILS ? PHPMailer\SMTP::DEBUG_CONNECTION : 0;    
        
        $mailer->Debugoutput = function($str, $level){ 
            if (!Utilities::isUTF8($str)) $str = base64_encode($str);
            ErrorManager::GetInstance()->LogDebugInfo("PHPMailer $level: $str"); };
        
        $mailer->setFrom(
            $this->from_address->GetValue(), 
            $this->from_name->TryGetValue() ?? self::DEFAULT_FROM);
        
        if ($type == self::TYPE_SMTP)
        {
            $mailer->Username = $this->username->TryGetValue();
            $mailer->Password = $this->password->TryGetValue();
            if ($mailer->Username !== null) $mailer->SMTPAuth = true;

            $mailer->Host = implode(';', $this->hosts->TryGetArray());
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
        
        if ($from === null && $this->GetUseReply())
        {
            $mailer->addReplyTo(
                $this->from_address->GetValue(),
                $this->from_name->TryGetValue() ?? self::DEFAULT_FROM); 
        }
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
        catch (\Throwable $e) { throw MailSendException::Append($e); }
        
        $mailer->clearAddresses(); $mailer->clearAttachments();
    }
}
