<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/apps/accounts/Authenticator.php"); use Andromeda\Apps\Accounts\Authenticator;
require_once(ROOT."/apps/accounts/AuthAccessLog.php"); use Andromeda\Apps\Accounts\AuthAccessLog;

/** Access log for the files app */
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
