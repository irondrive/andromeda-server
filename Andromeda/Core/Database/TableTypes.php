<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php");
require_once(ROOT."/Core/Database/QueryBuilder.php");

require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

/** Exception indicating the requested class is not a child */
class BadPolyClassException extends Exceptions\ServerException { public $message = "BAD_POLY_CLASS"; }

/** Exception indicating the given row has a bad type value */
class BadPolyTypeException extends Exceptions\ServerException { public $message = "BAD_POLY_TYPE"; }

/** A trait for classes that have a database table */
trait HasTable
{
    public static function GetTableClasses() : array
    {
        $tables = parent::GetTableClasses();
        $tables[] = self::class; return $tables;
    }
}

/** A trait for final classes with no children */
trait NoChildren
{
    use NoTypedChildren;
    
    public static function GetChildMap() : ?array { return null; }
}

/** A trait for classes with no typed children */
trait NoTypedChildren
{
    public static function HasTypedRows() : bool { return false; }
    
    public static function GetWhereChild(ObjectDatabase $db, QueryBuilder $q, string $class) : string
    {
        throw new NotMultiTableException(self::class);
    }
    
    public static function GetRowClass(array $row) : string
    {
        throw new NotMultiTableException(self::class);
    }
}

/** A trait for classes with a table and no children */
trait TableNoChildren
{
    use HasTable, NoChildren;
}

/** A trait for base classes with a table whose children in 
 * GetChildMap() all have their own tables linked via foreign key */
trait TableLinkedChildren
{
    use HasTable, NoTypedChildren;
}

/** A trait for base classes with a table whose types in GetChildMap() do not all have their own table,
 * i.e. this is the final (most derived) table for > 1 class, or is both final and non-final.
 * Uses a type field to determine which rows are what for GetWhereChild() */
trait TableTypedChildren
{
    use HasTable;
    
    public static function HasTypedRows() : bool { return true; }
    
    public static function GetWhereChild(ObjectDatabase $db, QueryBuilder $q, string $class) : string
    {
        $map = array_flip(self::GetChildMap());
        
        if (!array_key_exists($class, $map))
            throw new BadPolyClassException($class);
        
        $table = $db->GetClassTableName(self::class);
        return $q->Equals("$table.type",$map[$class]);
    }
    
    public static function GetRowClass(array $row) : string
    {
        $type = $row['type']; $map = self::GetChildMap();
        
        if (!array_key_exists($type, $map))
            throw new BadPolyClassException($type);
        
        return $map[$type];
    }
    
    protected static function BaseCreate(ObjectDatabase $database) : BaseObject
    {
        $obj = parent::BaseCreate($database);
        
        foreach (self::GetChildMap() as $type=>$class)
        {
            // determine the type value based on the object
            if (is_a($obj, $class)) $obj->typefield->SetValue($type);
        }
        
        return $obj;
    }
    
    /**
     * The field that holds the enum for determining the child class.
     *
     * This is normally undefined for all but the most derived class as
     * when joining base tables, the DB will only give us the type from
     * the most derived table (the field name conflicts between tables).
     * It only actually matters for inserting rows into the DB though which
     * is fine because BaseCreate() will make sure they are set for every table.
     * After that, they can't change, and the value only matters for the top table.
     */
    private FieldTypes\IntType $typefield;
    
    /**
     * @return array<FieldTypes\BaseField> an array with the internal type field
     * ... to be merged into the field array by users of this trait
     */
    private function GetTypeFields() : array
    {
        // only the most derived class will have 'type' accessible
        return array($this->typefield = new FieldTypes\IntType('type'));
    }
}
