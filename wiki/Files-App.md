
* [General Filesystem](#general-filesystem)
* [Social Features](#social-features)
* [Sharing and Permissions](#sharing-and-permissions)
* [Storages and Filesystems](#storagesfilesystems)
* [Filesystem Types](#filesystem-types)
* [Storage Drivers](#storage-drivers)
* [Global Config](#global-config)
* [Policy Config](#policy-config)
* [Timed Policy Config](#timed-policy-config)

The files app provides the cloud filesystem implementation.  It includes general file/folder management, with byte-level read/write access.  It allows creating any number of backend storages (with various drivers) that can be either global or user-private, and Andromeda-specific (possibly with server-side encryption) or externally accessible.  It includes social features including file/folder likes and comments, granular sharing of content via public links or to users or groups, and granular statistics (e.g. bandwidth) gathering and policy.  Using an account with CLI is generally required.

Note that FilesApp file storage has nothing to do with the core's optional data directory.

# User Functions

## General Filesystem

Andromeda functions as a hybrid object/traditional filesystem.  Everything is generally referenced by its object ID, but the traditional folder hierarchy still exists.  Files and folders collectively are often referred to as "items".  The folder hierarchy is defined by all items (other than the root folder) having a "parent".  All items have an optional "description" field and keep track of accessed, created and modified dates.  Any number of backend storages can be created, and they can be owned by a single user, or shared amongst all users (public).  Owned storages have one root folder/filesystem.  Shared storages have one root folder/filesystem with no owner if it's an external storage, else it has N root folders/filesystems (one per user).  A root folder's name is tied to that of its storage object.  Files/folders use the filesystem layer, which in turn uses the storage object layer.

The `files getfolder` function lists the contents of a folder. You can specify a folder, or a storage to get its root folder, or nothing to get the root folder of the user's default storage.  The `files getfile` function fetches corresponding metadata for a file.  These get functions also return other metadata like timestamps and like counts.  If `--details` is used, the metadata will include tags and shares.  The `files editfile` and `files editfolder` functions edit metadata for files/folder. Right now the only metadata that can be changed is the item's description field.  

In order to optimize folder-based storages or manual CLI usage, the `files getitembypath` function can be used to fetch files/folders by their traditional path.  The first folder layer in the path is the list of the user's list of storages.  E.g. `/myfs/myfolder/myitem.txt` would reference a file called myitem.txt in a folder called myfolder on a storage called myfs.

The `files download` function is used to download files.  This function disables JSON output and will just output the content of the file.  The download function supports the traditional byte range HTTP headers in addition to the `fstart` and `flast` parameters.  

The `files upload` function uploads (creates) a file into the specified folder.  The `files writefile` function can overwrite all or part of an existing file.  It takes a regular file input as the content to write, with an optional starting offset.  The `files truncate` function changes a file's size.

Other traditional storage functions include `createfolder`, `deletefile`, `deletefolder`, `renamefile`, `renamefolder`, `movefile`, and `movefolder`.  Copy functionality is provided by rename and move using the `--copy bool` parameter.  To copy a file to a new name, use `renamefile --copy` and to copy it to a new location, use `movefile --copy`.

As Andromeda is a REST-like transactional API, there is no concept of file handles or `fopen` or any equivalent.  All writes are done without handles and are committed immediately.  

## Social Features

Items support tracking "likes" by those with access to them.  Use the `files likefile`, `files likefolder` functions.  A user can only like/dislike an item once of course.  Items also can have "comments" made on them.  The `files commentfile`, `files commentfolder`, `files editcomment` and `files deletecomment` functions implement comments.  Likes and comments are not automatically returned in file/folder metadata due to the potentially large number of them.  They can be fetched in smaller chunks using `files getfilelikes`, `files getfolderlikes`, `files getfilecomments` and `files getfoldercomments`.

Note that if you only want the like/dislike count and not the individual objects, this information is returned in the normal file/folder object metadata. 

Items also support category tagging.  The `files tagfile`, `files tagfolder` and `files deletetag` implement this.  Tags will be used for future server-side item-searching functionality.  

## Sharing and Permissions

Andromeda's basic permissions model gives every item a single "owner", and gives access to other users by allocating "shares".  A user can access an item if they own it or any of its parents, or if they were shared the item or any of its parents.  Shares therefore always grant cascading access to all items within a folder when the folder itself is shared.  As external storages are common between all users that can access it, an external storage with no owner is "global" and all users will be able to access it and see the same content.  Items discovered by the server on an external storage with no owner, have a null owner and are globally accessible.

Both files and folders can be shared, either by link, or to specified users or entire groups.  To create a share, use `files sharefile` and `files sharefolder`.  When creating a link-type share, a share ID and key will be returned.  Links can also be sent by the server in an email to a given address. To access the item using the share, add them to the `sid` and `skey` parameters in the request.  The `files getshare` function returns the item that you would be accessing with the given `sid` and `skey`.  The `files deleteshare` function deletes a share.

Shares also support individualized permissions.  `read` controls whether a user can read a file or list the folder (default true).  `upload` controls whether a user can overwrite a file or add content to a folder (default false).  `modify` controls whether a user can write to, rename, move, or delete a file or folder (default false).  `social` controls whether a user is allowed to like an item or create comments (default true).  `reshare` controls whether a user can re-share the item with someone else (creating their own share object for it) (default false).  Note they would be limited to whatever permissions they were originally given.  

The `keepowner` parameter controls whether new content uploaded by a user into a shared folder will be owned by that user (true - default), or the original owner of the folder (false).  Being the owner of the new content means they would be able to access it in perpetuity, create shares for it, etc.  To change this later, owners of a folder can "take ownership" of any content within their folder, using the `files ownfile` and `files ownfolder` functions.  Any permissions of a share can be edited later with `files editshare`. NOTE removing permissions from a share will NOT remove them from any re-shares.  

The `getshares --mine` function lists all shares on all items owned by you.  The `getshares` function without the flag lists all shares that you are a target of (whether by account, or group).  The `getadopted` function lists all content that you own that is contained in a folder owned by someone else.

Shares can also be set to require a password (`--spassword`), and can be set to expire after a set number of accesses (`--maxaccess`) or after a certain time (`--expires`).  To this end, shares also track the last access time and total access count.

## Storages/Filesystems

Item storage is logically divided into the "filesystem" and the backend "storage".  Storages are where data is stored, filesystems are how it's used.  

Users can have access to multiple storages.  Storages can either have an owner, in which case they are private to that user, or have no owner, which makes them accessible to all users.  Only administrators can create a global storage (no owner).  Storages with an `external` filesystem type (see below), and no owner, are shared between all users.  Storages with a `native` filesystem type, and no owner, are still separate between each user, though all of their content will be pooled on the one storage.

The `files getstorages` function lists the storages that the user has access to.  The `files getstorage` function returns metadata for a storage.  The "default" storage is the one with no name set (null) and will show up as "Default" in the folder hierarchy.  The `file deletestorage` function removes a storage and, if not `External`, deletes all of its content.  Use `files createstorage` to create a storage (options vary depending on which `--sttype` storage type is chosen), and `files editstorage` to edit a storage.

Filesystem types, their storage driver types, and properties like their crypto chunksize can only be set at creation time, and never changed.  Storages can be set read-only via the `--readonly` flag, which can be changed at any time.  The storage's name can also be changed at any time with `--name`.  

### Filesystem Types

Filesystem implementation types (`--fstype`) are `native`, `crypt`, and `external`.  

* `Native` is the standard Andromeda storage type.  It uses the underlying storage only as a flat object storage, and all metadata including file names, folder structure, etc. is kept only in the database.  The database is the "authoritative" record of the storage, and Andromeda is the logical owner of all content - the storage starts empty and is "dedicated" to Andromeda.  This system is fast and simple as it does not have to sync with the underlying storage for every request, but content will only be accessible through Andromeda. Each user can only access their own content.  Deleting a native storage also deletes all of its content.  Storages with no owner still store each user's data separately with this type.
* `NativeCrypt` implements an encryption layer on top of the regular `Native` type.  This is designed for use cases where an external storage (e.g. an FTP server) is not trusted.  Andromeda uses a single master key for the entire storage and stores it plainly in the database, so it does not protect from any attacks relating to the Andromeda server itself.  The encryption uses libsodium's XCHACHA20POLY1305_IETF authenticated encryption.  Files are divided into chunks, the size of which is configurable (only at storage creation time) from 4K to 1M, defaulting to the globally configured chunk size, which itself defaults to 128K.  Chunks are encrypted and signed with the file's ID and block number, and have 40 bytes of overhead.  Bigger chunks are potentially faster for sequential operations and result in less space overhead but are but slower for small/random operations. 
* `External` storages are used by Andromeda as a regular filesystem whose content is accessible outside Andromeda.  In this case the file names, folder structure etc. are present on the storage and the database acts only as a way to map this information to objects.  The underlying storage is considered the authoritative record and all operations will require Andromeda to sync with the storage.  The storage content is shared amongst all users that have access to it, but this should not just be used as a way to make a storage shared in terms of among users. The better way to do that would be to use a native storage and create a share to the default group that includes all users.  An External storage does not delete its content from disk when removed. 

Note that Native/External has nothing to do with whether the content is actually located on disk or on the network.  It only changes the way that content is stored.  For example you can run Native storage over SMB, and you can use a locally mounted folder as "External" storage.

| | Native | External |
| ------------- | ------------- | ------------- |
| Can use pre-existing content | No | Yes |
| Can access content outside Andromeda | No | Yes |
| Deletes disk content when removed | Yes | No |
| Speed | Faster - fewer disk accesses | Slower - more disk accesses |
| Authoritative Record/Owner | Andromeda + Database | Disk Filesystem |
| On-disk Appearance | Dump of nameless files | Normal content with names and folders |
| User separation | Users are separated | Users share content |

### Storage Drivers

The storage also uses an underlying driver for actually storing the content.  This is specified with the `--sttype` flag, and various options specific to the driver selected.  The currently supported drivers are Local, FTP, S3, SFTP, and SMB.  Each come with their own caveats.

* Local storage, of course, can only be added by administrators.
* SFTP supports password or private key authentication.  It also keeps track of the server's key after the first connection.  If the host key changes, Andromeda will refuse to connect.  The `--resethost` parameter can be used to reset it, and the FS metadata will show the host key.
* FTP does not support file copy or random file writing or truncating.  It DOES support file appending, so writes to the offset equal to a file's size (or ones that overwrite the whole file) do work.
* S3 supports any S3-compatible storage, not just Amazon.  The major limitation with S3 is that objects are immutable.  Files cannot be written to or appended.  Since it's an object storage and doesn't support folders, External storages combined with S3 cannot use folders (but native can!). S3 also takes an import_chunksize parameter (defaults to the global RW size) which determines the size of the read/write chunks used for importing files (not much reason to change this).

Some storages also require additional PHP extensions.
* SMB requires the PHP smbclient extension.
* FTP requires the PHP FTP extension.
* S3 requires the PHP simplexml extension.

As the external storages can all store external authentication credentials in the database, the `--credcrypt` param allows encrypting these credentials using the [Account encryption service](Accounts-App.md#account-cryptography).  FTP/SMB/SFTP use it for username/password while S3 uses it for accesskey/secretkey.  SFTP also uses it for the private key and private key password.  This will only work if your account has server-side encryption enabled.  This will also prevent sharing any content out of the storage to other users (or via link), since they won't have the key to read the authentication information.

# Administration

## Global Config

The `files getconfig` and `files setconfig` functions are used to get/set global files config.

The `--apiurl` parameter informs the server of its external HTTP address. This is required for link sharing via email to work.  The `--rwchunksize` parameter determines the size of the chunks read into memory before being output/written in file download and writes - default 16M.  Larger values may improve performance (to an extent) but use more RAM.  The `--crchunksize` parameter determines the default chunk size for NativeCrypt storages (default 128K).  The `--upload_maxsize` parameter is relayed to clients as the maximum upload file size.  Andromeda also reads PHP's `post_max_size` and `upload_max_size` and returns the smallest value.  If Nginx or something else has a max file size, set it here.  This parameter is *only* to notify clients and Andromeda *will not* reject uploads that exceed this value, if they make it past the web server and PHP.  

## Policy Config

Andromeda supports fine-grained permissions and limitations configuration called Policy.  Policies can apply to any individual account, group, or storage.  To configure a policy for an object, use the `files configpolicies` command.  The permissions available (all true by default) include:
* `--itemsharing` whether users are allowed to create shares for items
* `--publicupload` whether unauthenticated people are allowed to write to shared folders via link
* `--publicmodify` whether unauthenticated people are allowed to modify items via link
* `--randomwrite` whether users are allowed to write to random file offsets (otherwise, files are immutable)

Accounts and Groups (not storages) also support: 
* `--emailshare` whether users are allowed to email share links
* `--userstorage` whether users are allowed to add their own storages

Certain "item stats" can also be tracked if `--track_items` is enabled (default false).  If true, the object will track its total size, number of items, and shares.  The corresponding policies are only available if track_items is enabled and include (all null by default):
* `--max_size` the maximum used storage space in bytes
* `--max_items` the maximum number of files and folders (items)
* `--max_shares` the maximum number of share objects

Policies are also used to track some statistics.  Objects can also have "download stats" tracked if `--track_dlstats` is enabled (default false).  If true, the object will track its total number of public downloads (not including the owner) and bandwidth (including the owner).  These cannot be limited since that would not make sense in the context of a "forever" limit (see below).  

For accounts/groups, the normal [inheritance rules](Accounts-App.md#account-groups) apply.  Group policies are applied to individual member users as an inherited property and do not limit the group as a whole.  For groups, the `--track_items` and `--track_dlstats` have two options.  The `accounts` setting tracks stats for individual group members only.  The `wholegroup` setting additionally collects stats for the group as a whole.

To view all policies and collected stats for an object, use `files getpolicies`.  This can optionally print policies for ALL objects if none is specified.  To reset all policies and permissions to default for an object, use `files purgepolicies`.

### Example

One common use case is to limit every user to a maximum of 5GB of storage.  To do so you would run `files configpolicies --group id --track_dlstats peraccount --max_size 5000000000` with the ID of the global group.  

## Timed Policy Config

Andromeda also supports a smaller set of config in the context of policies associated with time periods, meaning the stats reset at the end of the time period.  Use `files configtimedpolicies` to configure one.  This requires both a) an object to limit and b) a time period to configure.  Any number of time periods can be associated with an object.

These can track the same `--track_items` statistics as before, but without policies since those would not make sense in the context of a time period.  The `--track_dlstats` statistics can now be limited instead.  The `--max_pubdownloads` controls the maximum number of public downloads in the time period, while `--max_bandwidth` controls the max bandwidth used in a time period.  At the end of the time period, the object is un-limited again.  

When tracking statistics for time periods, a history is kept of stats for all previous time periods.  The `--max_stats_age` controls how long this history is kept.  `-1` indicates forever (default), `0` is no history, else an integer in seconds.  A group's `--max_stats_age` applies to both the stats of member accounts (if inherited) as well as its own stats (if `--track_items` or `--track_dlstats` are `wholegroup`).  

To view timed policies for an object, use `files gettimedpolicies` and to clear them, use `files purgetimedpolicies`.  Gathered statistics can also be viewed using `files gettimedstatsfor` to show the history for a specific object, and `files gettimedstatsat` to show history for all objects (type or specific object must be provided) at the given timestamp.

### Example

A common use case would be to limit every account to 100GB of bandwidth per month.  To configure that you would run `files configtimedpolicies --group id --track_dlstats peraccount --timeperiod 2592000 --max_bandwidth 100000000000`.
