<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/apps/accounts/KeySource.php");

/**
 * A recovery key allows account recovery by bypassing a password
 * 
 * Also stores a backup copy of the account's master key, 
 * and as a matter of convention, can byapss two factor
 */
class RecoveryKey extends KeySource
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
        if ($this->codeused) $this->Delete();
        else return parent::Save($isRollback);
    }
    
    /**
     * Tries to load a recovery key object
     * @param ObjectDatabase $database database reference
     * @param Account $account the owner of the recovery key
     * @param string $code the full user/serialized code
     * @return self|NULL loaded object or null if not found
     */
    public static function LoadByFullKey(ObjectDatabase $database, Account $account, string $code) : ?self
    {
        $code = explode(":", $code, 3);        
        if (count($code) !== 3 || $code[0] !== "tf") return null;
        
        $q = new QueryBuilder(); $q->Where($q->And($q->Equals('account',$account->ID()),$q->Equals('id',$code[1])));
        return static::TryLoadUniqueByQuery($database, $q);
    }
    
    /** Checks the given full/serialized key for validity, returns result */
    public function CheckFullKey(string $code) : bool
    {
        $code = explode(":", $code, 3);
        if (count($code) !== 3 || $code[0] !== "tf") return false;

        $retval = $this->CheckKeyMatch($code[2]);
        
        if ($retval) 
        {
            $this->codeused = true;
            $this->database->setModified($this);
            // schedule the delete for later
        }
        
        return $retval;
    }
    
    /**
     * Gets the full serialized recovery key value for the user
     * 
     * The serialized string contains both the key ID and key value
     */
    public function GetFullKey() : string
    {
        return implode(":",array("tf",$this->ID(),$this->GetAuthKey()));
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

