#!/usr/bin/env python3

import os, sys, json, getopt, atexit, time, random, importlib

import Interface, Database, TestUtils

ROOT = os.path.dirname(os.path.realpath(sys.argv[0]))+'/'

class Main():

    phproot = '.'
    verbose = False

    random = None
    randseed = 0

    appMap = { }

    interfaces = [ ]
    databases = [ ]

    def __init__(self):

        shortargs = "uvp:s:"
        longargs = ["usage","verbose","phproot=","seed="]
        opts, args = getopt.getopt(sys.argv[1:],shortargs,longargs)

        for opt,arg in opts:
            if opt in ('-u','--usage'):
                print(longargs); sys.exit(1)
            if opt in ('-p','--phproot'):
                self.phproot = arg
            if opt in ('-v','--verbose'):
                self.verbose = True
            if opt in ('-s','--seed'):
                self.randseed = arg

        if not os.path.exists(self.phproot+'/index.php'):
            raise Exception("cannot find index.php")            

        with open(ROOT+"config.json") as file:
            self.config = json.load(file)

        self.random = random.Random()
        self.random.seed(self.randseed)
  
        if 'cli' in self.config:
            self.interfaces.append(Interface.CLI(
                self.phproot, self.config['cli'], self.verbose))
        if 'ajax' in self.config:
            self.interfaces.append(Interface.AJAX(
                self.config['ajax'], self.verbose))

        if not len(self.interfaces):
            raise Exception("no interfaces configured")

        if 'sqlite' in self.config:
            self.databases.append(Database.SQLite(self.config['sqlite']))
        if 'mysql' in self.config:
            self.databases.append(Database.MySQL(self.config['mysql']))
        if 'pgsql' in self.config:
            self.databases.append(Database.PostgreSQL(self.config['pgsql']))

        if not len(self.databases):
            raise Exception("no databases configured")

        self.dbconfig = self.phproot+'/core/Database/Config.php'
        if os.path.exists(self.dbconfig):
            os.rename(self.dbconfig, self.dbconfig+'.old')
        atexit.register(self.restoreConfig)

        for database in self.databases:
            for interface in self.interfaces:
                print("\n--- TEST SUITE -",interface,database,'---')

                atexit.register(database.deinstall)
                database.install(interface)

                self.runTests(interface)

                atexit.unregister(database.deinstall)
                database.deinstall()

                os.remove(self.dbconfig)
                os.sync(); time.sleep(1) # TODO why???
        
        print("\n!ALL TESTS COMPLETE!")

    def runTests(self, interface):
        
        for app in os.listdir('./apps'):        
            path = './apps/'+app+'/tests/integration'
            if not os.path.exists(path): continue

            spec = importlib.util.spec_from_file_location('AppTests', path+'/AppTests.py')
            module = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(module)

            self.appMap[app] = module.AppTests(self, interface)

        if self.verbose: print("APPS FOUND:", list(self.appMap.keys()))

        print(" -- BEGIN INSTALLS -- ")
        appNames = TestUtils.assertOk(interface.run('server','install',{'enable':True}))
        assert(set(app for app in self.appMap if app != 'server') == set(appNames))      
        
        appTests = list(self.appMap.values())
        self.random.shuffle(appTests)

        for app in appTests: app.install()

        print(" -- BEGIN", interface, "TESTS --")
        interface.runTests()

        for app in appTests: 
            print(" -- BEGIN APP TESTS -", app)
            app.runTests()
    
    def restoreConfig(self):
        if os.path.exists(self.dbconfig+'.old'):
            os.rename(self.dbconfig+'.old', self.dbconfig)

if __name__ == "__main__": Main()