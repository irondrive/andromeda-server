<?php namespace Andromeda\Apps\Accounts\Auth; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/core/database/FieldTypes.php"); use Andromeda\Core\Database\FieldTypes;
require_once(ROOT."/core/database/BaseObject.php"); use Andromeda\Core\Database\BaseObject;
require_once(ROOT."/core/database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;
require_once(ROOT."/core/database/QueryBuilder.php"); use Andromeda\Core\Database\QueryBuilder;

class Pointer extends BaseObject
{
    public static function GetFieldTemplate() : array
    {
        return array_merge(parent::GetFieldTemplate(), array(
            'description' => null,
            'authsource' => new FieldTypes\ObjectPoly(ISource::class)
        ));
    }
    
    public static function LoadBySource(ObjectDatabase $database, ISource $source) : ?ISource
    {
        $objval = FieldTypes\ObjectPoly::GetValueFromObject($source);
        $q = new QueryBuilder(); return self::LoadByQuery($database, $q->Where($q->Equals('authsource', $objval)));
    }
    
    public static function TryLoadSourceByPointer(ObjectDatabase $database, string $pointer) : ?ISource
    {
        $authsource = self::TryLoadByID($database, $pointer);
        if ($authsource === null) return null; else return $authsource->GetSource();
    }
    
    public function GetSource() : ISource { return $this->GetObject('authsource'); }
    
    public function GetDescription()
    {
        return $this->TryGetScalar("description") ?? get_class($this->GetSource());
    }
    
    public function GetClientObject() : array
    {
        return array(
            'id' => $this->ID(),
            'description' => $this->GetDescription(),
        );
    }
}
