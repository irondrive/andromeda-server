<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\InstallerApp;
use Andromeda\Core\Utilities;
use Andromeda\Core\IOFormat\SafeParams;

/**
 * The accounts app installer
 * @phpstan-import-type ScalarOrArray from Utilities
 */
class AccountsInstallApp extends InstallerApp
{
    public function getName() : string { return 'accounts'; }
    
    protected function getConfigClass() : string { return Config::class; }
    
    protected function getInstallFlags() : string { return '[--username alphanum --password raw]'; }
    
    /**
     * {@inheritDoc}
     * Also optionally creates an admin account
     * @see InstallerApp::Install()
     * @see Account::GetClientObject()
     * @return ScalarOrArray
     */
    protected function Install(SafeParams $params)
    {
        parent::Install($params);
        
        $db = $this->runner->RequireDatabase();
        
        if ($params->HasParam('username'))
        {
            $username = $params->GetParam("username", SafeParams::PARAMLOG_ALWAYS)->CheckLength(127)->GetAlphanum();
            $password = $params->GetParam("password", SafeParams::PARAMLOG_NEVER)->GetRawString();
            
            return Account::Create($db, $username, null, $password)->SetAdmin(true)->GetClientObject(); // @phpstan-ignore-line TODO FIX ME
        }
        else return null;
    }

    protected function getUpgradeScripts() : array
    {
        return array(/*
            '1.0.2' => function() { },
            '1.0.4' => function() { }
        */);        
    }
}
