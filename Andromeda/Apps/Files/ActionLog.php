<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Apps/Accounts/Authenticator.php"); use Andromeda\Apps\Accounts\Authenticator;
require_once(ROOT."/Apps/Accounts/AuthActionLog.php"); use Andromeda\Apps\Accounts\AuthActionLog;

require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Apps/Files/Item.php");
require_once(ROOT."/Apps/Files/File.php");
require_once(ROOT."/Apps/Files/Folder.php");
require_once(ROOT."/Apps/Files/Share.php");

/** Exception indicating that only one file/folder access can logged */
class ItemLogFullException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("ITEM_LOG_SLOT_FULL", $details);
    }
}

/** Exception indicating that an unknown item type was given */
class BadItemTypeException extends Exceptions\ServerException
{
    public function __construct(?string $details = null) {
        parent::__construct("UNKNOWN_ITEM_TYPE", $details);
    }
}

/** Access log for the files app */
class ActionLog extends AuthActionLog
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_file' => new FieldTypes\ObjectRef(File::class),
            'obj_folder' => new FieldTypes\ObjectRef(Folder::class), // TODO combine to item?
            'obj_parent' => new FieldTypes\ObjectRef(Folder::class),
            'obj_file_share' => new FieldTypes\ObjectRef(Share::class),
            'obj_folder_share' => new FieldTypes\ObjectRef(Share::class), // TODO combine to item_share
            'obj_parent_share' => new FieldTypes\ObjectRef(Share::class)
        ));
    }
    
    /** 
     * Links this log to the given item accessor
     * @param bool $isParent if true, log as a parent folder access
     */
    public function LogAccess(Item $item, ?Share $share, bool $isParent = false) : self // TODO replace with LogItemAccess and LogParentAccess
    {        
        if ($isParent) return $this->SetObject('parent',$item)->SetObject('parent_share',$share);   
        
        if ($item instanceof File) 
        {
            if ($this->HasObject('file')) throw new ItemLogFullException();
            
            return $this->SetObject('file',$item)->SetObject('file_share',$share);
        }
        else if ($item instanceof Folder) 
        {
            if ($this->HasObject('folder')) throw new ItemLogFullException();
            
            return $this->SetObject('folder',$item)->SetObject('folder_share',$share);
        }
        else throw new BadItemTypeException();
    }

    /**
     * Creates a new log object that logs the given $auth value
     * @see AuthActionLog::BaseAuthCreate()
     */
    public static function Create(ObjectDatabase $database, ?Authenticator $auth) : ?self
    {
        return parent::BaseAuthCreate($database, $auth);
    }

    public static function GetPropUsage() : string { return parent::GetPropUsage()." [--file id] [--folder id] [--file_share id] [--folder_share id]"; } // TODO combine to item_share?
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params) : array
    {
        $criteria = array(); $table = $database->GetClassTableName(static::class);
        
        if ($params->HasParam('file')) $criteria[] = $q->Equals("$table.obj_file", $params->GetParam('file')->GetRandstr());
        if ($params->HasParam('file_share')) $criteria[] = $q->Equals("$table.obj_file_share", $params->GetParam('file_share')->GetRandstr());
        
        if ($params->HasParam('folder')) 
        {
            $folder = $params->GetParam("folder")->GetRandstr();
            $criteria[] = $q->Or($q->Equals("$table.obj_folder",$folder), 
                                 $q->Equals("$table.obj_parent",$folder));
        }
        
        if ($params->HasParam('folder_share'))
        {
            $folder = $params->GetParam("folder_share")->GetRandstr();
            $criteria[] = $q->Or($q->Equals("$table.obj_folder_share",$folder),
                                 $q->Equals("$table.obj_parent_share",$folder));
        }
        
        return array_merge($criteria, parent::GetPropCriteria($database, $q, $params));
    }
    
    /**
     * @return array add `{?file:id, ?folder:id, ?parent:id,
        ?file_share:id, ?folder_share:id, ?parent_share:id}`
       @see AuthActionLog::GetClientObject()
     */
    public function GetClientObject(bool $expand = false) : array
    {
        $retval = array();
        
        foreach (array('file','folder','parent','file_share','folder_share','parent_share') as $prop)
            if (($id = $this->TryGetObjectID($prop)) !== null) $retval[$prop] = $id;
        
        return array_merge(parent::GetClientObject($expand), $retval);
    }
}
