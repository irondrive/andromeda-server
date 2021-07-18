
from BaseAppTests import *
from TestUtils import *

class AppTests(BaseAppTests):
    def __str__(self):
        return "SERVER"

    account = None

    def install(self):
        pass # already installed by main

    def runTests(self):
        if 'accounts' in self.main.appMap:        
            self.account = self.main.appMap['accounts'].account
