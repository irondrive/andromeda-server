<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, QueryBuilder};
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Core\Logging\ActionLog as BaseActionLog;

require_once(ROOT."/Apps/Accounts/Account.php");
require_once(ROOT."/Apps/Accounts/Authenticator.php"); 
require_once(ROOT."/Apps/Accounts/Resource/Client.php"); use Andromeda\Apps\Accounts\Resource\Client;

/** Provides a base class for apps that use the Authenticator to log auth info */
abstract class AuthActionLog extends BaseActionLog
{
    /** True if the request was done as admin */
    protected FieldTypes\NullBoolType $admin;
    /** The real account the action was done with */
    protected FieldTypes\NullObjectRefT $account;
    /** The sudouser the action was done as */
    protected FieldTypes\NullObjectRefT $sudouser;
    /** The client the action was done with */
    protected FieldTypes\NullObjectRefT $client;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->admin = $fields[] = new FieldTypes\NullBoolType('admin');
        
        $this->account = $fields[] = new FieldTypes\NullObjectRefT(Account::class, 'account');
        $this->sudouser = $fields[] = new FieldTypes\NullObjectRefT(Account::class, 'sudouser');
        $this->client = $fields[] = new FieldTypes\NullObjectRefT(Client::class, 'client');
        
        $this->RegisterChildFields($fields);
        
        parent::CreateFields();
    }

    /** 
     * Logs the authenticator used for this action 
     * @return $this
     */
    public function SetAuth(?Authenticator $auth) : self
    {
        if ($auth === null)
        {
            $this->admin->SetValue(null);
            $this->client->SetObject(null);
            $this->account->SetObject(null);
            $this->sudouser->SetObject(null);
        }
        else
        {
            $this->admin->SetValue($auth->isAdmin());
            $this->client->SetObject($auth->TryGetClient());
            $this->account->SetObject($auth->TryGetRealAccount());
            $this->sudouser->SetObject($auth->isSudoUser() ? $auth->TryGetAccount() : null);
        }
        
        return $this;
    }

    public static function GetAppPropUsage() : string { return "[--admin bool] [--account id] [--sudouser id] [--client id]"; }
    
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
     * @return array<mixed> `{admin:?bool, ?account:id, ?client:id, ?sudouser:id}`
        if $expand, `{?account:Account, ?client:Client, ?sudouser:Account}`
       @see Account::GetClientObject()
       @see Client::GetClientObject()
     */
    public function GetClientObject(bool $expand = false) : array
    {        
        $retval = parent::GetClientObject($expand);
    
        $retval['admin'] = (bool)$this->admin->TryGetValue();

        if ($expand)
        {
            $account = $this->account->TryGetObject();
            $sudouser = $this->sudouser->TryGetObject();
            $client = $this->client->TryGetObject();
            
            if ($account !== null)  $retval['account']  = $account->GetClientObject();
            if ($client !== null)   $retval['client']   = $client->GetClientObject();
            if ($sudouser !== null) $retval['sudouser'] = $sudouser->GetClientObject();
        }
        else
        {
            if (($id = $this->account->TryGetObjectID()) !== null)
                $retval['account'] = $id;
            if (($id = $this->client->TryGetObjectID()) !== null)
                $retval['client'] = $id;
            if (($id = $this->sudouser->TryGetObjectID()) !== null)
                $retval['sudouser'] = $id;
        }

        return $retval;
    }
}
