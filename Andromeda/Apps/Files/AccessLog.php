<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/Database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/Core/Database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

require_once(ROOT."/Apps/Accounts/Authenticator.php"); use Andromeda\Apps\Accounts\Authenticator;
require_once(ROOT."/Apps/Accounts/AuthAccessLog.php"); use Andromeda\Apps\Accounts\AuthAccessLog;

require_once(ROOT."/Core/IOFormat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;
require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/Exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/Apps/Files/Item.php");
require_once(ROOT."/Apps/Files/File.php");
require_once(ROOT."/Apps/Files/Folder.php");
require_once(ROOT."/Apps/Files/Share.php");

/** Exception indicating that only one file/folder access can logged */
class ItemLogFullException extends Exceptions\ServerException { public $message = "ITEM_LOG_SLOT_FULL"; }

/** Exception indicating that an unknown item type was given */
class BadItemTypeException extends Exceptions\ServerException { public $message = "UNKNOWN_ITEM_TYPE"; }

/** Access log for the files app */
class AccessLog extends AuthAccessLog
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'obj_file' => new FieldTypes\ObjectRef(File::class),
            'obj_folder' => new FieldTypes\ObjectRef(Folder::class),
            'obj_parent' => new FieldTypes\ObjectRef(Folder::class),
            'obj_file_share' => new FieldTypes\ObjectRef(Share::class),
            'obj_folder_share' => new FieldTypes\ObjectRef(Share::class),
            'obj_parent_share' => new FieldTypes\ObjectRef(Share::class)
        ));
    }
    
    /** 
     * Links this log to the given item accessor
     * @param bool $isParent if true, log as a parent folder access
     */
    public function LogAccess(Item $item, ?Share $share, bool $isParent = false) : self
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
     * @see AuthAccessLog::BaseAuthCreate()
     */
    public static function Create(ObjectDatabase $database, ?Authenticator $auth) : ?self
    {
        return parent::BaseAuthCreate($database, $auth);
    }

    public static function GetPropUsage() : string { return parent::GetPropUsage()." [--file id] [--folder id] [--file_share id] [--folder_share id]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, Input $input) : array
    {
        $criteria = array(); $table = $database->GetClassTableName(static::class);
        
        if ($input->HasParam('file')) $criteria[] = $q->Equals("$table.obj_file", $input->GetParam('file',SafeParam::TYPE_RANDSTR));
        if ($input->HasParam('file_share')) $criteria[] = $q->Equals("$table.obj_file_share", $input->GetParam('file_share',SafeParam::TYPE_RANDSTR));
        
        if ($input->HasParam('folder')) 
        {
            $folder = $input->GetParam("folder",SafeParam::TYPE_RANDSTR);
            $criteria[] = $q->Or($q->Equals("$table.obj_folder",$folder), 
                                 $q->Equals("$table.obj_parent",$folder));
        }
        
        if ($input->HasParam('folder_share'))
        {
            $folder = $input->GetParam("folder_share",SafeParam::TYPE_RANDSTR);
            $criteria[] = $q->Or($q->Equals("$table.obj_folder_share",$folder),
                                 $q->Equals("$table.obj_parent_share",$folder));
        }
        
        return array_merge($criteria, parent::GetPropCriteria($database, $q, $input));
    }
    
    /**
     * @return array add `{?file:id, ?folder:id, ?parent:id,
        ?file_share:id, ?folder_share:id, ?parent_share:id}`
       @see AuthAccessLog::GetClientObject()
     */
    public function GetClientObject(bool $expand = false) : array
    {
        $retval = array();
        
        foreach (array('file','folder','parent','file_share','folder_share','parent_share') as $prop)
            if (($id = $this->TryGetObjectID($prop)) !== null) $retval[$prop] = $id;
        
        return array_merge(parent::GetClientObject($expand), $retval);
    }
}
