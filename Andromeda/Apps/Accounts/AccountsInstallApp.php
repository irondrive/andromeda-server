<?php namespace Andromeda\Apps\Accounts; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/InstallerApp.php"); use Andromeda\Core\InstallerApp;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;

require_once(ROOT."/Apps/Accounts/Config.php");

/** The accounts app installer */
final class AccountsInstallApp extends InstallerApp
{
    public static function getName() : string { return 'accounts'; }
    
    /** @return class-string<Config> */
    protected static function getConfigClass() : string { return Config::class; }
    
    protected static function getInstallFlags() : string { return '[--username alphanum --password raw]'; }
    
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
        
        $db = $this->RequireDatabase();
        
        if ($params->HasParam('username'))
        {
            $username = $params->GetParam("username", SafeParams::PARAMLOG_ALWAYS)->CheckLength(127)->GetAlphanum();
            $password = $params->GetParam("password", SafeParams::PARAMLOG_NEVER)->GetRawString();
            
            return Account::Create($db, Auth\Local::GetInstance(), // TODO Auth\local won't have been init'd yet, do in constructor here also? issue though, which if constructed > 1?
                $username, $password)->setAdmin(true)->GetClientObject();
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
