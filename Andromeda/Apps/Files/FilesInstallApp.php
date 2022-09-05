<?php namespace Andromeda\Apps\Files; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/InstallerApp.php"); use Andromeda\Core\InstallerApp;

require_once(ROOT."/Apps/Files/Config.php");

/**
 * The files app installer
 * @extends InstallerApp<Config>
 */
final class FilesInstallApp extends InstallerApp
{
    public static function getName() : string { return 'files'; }
    
    public static function getDependencies() : array { return array('accounts'); }
    
    protected static function getConfigClass() : string { return Config::class; }

    protected function getUpgradeScripts() : array
    {
        return array(/*
            '1.0.2' => function() { },
            '1.0.4' => function() { }
        */);        
    }
}
