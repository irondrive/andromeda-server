<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Core/Logging/ActionLog.php"); use Andromeda\Core\Logging\ActionLog as BaseActionLog;

require_once(ROOT."/Apps/Accounts/Authenticator.php"); 
require_once(ROOT."/Apps/Accounts/Account.php");
require_once(ROOT."/Apps/Accounts/Client.php");

/** Provides a base class for apps that use the Authenticator to log auth info */
abstract class AuthActionLog extends BaseActionLog
{
    /** True if the request was done as admin */
    private FieldTypes\NullBoolType $admin;
    /** The real account the action was done with */
    private FieldTypes\NullObjectRefT $account;
    /** The sudouser the action was done as */
    private FieldTypes\NullObjectRefT $sudouser;
    /** The client the action was done with */
    private FieldTypes\NullObjectRefT $client;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->admin = $fields[] = new FieldTypes\NullBoolType('admin');
        
        $this->account = $fields[] = new FieldTypes\NullObjectRefT(Account::class, 'account');
        $this->sudouser = $fields[] = new FieldTypes\NullObjectRefT(Account::class, 'sudouser');
        $this->client = $fields[] = new FieldTypes\NullObjectRefT(Client::class, 'client');
        
        $this->RegisterFields($fields); // child table
        
        parent::CreateFields();
    }

    /** Logs the authenticator used for this action */
    public function SetAuth(Authenticator $auth) : self
    {
        $this->admin->SetValue($auth->isAdmin());
        $this->client->SetValue($auth->TryGetClient());
        $this->account->SetValue($auth->TryGetRealAccount());
        
        if ($auth->isSudoUser())
            $this->sudouser->SetValue($auth->TryGetAccount());
    }

    public static function GetPropUsage() : string { return "[--admin bool] [--account id] [--sudouser id] [--client id]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params) : array
    {
        $criteria = array();
        
        if ($params->HasParam('admin')) $criteria[] = $params->GetParam('admin')->GetBool() 
            ? $q->IsTrue("admin") : $q->Not($q->IsTrue("admin"));
        
        foreach (array('account','sudouser','client') as $prop) if ($params->HasParam($prop)) 
            $criteria[] = $q->Equals("$prop", $params->GetParam($prop)->GetRandstr());       

        return array_merge($criteria, parent::GetPropCriteria($database, $q, $params));
    }

    /**
     * Returns the printable client object of this AuthActionLog
     * @param bool $expand if true, expand linked objects
     * @return array `{admin:?bool, account:?id, client:?id, ?sudouser:id}`
        if $expand, `{account:?Account, client:?Client, ?sudouser:Account}`
       @see Account::GetClientObject()
       @see Client::GetClientObject()
     */
    public function GetClientObject(bool $expand = false) : array
    {        
        $retval = parent::GetClientObject($expand);
    
        $retval['admin'] = (bool)$this->admin->TryGetValue();

        if ($expand)
        {
            $account = $this->account->TryGetValue();
            $sudouser = $this->sudouser->TryGetValue();
            $client = $this->client->TryGetValue();
            
            $retval['account'] = ($account !== null) ? $account->GetClientObject() : null;
            $retval['client'] = ($client !== null) ? $client->GetClientObject() : null;
            if ($sudouser !== null) $retval['sudouser'] = $sudouser->GetClientObject();
        }
        else
        {
            $retval['account'] = $this->account->TryGetObjectID();
            $retval['client'] = $this->client->TryGetObjectID();
            
            $sudouser = $this->sudouser->TryGetObjectID();
            if ($sudouser !== null) $retval['sudouser'] = $sudouser;
        }

        return $retval;
    }
}
