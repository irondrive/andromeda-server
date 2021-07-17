#!/usr/bin/env python3

import os, sys, json, getopt, atexit

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

        interfaces = [Interface.CLI(self.phproot, self.verbose)]
        
        with open(ROOT+"config.json") as file:
            self.config = json.load(file)

        #if 'ajax' in self.config:
        #    interfaces.append(Interface.AJAX(
        #        self.config['ajax'], self.verbose))

        databases = []
        if 'sqlite' in self.config:
            databases.append(Database.SQLite(self.config['sqlite']))
        if 'mysql' in self.config:
            databases.append(Database.MySQL(self.config['mysql']))
        if 'pgsql' in self.config:
            databases.append(Database.PostgreSQL(self.config['pgsql']))

        if not len(databases):
            raise Exception("no databases configured")

        dbconfig = self.phproot+'/core/Database/Config.php'
        if os.path.exists(dbconfig):
            os.rename(dbconfig, dbconfig+'.old')
        atexit.register(self.restoreConfig)

        for interface in interfaces:
            for database in databases:
                if self.verbose:
                    print("STARTING TEST -",interface,database)
                database.install(interface)
                CoreTests.runTests(self.phproot, interface)
                os.remove(dbconfig)
    
    def restoreConfig(self):
        dbconfig = self.phproot+'/core/Database/Config.php'
        if os.path.exists(dbconfig+'.old'):
            os.rename(dbconfig+'.old', dbconfig)

if __name__ == "__main__": Main()