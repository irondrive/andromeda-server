<?php if (!defined('Andromeda')) die();

$api = \Andromeda\Core\Main::GetInstance();
$errors = $api->GetErrorManager();
$database = $api->GetDatabase();

return array(
    /*'1.0.2' => function()use($main,$errors,$database)
    {
        $errors->LogDebug("upgrading accounts to 1.0.2");
    },
    '1.0.4' => function()use($main,$errors,$database)
    {
        $errors->LogDebug("upgrading accounts to 1.0.4");
    },
    '1.0.0-alpha' => function()use($main,$errors,$database)
    {
        $errors->LogDebug("upgrading accounts to 1.0.0-alpha");
        //$database->importTemplate(ROOT."/Apps/Accounts/_upgrade/1.0.0-alpha");
    }*/
);
