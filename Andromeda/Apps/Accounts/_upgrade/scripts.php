<?php if (!defined('Andromeda')) die();

$main = \Andromeda\Core\Main::GetInstance();
$errors = $main->GetErrorManager();
$database = $main->GetDatabase();

return array(
    /*'1.0.2' => function()use($main,$errors,$database)
    {
        $errors->LogDebug("upgrading accounts to 1.0.2");
    },
    '1.0.4' => function()use($main,$errors,$database)
    {
        $errors->LogDebug("upgrading accounts to 1.0.4");
    },
    '2.0.0-alpha' => function()use($main,$errors,$database)
    {
        $errors->LogDebug("upgrading accounts to 2.0.0-alpha");
        //$database->importTemplate(ROOT."/Apps/Accounts/_upgrade/2.0.0-alpha");
    }*/
);
