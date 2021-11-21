#!/usr/bin/env python3

import os, sys, json, getopt, atexit, time, random, importlib

import Interface, Database, TestUtils

class Main():

    phproot = '.'
    verbose = False
    doInstall = True

    random = None
    randseed = 0

    appMap = { } # map of app name to test object
    servApps = [ ] # array of apps enabled on the server

    interfaces = [ ]
    databases = [ ]

    testMatch = None

    def __init__(self, config):

        shortargs = "hvp:s:t:n"
        longargs = ["help","verbose","phproot=","seed=","test=","noinst"]
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
            if opt in ('-t','--test'):
                self.testMatch = arg
            if opt in ('-n','--noinst'):
                self.doInstall = False

        if not os.path.exists(self.phproot+'/index.php'):
            raise Exception("cannot find index.php")            

        with open(config) as file:
            self.config = json.load(file)
  
        if 'cli' in self.config and self.config['cli']:
            self.interfaces.append(Interface.CLI(
                self, self.phproot, self.config['cli'], self.verbose))
        if 'ajax' in self.config and self.config['ajax']:
            self.interfaces.append(Interface.AJAX(
                self, self.config['ajax'], self.verbose))

        if not len(self.interfaces):
            raise Exception("no interfaces configured")

        if self.doInstall and 'sqlite' in self.config:
            self.databases.append(Database.SQLite(self.config['sqlite']))
        if self.doInstall and 'mysql' in self.config:
            self.databases.append(Database.MySQL(self.config['mysql']))
        if self.doInstall and 'pgsql' in self.config:
            self.databases.append(Database.PostgreSQL(self.config['pgsql']))

        if not self.doInstall: self.databases.append(None)

        if not len(self.databases):
            raise Exception("no databases configured")

        if self.doInstall:
            self.dbconfig = self.phproot+'/DBConfig.php'
            if os.path.exists(self.dbconfig):
                os.rename(self.dbconfig, self.dbconfig+'.old')
            atexit.register(self.restoreConfig)

        self.random = random.Random()
        self.random.seed(self.randseed)

        for database in self.databases:
            for interface in self.interfaces: 
                print();print("------------------------------------")
                print("--- TEST SUITE -",interface,database,'---')
                print("------------------------------------")

                self.runTests(interface, database)
        
        count = 0
        for iface in self.interfaces: count += iface.count
        print();print("!! ALL TESTS COMPLETE! RAN {} COMMANDS!".format(count))


    def runTests(self, interface, database):

        # test usage command works pre-configure
        TestUtils.assertOk(interface.run(app='core',action='usage'))
        TestUtils.assertError(interface.run(app='core',action='getconfig'),
            500,'DATABASE_CONFIG_MISSING')

        if self.doInstall:
            atexit.register(database.deinstall)
            database.install(interface)

        # build list of server apps
        if self.doInstall:
            for app in os.listdir(self.phproot+'/Apps'): 
                path = self.phproot+'/Apps/'+app+'/'+app+'App.php'
                if not os.path.exists(path): continue 
                else: self.servApps.append(app.lower())
        else:
            config = TestUtils.assertOk(interface.run(app='core',action='getconfig'))
            self.servApps = config['config']['apps'].keys()

        # load app test modules
        for app in self.servApps:
            path = self.phproot+'/Apps/'+app.capitalize()+'/_tests/integration'
            if not os.path.exists(path): continue

            spec = importlib.util.spec_from_file_location('AppTests', path+'/AppTests.py')
            module = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(module)

            appConfig = None
            if app in self.config: appConfig = self.config[app]
            self.appMap[app] = module.AppTests(interface, appConfig)

        if self.verbose: print("APPS FOUND:", list(self.appMap.keys()))
        
        appTests = list(self.appMap.values())
        self.random.shuffle(appTests)

        # install everything
        if self.doInstall:
            print(" -- BEGIN INSTALLS -- ")

            # test usage command works pre-install
            TestUtils.assertOk(interface.run(app='core',action='usage'))
            TestUtils.assertError(interface.run(app='core',action='getconfig'),
                500,'APP_INSTALL_REQUIRED: core')

            # test installing everything at once
            params = { }
            for app in appTests:
                for key,val in app.getInstallParams().items():
                    params[key] = val
            appNames = TestUtils.assertOk(interface.run(app='core',action='install',params=params))
            TestUtils.assertEquals(set(self.servApps), set(appNames))

            database.deinstall()
            os.remove(self.dbconfig)
            database.install(interface)

            # test installing everything separately
            appNames = TestUtils.assertOk(interface.run(app='core',action='install',params={'noapps':True}))
            TestUtils.assertEquals(set(self.servApps), set(appNames))
            for app in appTests: app.install()

        # run all test modules
        print(" -- BEGIN", interface, "TESTS --"); interface.runTests()
        for app in appTests: print(" -- BEGIN", app, "TESTS --"); app.runTests()

        if self.doInstall:
            atexit.unregister(database.deinstall)
            database.deinstall()
            os.remove(self.dbconfig)
    
    def restoreConfig(self):
        if os.path.exists(self.dbconfig+'.old'):
            os.rename(self.dbconfig+'.old', self.dbconfig)

if __name__ == "__main__": Main(config=os.getcwd()+'/../pytest-config.json')