<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, QueryBuilder};
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Core\Logging\ActionLog as BaseActionLog;

use Andromeda\Apps\Accounts\Account;
use Andromeda\Apps\Accounts\Resource\Client;

/** 
 * Provides a base class for apps that use the Authenticator to log auth info
 * @phpstan-import-type ActionLogJ from BaseActionLog
 * @phpstan-import-type UserAccountJ from Account
 * @phpstan-import-type ClientJ from Client
 * @phpstan-type AuthActionLogJ \Union<ActionLogJ, array{admin:?bool, account:?string, client:?string, sudouser:?string}>
 * @phpstan-type ExpandAuthActionLogJ \Union<ActionLogJ, array{admin:?bool, account:?UserAccountJ, client:?ClientJ, sudouser:?UserAccountJ}>
 */
abstract class AuthActionLog extends BaseActionLog
{
    /** True if the request was done as admin */
    protected FieldTypes\NullBoolType $admin;
    /** 
     * The real account the action was done with 
     * @var FieldTypes\NullObjectRefT<Account>
     */
    protected FieldTypes\NullObjectRefT $account;
    /** 
     * The sudouser the action was done as 
     * @var FieldTypes\NullObjectRefT<Account>
     */
    protected FieldTypes\NullObjectRefT $sudouser;
    /** 
     * The client the action was done with 
     * @var FieldTypes\NullObjectRefT<Client>
     */
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
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $isCount = false) : array
    {
        $criteria = array();
        
        if ($params->HasParam('admin')) $criteria[] = $params->GetParam('admin')->GetBool() 
            ? $q->IsTrue("admin") : $q->Not($q->IsTrue("admin"));
        
        foreach (array('account','sudouser','client') as $prop) if ($params->HasParam($prop)) 
            $criteria[] = $q->Equals("$prop", $params->GetParam($prop)->GetRandstr());       

        return array_merge($criteria, parent::GetPropCriteria($database, $q, $params, $isCount));
    }

    /**
     * Returns the printable client object of this AuthActionLog
     * @param bool $expand if true, expand linked objects
     * @return ($expand is true ? ExpandAuthActionLogJ : AuthActionLogJ)
     */
    public function GetClientObject(bool $expand = false) : array
    {
        $retval = parent::GetClientObject($expand);
    
        $retval['admin'] = (bool)$this->admin->TryGetValue();

        if ($expand)
        {
            $retval += array(
                'account' => $this->account->TryGetObject()?->GetUserClientObject(),
                'sudouser' => $this->sudouser->TryGetObject()?->GetUserClientObject(),
                'client' => $this->client->TryGetObject()?->GetClientObject()
            );
        }
        else
        {
            $retval += array(
                'account' => $this->account->TryGetObjectID(),
                'sudouser' => $this->sudouser->TryGetObjectID(),
                'client' => $this->client->TryGetObjectID()
            );
        }

        return $retval;
    }
}
