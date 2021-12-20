<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Apps/Accounts/Account.php");
require_once(ROOT."/Apps/Accounts/AuthObject.php");

require_once(ROOT."/Core/Main.php"); use Andromeda\Core\Main;
require_once(ROOT."/Core/Emailer.php"); use Andromeda\Core\EmailRecipient;

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating that a valid contact value was not given */
class ContactNotGivenException extends Exceptions\ClientErrorException { public $message = "CONTACT_NOT_GIVEN"; }

/** Exception indicating that this contact is not an email address */
class ContactNotEmailException extends Exceptions\ServerException { public $message = "CONTACT_NOT_EMAIL"; }

/** Pair representing a contact type and value */
class ContactInfo 
{ 
    public int $type; public string $info;
    public function __construct(int $type, string $info){
        $this->type = $type; $this->info = $info; } 
}

abstract class ContactBase extends AuthObject 
{ 
    use FullAuthKey; protected static function GetFullKeyPrefix() : string { return "ci"; }
}

/** An object describing a contact method for a user account */
class Contact extends ContactBase
{    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'type' => null, // type of contact
            'info' => null, // value of the contact
            'valid' => new FieldTypes\Scalar(false), // true if it has been validated
            'usefrom' => null, // true if this should be used as from
            'public' => new FieldTypes\Scalar(false), // true if this should be searchable
            'account' => new FieldTypes\ObjectRef(Account::class, 'contacts')
        ));
    }
    
    protected const KEY_LENGTH = 8;
    
    public function CheckFullKey(string $code) : bool
    {
        $retval = parent::CheckFullKey($code);
        
        if ($retval) 
        {
            $this->GetAccount()->NotifyValidContact();
            
            $this->SetIsValid()->ChangeAuthKey(null);
        }
        
        return $retval;
    }
    
    public const TYPE_EMAIL = 1;
    
    private const TYPES = array(self::TYPE_EMAIL=>'email');
    
    /**
     * Attemps to load a from contact for the given account
     * @param ObjectDatabase $database database reference
     * @param Account $account account of interest
     * @return static|NULL contact to use as "from" or none if not set
     */
    public static function TryLoadAccountFromContact(ObjectDatabase $database, Account $account) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('account',$account->ID()),$q->IsTrue('usefrom'));
        
        return static::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
   /** Returns the contact object matching the given value and type, or null */
    public static function TryLoadByInfoPair(ObjectDatabase $database, ContactInfo $input) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('info',$input->info),$q->Equals('type',$input->type));
        
        return static::TryLoadUniqueByQuery($database, $q->Where($w));
    }
    
    /** Attempts to load a contact by the given ID for the given account */
    public static function TryLoadByAccountAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('id',$id),$q->Equals('account',$account->ID()));
        
        return static::TryLoadUniqueByQuery($database, $q->Where($w));
    }

    /**
     * Returns all accounts matching the given contact value
     * @param ObjectDatabase $database database reference
     * @param string $info contact value to match (wildcard)
     * @param int $limit return up to this many
     * @return array Account
     * @see Account::GetClientObject()
     */
    public static function LoadAccountsMatchingValue(ObjectDatabase $database, string $info, int $limit) : array
    {
        $q = new QueryBuilder(); $info = QueryBuilder::EscapeWildcards($info).'%'; // search by prefix
        
        $w = $q->And($q->Like('info',$info,true),$q->IsTrue('public'));
        
        $loaded = static::LoadByQuery($database, $q->Where($w)->Limit($limit));
        
        $retval = array(); foreach ($loaded as $obj){ $acct = $obj->GetAccount(); $retval[$acct->ID()] = $acct; }; return $retval;
    }
    
    /** Returns the enum describing the type of contact */
    protected function GetType() : int { return $this->GetScalar('type'); }
    
    /** Returns the actual contact value */
    protected function GetInfo() : string { return $this->GetScalar('info'); }
    
    /** Returns true if the contact has been validated */
    public function GetIsValid() : bool { return $this->GetScalar('valid'); }
    
    /** Sets whether the contact has been validated */
    protected function SetIsValid() : self { return $this->SetScalar('valid',true); }
    
    /** Returns whether or not the contact is public */
    public function getIsPublic() : bool { return $this->GetScalar('public'); }
    
    /** Sets whether this contact should be publically searchable */
    public function setIsPublic(bool $val) : self { return $this->SetScalar('public',$val); }
    
    /** Sets whether this contact should be used as from (can only be one) */
    public function setUseFrom(bool $val) : self
    {
        if ($val)
        {
            $old = static::TryLoadAccountFromContact($this->database, $this->GetAccount());
            
            if ($old !== null) $old->setUseFrom(false);
        }
        else $val = null;
        
        return $this->SetScalar('usefrom',$val);
    }
    
    /** Returns the account that owns the contact */
    public function GetAccount() : Account { return $this->GetObject('account'); }

    /**
     * Creates a new contact
     * @param ObjectDatabase $database database reference
     * @param Account $account account of contact 
     * @param ContactInfo $input type/value pair
     * @param bool $verify true to send a validation message
     * @return Contact
     */
    public static function Create(ObjectDatabase $database, Account $account, ContactInfo $input, bool $verify = false) : Contact
    {        
        $contact = parent::BaseCreate($database, false)->SetScalar('info',$input->info)
            ->SetScalar('type',$input->type)->SetObject('account', $account);
        
        if ($verify)
        {
            $key = $contact->InitAuthKey()->GetFullKey();
            
            $subject = "Andromeda Email Validation Code";
            $body = "Your validation code is: $key";
            
            $contact->SendMessage($subject, null, $body);
        }
        else $contact->SetIsValid();
        
        return $contact;
    }
    
    /**
     * Fetches a type/value pair from input (depends on the param name given)
     * @throws ContactNotGivenException if nothing valid was found
     * @return ContactInfo
     */
   public static function FetchInfoFromInput(Input $input) : ContactInfo
    {
        if ($input->HasParam('email')) 
        { 
            $type = self::TYPE_EMAIL;
            
            $info = $input->GetParam('email',SafeParam::TYPE_EMAIL, SafeParams::PARAMLOG_ALWAYS); 
        }
        
        else throw new ContactNotGivenException();
        
        return new ContactInfo($type, $info);
    }
    
    public static function GetFetchUsage() : string { return "--email email"; }
    
    /**
     * Sends a message to this contact
     * @see Contact::SendMessageMany()
     */
    public function SendMessage(string $subject, ?string $html, string $plain, ?Account $from = null) : void
    {
        static::SendMessageMany($subject, $html, $plain, array($this), false, $from);
    }

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
        $emails = array_filter($recipients, function(Contact $contact){ return $contact->isEmail(); });
        
        $message = $html ?? $plain; $ishtml = ($html !== null);
        
        static::SendEmails($subject, $message, $ishtml, $emails, $bcc, $from);
    }
    
    /**
     * Sends a message to the given email recipients
     * @see Contact::SendMessageMany()
     * @see Emailer::SendMail()
     */
    protected static function SendEmails(string $subject, string $message, bool $ishtml, array $recipients, bool $bcc, ?Account $from = null) : void
    {
        $mailer = Main::GetInstance()->GetConfig()->GetMailer();
        
        $recipients = array_map(function(Contact $contact){ return $contact->GetAsEmailRecipient(); }, $recipients);
        
        if ($from !== null) $from = $from->GetEmailFrom();
        
        $mailer->SendMail($subject, $message, $ishtml, $recipients, $bcc, $from);
    }
    
    /** Returns true if this contact is an email contact */
    public function isEmail() : bool { return $this->GetType() === self::TYPE_EMAIL; }
    
    /** 
     * Returns this contact as an email recipient
     * @throws ContactNotEmailException if not an email contact
     */
    public function GetAsEmailRecipient() : EmailRecipient
    {
        if (!$this->isEmail()) throw new ContactNotEmailException();
        
        return new EmailRecipient($this->GetInfo(), $this->GetAccount()->GetDisplayName());
    }

    /**
     * Gets this contact as a printable object
     * @return array `{id:id, type:enum, info:string, valid:bool, usefrom:bool, public:bool, dates:{created:float}}`
     */
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'type' => self::TYPES[$this->GetType()],
            'info' => $this->GetInfo(),
            'valid' => $this->GetIsValid(),
            'usefrom' => (bool)($this->TryGetScalar('usefrom')),
            'public' => $this->getIsPublic(),
            'dates' => array(
                'created' => $this->GetDateCreated()
            )
        );
    }
}
