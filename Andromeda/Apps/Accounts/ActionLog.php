<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Apps/Accounts/Authenticator.php"); 
require_once(ROOT."/Apps/Accounts/AuthActionLog.php"); 

/** Access log for the accounts app */
class ActionLog extends AuthActionLog
{
    /**
     * Creates a new log object that logs the given $auth value
     * @see AuthActionLog::BaseAuthCreate()
     */
    public static function Create(ObjectDatabase $database, ?Authenticator $auth) : ?self
    {
        return parent::BaseAuthCreate($database, $auth);
    }
}
