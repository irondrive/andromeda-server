<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Authenticator.php"); 
require_once(ROOT."/apps/accounts/AuthAccessLog.php"); 

/** Access log for the accounts app */
class AccessLog extends AuthAccessLog
{
    /**
     * Creates a new log object that logs the given $auth value
     * @see AuthAccessLog::BaseAuthCreate()
     */
    public static function Create(ObjectDatabase $database, ?Authenticator $auth) : ?self
    {
        return parent::BaseAuthCreate($database, $auth);
    }
}
