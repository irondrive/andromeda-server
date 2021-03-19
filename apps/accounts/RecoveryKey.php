<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/apps/accounts/KeySource.php");

class RecoveryKeyBase extends KeySource { use FullAuthKey; }

/**
 * A recovery key allows account recovery by bypassing a password
 * 
 * Also stores a backup copy of the account's master key, 
 * and as a matter of convention, can byapss two factor
 */
class RecoveryKey extends RecoveryKeyBase
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(      
            'account' => new FieldTypes\ObjectRef(Account::class, 'recoverykeys')
        ));
    }      

    const SET_SIZE = 8;

    /**
     * Returns a new array of recovery keys of the default set size
     * @param ObjectDatabase $database
     * @param Account $account
     * @return array
     */
    public static function CreateSet(ObjectDatabase $database, Account $account) : array
    {        
        return array_map(function($i)use($database, $account){ 
            return static::Create($database, $account); 
        }, range(0, self::SET_SIZE-1));
    }
 
    /** Creates a single recovery key for an account */
    public static function Create(ObjectDatabase $database, Account $account) : self
    {
        return parent::CreateKeySource($database, $account);
    }
    
    private bool $codeused = false;  
    
    /** Overrides Save(), calling Delete() instead if the code was used */
    public function Save(bool $isRollback = false) : self
    {
        if ($this->codeused) { $this->Delete(); return $this; }
        
        return parent::Save($isRollback);
    }
    
    protected static function GetFullKeyPrefix() : string { return "rk"; }

    public function CheckFullKey(string $code) : bool
    {
        $retval = parent::CheckFullKey($code);
        
        if ($retval) 
        {
            $this->codeused = true;
            $this->database->setModified($this);
            // schedule the delete for later
        }
        
        return $retval;
    }
    
    /** Deletes all recovery keys owned by the given account */
    public static function DeleteByAccount(ObjectDatabase $database, Account $account) : void
    {
        static::DeleteByObject($database, 'account', $account);
    }

    /**
     * Gets a printable client object for this key
     * @return array `{authkey:string}` if $secret else `{}`
     */
    public function GetClientObject(bool $secret = false) : array
    {
        return $secret ? array('authkey'=>$this->GetFullKey()) : array();
    }
}

