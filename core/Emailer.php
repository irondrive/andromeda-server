<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

if (!file_exists(ROOT."/core/libraries/PHPMailer/src/PHPMailer.php")) die("Missing library: PHPMailer - git submodule init/update?\n");
require_once(ROOT."/core/libraries/PHPMailer/src/PHPMailer.php"); 
require_once(ROOT."/core/libraries/PHPMailer/src/SMTP.php");
require_once(ROOT."/core/libraries/PHPMailer/src/Exception.php"); use \PHPMailer\PHPMailer;

class MailSendException extends Exceptions\ServerException          { public $message = "MAIL_SEND_FAILURE"; }
class EmptyRecipientsException extends Exceptions\ServerException   { public $message = "NO_RECIPIENTS_GIVEN"; }

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

interface Emailer
{
    public function SendMail(string $subject, string $message, array $recipients, ?EmailRecipient $from = null, bool $usebcc = false) : void;
}

class BasicEmailer implements Emailer
{
    public function SendMail(string $subject, string $message, array $recipients, ?EmailRecipient $from = null, bool $usebcc = false) : void
    {
        if (count($recipients) == 0) throw new EmptyRecipientsException(); 
        
        $recipients = implode(array_map(function($r){ return $r->ToString(); }, $recipients), ', ');
        
        $headers = array();        
        if ($from) array_push($headers, "Reply-To: ".$from->ToString());
        if ($usebcc) array_push($headers, "BCC: $recipients");
        
        $to = !$usebcc ? $recipients : "undisclosed-recipients:;";
        
        if (!mail($to, $subject, $message, implode("\r\n", $headers))) throw new MailSendException();
    }
}

class FullEmailer extends StandardObject implements Emailer
{    
    private $mail = null;
    
    const MODE_SMTPS = 1; const MODE_STARTTLS = 2;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'hostname' => null,
            'port' => null,
            'username' => null,
            'password' => null,
            'secure' => null,
            'from_address' => null,
            'from_name' => null
        ));
    }
    
    public function __construct(ObjectDatabase $database, array $data)
    {
        parent::__construct($database, $data);
        
        $mailer = new PHPMailer\PHPMailer(true); $mailer->isSMTP();
        
        $api = Main::GetInstance();
        $mailer->SMTPDebug = $api->GetConfig()->GetDebugLogLevel() ? PHPMailer\SMTP::DEBUG_CONNECTION+1 : 0;        
        $mailer->Debugoutput = function($str, $level)use($api){ $api->PrintDebug("PHPMailer $level: $str"); };
        
        $mailer->Host = $this->GetScalar('hostname');
        $mailer->Port = $this->GetScalar('port');
        
        if ($this->TryGetScalar('username') !== null)
        {
            $mailer->SMTPAuth = true;
            $mailer->Username = $this->GetScalar('username');
            $mailer->Password = $this->GetScalar('password');
        }
        
        $secure = $this->TryGetScalar('secure');
        if ($secure === self::MODE_SMTPS) $mailer->SMTPSecure = PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        if ($secure === self::MODE_STARTTLS) $mailer->SMTPSecure = PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        
        $mailer->setFrom($this->GetScalar('from_address'), $this->GetScalar('from_name'));
        
        $this->mailer = $mailer;
    }
    
    public function SendMail(string $subject, string $message, array $recipients, ?EmailRecipient $from = null, bool $usebcc = false) : void
    {
        if (count($recipients) == 0) throw new EmptyRecipientsException();
        
        $mailer = $this->mailer;        
        
        if ($from !== null) $mailer->addReplyTo($from->GetAddress(), $from->GetName());
        
        foreach ($recipients as $recipient) 
        {
            if ($usebcc) $mailer->addBCC($recipient->GetAddress(), $recipient->GetName());
            else $mailer->addAddress($recipient->GetAddress(), $recipient->GetName());          
        }                 
 
        $mailer->Subject = $subject; $mailer->Body = $message;
        
        try { if (!$mailer->send()) throw new MailSendException($mailer->ErrorInfo); }
        catch (PHPMailer\Exception $e) { throw new MailSendException($e->getMessage()); }
    }
}
