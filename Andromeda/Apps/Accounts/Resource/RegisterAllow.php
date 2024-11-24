<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};

/** RegisterAllow entry for allowing account signups */
class RegisterAllow extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    /** A whitelisted username */
    public const TYPE_USERNAME = 0;
    /** A whitelisted contact info */
    public const TYPE_CONTACT = 1; // TODO maybe refactor to have actual contact types?
    
    public const TYPES = array(
        'username'=>self::TYPE_USERNAME, 
        'contact'=>self::TYPE_CONTACT);
    
    /** The type of whitelist entry */
    private FieldTypes\IntType $type; // keep it simple, no polymorphism
    /** The value of the whitelist entry */
    private FieldTypes\StringType $value;
    /** Date the entry was created */
    private FieldTypes\Timestamp $date_created;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->type = $fields[] =  new FieldTypes\IntType('type');
        $this->value = $fields[] = new FieldTypes\StringType('value');
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }
    
    /**
     * Creates a new whitelist entry
     * @param ObjectDatabase $database database reference
     * @param int $type entry type enum
     * @param string $value value of whitelist entry
     * @return static new whitelist entry
     */
    public static function Create(ObjectDatabase $database, int $type, string $value) : self
    {
        $obj = $database->CreateObject(static::class);
        $obj->date_created->SetTimeNow();
        $obj->type->SetValue($type);
        $obj->value->SetValue($value);
        return $obj;
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
        
        return ($database->TryLoadUniqueByQuery(self::class, $q) !== null);
    }
    
    /**
     * Removes a whitelist entry, if it exists 
     * @param ObjectDatabase $database database reference
     * @param int $type type enum of whitelist entry
     * @param string $value value of whitelist entry
     */
    public static function DeleteByTypeAndValue(ObjectDatabase $database, int $type, string $value) : bool
    {
        $q = new QueryBuilder(); $w = $q->And($q->Equals('type',$type),$q->Equals('value',$value));
        
        return $database->TryDeleteUniqueByQuery(self::class, $q);
    }
    
    /**
     * Returns a printable client object for this entry
     * @return array<mixed> `{date_created:float, type:enum, value:string}`
     */
    public function GetClientObject() : array
    {
        return array(
            'date_created' => $this->date_created->GetValue(),
            'type' => array_flip(self::TYPES)[$this->type->GetValue()], 
            'value' => $this->value->GetValue()
        );
    }    
}
