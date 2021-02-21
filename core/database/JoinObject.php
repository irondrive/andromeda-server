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
    /**
     * Creates a new join object and notifies the joined object
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field creating this join
     * @param BaseObject $destobj the object to be joined to the field
     */
    public static function CreateJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : void
    {
        $thisobj = $joinfield->GetParent();
        
        $newobj = parent::BaseCreate($database)->SetDate('created')
            ->SetObject($joinfield->GetMyField(), $destobj)
            ->SetObject($joinfield->GetRefField(), $thisobj, true);
        
        $newobj->created = true; $newobj->Save();
    }
    
    /**
     * Loads the join object that joins together two objects
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field loading this join
     * @param BaseObject $destobj the object joined to this field
     */
    public static function TryLoadJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : ?self
    {        
        $q = new QueryBuilder(); $thisobj = $joinfield->GetParent();
        
        $w = $q->And($q->Equals($joinfield->getMyField(),$destobj->ID()),
                     $q->Equals($joinfield->getRefField(),$thisobj->ID()));
        
        return parent::TryLoadUniqueByQuery($database, $q->Where($w));
    }
}
