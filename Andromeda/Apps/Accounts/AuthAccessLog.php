<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;

require_once(ROOT."/Core/Logging/BaseAppLog.php"); use Andromeda\Core\Logging\BaseAppLog;

require_once(ROOT."/Apps/Accounts/Authenticator.php"); 
require_once(ROOT."/Apps/Accounts/Account.php");
require_once(ROOT."/Apps/Accounts/Client.php");

/** Provides a base class for apps that use the Authenticator to log auth info */
abstract class AuthAccessLog extends BaseAppLog
{
    public static function GetFieldTemplate() : array
    {
        return array(
            'admin' => null,
            'account' => new FieldTypes\ObjectRef(Account::class),
            'sudouser' => new FieldTypes\ObjectRef(Account::class),
            'client' => new FieldTypes\ObjectRef(Client::class)
        );
    }
    
    /**
     * Returns a new self object with the given auth info logged
     * @param ObjectDatabase $database database reference
     * @param Authenticator $auth Authenticator info to log
     * @return self|NULL new log object or null if BaseRunCreate returns null
     * @see BaseAppLog::BaseRunCreate()
     */
    public static function BaseAuthCreate(ObjectDatabase $database, ?Authenticator $auth) : ?self
    {
        $obj = parent::BaseRunCreate($database);
        
        if ($obj !== null && $auth !== null)
        {            
            if ($auth->isSudoUser()) 
                $obj->SetObject('sudouser',$auth->TryGetAccount());
            
            $obj->SetScalar('admin', $auth->isAdmin())
                ->SetObject('client', $auth->TryGetClient())
                ->SetObject('account', $auth->TryGetRealAccount());
        }
        
        return $obj;
    }
    
    /** Returns whether or not the Authenticator had admin status, or null auth was null */
    public function isAdmin() : ?bool { return $this->TryGetScalar('admin'); }
    
    public static function GetPropUsage() : string { return "[--admin bool] [--account id] [--sudouser id] [--client id]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input) : array
    {
        $criteria = array(); $table = $database->GetClassTableName(static::class);
        
        if ($input->HasParam('admin')) $criteria[] = $input->GetParam('admin',SafeParam::TYPE_BOOL) 
            ? $q->IsTrue("$table.admin") : $q->Not($q->IsTrue("$table.admin"));
        
        foreach (array('account','sudouser','client') as $prop) if ($input->HasParam($prop)) 
            $criteria[] = $q->Equals("$table.$prop", $input->GetParam($prop,SafeParam::TYPE_RANDSTR));       

        return array_merge($criteria, parent::GetPropCriteria($database, $q, $input));
    }
    
    private function TryGetAccount() : ?Account { return $this->TryGetObject('account'); }
    private function TryGetSudouser() : ?Account { return $this->TryGetObject('sudouser'); }
    private function TryGetClient() : ?Client { return $this->TryGetObject('client'); }
    
    /**
     * Returns the printable client object of this AuthAccessLog
     * @param bool $expand if true, expand linked objects
     * @return array `{admin:?bool, account:?id, client:?id, ?sudouser:id}`
        if $expand, `{account:?Account, client:?Client, ?sudouser:Account}`
       @see Account::GetClientObject()
       @see Client::GetClientObject()
     */
    public function GetClientObject(bool $expand = false) : array
    {
        $retval = array('admin' => $this->isAdmin());
        
        if ($expand)
        {
            $account = $this->TryGetAccount();
            $client = $this->TryGetClient();
            
            $retval['account'] = ($account !== null) ? $account->GetClientObject() : null;
            $retval['client'] = ($client !== null) ? $client->GetClientObject() : null;
            
            $sudouser = $this->TryGetSudouser();
            if ($sudouser !== null) $retval['sudouser'] = $sudouser->GetClientObject();
        }
        else
        {
            $retval['account'] = $this->TryGetObjectID('account');
            $retval['client'] = $this->TryGetObjectID('client');
            
            $sudouser = $this->TryGetObjectID('sudouser');
            if ($sudouser !== null) $retval['sudouser'] = $sudouser;
        }

        return $retval;
    }
}
