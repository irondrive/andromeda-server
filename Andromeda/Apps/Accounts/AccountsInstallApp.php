<?php declare(strict_types=1); namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) die();

use Andromeda\Core\InstallerApp;
use Andromeda\Core\IOFormat\SafeParams;

/**
 * The accounts app installer
 * @extends InstallerApp<Config>
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
     * @return ?array Account if admin was created
     */
    protected function Install(SafeParams $params) : ?array
    {
        parent::Install($params);
        
        $db = $this->runner->RequireDatabase();
        
        if ($params->HasParam('username'))
        {
            $username = $params->GetParam("username", SafeParams::PARAMLOG_ALWAYS)->CheckLength(127)->GetAlphanum();
            $password = $params->GetParam("password", SafeParams::PARAMLOG_NEVER)->GetRawString();
            
            return Account::Create($db, AuthSource\Local::GetInstance(), // TODO Auth\local won't have been init'd yet, do in constructor here also? issue though, which if constructed > 1?
                $username, $password)->SetAdmin(true)->GetClientObject();
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
