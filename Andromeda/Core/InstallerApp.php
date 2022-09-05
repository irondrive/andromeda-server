<?php namespace Andromeda\Core; if (!defined('Andromeda')) { die(); }

require_once(ROOT."/Core/BaseApp.php");
require_once(ROOT."/Core/BaseConfig.php");
require_once(ROOT."/Core/Utilities.php");
require_once(ROOT."/Core/Exceptions.php");

require_once(ROOT."/Core/IOFormat/Input.php"); use Andromeda\Core\IOFormat\Input;
require_once(ROOT."/Core/IOFormat/SafeParams.php"); use Andromeda\Core\IOFormat\SafeParams;
require_once(ROOT."/Core/Database/ObjectDatabase.php"); use Andromeda\Core\Database\ObjectDatabase;

/** 
 * Describes an installer for an app that needs database installation and has upgrade
 * scripts for upgrading the database, with a BaseConfig storing the schema version
 * 
 * The base class handles the install/upgrade actions automatically
 * Unlike BaseApp() there are no custom app commit/rollback handlers
 * @template ConfigType of BaseConfig
 */
abstract class InstallerApp
{
    /** Returns the lowercase name of the app */
    public abstract function getName() : string;

    /** 
     * Returns the list of apps our database tables depend on
     * @return array<string>
     */
    public function getDependencies() : array { return array(); }
    
    /** Returns any install flags for this app */
    protected function getInstallFlags() : string { return ""; }
    /** Returns any upgrade flags for this app */
    protected function getUpgradeFlags() : string { return ""; }
    
    /**
     * Returns an array of strings showing the CLI usage of the app
     * @return array<string> possible commands
     */
    public function getUsage() : array 
    {
        $istr = 'install'; if ($if = $this->getInstallFlags()) $istr .= " $if";
        $ustr = 'upgrade'; if ($uf = $this->getUpgradeFlags()) $ustr .= " $uf";
        
        return array($istr,$ustr);
    }
    
    /** Returns the path of the app's code folder */
    protected function getTemplateFolder() : string
    {
        return ROOT.'/Apps/'.Utilities::FirstUpper($this->getName());
    }
    
    /**
     * Return the BaseConfig class for this app 
     * @return class-string<ConfigType>
     */
    protected abstract function getConfigClass() : string;
    
    /** 
     * Returns the array of upgrade scripts indexed by version (IN ORDER!) 
     * @return array<string,callable>
     */
    protected abstract function getUpgradeScripts() : array;
    
    protected InstallRunner $runner;
    
    private string $oldVersion;
    private int $install_state;
    
    /** The app needs installing */
    public const NEED_INSTALL = 0;
    /** The app needs upgrading */
    public const NEED_UPGRADE = 1;
    /** The app is installed+upgraded */
    public const NEED_NOTHING = 2;
    
    /**
     * Creates a new installer app and checks the install state
     * @param InstallRunner $runner InstallRunner instance
     */
    public function __construct(InstallRunner $runner)
    {
        $this->runner = $runner;
        
        if ($runner->HasDatabase()) try
        {
            $class = $this->getConfigClass();
            $class::GetInstance($runner->RequireDatabase());
            $this->install_state = self::NEED_NOTHING;
        }
        catch (InstallRequiredException $e) { 
            $this->install_state = self::NEED_INSTALL; }
        catch (UpgradeRequiredException $e) { 
            $this->oldVersion = $e->getOldVersion();
            $this->install_state = self::NEED_UPGRADE; }
    }
    
    /** @see InstallRunner::RequireDatabase() */
    public function RequireDatabase() : ObjectDatabase 
    { 
        return $this->runner->RequireDatabase(); 
    }
    
    /** Returns the install state enum for this app */
    public function getInstallState() : int { return $this->install_state; }

    /**
     * Run an action on the installer with the given input
     * 
     * Automatically handles the install/upgrade actions
     * @param Input $input the user input
     * @return mixed the result value to be output to the user
     * @throws UnknownActionException if unknown action
     */
    public function Run(Input $input)
    {
        switch ($input->GetAction())
        {
            case 'install': return $this->Install($input->GetParams());
            case 'upgrade': return $this->Upgrade($input->GetParams());
            default: throw new UnknownActionException();
        }
    }
    
    /** 
     * Installs the app by importing its SQL file and creating config 
     * @return mixed
     */
    protected function Install(SafeParams $params)
    {
        $db = $this->runner->RequireDatabase();
        
        if ($this->install_state > self::NEED_INSTALL)
            throw new InstalledAlreadyException($this->getName());
        
        $installers = $this->runner->GetInstallers();
        foreach ($this->getDependencies() as $depapp)
        {
            if (!array_key_exists($depapp, $installers) || 
                $installers[$depapp]->getInstallState() !== self::NEED_NOTHING)
                throw new AppDependencyException($this->getName()." requires $depapp");
        }
        
        $db->GetInternal()->importTemplate($this->getTemplateFolder());
        
        ($this->getConfigClass())::Create($db)->Save();
        $this->install_state = self::NEED_NOTHING;
    }
    
    /**
     * Iterates over the list of upgrade scripts, running them
     * sequentially until the DB is up to date with the code
     * @return mixed
     */
    protected function Upgrade(SafeParams $params)
    {
        $db = $this->runner->RequireDatabase();
        
        if ($this->install_state > self::NEED_UPGRADE)
            throw new UpgradedAlreadyException($this->getName());
    
        $class = $this->getConfigClass();
        
        foreach ($this->getUpgradeScripts() as $newVersion=>$script)
        {
            if (version_compare($newVersion, $this->oldVersion) === 1 &&
                version_compare($newVersion, $class::getVersion()) <= 0)
            {
                $script($db); // run app upgrade script
            }
        }
        
        $class::ForceUpdate($db); // load, set version        
        $this->install_state = self::NEED_NOTHING;
    }
}

