<?php if (!defined('Andromeda')) die();

$api = \Andromeda\Core\ApiPackage::GetInstance();

$errors = $api->GetErrorManager();
$database = $api->GetDatabase();

return array(
    /*'1.0.2' => function()use($main,$errors,$database)
    { 
        $errors->LogDebug("upgrading core to 1.0.2"); 
    },
    '1.0.4' => function()use($main,$errors,$database)
    { 
        $errors->LogDebug("upgrading core to 1.0.4"); 
    },
    '1.0.0-alpha' => function()use($main,$errors,$database)
    {
        $errors->LogDebug("upgrading core to 1.0.0-alpha");
        //$database->importTemplate(ROOT."/Core/_upgrade/1.0.0-alpha");
    }*/
);
