
import string

from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "ACCOUNTS"

    session = None

    def __init__(self, interface, config):
        super().__init__(interface, config)
        if config is not None:
            if 'session' in config: 
                self.session = config['session']

    def install(self):
        self.username = ''.join(self.main.random.choice(string.ascii_letters) for _ in range(8))
        self.password = ''.join(self.main.random.choice(string.printable) for _ in range(16))
        assertOk(self.interface.run(app='accounts',action='install',
            params={'username':self.username,'password':self.password}))
        self.session = assertOk(self.interface.run(app='accounts',action='createsession',
            params={'username':self.username,'auth_password':self.password}))['client']['session']

    def asAdmin(self, params:dict):
        assert(self.session is not None), 'no session given in config!'
        params['auth_sessionid'] = self.session['id']
        params['auth_sessionkey'] = self.session['authkey']
        return params