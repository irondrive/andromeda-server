#!/usr/bin/env python3

import os, sys, json, getopt, atexit, time, random, importlib, colorama

import Interface, Database, TestUtils

class Main():

    phproot = '.'
    verbose = False

    random = None
    randseed = 0

    appList = [ ] # array of apps on the server
    appMap = { } # map of app name to test object

    interfaces = [ ]
    databases = [ ]

    testMatch = None

    def __init__(self, config):

        self.random = random.Random()

        # process command line args
        shortargs = "hvp:s:f:"
        longargs = ["help","verbose","phproot=","seed=","filter="]
        opts, args = getopt.getopt(sys.argv[1:],shortargs,longargs)

        for opt,arg in opts:
            if opt in ('-h','--help'):
                print(longargs); sys.exit(1)
            if opt in ('-p','--phproot'):
                self.phproot = arg
            if opt in ('-v','--verbose'):
                self.verbose = True
            if opt in ('-s','--seed'):
                self.randseed = arg
            if opt in ('-f','--filter'):
                self.testMatch = arg

        if not os.path.exists(self.phproot+'/Core'):
            raise Exception("cannot find /Andromeda")
        if not os.path.exists(config):
            raise Exception(f"cannot find {config}")

        # load the config file, process
        with open(config) as file:
            self.config = json.load(file)

        interfaces = self.config['interfaces']

        if 'cli' in interfaces:
            self.interfaces.append(Interface.CLI(
                self, interfaces['cli'], self.verbose))
        if 'http' in interfaces:
            self.interfaces.append(Interface.HTTP(
                self, interfaces['http'], self.verbose))

        if not len(self.interfaces):
            raise Exception("no interfaces configured")

        databases = self.config['databases']

        if 'sqlite' in databases:
            self.databases.append(Database.SQLite(databases['sqlite']))
        if 'mysql' in databases:
            self.databases.append(Database.MySQL(databases['mysql']))
        if 'pgsql' in databases:
            self.databases.append(Database.PostgreSQL(databases['pgsql']))

        if not len(self.databases):
            raise Exception("no databases configured")

        # check for an existing DBConfig
        self.dbconfig = self.phproot+'/DBConfig.php'
        if os.path.exists(self.dbconfig):
            os.rename(self.dbconfig, self.dbconfig+'.old')
        atexit.register(self.restoreConfig)

        # run tests for every database/interface combination
        testCount = 0
        for database in self.databases:
            for interface in self.interfaces: 
                self.printWhiteBack(
                    " ------------------------------------\n",
                    "--- TEST SUITE -",interface,database,'---\n',
                    "------------------------------------"); print()
                testCount += self.runTests(interface, database)
        
        apiCount = 0
        for iface in self.interfaces: 
            apiCount += iface.apiCount
        
        self.printGreenBack("\n!! ALL TESTS COMPLETE! "
            f"RAN {testCount} TESTS, {apiCount} COMMANDS!\n")


    def runTests(self, interface, database):

        # build the app list by scanning
        for app in os.listdir(self.phproot+'/Apps'): 
            path = self.phproot+'/Apps/'+app+'/'+app+'App.php'
            if not os.path.exists(path): continue 
            else: self.appList.append(app.lower())

        # load the python test module for every app
        for app in self.appList:
            path = self.phproot+'/Apps/'+app.capitalize()+'/_tests/integration'
            if not os.path.exists(path): continue

            spec = importlib.util.spec_from_file_location('AppTests', path+'/AppTests.py')
            module = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(module)

            appConfig = None
            if app in self.config: appConfig = self.config[app]
            self.appMap[app] = module.AppTests(interface, appConfig)

        print("APPS TO TEST:", ', '.join(self.appMap.keys()))
        
        self.random.seed(self.randseed)

        appTests = list(self.appMap.values())
        self.random.shuffle(appTests)

        self.printCyanFore("-- BEGIN INSTALLS -- ")

        # test usage command works pre-configure
        TestUtils.assertOk(interface.run(app='core',action='usage'))
        TestUtils.assertError(interface.run(app='core',action='getconfig'),
            503, 'DATABASE_CONFIG_MISSING')
            
        atexit.register(database.deinstall)
        database.install(interface)

        # test usage command works pre-install
        TestUtils.assertOk(interface.run(app='core',action='usage'))
        TestUtils.assertError(interface.run(app='core',action='getconfig'),
            503, 'APP_INSTALL_REQUIRED: core')

        # install just the core
        appNames = TestUtils.assertOk(interface.run(app='core',action='install',params={'noapps':True}))
        TestUtils.assertEquals(set(['core']), set(appNames))

        # test installing apps if allowed
        if interface.isPriv:
            for app in appTests: app.install()

        database.deinstall()
        os.remove(self.dbconfig)
        database.install(interface)

        # install everything at once
        params = { }
        for app in appTests:
            for key,val in app.getInstallParams().items():
                params[key] = val
        appNames = TestUtils.assertOk(interface.run(app='core',action='install',params=params))
        TestUtils.assertEquals(set(self.appList), set(appNames))

        # run all test modules
        self.printCyanFore("-- BEGIN", interface, "TESTS --")
        testCount = interface.runTests()
        
        for app in appTests: 
            self.printCyanFore("-- BEGIN", app, "TESTS --"); 
            testCount += app.runTests()

        atexit.unregister(database.deinstall)
        database.deinstall()
        os.remove(self.dbconfig)

        self.printGreenFore(f"{testCount} TESTS COMPLETE!")

        return testCount

    def restoreConfig(self):
        if os.path.exists(self.dbconfig+'.old'):
            os.rename(self.dbconfig+'.old', self.dbconfig)

    def printGreenBack(self, *args):
        print(colorama.Back.GREEN)
        print(*args, colorama.Back.RESET)

    def printWhiteBack(self, *args):
        print(colorama.Back.WHITE + colorama.Fore.BLACK)
        print(*args, colorama.Back.RESET + colorama.Fore.RESET)

    def printCyanFore(self, *args):
        print(colorama.Fore.CYAN, *args, colorama.Fore.RESET)

    def printGreenFore(self, *args):
        print(colorama.Fore.GREEN, *args, colorama.Fore.RESET)

if __name__ == "__main__": 
    Main(config=os.getcwd()+'/../tools/conf/pytest-config.json')
