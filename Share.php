<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/Utilities.php"); use Andromeda\Core\Utilities;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;
require_once(ROOT."/core/exceptions/Exceptions.php"); use Andromeda\Core\Exceptions;

require_once(ROOT."/core/ioformat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/core/ioformat/SafeParam.php"); use Andromeda\Core\IOFormat\SafeParam;

require_once(ROOT."/apps/accounts/Account.php"); use Andromeda\Apps\Accounts\Account;
require_once(ROOT."/apps/accounts/AuthObject.php"); use Andromeda\Apps\Accounts\AuthObject;
require_once(ROOT."/apps/accounts/GroupStuff.php"); use Andromeda\Apps\Accounts\AuthEntity;

use Andromeda\Core\Database\CounterOverLimitException;

class ShareExpiredException extends Exceptions\ClientDeniedException { public $message = "SHARE_EXPIRED"; } // TODO need framework support to delete this object anyway...

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
            'dates__accessed' => new FieldTypes\Scalar(null, true),
            'counters__accessed' => new FieldTypes\Counter(),
            'counters_limits__accessed' => null,
            'dates__expires' => null,
            'features__read' => new FieldTypes\Scalar(true),
            'features__upload' => new FieldTypes\Scalar(false),
            'features__modify' => new FieldTypes\Scalar(false),
            'features__social' => new FieldTypes\Scalar(true),
            'features__reshare' => new FieldTypes\Scalar(false)
        ));
    }
    
    public function IsLink() : bool { return boolval($this->TryGetScalar('authkey')); }
    
    public function GetItem() : Item { return $this->GetObject('item'); }
    public function GetOwner() : Account { return $this->GetObject('owner'); }
    
    public function CanRead() : bool { return $this->GetFeature('read'); }
    public function CanUpload() : bool { return $this->GetFeature('upload'); }
    public function CanModify() : bool { return $this->GetFeature('modify'); }
    public function CanSocial() : bool { return $this->GetFeature('social'); }
    public function CanReshare() : bool { return $this->GetFeature('reshare'); }

    private function DoExpire() : void { $this->Delete(); throw new ShareExpiredException(); }
    
    public function SetAccessed() : self 
    {
        $expires = $this->TryGetDate('expires');
        if ($expires !== null && microtime(true) > $expires) $this->DoExpire();

        try { return $this->SetDate('accessed')->DeltaCounter('accessed'); }
        catch (CounterOverLimitException $e) { $this->DoExpire(); }
    }
    
    private static function GetItemDestQuery(Item $item, ?AuthEntity $dest, QueryBuilder $q) : string
    {
        return $q->And($q->IsNull('authkey'),
            $q->Equals('item',FieldTypes\ObjectPoly::GetObjectDBValue($item)),
            $q->Equals('dest',FieldTypes\ObjectPoly::GetObjectDBValue($dest)));  
    }
    
    public static function Create(ObjectDatabase $database, Account $owner, Item $item, ?AuthEntity $dest) : self
    {
        $q = new QueryBuilder(); if (($ex = static::LoadOneByQuery($database, $q->Where(static::GetItemDestQuery($item, $dest, $q)))) !== null) return $ex;      
        return parent::BaseCreate($database,false)->SetObject('owner',$owner)->SetObject('item',$item)->SetObject('dest',$dest);
    }
    
    public static function CreateLink(ObjectDatabase $database, Account $owner, Item $item) : self
    {
        return parent::BaseCreate($database)->SetObject('owner',$owner)->SetObject('item',$item);
    }
    
    public function NeedsPassword() : bool { return boolval($this->TryGetScalar('password')); }
    
    public function CheckPassword(string $password) : bool
    {
        $hash = $this->GetScalar('password');        
        $correct = password_verify($password, $hash);
        if ($correct) $this->SetPassword($password, true);
        return $correct;
    }
    
    protected function SetPassword(string $password, bool $check = false) : self
    {
        $algo = Utilities::GetHashAlgo();        
        if (!$check || password_needs_rehash($this->GetScalar('password'), $algo))
            $this->SetScalar('password', password_hash($password, $algo));        
        return $this;
    }
    
    public function SetShareOptions(Input $input, ?Share $access = null) : self
    {
        $f_read =    $input->TryGetParam('read',SafeParam::TYPE_BOOL);
        $f_upload =  $input->TryGetParam('upload',SafeParam::TYPE_BOOL);
        $f_modify =  $input->TryGetParam('modify',SafeParam::TYPE_BOOL);
        $f_social =  $input->TryGetParam('social',SafeParam::TYPE_BOOL);
        $f_reshare = $input->TryGetParam('reshare',SafeParam::TYPE_BOOL);
        
        if ($f_read !== null)    $this->SetFeature('read', $f_read && ($access === null || $access->CanRead()));
        if ($f_upload !== null)  $this->SetFeature('upload', $f_upload && ($access === null || $access->CanUpload()));
        if ($f_modify !== null)  $this->SetFeature('modify', $f_modify && ($access === null || $access->CanModify()));
        if ($f_social !== null)  $this->SetFeature('social', $f_social && ($access === null || $access->CanSocial()));
        if ($f_reshare !== null) $this->SetFeature('reshare', $f_reshare && ($access === null || $access->CanReshare()));
        
        $password = $input->TryGetParam('spassword',SafeParam::TYPE_RAW);
        if ($password !== null) $this->SetPassword($password);
        
        $expires = $input->TryGetParam('expires',SafeParam::TYPE_INT);
        if ($expires !== null) $this->SetDate('expires', $expires);
        
        $maxaccess = $input->TryGetParam('maxaccess',SafeParam::TYPE_INT);
        if ($maxaccess !== null) $this->SetCounterLimit('accesses',$maxaccess);
        
        return $this;
    }
    
    private static function GetDestsQuery(array $dests, QueryBuilder $q) : string
    {
        return $q->Or($q->OrArr(array_values($dests)),$q->And($q->IsNull('authkey'),$q->IsNull('dest')));
    }
    
    public static function LoadByAccount(ObjectDatabase $database, Account $account, bool $mine = false) : array // TODO limit/offset
    {
        if ($mine) return static::LoadByObject($database, 'owner', $account);
        
        $dests = array_merge(array($account), $account->GetGroups());
        $q = new QueryBuilder(); $dests = array_map(function($dest)use($q){ 
            return $q->Equals('dest', FieldTypes\ObjectPoly::GetObjectDBValue($dest)); },$dests);
        
        return static::LoadByQuery($database, $q->Where(static::GetDestsQuery($dests,$q))); 
        
        // TODO what about duplicates? also integrate with group priority
    }
    
    public static function TryLoadByOwnerAndID(ObjectDatabase $database, Account $account, string $id, bool $allowDest = false) : ?self
    {
        $found = static::TryLoadByID($database, $id); if (!$found) return null;
        
        $ok1 = $found->GetOwner() === $account;
        $ok2 = $found->GetItem()->GetOwner() === $account;
        $ok3 = $allowDest && $found->TryGetObject('dest') === $account;
        
        return ($ok1 || $ok2 || $ok3) ? $found : null;
    }

    public static function TryAuthenticate(ObjectDatabase $database, Item $item, Account $account) : ?self
    {
        do {
            $dests = array_merge(array($account), $account->GetGroups());
            $q = new QueryBuilder(); $dests = array_map(function($dest)use($q,$item){ 
                return static::GetItemDestQuery($item, $dest, $q); },$dests);
            
            $found = static::LoadOneByQuery($database, $q->Where(static::GetDestsQuery($dests,$q)));  // TODO integrate with group priority if > 1 found
            if ($found) return $found;
        }
        while (($item = $item->GetParent()) !== null);
        return null;
    }
    
    public static function TryAuthenticateByLink(ObjectDatabase $database, string $id, string $key, ?Item $item = null) : ?self
    {
        $share = static::TryLoadByID($database, $id);
        if (!$share || !$share->CheckKeyMatch($key)) return null;        
        if ($item === null) return $share;
        
        do { if ($item === $share->GetItem()) return $share; }
        while (($item = $item->GetParent()) !== null);
        return null;
    }

    public function GetClientObject(bool $item = false, bool $secret = false) : array
    {
        $item = $item ? $this->GetItem()->GetClientObject() : $this->GetObjectID('item');
        
        switch ($this->GetObjectType('item'))
        {
            case File::class: $itype = 'file'; break;
            case Folder::class: $itype = 'folder'; break;
            default: $itype = 'item'; break;
        }
        
        return array_merge(parent::GetClientObject($secret),array(
            'id' => $this->ID(),
            'owner' => $this->GetOwner()->GetDisplayName(),
            'item' => $item,
            'itemtype' => $itype,
            'islink' => $this->IsLink(),
            'password' => $this->NeedsPassword(),
            'dest' => $this->GetObjectID('dest'),
            'dates' => $this->GetAllDates(),
            'counters' => $this->GetAllCounters(), // TODO is the counter limit printed?
            'features' => $this->GetAllFeatures()
        ));
    }
}
