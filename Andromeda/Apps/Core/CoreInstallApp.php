<?php declare(strict_types=1); namespace Andromeda\Apps\Core; if (!defined('Andromeda')) die();

require_once(ROOT."/Core/Config.php");
require_once(ROOT."/Core/InstallerApp.php");
use Andromeda\Core\{Config, InstallerApp};

require_once(ROOT."/Core/Database/Database.php");
use Andromeda\Core\Database\Database;

require_once(ROOT."/Core/IOFormat/Input.php");
require_once(ROOT."/Core/IOFormat/SafeParams.php");
use Andromeda\Core\IOFormat\{Input, SafeParams};

require_once(ROOT."/Apps/Core/Exceptions.php");

/** The core config installer, also can install/upgrade all apps */
final class CoreInstallApp extends InstallerApp
{
    public function getName() : string { return 'core'; }

    protected function getTemplateFolder() : string { return ROOT.'/Core'; }
    
    /** @return class-string<Config> */
    protected function getConfigClass() : string { return Config::class; }

    public function getUsage() : array
    {
        $retval = array_merge(array(
            'usage [--appname alphanum]',
            'dbconf '.Database::GetInstallUsage(),
            ...array_map(function($u){ return "(dbconf) $u"; }, Database::GetInstallUsages()),
        ), parent::getUsage());
    
        $inst_flags = implode(" ",array_map(function(InstallerApp $installer){
            return $installer->getInstallFlags(); }, $this->runner->GetInstallers()));
        
        $upgr_flags = implode(" ",array_map(function(InstallerApp $installer){
            return $installer->getUpgradeFlags(); }, $this->runner->GetInstallers()));
        
        $retval[] = 'install-all '.$inst_flags;
        $retval[] = 'upgrade-all '.$upgr_flags;
        
        return $retval;
    }
    
    /**
     * {@inheritDoc}
     * @see InstallerApp::Run()
     */
    public function Run(Input $input)
    {
        $params = $input->GetParams();
        
        switch ($input->GetAction())
        {
            case 'usage':       return $this->GetUsages($params);
            case 'dbconf':      return $this->ConfigDB($params);
            case 'install-all': return $this->InstallAll($params);
            case 'upgrade-all': return $this->UpgradeAll($params);
            
            default: return parent::Run($input);
        }
    }

    /**
     * Collects usage strings from every installed app and returns them
     * @return array<string> array of possible commands
     */
    protected function GetUsages(SafeParams $params) : array
    {
        $want = $params->HasParam('appname') ? $params->GetParam('appname')->GetAlphanum() : null;
        
        $installers = $this->runner->GetInstallers();

        $output = array(); foreach ($installers as $name=>$installer)
        {
            if ($want !== null && $want !== $name) continue;
            
            array_push($output, ...array_map(function(string $line)use($name){
                return "$name $line"; }, $installer->getUsage()));
        }
        return $output;
    }

    /**
     * Creates a database config with the given input
     * @throws AdminRequiredException if config exists and not a privileged interface
     */
    protected function ConfigDB(SafeParams $params) : ?string
    {
        if ($this->runner->HasDatabaseConfig() &&
            !$this->runner->GetInterface()->isPrivileged())
        {
            throw new AdminRequiredException();
        }
        
        return Database::Install($params);
    }
    
    /** 
     * Returns true if a has a dependency on b (directly or indirectly)
     * @param array<string, InstallerApp> $insts
     */
    public static function HasDependency(array $insts, InstallerApp $a, InstallerApp $b) : bool
    {
        $adeps = $a->getDependencies();
        if (in_array($b->getName(), $adeps)) return true;
        
        foreach ($adeps as $adep)
        {
            if (array_key_exists($adep, $insts) && 
                self::HasDependency($insts, $insts[$adep], $b)) return true;
        }
        
        return false;
    }
    
    /**
     * Sort installers by resolving dependencies
     * @param array<string,InstallerApp> $insts
     */
    public static function SortInstallers(array &$insts) : void
    {
        uasort($insts, function(InstallerApp $a, InstallerApp $b)use($insts){
            if (self::HasDependency($insts, $a, $b)) return 1;
            if (self::HasDependency($insts, $b, $a)) return -1;
            return 0;
        });
    }
    
    /**
     * Installs all available apps (including core)
     * @return array<string, mixed> map of installed apps to their install retval
     */
    protected function InstallAll(SafeParams $params) : array
    {
        // install all existing apps
        $installers = $this->runner->GetInstallers();
        self::SortInstallers($installers);
        
        $retval = array(); foreach ($installers as $name=>$installer)
        {
            $retval[$name] = $installer->Install($params);
        }

        // TODO need to do dependency resolution, install order matters here... 
        
        return $retval;
    }
    
    /**
     * Upgrades all installed apps (including core)
     * @return array<string, mixed> map of upgraded apps to their upgrade retval
     */
    protected function UpgradeAll(SafeParams $params) : array
    {
        // upgrade all installed apps
        $installers = $this->runner->GetInstallers();
        self::SortInstallers($installers);
        
        $retval = array(); foreach ($installers as $name=>$installer)
        {
            $retval[$name] = $installer->Upgrade($params);
        }

        return $retval;
    }
    
    protected function getUpgradeScripts() : array
    {
        return array(/*
            '1.0.2' => function() { },
            '1.0.4' => function() { }
        */);        
    }
}
