<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/AuthObject.php"); use Andromeda\Apps\Accounts\AuthObject;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\AuthEntity;

class Share extends AuthObject
{
    public const IDLength = 16;
    
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'item' => new FieldTypes\ObjectPoly(Item::Class, 'shares'),
            'owner' => new FieldTypes\ObjectRef(Account::class),
            'dest' => new FieldTypes\ObjectPoly(AuthEntity::class),
            'password' => null,
            'dates__accessed' => null,
            'counters__accessed' => new FieldTypes\Counter(),
            'dates__expire' => null,
            'features__read' => null,
            'features__upload' => null,
            'features__modify' => null,
            'features__social' => null,
            'features__reshare' => null
        ));
    }
    
    public function IsLink() : bool { return boolval($this->TryGetScalar('authkey')); }
    
    public function GetItem() : Item { return $this->GetObject('item'); }
    public function GetOwner() : Account { return $this->GetObject('owner'); }
    
    public function CanRead() : bool { return $this->TryGetFeature('read') ?? true; }
    public function CanUpload() : bool { return $this->TryGetFeature('upload') ?? false; }
    public function CanModify() : bool { return $this->TryGetFeature('modify') ?? false; }
    public function CanSocial() : bool { return $this->TryGetFeature('social') ?? true; }
    public function CanReshare() : bool { return $this->TryGetFeature('reshare') ?? false; }
    
    public function SetCanRead(?bool $v) : self { return $this->SetFeature('read',$v); }
    public function SetCanUpload(?bool $v) : self { return $this->SetFeature('upload',$v); }
    public function SetCanModify(?bool $v) : self { return $this->SetFeature('modify',$v); }
    public function SetCanSocial(?bool $v) : self { return $this->SetFeature('social',$v); }
    public function SetCanReshare(?bool $v) : self { return $this->SetFeature('reshare',$v); }
    
    public function SetAccessed() : self { return $this->SetDate('accessed')->DeltaCounter('accessed'); }
    
    private static function GetItemDestQuery(Item $item, AuthEntity $dest, QueryBuilder $q) : string
    {
        return $q->And(
            $q->Equals('item',FieldTypes\ObjectPoly::GetObjectDBValue($item)),
            $q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($dest)));  
    }
    
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, AuthEntity $dest) : self
    {
        $q = new QueryBuilder(); if (($ex = static::LoadOneByQuery($database, $q->Where(static::GetItemDestQuery($item, $dest, $q)))) !== null) return $ex;      
        return parent::BaseCreate($database,false)->SetObject('owner',$owner)->SetObject('item',$item)->SetObject('dest',$dest);
    }
    
    public static function CreateLink(ObjectDatabase $database, Account $owner, Item $item) : self
    {
        return parent::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item);
    }
    
    public function SetShareOptions(Input $input, ?Share $access = null) : self
    {
        $f_read =    $input->TryGetParam('read',SafeParam::TYPE_BOOL);
        $f_upload =  $input->TryGetParam('upload',SafeParam::TYPE_BOOL);
        $f_modify =  $input->TryGetParam('modify',SafeParam::TYPE_BOOL);
        $f_social =  $input->TryGetParam('social',SafeParam::TYPE_BOOL);
        $f_reshare = $input->TryGetParam('reshare',SafeParam::TYPE_BOOL);
        
        if ($f_read !== null)    $this->SetCanRead($f_read && ($access === null || $access->GetCanRead()));
        if ($f_upload !== null)  $this->SetCanUpload($f_upload && ($access === null || $access->GetCanUpload()));
        if ($f_modify !== null)  $this->SetCanModify($f_modify && ($access === null || $access->GetCanModify()));
        if ($f_social !== null)  $this->SetCanSocial($f_social && ($access === null || $access->GetCanSocial()));
        if ($f_reshare !== null) $this->SetCanReshare($f_reshare && ($access === null || $access->GetCanReshare()));
        
        return $this;
    }
    
    public static function LoadByAccount(ObjectDatabase $database, Account $account, bool $mine = false) : array // TODO limit/offset
    {
        if ($mine) return static::LoadByObject($database, 'owner', $account);
        
        $dests = array_merge(array($account), $account->GetGroups());
        $q = new QueryBuilder(); $dests = array_map(function($dest)use($q){ 
            return $q->Equals('dest', FieldTypes\ObjectPoly::GetObjectDBValue($dest)); },$dests);
        return static::LoadByQuery($database, $q->Where($q->OrArr(array_values($dests)))); 
        
        // TODO what about duplicates? also integrate with group priority
    }
    
    public static function TryLoadByOwnerAndID(ObjectDatabase $database, Account $account, string $id) : ?self
    {
        $found = static::TryLoadByID($database, $id); if (!$found) return null;
        
        return ($found->GetOwner() === $account || $found->GetItem()->GetOwner() === $account) ? $found : null;
    }

    public static function TryLoadByItemAndAccount(ObjectDatabase $database, Item $item, Account $account) : ?self
    {
        do {
            $dests = array_merge(array($account), $account->GetGroups());
            $q = new QueryBuilder(); $dests = array_map(function($dest)use($q,$item){ // TODO integrate with group priority if > 1 found
                return static::GetItemDestQuery($item, $dest, $q); },$dests);
                
            $found = static::LoadOneByQuery($database, $q->Where($q->OrArr(array_values($dests)))); 
            if ($found) return $found;
        }
        while (($item = $item->GetParent()) !== null);
        return null;
    }
    
    public static function TryLoadByIDAndKey(ObjectDatabase $database, string $id, string $key) : ?self
    {
        $found = static::TryLoadByID($database, $id);
        return $found->CheckKeyMatch($key) ? $found : null;
    }

    public function GetClientObject(bool $item = false, bool $secret = false) : array
    {
        return array_merge(parent::GetClientObject($secret),array(
            'id' => $this->ID(),
            'owner' => $this->GetOwner()->GetDisplayName(),
            'item' => $item ? $this->GetItem()->GetClientObject() : $this->GetObjectID('item'),
            'islink' => $this->IsLink(),
            'dest' => $this->GetObjectID('dest'),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters(),
            'features' => $this->GetAllFeatures()
        ));
    }
}
