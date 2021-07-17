#!/usr/bin/env python3

import os, sys, json, getopt, atexit, time

import Interface, Database, CoreTests

ROOT = os.path.dirname(os.path.realpath(sys.argv[0]))+'/'

class Main():

    phproot = '.'
    verbose = False

    def __init__(self):

        shortargs = "vr:"
        longargs = ["verbose","phproot="]
        opts, args = getopt.getopt(sys.argv[1:],shortargs,longargs)

        for opt,arg in opts:
            if opt in ('-r','--phproot'):
                self.phproot = arg
            if opt in ('-v','--verbose'):
                self.verbose = True

        if not os.path.exists(self.phproot+'/index.php'):
            raise Exception("cannot find index.php")            

        with open(ROOT+"config.json") as file:
            self.config = json.load(file)

        interfaces = []        
        if 'cli' in self.config:
            interfaces.append(Interface.CLI(
                self.phproot, self.config['cli'], self.verbose))
        if 'ajax' in self.config:
            interfaces.append(Interface.AJAX(
                self.config['ajax'], self.verbose))

        databases = []
        if 'sqlite' in self.config:
            databases.append(Database.SQLite(self.config['sqlite']))
        if 'mysql' in self.config:
            databases.append(Database.MySQL(self.config['mysql']))
        if 'pgsql' in self.config:
            databases.append(Database.PostgreSQL(self.config['pgsql']))

        if not len(databases):
            raise Exception("no databases configured")

        self.dbconfig = self.phproot+'/core/Database/Config.php'
        if os.path.exists(self.dbconfig):
            os.rename(self.dbconfig, self.dbconfig+'.old')
        atexit.register(self.restoreConfig)

        for database in databases:
            for interface in interfaces:
                if self.verbose:
                    print("\n--- STARTING TEST -",interface,database,'---')
                atexit.register(database.deinstall)
                database.install(interface)
                CoreTests.runTests(self.phproot, interface)
                database.deinstall()
                atexit.unregister(database.deinstall)
                os.remove(self.dbconfig); 
                os.sync(); time.sleep(1)
    
    def restoreConfig(self):
        if os.path.exists(self.dbconfig+'.old'):
            os.rename(self.dbconfig+'.old', self.dbconfig)

if __name__ == "__main__": Main()