
import abc, inspect, re

from Interface import Interface
from TestUtils import *

class BaseTest():
    """ The base class that all test modules inherit from """
    util:TestUtils = None
    interface:Interface = None
    verbose:int = None

    def __init__(self, util:TestUtils, interface:Interface, verbose:int):
        self.util = util
        self.verbose = verbose
        self.interface = interface

    def runTests(self, testMatch:str) -> int:
        """ Run all tests for this module and return the test count """
        testCount = 0
        self.beforeTests()

        attrs = (getattr(self, name) for name in dir(self))
        funcs = list(filter(lambda attr: 
            inspect.ismethod(attr) and attr.__name__.startswith("test"), attrs))
        funcs = list(filter(lambda func: testMatch is None or
            re.search(testMatch, func.__name__) is not None, funcs))
        self.util.random.shuffle(funcs)

        for func in funcs:
            if self.verbose >= 1: 
                printYellowOnBlack('RUN TEST:',func.__name__+'()')
            rval = func()
            if rval is False: # return False is skipped test
                if self.verbose >= 1:
                    printYellowOnBlack('... SKIPPED',func.__name__+'()')
                else: print('S',end='',flush=True)
            else:
                testCount += 1
                if self.verbose >= 1:
                    printYellowOnBlack('... COMPLETE',func.__name__+'()')
                else: print('.',end='',flush=True)
        if not self.verbose: print()
        self.afterTests()
        return testCount
    
    def afterInstall(self):
        """ Function to run after install, before appTests """
        pass
    
    def beforeTests(self):
        """ Function to run just before runTests() for init """
        pass

    def afterTests(self):
        """ Function to run just after runTests() for cleanup """
        pass

class BaseAppTest(BaseTest):
    """ The base class that all app test modules inherit from """
    config = None
    def __init__(self, util:TestUtils, interface:Interface, verbose:int, config):
        super().__init__(util, interface, verbose)
        self.config = config if config is not None else {}

    @abc.abstractmethod
    def requiresInstall(self) -> bool:
        """ Returns true if the app requires install """
        return False
    
    @abc.abstractmethod
    def getInstallParams(self) -> dict:
        """ Returns the dict of params to pass to the single install command """
        pass

    @abc.abstractmethod
    def installSelf(self):
        """ Runs the installer command to install the app """
        pass

    @abc.abstractmethod
    def checkInstallRetval(self, retval):
        """ Checks the return value from the single install command"""
        pass
