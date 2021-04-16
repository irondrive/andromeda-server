<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php");
require_once(ROOT."/core/database/QueryBuilder.php");

/**
 * The base class for join objects managed by an ObjectJoin field type.
 * 
 * Includes utility functions for creating/loading/deleting join objects.
 * A class that extends this one to join two classes together in a many-to-many
 * needs (at a minimum) to have two fields, each as an ObjectRefs to a class.
 */
abstract class JoinObject extends StandardObject
{
    /** Return the column name of the left side of the join */
    protected abstract static function GetLeftField() : string;
    
    /** Return the object class referred to by the left side of the join */
    protected abstract static function GetLeftClass() : string;
    
    /** Return the column name of the right side of the join */
    protected abstract static function GetRightField() : string;
    
    /** Return the object class referred to by the right side of the join */
    protected abstract static function GetRightClass() : string;
    
    public static function GetFieldTemplate() : array
    {
        $lfield = static::GetLeftField(); $rfield = static::GetRightField();
        
        return array_merge(parent::GetFieldTemplate(), array(
            $lfield => new FieldTypes\ObjectRef(static::GetLeftClass(), $rfield),
            $rfield => new FieldTypes\ObjectRef(static::GetRightClass(), $lfield)
        ));
    }
    
    /**
     * Creates a new join object and notifies the joined object
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field creating this join
     * @param BaseObject $destobj the object to be joined to the field
     */
    public static function CreateJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : void
    {
        $thisobj = $joinfield->GetParent();
        
        $destobj->AddObjectRef($joinfield->GetRefField(), $thisobj, true);
        $thisobj->AddObjectRef($joinfield->GetMyField(), $destobj, true);
        
        parent::BaseCreate($database)->SetDate('created')
            ->SetObject($joinfield->GetMyField(), $destobj, true)
            ->SetObject($joinfield->GetRefField(), $thisobj, true)->Save();
    }
    
    /**
     * Returns a query for selecting a unique join object
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field loading this join
     * @param BaseObject $destobj the object joined to this field
     */
    protected static function GetWhereQuery(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : QueryBuilder
    {
        $q = new QueryBuilder(); $thisobj = $joinfield->GetParent();
        
        $w = $q->And($q->Equals($joinfield->getMyField(),$destobj->ID()),
                     $q->Equals($joinfield->getRefField(),$thisobj->ID()));
        
        return $q->Where($w);
    }
    
    /**
     * Loads the join object that joins together two objects
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field loading this join
     * @param BaseObject $destobj the object joined to this field
     */
    public static function TryLoadJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : ?self
    {        
        return parent::TryLoadUniqueByQuery($database, static::GetWhereQuery($database, $joinfield, $destobj));
    }
    
    /**
     * Deletes the join object that joins together two objects
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field loading this join
     * @param BaseObject $destobj the object joined to this field
     * @return bool true if an object was deleted
     */
    public static function TryDeleteJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : bool
    {
        return (bool)parent::DeleteByQuery($database, static::GetWhereQuery($database, $joinfield, $destobj));
    }

    public function Delete() : void
    {
        $lfield = static::GetLeftField(); $lobj = $this->GetObject($lfield);
        $rfield = static::GetRightField(); $robj = $this->GetObject($rfield);
        
        // doing $this->RemoveObjectRef would result in a recursion
        // loop so instead just directly notify the joined objects
        $lobj->RemoveObjectRef($rfield, $robj, true);
        $robj->RemoveObjectRef($lfield, $lobj, true);
        
        unset($this->objects[$lfield]); 
        unset($this->objects[$rfield]);
        
        parent::Delete();
    }
}
