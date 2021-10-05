<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

/** Whitelist entry for allowing account signups */
class Whitelist extends StandardObject
{
    public const TYPE_USERNAME = 1;
    public const TYPE_CONTACT = 2;
    
    public const TYPES = array('username'=>self::TYPE_USERNAME, 'contact'=>self::TYPE_CONTACT);
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'type' => null, 'value' => null
        ));
    }
    
    /**
     * Creates a new whitelist entry
     * @param ObjectDatabase $database database reference
     * @param int $type entry type enum
     * @param string $value value of whitelist entry
     * @return self new whitelist entry
     */
    public static function Create(ObjectDatabase $database, int $type, string $value) : self
    {
        return parent::BaseCreate($database)->SetScalar('type',$type)->SetScalar('value',$value);
    }
    
    /**
     * Checks whether a whitelist entry exists
     * @param ObjectDatabase $database database reference
     * @param int $type type enum of whitelist entry
     * @param string $value value of whitelist entry
     * @return bool true if it exists (is whitelisted)
     */
    public static function ExistsTypeAndValue(ObjectDatabase $database, int $type, string $value) : bool
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('type',$type),$q->Equals('value',$value));
        
        return (static::TryLoadUniqueByQuery($database, $q->Where($w)) !== null);
    }
    
    /**
     * Removes a whitelist entry, if it exists 
     * @param ObjectDatabase $database database reference
     * @param int $type type enum of whitelist entry
     * @param string $value value of whitelist entry
     */
    public static function DeleteByTypeAndValue(ObjectDatabase $database, int $type, string $value) : void
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('type',$type),$q->Equals('value',$value));
        
        static::DeleteByQuery($database, $q->Where($w));
    }
    
    /**
     * Returns a printable client object for this entry
     * @return array `{type:enum, value:string}`
     */
    public function GetClientObject() : array
    {
        return array(
            'type' => array_flip(self::TYPES)[$this->GetScalar('type')], 
            'value' => $this->GetScalar('value')            
        );
    }    
}
