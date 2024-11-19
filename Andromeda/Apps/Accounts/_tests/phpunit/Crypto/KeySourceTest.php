<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts\Crypto; require_once("init.php");

use Andromeda\Core\Database\{BaseObject, TableTypes};

class MyKeySource extends BaseObject
{
    use KeySource, TableTypes\TableNoChildren;
    
    protected function CreateFields() : void
    {
        $fields = array();
        
        $this->RegisterFields($fields, self::class);
        
        $this->KeySourceCreateFields();
        
        parent::CreateFields();
    }
    
    // TODO other methods?
}

/*class KeySourceTest extends \PHPUnit\Framework\TestCase
{
    
}*/

