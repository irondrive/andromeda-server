<?php declare(strict_types=1); namespace Andromeda\Apps\Files\Policy; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{ObjectDatabase, TableTypes, FieldTypes};

abstract class Periodic extends Base
{
    use TableTypes\TableLinkedChildren;
    
    /** @return array<class-string<self>> */
    public static function GetChildMap(ObjectDatabase $database) : array { 
        return array(PeriodicAccount::class, PeriodicGroup::class, PeriodicStorage::class); }

    /** Timestamp that the object was created */
    protected FieldTypes\Timestamp $date_created;

    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->date_created = $fields[] = new FieldTypes\Timestamp('date_created');

        $this->RegisterFields($fields, self::class);
        parent::CreateFields();
    }




    // TODO RAY !! implement these
    protected function canTrackItems() : bool { return false; }
    protected function canTrackDLStats() : bool { return false; }
    public function GetSize() : int { return 0; }
    public function CheckSize(int $delta) : void { }
    public function CountSize(int $delta, bool $noLimit = false) : void { }
    public function GetItems() : int { return 0; }
    public function CountItems(int $delta = 1, bool $noLimit = false) : void { }
    public function GetPublicDownloads() : int { return 0; }
    public function CountPublicDownloads(int $delta = 1, bool $noLimit = false) : void { }
    public function GetBandwidth() : int { return 0; }
    public function CheckBandwidth(int $delta) : void { }
    public function CountBandwidth(int $delta, bool $noLimit = false) : void { }
}
