<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require ROOT."/core/libraries/PHPMailer/src/Exception.php"; use PHPMailer\PHPMailer\Exception;
require ROOT."/core/libraries/PHPMailer/src/PHPMailer.php"; use PHPMailer\PHPMailer\PHPMailer;
require ROOT."/core/libraries/PHPMailer/src/SMTP.php"; 

/* TODO - note that this class is mostly a placeholder/demo */

class Emailer extends StandardObject
{
    public static function PHPMail(string $recipient, string $subject, string $message)
    {
        // TODO special care must be taken to send html email (content type header, etc)
        mail($recipient, $subject, $message);
    }
    
    public function SendMail(string $recipient, string $subject, string $message)
    {
        // TODO special care must be taken to send html email (content type header, etc)
        // TODO should we support sending html files directly?
        // $mail->isHTML(true);
        // TODO there is a whole lot more of this interface
        
        $mail = new PHPMailer(true); $mail->isSMTP();         

        $mail->Host = $this->GetScalar('hostname');
        $mail->Port = $this->GetScalar('port');
        
        if ($this->GetScalar('username') !== null)
        {
            $mail->SMTPAuth = true;
            $mail->Username = $this->GetScalar('username');
            $mail->Password = $this->GetScalar('password');
        }
        
        $secure = $this->GetScalar('secure');
        if ($secure == 1) $mail->SMTPSecure = 'ssl';
        else if ($secure == 2) $mail->SMTPSecure = 'tls';
        
        $mail->setFrom($this->GetScalar('from_address'), $this->GetScalar('from_name'));
        
        $mail->addAddress($recipient);        
        
        $mail->Subject = $subject; $mail->Body = $message;
        
        $mail->send();       
    }
}
