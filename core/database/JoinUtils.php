<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;

abstract class JoinUtils extends StandardObject
{    
    public static function CreateJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinobj, BaseObject $thisobj, BaseObject $destobj) : void
    {
        $joinclass = $joinobj->GetJoinClass();
        $newobj = $database->CreateObject($joinclass)->SetDate('created')
            ->SetObject($joinobj->GetMyField(), $destobj, true)
            ->SetObject($joinobj->GetRefField(), $thisobj, true);
        $newobj->created = true; $newobj->Save();
        
        $thisobj->AddObjectRef($joinobj->GetMyField(), $destobj, true);
        $destobj->AddObjectRef($joinobj->GetRefField(), $thisobj, true);
    }
    
    public static function LoadJoinObject(ObjectDatabase $database, FieldTypes\ObjectJoin $joinobj, BaseObject $thisobj, BaseObject $destobj) : ?self
    {
        $criteria = array($joinobj->getRefField() => $destobj->ID(), $joinobj->getMyField() => $thisobj->ID());
        
        $joinclass = $joinobj->GetJoinClass();
        $objects = $database->LoadObjectsMatchingAll($joinclass, $criteria);
        return (count($objects) == 1) ? array_values($objects)[0] : null;
    }
    
    public static function DeleteJoin(ObjectDatabase $database, FieldTypes\ObjectJoin $joinobj, BaseObject $thisobj, BaseObject $destobj) : void
    {
        $obj = self::LoadJoinObject($database, $joinobj, $thisobj, $destobj);
        if ($obj !== null) $obj->Delete();
    }
}
