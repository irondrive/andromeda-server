<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/apps/accounts/KeySource.php");

class RecoveryKey extends KeySource
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(      
            'account' => new FieldTypes\ObjectRef(Account::class, 'recoverykeys')
        ));
    }      

    const SET_SIZE = 8;

    public static function CreateSet(ObjectDatabase $database, Account $account) : array
    {        
        return array_map(function($i) use ($database, $account){ 
            return static::Create($database, $account); 
        }, range(0, self::SET_SIZE-1));
    }
 
    public static function Create(ObjectDatabase $database, Account $account) : self
    {
        return parent::CreateKeySource($database, $account);
    }
    
    private bool $codeused = false;    
    public function Save(bool $isRollback = false) : self
    {
        if ($this->codeused) $this->Delete();
        return parent::Save($isRollback);
    }
    
    public static function LoadByFullKey(ObjectDatabase $database, Account $account, string $code) : ?self
    {
        $code = explode(":", $code, 3);        
        if (count($code) !== 3 || $code[0] !== "tf") return null;
        
        $q = new QueryBuilder(); $q->Where($q->And($q->Equals('account',$account->ID()),$q->Equals('id',$code[1])));
        $recoverykey = static::LoadByQuery($database, $q);

        if (!count($recoverykey)) return null;
        return array_values($recoverykey)[0];
    }
    
    public function CheckFullKey(string $code) : bool
    {
        $code = explode(":", $code, 3);
        if (count($code) !== 3 || $code[0] !== "tf") return false;

        $retval = $this->CheckKeyMatch($code[2]);
        
        if ($retval) 
        {
            $this->codeused = true;
            $this->database->setModified($this);
        }
        
        return $retval;
    }
    
    public function GetFullKey() : string
    {
        return implode(":",array("tf",$this->ID(),$this->GetAuthKey()));
    }
    
    public function GetClientObject(int $level = 0) : array
    {
        return ($level === self::OBJECT_WITHSECRET) ? array('authkey'=>$this->GetFullKey()) : array();
    }
}

