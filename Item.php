<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/StandardObject.php"); use Andromeda\Core\Database\StandardObject;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;

require_once(ROOT."/apps/files/Filesystem.php");

abstract class Item extends StandardObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'name' => null,
            'dates__created' => null,
            'dates__modified' => null,
            'dates__accessed' => new FieldTypes\Scalar(null, true),
            'counters__size' => new FieldTypes\Counter(),            
            'counters__bandwidth' => new FieldTypes\Counter(null, true),
            'counters__downloads' => new FieldTypes\Counter(),
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'filesystem' => new FieldTypes\ObjectRef(Filesystem::class)
        ));
    }
    
    public function GetOwner() : ?Account { return $this->TryGetObject('owner'); }
    
    public function SetAccessed(int $time) : self { return $this->SetDate('accessed', $time); }
    public function SetCreated(int $time) : self { return $this->SetDate('created', $time); }
    public function SetModified(int $time) : self { return $this->SetDate('modified', $time); }
    
    protected function GetFilesystem() : Filesystem { return $this->GetObject('filesystem'); }
    protected function GetFilesystemImpl() : FilesystemImpl { return $this->GetFilesystem()->GetIface(); }
}
