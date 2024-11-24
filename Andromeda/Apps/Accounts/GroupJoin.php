<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\Database\{BaseObject, FieldTypes, ObjectDatabase, QueryBuilder, TableTypes};

/** Class representing a group membership, joining an account and a group */
class GroupJoin extends BaseObject
{
    use TableTypes\TableNoChildren;
    
    /** The date this session was created */
    private FieldTypes\Timestamp $date_created;

    // TODO RAY !! not sure if this should become a base class again... the goal though is to get some kind of caching of loads!
    // should be fairly easy since load/delete of join objects only happen through this class. can intercept and update the cache
    // maybe present a simple interface - e.g. load by account returns an array of pairs of (Group, GroupJoin) ...



    


    /** 
     * Return the column name of the left side of the join
     * 
     * Must match the name of the column in the right-side object that refers to this join
     */
    protected static function GetLeftField() : string { return 'accounts'; }
    
    /**
     * Return the column name of the right side of the join
     *
     * Must match the name of the column in the left-side object that refers to this join
     */
    protected static function GetLeftClass() : string { return Account::class; }
    
    /** Return the column name of the right side of the join */
    protected static function GetRightField() : string { return 'groups'; }
    
    /** Return the object class referred to by the right side of the join */
    protected static function GetRightClass() : string { return Group::class; }
    
    /** Returns the joined account */
    public function GetAccount() : Account { return $this->GetObject('accounts'); }
    
    /** Returns the joined group */
    public function GetGroup() : Group { return $this->GetObject('groups'); }
    
    /**
     * Returns a printable client object of this group membership
     * @return array<mixed> `{dates:{created:float}}`
     */
    public function GetClientObject()
    {
        return array(
            'date_created' => $this->GetDateCreated()
        );
    }
}
