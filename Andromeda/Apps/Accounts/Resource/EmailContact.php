<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\{Emailer, EmailRecipient};

use Andromeda\Apps\Accounts\Account;

class EmailContact extends Contact
{
    /**
     * Sends a message to the given array of contacts
     * @param string $subject subject line
     * @param string $html html message (optional)
     * @param string $plain plain text message
     * @param array<Contact> $recipients array of contacts
     * @param Account $from account sending the message
     * @param bool $bcc true to use BCC for recipients
     */
    public static function SendMessageMany(string $subject, ?string $html, string $plain, array $recipients, bool $bcc, ?Account $from = null) : void
    {
        $message = $html ?? $plain;
        $ishtml = ($html !== null);
        
        $emails = array_filter($recipients, function(Contact $contact){ 
            return $contact instanceof self; });
        
        if (count($emails) === 0) return;
        $mailer = Emailer::LoadAny(array_values($emails)[0]->database);
        
        $recipients = array_map(function(self $contact){
            return $contact->GetAsEmailRecipient(); }, $emails);
        
        if ($from !== null) $from = $from->GetEmailFrom();
        
        $mailer->SendMail($subject, $message, $ishtml, $recipients, $bcc, $from);
    }

    /** Returns this contact as an Emailer recipient */
    public function GetAsEmailRecipient() : EmailRecipient // TODO should not really be public (take email-specific things out of Account)
    {
        return new EmailRecipient($this->GetAddress(), $this->GetAccount()->GetDisplayName());
    }
}
