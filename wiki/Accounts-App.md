
* [Install](#install)
* [Configuration](#configuration)
* [Basic GetAccount](#basic-getaccount)
* [Account Creation](#account-creation)
* [Contact Information](#contact-information)
* [Clients and Sessions](#clients-and-sessions)
* [Two Factor Authentication](#two-factor-authentication)
* [Account Cryptography](#account-cryptography)
* [Password Recovery](#password-recovery)
* [Account/Group Search](#accountgroup-search)
* [Misc Admin Functions](#misc-admin-functions)
* [Global Config](#global-config)
* [Policy Groups](#policy-groups)
* [Account Policies](#account-policies)
* [External Authentication](#external-authentication)
* [Registration Allowlist](#registration-allowlist)

The accounts app handles user account and privilege management.  It provides an authentication service that other apps can use.  It also keeps track of user contact information, and provides two-factor authentication, account-based server-side cryptography, multi-client/session management, and per-account/per-group policies.  Authentication can be done via external authentication services. Accounts can either be admins or standard users.

## Install

The accounts app allows creating an initial admin account with `--username username --password password` as part of the database installation.  This is to facilitate usage over HTTP, since creating an account without authentication would not be possible post-install over HTTP.  

## Configuration

Configuration is separated into global config and per-account/per-group policies.  The functions `accounts getconfig` and `accounts setconfig` handle global config.  Unauthenticated users may view a subset of the current global config (e.g. whether account creation is enabled).  The `accounts getaccount` command is used to show policies, compiling all applicable account/group policies together into a single set of "effective" policy.  Account policies are set using `accounts editaccount` and `accounts editgroup` functions (admin only).  

## Basic GetAccount

The primary function `accounts getaccount` with no arguments returns the Account object you are being authenticated as.  If it returns null, you are unauthenticated to the server.  The account object contains information pertaining to the account itself, sessions, contacts, effective policies, etc. (if using `--full`).  Users can also lookup public contact information for other users via the `--account` flag.

## Account Creation

If admin, or if public account creation is enabled (see global config `createaccount`), you can create new accounts with `accounts createaccount`.  Admins can use `--admin` to create an admin account.  Depending on config, a user name or contact (or both) may be required (see global config `requirecontact` and `usernameiscontact`).  The admin allowlist may enable only certain usernames to be registered (see global config `createaccount`).

If the `requirecontact` config is set to `valid` then the new account's contact will have to be validated before the account can be used.  The server will send a validation code to the contact, which you then use with the `accounts verifycontact` function to enable the account.

Accounts can be deleted permanently using the `accounts deleteaccount` function (if allowed by admin).  Admins can use `accounts deleteaccount` with `auth_sudouser` to delete another user's account.

## Contact Information

Depending on config, contact information may be stored for accounts.  This enables functions such as password recovery, admin messaging, file sharing etc.  The only supported contact type is currently email.

Additional contacts can be created using `accounts createcontact`, and existing ones can be removed with `accounts deletecontact`.  The contact `isfrom` property determines whether it should be used as the 'from' address when sending a message from this account (e.g. file sharing).  The `public` property determines whether this contact can be used by other users to search for this account.

Accounts also have an optional `fullname` property. This is the owner's full "real" name and can be used in place of usernames in GUIs if it is populated.  It can also be used to search for the account.

## Clients and Sessions

Sessions are the mechanism through which requests are authenticated as corresponding to accounts.  Sessions have a corresponding Client registration.  Client applications are meant to register as a client, then create/delete sessions using the same client registration each time.  Re-using the client registration allows bypassing two factor authentication, and better enables users to track them (they can be named).  

To create a new session (or both a client and a session), use the `accounts createsession` command.  The standard parameters are `username` and `auth_password`, but you can also sign into an account using any of its registered contacts.  If you are reusing an existing client, use the `auth_clientid` and `auth_clientkey` parameters, else a new one will be created.  In that case, either an `auth_twofactor` code or an `auth_recoverykey` will be required (if configured).  The command returns the Account and Client object that you logged into.  The `client` field will contain the client ID and key, while the `client.session` field will contain the session ID and key.

The `createsession` command takes an optional `authsource` parameter describing what authentication service to sign in to (see the admin section on external authentication).  The list of available auth sources can be fetched with `accounts getauthsources`.  See the [External Authentication](#external-authentication) section.

To be authenticated for subsequent requests, all requests must contain the `auth_sessionid` and `auth_sessionkey` fields.  Via HTTP, these must be in the POST body or cookies (or basic HTTP auth as username/password) and not the URL.  

When using CLI, authentication is not required.  However, some actions logically require an account to act as, which you can directly specify using `auth_sudouser` field, without using a session.  The `auth_sudouser` and `auth_sudoacct` fields can also be used by authenticated admins (even over HTTP) to "act as" another user.  This is required for some commands (e.g. `deleteaccount`) that don't have a direct way for admins to specify the account to act on.  

### Full Example

After installing the server and creating an admin account named admin, we can run the following command:

```./andromeda-server accounts createsession --username admin --auth_password password```

The example output is:

```
Array
(
    [client] => Array
        (
            [id] => ujnc43n7um2v
            [name] =>
            [lastaddr] => CLI admin 10.10.2.1
            [useragent] => CLI /bin/bash
            [date_created] => 1735151577.46
            [date_loggedon] => 1735151577.46
            [date_active] =>
            [authkey] => vnesfhcvu0vrdwygyro96rgji0nneh2a
            [session] => Array
                (
                    [id] => 3x5p5n1n3c41
                    [client] => ujnc43n7um2v
                    [date_created] => 1735151577.46
                    [date_active] =>
                    [authkey] => 9xs677vcnjur56kmimedytsxma3jg1hf
                )

        )

    [account] => Array
        (
            [id] => 1buraan158c3
            [username] => admin
            [dispname] => 
        )

)
```

To be authenticated from CLI using a session, we could run `export andromeda_auth_sessionid=3x5p5n1n3c41` and `export andromeda_auth_sessionkey=9xs677vcnjur56kmimedytsxma3jg1hf`.  Though CLI usage does not require a session, operations that involve server-side crypto require either a session to be used, or the account's password to be posted with every request.  Using a session in the environment may be easier than always including `auth_password`.

To sign out, use either the `accounts deletesession` function (if you're going to reuse the client ID), or `accounts deleteclient` (to delete both the session and the client).  The `accounts deleteallauth` function will remove all clients and sessions other than the current one.  The `accounts changepassword` function can be used to change your password when signed in.

## Two Factor Authentication

Accounts can be configured to require two factor authentication (TOTP) when creating clients (and some other tasks).  To register a new two factor device, use `accounts createtwofactor`.  This will also create a set of recovery keys.  To "activate" the two factor registration, use `accounts verifytwofactor`.  The two factor will not be required when signing in until it is verified.  Any number of two factor instances can be created.  The `accounts deletetwofactor` command will delete a registered two factor.  Use `accounts createrecoverykeys` to create additional recovery keys if required.  A recovery key can be used in place of `auth_twofactor` when signing in if the two factor device is lost.

## Account Cryptography

Account-based server-side cryptography can be enabled for individual accounts.  If enabled, all two factor TOTP secrets will be stored encrypted in the database.  It can also be used by other apps, e.g. for files app storage credential encryption.  Encryption is done server-side, so it still requires trust in the server, but it can prevent secrets from being stolen out of the database.  libsodium's XCHACHA20POLY1305_IETF AEAD encryption is used.

The encryption scheme involves the account being assigned a "master key".  This master key can be used to encrypt values in the database, e.g. the two factor secret key.  The master key is stored in the database, wrapped by a number of "key sources".  These key sources can be the account's password, a recovery key, or a session key.  All of these are stored as hashes so the master key is not effectively not retrievable from the database.  When sessions or recovery keys are created, the master key is unlocked by the account's password and then re-wrapped with the new key.  Then when requests are made that provide the session key, crypto can be unlocked and accessed in memory for that request.

To enable crypto on an account, use `accounts enablecrypto`.  This will delete all existing recovery keys and sessions other than the current one. It will also return a new set of recovery keys and re-store all two factor secrets in encrypted form.  To disable crypto, use `accounts disablecrypto`.  This function will attempt to re-store in unencrypted form all encrypted secrets in the database.  Using account crypto prevents email-based password recovery.  Using `accounts changepassword` will perform a re-wrap (new salt, nonce) of the crypto keys.

## Password Recovery

Firstly, note that recovery keys are deleted after they are used (single-use).  Password recovery is done using the `accounts changepassword` function when not signed in.  This requires a recovery key.  If you don't have a recovery key, you can use the `accounts emailrecovery` function to receive a new recovery key via email.  This email method will NOT work if a) you don't have contact information configured, b) account crypto is enabled, or c) the account has two factor enabled.  For case A, an admin can change the password using `auth_sudouser`.  In case B, you are out of luck.   

## Account/Group Search

Authenticated users can search for other accounts or groups using the `accounts searchaccounts` or `accounts searchgroups` commands, e.g. to facilitate sharing content.  This can be enabled/disabled by the `accountsearch` and `groupsearch` policy.  These configs also control the maximum number of results a lookup can return at once (if exceeded, you get an empty result).  Searches can match an account's username, full name, or public contacts.  The `accounts getaccount` command also allows fetching the username and public contacts for other accounts from their ID.  

## Misc Admin Functions

Administrators can "masquerade" as other users using the `auth_sudouser` request parameter.  Set it to the username of the account you wish to impersonate, and the request will appear as if it was made by that account.  This works with any function, though you will not be able to do anything that requires unlocking the account's crypto.  This can be used e.g. to delete another account.

The `accounts getaccounts` function lists accounts while `accounts getgroups` lists groups.  The `accounts sendmessage` function can be used to send messages to users' contacts, even to entire groups.  

## Global Config

Global config is read/set using `accounts getconfig` and `accounts setconfig`.

The `--createaccount` parameter allows enabling/disabling public account creation (or allowing via the allowlist) - default is disabled.  The `--requirecontact` parameter determines whether publically created accounts must have contact or not - default is disabled.  The `verify` setting requires that the contact is also verified before the account is enabled.  The `usernameiscontact` parameter determines whether contact is used as a username - default is disabled.  E.g. if set to true, accounts' usernames would be their email addresses, not a separate username.  

`--createdefgroup` will create a global "default" group.  All users will implicitly be a member of this group.  The default group is how globally-applicable policies can be set.  There is no default group by default.  

`--default_auth` sets a default authentication service.  If users don't specify an authsource when creating sessions, this will be used as the default.  If not set, then local authentication (password hashes in the database) is the implicit default.  

## Policy Groups

Accounts can be put into groups, the primary purpose of which is for inheriting policies.  Groups can be edited, created, and deleted using `accounts creategroup`, `accounts editgroup` and `accounts deletegroup`.  The `accounts getgroup` function shows a group and its config.  Users can be added to/removed from groups using `accounts addgroupmember` and `accounts removegroupmember`.  The `accounts getmembership` function checks whether or not a group membership exists.  

Groups are assigned a priority number that determines how conflicting policies from multiple group memberships is reconciled.  When calculating an "effective" policy for an account, the account's individual value is used if it is set.  If it's not set, Andromeda loops its group memberships and takes the non-null value with the highest priority.  If there is still nothing, a default is used.

Groups are also exposed to other apps for additional functionality.  E.g. groups can be used as a share target for a file (see the Files App documentation).

### Policy Example

Account A is a member of groups X Y and Z.  We want to determine the value of 'foo'.  'foo' is set to null (not configured) for the account itself, so now we check the groups.  X is null, so it's skipped.  Y (group priority 10) is set to 5.  Z (group priority 20) is set to 3.  So we take the value from Z, and A's effective 'foo' value is 3.  

## Account Policies

Use `accounts editaccount` to set policies for a specific account.  Use `accounts editgroup` to set it for a given group.  Use `accounts getgroup` to view a group and its policies, and `accounts getaccount` to view an account and its effective policies.  Accounts and Groups both have an optional `--comment` field.

The `session_timeout` property determines how old a session is allowed to be inactive and still be valid (default null, reasonable value e.g. 60 minutes).  The `client_timeout` property determines how old a client is allowed to be inactive and still be valid (default null, reasonable value e.g. 60 days).  The `max_password_age` property determines the max age of a users' password before they are forced to change it when signing in (local auth only, default null, reasonable value e.g. 365 days).  An account's password can be immediately expired with `editaccount --expirepw`.  

The `max_clients`, `max_contacts` and `max_recoverykeys` paramters determine the maximum number of allowed clients, contacts and recovery keys respectively for an individual account (all null by default).

The `admin` parameter determines if the user is an admin (default false).  The `disabled` parameter determines whether the account is enabled (default false).  The `forcetf` parameter, if enabled, does not allow bypassing twofactor when re-using a client ID (default is false).  The `allowcrypto` parameter determines whether server-side crypto is allowed (default true).  The `userdelete` parameter controls whether users are allowed to delete their accounts (default true).  

The `accountsearch` parameter determines whether the user is allowed to search for other accounts.  It takes an int indicating the maximum number of results that can be returned from a search.  If this limit is exceeded, the result returns zero results.  The `groupsearch` parameter does the same for group search. The default for both is a max of 3 results.

## External Authentication

Andromeda supports signing in via external authentication services rather than using the local database.  When signing in with an external service, the account being used will be automatically created if it doesn't exist.  The account's password will not be stored in the database.  Recovery keys and account crypto work normally, but the user cannot change their password via Andromeda and `max_password_age` has no effect.  If account crypto is enabled and the user's password changes externally, they will have to additionally enter the old password when signing in so that the crypto can be re-keyed.  

External authentication services must be configured by an admin.  This is done using the `createauthsource`, `testauthsource`, `editauthsource` and `deleteauthsource` functions.  NOTE that by deleting an auth source, you will also delete all accounts that were created by signing in with that service.  An auth source can be set to disabled, enabled for only existing users, or enabled for all (including new) users (default).

The currently supported backend services are FTP, IMAP and LDAP.  They are only used for username/password checks (LDAP is not integrated with groups or anything else).  They require the PHP-FTP, PHP-IMAP and PHP-LDAP extensions respectively.  

## Registration Allowlist

If the `createaccount` config is set to `allowlist`, public account creation is enabled but only if they match a pre-defined list of admin-configured usernames or contacts (an allowlist).  To add to or remove from the allowlist, use `accounts addallowlist` and `accounts removeallowlist`.  To see the currently configured list, use `accounts getallowlist`.  
