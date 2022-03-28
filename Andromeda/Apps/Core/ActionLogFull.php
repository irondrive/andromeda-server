<?php namespace Andromeda\Apps\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Apps/Core/ActionLog.php");

require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\NoChildren;
require_once(ROOT."/Apps/Accounts/AuthActionLog.php"); use Andromeda\Apps\Accounts\AuthActionLog;

/** Core app access log for use with the accounts app installed */
final class ActionLogFull extends AuthActionLog
{
    use NoChildren;
    
    public static function GetTableClasses() : array
    {
        $tables = parent::GetTableClasses();
        $tables[] = ActionLog::class; return $tables;
    }
}
