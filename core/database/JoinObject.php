<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php");
require_once(ROOT."/core/database/QueryBuilder.php");

/**
 * The base class for join objects managed by an ObjectJoin field type.
 * 
 * Includes utility functions for creating/loading/deleting join objects.
 */
abstract class JoinObject extends StandardObject
{
    /**
     * Creates a new join object
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field creating this join
     * @param BaseObject $destobj the object to be joined to the field
     */
    public static function CreateJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : void
    {
        $thisobj = $joinfield->GetParent();        
        $joinclass = $joinfield->GetJoinClass();
        $newobj = $database->CreateObject($joinclass)->SetDate('created')
            ->SetObject($joinfield->GetMyField(), $destobj, true)
            ->SetObject($joinfield->GetRefField(), $thisobj, true);
        $newobj->created = true; $newobj->Save();
        
        $thisobj->AddObjectRef($joinfield->GetMyField(), $destobj, true);
        $destobj->AddObjectRef($joinfield->GetRefField(), $thisobj, true);
    }
    
    /**
     * Loads the join object that joins together two objects
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field loading this join
     * @param BaseObject $destobj the object joined to this field
     */
    public static function LoadJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : ?self
    {        
        $joinclass = $joinfield->GetJoinClass(); $q = new QueryBuilder();
        $q->Where($q->And($q->Equals($joinfield->getRefField(),$destobj->ID()),$q->Equals($joinfield->getMyField(),$joinfield->GetParent()->ID())));
        $objects = $database->LoadObjectsByQuery($joinclass, $q);
        return (count($objects) == 1) ? array_values($objects)[0] : null;
    }
    
    /**
     * Deletes the join object that joins together two objects
     * @param ObjectDatabase $database reference to the database
     * @param FieldTypes\ObjectJoin $joinfield the field deleting this join
     * @param BaseObject $destobj the object joined to be un-joined
     */
    public static function DeleteJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinfield, BaseObject $destobj) : void
    {
        $obj = static::LoadJoinObject($database, $joinfield, $joinfield->GetParent(), $destobj);
        if ($obj !== null) $obj->Delete();
    }
}
