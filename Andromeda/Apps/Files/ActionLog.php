<?php declare(strict_types=1); namespace Andromeda\Apps\Files; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};
use Andromeda\Core\IOFormat\SafeParams;
use Andromeda\Apps\Accounts\AuthActionLog;

use Andromeda\Apps\Files\Items\{Item, Folder};
use Andromeda\Apps\Files\Social\Share;

/** 
 * Access log for the files app
 * @phpstan-import-type AuthActionLogJ from ActionLog
 * @phpstan-import-type ExpandAuthActionLogJ from ActionLog
 * @phpstan-import-type FolderJ from Folder
 * @phpstan-import-type ItemJ from Item
 * @phpstan-import-type ShareJ from Share
 * @phpstan-type FilesActionLogJ \Union<AuthActionLogJ, array{item:?string, parent:?string, item_share:?string, parent_share:?string}>
 * @phpstan-type ExpandFilesActionLogJ \Union<ExpandAuthActionLogJ, array{item:?ItemJ, parent:?FolderJ, item_share:?ShareJ, parent_share:?ShareJ}>
 */
class ActionLog extends AuthActionLog
{
    use TableTypes\TableNoChildren;
    
    /** @var FieldTypes\NullObjectRefT<Item> */
    private FieldTypes\NullObjectRefT $item;
    /** @var FieldTypes\NullObjectRefT<Folder> */
    private FieldTypes\NullObjectRefT $parent;
    /** @var FieldTypes\NullObjectRefT<Share> */
    private FieldTypes\NullObjectRefT $item_share;
    /** @var FieldTypes\NullObjectRefT<Share> */
    private FieldTypes\NullObjectRefT $parent_share;
    
    protected function CreateFields() : void
    {
        $fields = array();

        $this->item = $fields[] = new FieldTypes\NullObjectRefT(Item::class, 'item');
        $this->parent = $fields[] = new FieldTypes\NullObjectRefT(Folder::class, 'parent');
        $this->item_share = $fields[] = new FieldTypes\NullObjectRefT(Share::class, 'item_share');
        $this->parent_share = $fields[] = new FieldTypes\NullObjectRefT(Share::class, 'parent_share');
        
        $this->RegisterFields($fields, self::class);
        
        parent::CreateFields();
    }

    /** 
     * Links this log to the given item accessor
     * @throws Exceptions\ItemLogFullException if already logged
     */
    public function LogItemAccess(Item $item, ?Share $share) : void
    {
        if ($this->item->TryGetObjectID() !== null) 
            throw new Exceptions\ItemLogFullException();
        
        $this->item->SetObject($item);
        $this->item_share->SetObject($share);
    }

    /** 
     * Links this log to the given parent accessor
     * @throws Exceptions\ItemLogFullException if already logged
     */
    public function LogParentAccess(Folder $parent, ?Share $share) : void
    {
        if ($this->parent->TryGetObjectID() !== null) 
            throw new Exceptions\ItemLogFullException();

        $this->parent->SetObject($parent);
        $this->parent_share->SetObject($share);
    }

    public static function GetAppPropUsage() : string { return "[--file id] [--folder id] [--file_share id] [--folder_share id]"; }
    
    public static function GetPropCriteria(ObjectDatabase $database, QueryBuilder $q, SafeParams $params, bool $isCount = false) : array
    {
        $criteria = array();
        
        if ($params->HasParam('item')) $criteria[] = $q->Or(
            $q->Equals('item', $params->GetParam('item')->GetRandstr()),
            $q->Equals('parent', $params->GetParam('item')->GetRandstr()));
        
        if ($params->HasParam('share')) 
            $criteria[] = $q->Equals("share", $params->GetParam('share')->GetRandstr());
        
        return array_merge($criteria, parent::GetPropCriteria($database, $q, $params));
    }
    
    /**
     * Returns the printable client object of this AuthActionLog
     * @param bool $expand if true, expand linked objects
     * @return ($expand is true ? ExpandFilesActionLogJ : FilesActionLogJ)
     */
    public function GetClientObject(bool $expand = false) : array // @phpstan-ignore-line unsure why return type is wrong
    {
        $retval = parent::GetClientObject($expand);
        
        if ($expand)
        {
            $retval += array(
                'item' => $this->item->TryGetObject()?->GetClientObject(owner:true),
                'parent' => $this->parent->TryGetObject()?->GetClientObject(owner:true),
                'item_share' => $this->item_share->TryGetObject()?->GetClientObject(fullitem:false,owner:false),
                'parent_share' => $this->parent_share->TryGetObject()?->GetClientObject(fullitem:false,owner:false)
            );
        }
        else
        {
            $retval += array(
                'item' => $this->item->TryGetObjectID(),
                'parent' => $this->parent->TryGetObjectID(),
                'item_share' => $this->item_share->TryGetObjectID(),
                'parent_share' => $this->parent_share->TryGetObjectID()
            );
        }

        return $retval;
    }
}
