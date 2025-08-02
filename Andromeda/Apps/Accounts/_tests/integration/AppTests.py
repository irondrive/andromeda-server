
import nacl.secret
import nacl.hash
import nacl.pwhash
import nacl.encoding
import base64

from BaseTest import BaseAppTest
from TestUtils import *

class AppTests(BaseAppTest):
    def __str__(self):
        return "ACCOUNTS"

    def getTestModules(self):
        import ContactTests, SessionTests, TwoFactorTests, AuthSourceTests, CryptoTests
        return [self, ContactTests, SessionTests, TwoFactorTests, AuthSourceTests, CryptoTests]

    def requiresInstall(self) -> bool:
        return True
    
    account:str = None
    username:str = None
    password:str = None

    def getInstallParams(self) -> dict:
        self.username = self.util.randAlphanum(8)
        self.password = self.util.randBytes(8)
        return {'username':self.username}|self.getPassword(prefix=None,newsalt=True)

    def installSelf(self):
        return self.util.assertOk(self.interface.run(app='accounts',action='install',install=True,params=self.getInstallParams()))

    def checkInstallRetval(self, retval):
        self.util.assertType(retval['id'], str)
        self.util.assertType(retval['username'], str)
        self.account = retval

    session:dict = None

    def afterInstall(self): # init
        if self.session is not None:
            return # already done
        res = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
            params={'username':self.username}|self.getPassword()))
        session = res['client']['session']
        self.session = session

    def asAdmin(self, params:dict = {}): # TODO migrate to | like getPassword
        """ Returns params with admin params added if not a private interface """
        if self.session is None:
            self.afterInstall() # make asAdmin available to other apps in afterInstall()
        params = params.copy()
        if self.interface.isPriv:
            if self.util.randMaybe():
                params['auth_sudouser'] = self.username
            else:
                params['auth_sudoacct'] = self.account['id']
        else:
            params['auth_sessionid'] = self.session['id']
            params['auth_sessionkey'] = self.session['authkey']
        return params
    
    def withSession(self, session:dict, params:dict = {}): # TODO migrate to | like getPassword
        """ Returns params with session params added """
        params = params.copy()
        params['auth_sessionid'] = session['id']
        params['auth_sessionkey'] = session['authkey']
        return params
    
    def getPassword(self, password:str=None, prefix:str='auth', newsalt:bool=False):
        """ Returns the given password as a password or passkey, as appropriate
        Adds the prefix to the beginning of the field name, if not null
        Also generates and send a new _salt if newsalt is True """
        if password is None:
            password = self.password
        field = 'password' if self.interface.isPriv else 'passkey'
        if prefix is not None:
            field = prefix+'_'+field
        if self.interface.isPriv:
            # TODO RAY !! want to do and self.util.randMaybe(): but then the correct hashing below does matter...
            return {field:password}
        else:
            # not bothering with the salt or the correct hashing process, doesn't matter
            if type(password) is str: password = password.encode('utf-8')
            ret = {field: nacl.hash.blake2b(password,encoder=nacl.encoding.Base64Encoder,digest_size=nacl.secret.SecretBox.KEY_SIZE)}
            if newsalt: ret[field+'_salt'] = base64.b64encode(self.util.randBytes(nacl.pwhash.argon2id.SALTBYTES))
            return ret
    
    def tempAccount(self):
        """ Creates a new account/session for temporary use """
        username = self.util.randAlphanum(8)
        password = self.util.randBytes(8)
        account = self.util.assertOk(self.interface.run(app='accounts',action='createaccount',
            params=self.asAdmin({'username':username}|self.getPassword(password,prefix=None,newsalt=True))))
        client = self.util.assertOk(self.interface.run(app='accounts',action='createsession',
            params={'username':username}|self.getPassword(password)))['client']
        session = client['session']
        return (username, password, account, client, session)
    
    def deleteAccount(self, account:dict):
        """ Deletes an account with the given session and password """
        self.util.assertOk(self.interface.run(app='accounts',action='deleteaccount',
            params=self.withSession(self.session, {'auth_sudoacct':account['id']}|self.getPassword())))
        
    def assertAccountRequired(self, res):
        if self.interface.isPriv:
            self.util.assertError(res, 400, 'ACCOUNT_REQUIRED')
        else:
            self.util.assertError(res, 403, 'AUTHENTICATION_FAILED')

    #################################################
    
    def testGetConfig(self):
        """ Tests the getconfig command """
        res = self.util.assertOk(self.interface.run(app='accounts',action='getconfig'))
        self.util.assertType(res['username_iscontact'], bool)
        self.util.assertType(res['create_account'], str)
        self.util.assertType(res['require_contact'], str)
        self.util.assertSame(res['default_auth'], None)

        if not self.interface.isPriv: # normie output
            self.util.assertNotIn('date_created',res)
            self.util.assertNotIn('default_group',res)
        else: # admin output
            self.util.assertType(res['date_created'], float)
            self.util.assertSame(res['default_group'], None)
            self.util.assertSame(res, self.util.assertOk(
                self.interface.run(app='accounts',action='setconfig'))) # setconfig gives admin output

    def testSudoUser(self):
        """ Tests auth_sudouser and auth_sudoacct overriding a session """
        # note this is different than withSession() because we are using sudouser to *override* a session
        (username, password, account, client, session) = self.tempAccount()

        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(self.session)))
        self.util.assertSame(res['id'], self.account['id'])
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(self.session, {'auth_sudouser':username})))
        self.util.assertSame(res['id'], account['id'])
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(self.session, {'auth_sudoacct':account['id']})))
        self.util.assertSame(res['id'], account['id'])
        self.deleteAccount(account)

    def testGetAccount(self):
        """ Test the getAccount command """
        self.assertAccountRequired(self.interface.run(app='accounts',action='getaccount'))

        (username, password, account, client, session) = self.tempAccount()
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session)))
        self.util.assertSame(res['id'], account['id'])
        self.util.assertSame(res['username'], username)
        self.util.assertSame(res['dispname'], None)
        self.util.assertNotIn('date_created',res) # full
        self.util.assertNotIn('date_modified',res) # admin
        self.util.assertNotIn('comment',res) # admin
        self.util.assertNotIn('groups',res) # admin

        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',params=self.withSession(session,{"full":True})))
        self.util.assertNotIn('comment',res) # admin
        self.util.assertSame(res['ssenc'], False)
        self.util.assertType(res['date_created'],float)
        self.util.assertType(res['date_loggedon'],float)
        self.util.assertType(res['date_active'],float)
        self.util.assertType(res['date_passwordset'],float)
        self.util.assertCount(res['recoverykeys'],0)
        self.util.assertCount(res['twofactors'],0)
        self.util.assertCount(res['contacts'],0)

        self.util.assertCount(res['clients'],1)
        self.util.assertIn(client['id'], res['clients'])

        # TODO TESTS should double check the defaults here?
        for prop in ['session_timeout','client_timeout','max_password_age','limit_clients','limit_contacts','limit_recoverykeys','disabled','account_search','group_search']:
            self.util.assertAny2((prop in res['policy']) and (res['policy'][prop] is None or type(res['policy'][prop]) == int), res['policy'])
        for prop in ['admin','forcetf','allowcrypto','userdelete']:
            self.util.assertAny2((prop in res['policy']) and (res['policy'][prop] is None or type(res['policy'][prop]) == bool), res['policy'])

        # regular accounts can use --account to get public info only
        self.util.assertError(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(session, {'account':"badID"})),404,'UNKNOWN_ACCOUNT')
        res = self.util.assertOk(self.interface.run(app='accounts',action='getaccount',
            params=self.withSession(session, {'account':self.account['id']})))
        self.util.assertSame(res['id'], self.account['id'])
        self.util.assertSame(res['username'], self.username)
        self.util.assertSame(res['dispname'], None)
        self.util.assertIn('contacts',res)

        # TODO TESTS test admin output (with --account)
        # TODO TESTS test full admin output (with --account)

        self.deleteAccount(account)

    # TODO TESTS !! create/delete acct + register allow + setfullname + relevant config
    # TODO TESTS !! group stuff (create, delete, membership), basic edit account/group
    # TODO TESTS !! all policies (timeouts, limits)
    # TODO TESTS !! account/group search and admin get
    # TODO TESTS !! test own app enable/disable and access logging
