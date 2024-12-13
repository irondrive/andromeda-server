
import abc, os, mysql.connector, psycopg2, atexit

from Interface import Interface
from TestUtils import *

class Database():
    """ Base class for database types """
    def __str__(self):
        return self.__class__.__name__

    def __init__(self, config:dict, dbconf:str):
        self.config = config
        self.dbconf = dbconf

    @abc.abstractmethod
    def install(self, util:TestUtils, interface:Interface):
        """ Install the database using the given interface """
        params = self.config.copy()
        util.assertError(interface.run(app='core',action='dbconf',
            params=params,install=True), 400, "SAFEPARAM_KEY_MISSING: outfile")
        
        params['outfile'] = "-" # return to stdout
        conf = util.assertOk(interface.run(app='core',action='dbconf',
            params=params,install=True))
        util.assertIn('?php', conf)
        util.assertIn('DRIVER', conf)

        params['outfile'] = None # store to default
        util.assertOk(interface.run(app='core',action='dbconf',
            params=params,install=True))
        atexit.register(self.deinstall)

    @abc.abstractmethod
    def deinstall(self):
        """ Cleanup the database config that was created """
        # leave the test database itself intact so it can be examined after testing
        atexit.unregister(self.deinstall)
        os.remove(self.dbconf)


class SQLite(Database):
    def install(self, util:TestUtils, interface:Interface):
        self.config['driver'] = 'sqlite'

        path = self.config['dbpath']
        if not os.path.isabs(path):
            path = os.getcwd()+'/'+path
            self.config['dbpath'] = path
        
        if os.path.exists(self.config['dbpath']):
            os.remove(self.config['dbpath'])
        
        super().install(util, interface)


class MySQL(Database):
    def install(self, util:TestUtils, interface:Interface):
        self.config['driver'] = 'mysql'
        params = {}
        if 'host' in self.config:
            params['host'] = self.config['host']
        if 'dbuser' in self.config:
            params['user'] = self.config['dbuser']
        if 'dbpass' in self.config:
            params['password'] = self.config['dbpass']
        if 'unix_socket' in self.config:
            params['unix_socket'] = self.config['unix_socket']
        if 'ssl_disabled' in self.config:
            params['ssl_disabled'] = self.config['ssl_disabled']

        self.db = mysql.connector.connect(**params)
        self.db.cursor().execute(
            "DROP DATABASE IF EXISTS {}".format(self.config['dbname']))
        self.db.cursor().execute(
            "CREATE DATABASE {}".format(self.config['dbname']))

        super().install(util, interface)

    def deinstall(self):
        self.db.close()
        super().deinstall()


class PostgreSQL(Database):
    def install(self, util:TestUtils, interface:Interface):
        self.config['driver'] = 'pgsql'
        self.config['persistent'] = False
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
            "DROP DATABASE IF EXISTS {}".format(self.config['dbname']))
        self.db.cursor().execute(
            "CREATE DATABASE {}".format(self.config['dbname']))

        super().install(util, interface)

    def deinstall(self):
        self.db.close()
        super().deinstall()
            