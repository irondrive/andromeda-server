<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes, FieldTypes};

abstract class Standard extends Base
{
    use TableTypes\TableLinkedChildren;
    
    /** @return array<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { 
        return array(StandardAccount::class, StandardGroup::class, StandardStorage::class); }

    /** Timestamp that the object was created */
    protected FieldTypes\Timestamp $date_created;
    /** Total item count (files and folders) */
    protected FieldTypes\Counter $count_items;
    /** Limit on the total number of items */
    protected FieldTypes\NullIntType $limit_items;
    /** Total space occupied by all items */
    protected FieldTypes\Counter $count_size;
    /** Limit on the total disk space usage */
    protected FieldTypes\NullIntType $limit_size;
    /** Total public downloads used for this object */
    protected FieldTypes\Counter $count_pubdownloads;
    /** Total bandwidth used for this object */
    protected FieldTypes\Counter $count_bandwidth;

    /** True if creating shares for items is allowed */
    protected FieldTypes\NullBoolType $can_itemshare;
    /** True if creating shares targeting groups is allowed */
    protected FieldTypes\NullBoolType $can_share2groups;
    /** True if uploading files via shares w/o being authenticated is allowed */
    protected FieldTypes\NullBoolType $can_publicupload;
    /** True if modifying files via shares w/o being authenticated is allowed */
    protected FieldTypes\NullBoolType $can_publicmodify;
    /** True if random/partial writes to files are allowed */
    protected FieldTypes\NullBoolType $can_randomwrite;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');
        $this->limit_items = $fields[] = new FieldTypes\NullIntType('limit_items');
        $this->count_items = $fields[] = new FieldTypes\Counter('count_items',limit:$this->limit_items);
        $this->limit_size = $fields[] = new FieldTypes\NullIntType('limit_size');
        $this->count_size = $fields[] = new FieldTypes\Counter('count_size',limit:$this->limit_size);
        $this->count_pubdownloads = $fields[] = new FieldTypes\Counter('count_pubdownloads');
        $this->count_bandwidth = $fields[] = new FieldTypes\Counter('count_bandwidth',saveOnRollback:true);

        $this->can_itemshare = $fields[] = new FieldTypes\NullBoolType('can_itemshare');
        $this->can_share2groups = $fields[] = new FieldTypes\NullBoolType('can_share2groups');
        $this->can_publicupload = $fields[] = new FieldTypes\NullBoolType('can_publicupload');
        $this->can_publicmodify = $fields[] = new FieldTypes\NullBoolType('can_publicmodify');
        $this->can_randomwrite = $fields[] = new FieldTypes\NullBoolType('can_randomwrite');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }

    /** Initializes the policy by gathering existing statistics */
    protected function InitializeCounts() : void
    {
        // reset the things that we need to recalculate
        $this->count_items->DeltaValue(-1*$this->count_items->GetValue());
        $this->count_size->DeltaValue(-1*$this->count_size->GetValue());
    }  

    // TODO RAY !! add Create functions
    // TODO RAY !! add GetClientObject functions

    public function GetSize() : int { 
        return $this->count_size->GetValue(); }

    public function CheckSize(int $delta) : void { 
        $this->count_size->CheckDelta($delta); }

    public function CountSize(int $delta, bool $noLimit = false) : void {
        $this->count_size->DeltaValue($delta, noLimit:$noLimit); }

    public function GetItems() : int {
        return $this->count_items->GetValue(); }

    public function CountItems(int $delta = 1, bool $noLimit = false) : void {
        $this->count_items->DeltaValue($delta, noLimit:$noLimit); }

    public function GetPublicDownloads() : int {
        return $this->count_pubdownloads->GetValue(); }

    public function CountPublicDownloads(int $delta = 1, bool $noLimit = false) : void {
        $this->count_pubdownloads->DeltaValue($delta, noLimit:$noLimit); }

    public function GetBandwidth() : int {
        return $this->count_bandwidth->GetValue(); }

    public function CheckBandwidth(int $delta) : void {
        $this->count_bandwidth->CheckDelta($delta); }

    public function CountBandwidth(int $delta, bool $noLimit = false) : void {
        $this->count_bandwidth->DeltaValue($delta, noLimit:$noLimit); }
    
    /** Returns true if the limited object should allow sharing items */
    public function GetAllowItemSharing() : ?bool { return $this->can_itemshare->TryGetValue(); }
    
    /** Returns true if the limited object should allow sharing to groups */
    public function GetAllowShareToGroups() : ?bool { return $this->can_share2groups->TryGetValue(); }
    
    /** Returns true if the limited object should allow public upload to folders */
    public function GetAllowPublicUpload() : ?bool { return $this->can_publicupload->TryGetValue(); }
    
    /** Returns true if the limited object should allow public modification of files */
    public function GetAllowPublicModify() : ?bool { return $this->can_publicmodify->TryGetValue(); } 
    
    /** Returns true if the limited object should allow random writes to files */
    public function GetAllowRandomWrite() : ?bool { return $this->can_randomwrite->TryGetValue(); }
    
}
