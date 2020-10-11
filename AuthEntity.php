<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Emailer.php"); use Andromeda\Core\{Emailer, EmailRecipient};
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

abstract class AuthEntity extends StandardObject
{  
    public abstract function GetEmailRecipients() : array;
    public abstract function SendMailTo(Emailer $mailer, string $subject, string $message, ?EmailRecipient $from = null);
}

class InheritedProperty
{
    private $value; private $source;
    public function GetValue() { return $this->value; }
    public function GetSource() : ?AuthEntity { return $this->source; }
    public function __construct($value, ?AuthEntity $source){
        $this->value = $value; $this->source = $source; }
}

class GroupJoin extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'accounts' => new FieldTypes\ObjectRef(Account::class, 'groups'),
            'groups' => new FieldTypes\ObjectRef(Group::class, 'accounts')
        ));
    }
}