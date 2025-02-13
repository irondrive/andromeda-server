
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
        self.interface = interface
        self.verbose = verbose

    def getTestModules(self):
        return [self]

    def runTests(self, testMatch:str) -> int:
        """ Run all tests for this module and return the test count """
        testCount = 0

        funcs = []
        for module in self.getTestModules():
            attrs = (getattr(module, name) for name in dir(module))
            # retrieve all functions from the module
            myfuncs = list(filter(lambda attr: inspect.isfunction(attr) or inspect.ismethod(attr), attrs))
            # filter functions by test* naming
            myfuncs = list(filter(lambda func: func.__name__.startswith("test"), myfuncs))
            # filter functions by testMatch if necessary
            myfuncs = list(filter(lambda func: testMatch is None or
                re.search(testMatch, func.__name__, re.IGNORECASE) is not None, myfuncs))
            funcs += myfuncs
        self.util.random.shuffle(funcs)

        for func in funcs:
            if self.verbose >= 1: 
                printYellowOnBlack('RUN TEST:',func.__name__+'()')

            rval = func() if inspect.ismethod(func) else func(self)

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
        return testCount
    
    def afterInstall(self):
        """ Function to run after all apps are installed, but before ANY appTests are run """
        pass
    
class BaseAppTest(BaseTest):
    """ The base class that all app test modules inherit from """
    appTestMap:dict = None
    config = None

    def __init__(self, util:TestUtils, interface:Interface, verbose:int, appTestMap:dict, config):
        super().__init__(util, interface, verbose)
        self.appTestMap = appTestMap
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
