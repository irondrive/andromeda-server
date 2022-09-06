<?php declare(strict_types=1); namespace Andromeda\Apps\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Database/TableTypes.php"); use Andromeda\Core\Database\TableNoChildren;
require_once(ROOT."/Apps/Accounts/AuthActionLog.php"); use Andromeda\Apps\Accounts\AuthActionLog;

/** Core app access log for use with the accounts app installed */
final class ActionLog extends AuthActionLog
{
    use TableNoChildren;
    
    protected function CreateFields() : void
    {
        $this->RegisterFields(array(), self::class);
        
        parent::CreateFields();
    }
    
    public function SetAdmin(bool $isAdmin) : self { /* no-op */ return $this; }
}
