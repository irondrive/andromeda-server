
def testChangePassword(self):
    """ Tests basic password change """
    (username, password, account, client, session) = self.tempAccount()
    password2 = self.util.randAlphanum(8)

    # test that password change requires a session, and the correct old password
    self.assertAccountRequired(self.interface.run(app='accounts',action='changepassword'))
    self.util.assertError(self.interface.run(app='accounts',action='changepassword', 
        params=self.withSession(session)),403,'PASSWORD_REQUIRED')
    self.util.assertError(self.interface.run(app='accounts',action='changepassword',
        params=self.withSession(session,self.getPassword('wrong')|self.getPassword(password2,prefix='new',newsalt=True))),403,'AUTHENTICATION_FAILED')
    
    # change the password, check that the new one now works and the old one doesn't
    self.util.assertOk(self.interface.run(app='accounts',action='changepassword',
        params=self.withSession(session,self.getPassword(password)|self.getPassword(password2,prefix='new',newsalt=True))))
    self.util.assertError(self.interface.run(app='accounts',action='createsession',
        params={'username':username}|self.getPassword(password)),403,'AUTHENTICATION_FAILED')
    self.util.assertOk(self.interface.run(app='accounts',action='createsession',
        params={'username':username}|self.getPassword(password2)))
                                             
    self.deleteAccount(account)

# TODO TESTS !! test changepassword as sudo user (no old PW required)
# TODO TESTS !! crypto/changePW/recovery + two factor w/ crypto



# TODO TESTS !! passkey testing - when passkey is required/length checking, setting via CLI, salt handling/getsalt, CLI sets correct salt, salt too short/ other error cases, etc.

def testPasskeyRequired(self):
    """ Tests that passkeys are required for HTTP but not CLI """
    if not self.interface.isPriv:
        self.util.assertError(self.interface.run(app='accounts',action='createsession',
                        params={'username':self.username,'auth_password':'test123'}),400,'PASSWORD_MUST_BE_PASSKEY')
        self.util.assertError(self.interface.run(app='accounts',action='createsession',
                        params={'username':self.username,'auth_passkey':'abc='}),400,'PASSWORD_MUST_BE_PASSKEY') # wrong length
    # else for CLI return true? or do nothing