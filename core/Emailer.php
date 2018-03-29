<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

if (!file_exists(ROOT."/core/libraries/PHPMailer/src/PHPMailer.php")) die("Missing library:PHPMailer - git submodule init/update?");
require_once(ROOT."/core/libraries/PHPMailer/src/PHPMailer.php"); use PHPMailer\PHPMailer\PHPMailer;
require_once(ROOT."/core/libraries/PHPMailer/src/Exception.php");
require_once(ROOT."/core/libraries/PHPMailer/src/SMTP.php"); 

class PHPMailException extends Exceptions\ServerException           { public $message = "PHPMAIL_FAILURE"; }
class EmptyRecipientsException extends Exceptions\ServerException   { public $message = "NO_RECIPIENTS_GIVEN"; }

class EmailRecipient
{
    private $name = null; private $address = null;
    public function GetAddress() : string { return $this->address; }
    public function GetName() : ?string { return $this->name; }
    public function __construct(string $address, string $name = null) {
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
        
        if (!mail($to, $subject, $message, implode("\r\n", $headers))) throw new PHPMailException();
    }
}

class FullEmailer extends StandardObject implements Emailer
{    
    private $mail = null;
    
    const MODE_SSL = 1; const MODE_TLS = 2;
    
    public function __construct(ObjectDatabase $database, array $data)
    {
        parent::__construct($database, $data);
        
        $mailer = new PHPMailer(true); $mailer->isSMTP();
        
        $mailer->Host = $this->GetScalar('hostname');
        $mailer->Port = $this->GetScalar('port');
        
        if ($this->TryGetScalar('username') !== null)
        {
            $mailer->SMTPAuth = true;
            $mailer->Username = $this->GetScalar('username');
            $mailer->Password = $this->GetScalar('password');
        }
        
        $secure = $this->TryGetScalar('secure');
        if ($secure == self::MODE_SSL) $mailer->SMTPSecure = 'ssl';
        else if ($secure == self::MODE_TLS) $mailer->SMTPSecure = 'tls';
        
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
        
        $mailer->send(); 
    }
}
