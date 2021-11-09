<?php namespace Andromeda\Apps\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Apps/Accounts/Authenticator.php"); use Andromeda\Apps\Accounts\Authenticator;
require_once(ROOT."/Apps/Accounts/AuthAccessLog.php"); use Andromeda\Apps\Accounts\AuthAccessLog;

/** Core app access log for use with the accounts app installed */
class AccessLog extends AuthAccessLog
{    
    /** 
     * Creates a new log object that logs the given $auth and $admin values 
     * @see AuthAccessLog::BaseAuthCreate()
     */
    public static function Create(ObjectDatabase $database, ?Authenticator $auth, bool $admin) : ?self
    {        
        if (($obj = parent::BaseAuthCreate($database, $auth)) === null) return null;
        
        return $obj->SetScalar('admin', $admin);
    }
}
