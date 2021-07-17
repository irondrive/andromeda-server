
import os, CoreTests

class Database():
    def __str__(self):
        return self.__class__.__name__
    def __init__(self, config):
        self.config = config

class SQLite(Database):
    def install(self, interface):
        
        self.config['driver'] = 'sqlite'
        if os.path.exists(self.config['dbpath']):
            os.remove(self.config['dbpath'])

        result = interface.run('server','dbconf',self.config)
        CoreTests.assertOk(result)

class MySQL(Database):
    def install(self, interface):
        self.config['driver'] = 'mysql'
        # TODO implement MySQL

class PostgreSQL(Database):
    def install(self, interface):
        self.config['driver'] = 'pgsql'
        # TODO implement pgsql
