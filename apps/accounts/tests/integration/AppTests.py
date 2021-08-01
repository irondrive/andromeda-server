
import string

from TestUtils import *

class AppTests(BaseTest):
    def __str__(self):
        return "ACCOUNTS"

    def __init__(self, interface):
        super().__init__(interface)

    def install(self):
        self.username = ''.join(self.main.random.choice(string.ascii_letters) for _ in range(8))
        self.password = ''.join(self.main.random.choice(string.printable) for _ in range(16))
        self.admin = assertOk(self.interface.run('accounts','install',
            {'username':self.username,'password':self.password}))
