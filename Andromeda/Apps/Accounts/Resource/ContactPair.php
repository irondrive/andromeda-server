<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Resource; if (!defined('Andromeda')) die();

/** A pair of a contact address and its class type */
class ContactPair
{
    /** 
     * contact type 
     * @var class-string<Contact>
     */
    private string $class;
    
    /** contact address */
    private string $addr;
    
    /**
     * @param class-string<Contact> $class contact type
     * @param string $addr contact address
     */
    public function __construct(string $class, string $addr)
    {
        $this->class = $class;
        $this->addr = $addr;
    }
    
    /** @return class-string<Contact> the contact type */
    public function GetClass() : string { return $this->class; }
    
    /** Returns the contact address */
    public function GetAddr() : string { return $this->addr; }
}
