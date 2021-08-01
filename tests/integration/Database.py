
import os, mysql.connector, psycopg2

import TestUtils

class Database():
    def __str__(self):
        return self.__class__.__name__
    def __init__(self, config):
        self.config = config
    def install(self, interface):
        result = interface.run('server','dbconf',self.config)
        TestUtils.assertOk(result)


class SQLite(Database):
    def install(self, interface):        
        self.config['driver'] = 'sqlite'
        self.deinstall()
        super().install(interface)
    def deinstall(self):
        if os.path.exists(self.config['dbpath']):
            os.remove(self.config['dbpath'])


class MySQL(Database):
    def install(self, interface):

        params = {}
        if 'host' in self.config:
            params['host'] = self.config['host']
        if 'dbuser' in self.config:
            params['user'] = self.config['dbuser']
        if 'dbpass' in self.config:
            params['password'] = self.config['dbpass']
        if 'unix_socket' in self.config:
            params['unix_socket'] = self.config['unix_socket']

        self.db = mysql.connector.connect(**params)

        self.db.cursor().execute(
            "CREATE DATABASE {}".format(self.config['dbname']))

        self.config['driver'] = 'mysql'
        super().install(interface)

    def deinstall(self):
        self.db.cursor().execute(
            "DROP DATABASE {}".format(self.config['dbname']))
        self.db.close()


class PostgreSQL(Database):
    def install(self, interface):
        params = {}
        if 'host' in self.config:
            params['host'] = self.config['host']
        if 'dbuser' in self.config:
            params['user'] = self.config['dbuser']
        if 'dbpass' in self.config:
            params['password'] = self.config['dbpass']

        self.db = psycopg2.connect(**params)
        self.db.autocommit = True

        self.db.cursor().execute(
            "CREATE DATABASE {}".format(self.config['dbname']))

        self.config['driver'] = 'pgsql'
        self.config['persistent'] = False
        super().install(interface)

    def deinstall(self):
        self.db.cursor().execute(
            "DROP DATABASE {}".format(self.config['dbname']))
        self.db.close()
            