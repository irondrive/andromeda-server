

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;


CREATE TABLE public.a2obj_apps_files_accesslog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar,
    file character(16) DEFAULT NULL::bpchar,
    folder character(16) DEFAULT NULL::bpchar,
    parent character(16) DEFAULT NULL::bpchar,
    file_share character(16) DEFAULT NULL::bpchar,
    folder_share character(16) DEFAULT NULL::bpchar,
    parent_share character(16) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_files_comment (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    comment text NOT NULL,
    dates__created double precision NOT NULL,
    dates__modified double precision NOT NULL
);



CREATE TABLE public.a2obj_apps_files_config (
    id character(12) NOT NULL,
    version character varying(255) NOT NULL,
    dates__created double precision NOT NULL,
    apiurl text,
    rwchunksize bigint NOT NULL,
    crchunksize bigint NOT NULL,
    upload_maxsize bigint,
    features__timedstats boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_file (
    id character(16) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    dates__created double precision NOT NULL,
    dates__modified double precision,
    dates__accessed double precision,
    size bigint DEFAULT '0'::bigint NOT NULL,
    counters__pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    parent character(16) NOT NULL,
    filesystem character(12) NOT NULL,
    likes bigint DEFAULT '0'::bigint NOT NULL,
    counters__likes bigint DEFAULT '0'::bigint NOT NULL,
    counters__dislikes bigint DEFAULT '0'::bigint NOT NULL,
    tags bigint DEFAULT '0'::bigint NOT NULL,
    comments bigint DEFAULT '0'::bigint NOT NULL,
    shares bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_filesystem_fsmanager (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    type smallint NOT NULL,
    readonly boolean NOT NULL,
    storage character varying(64) NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    name character varying(127) DEFAULT NULL::character varying,
    crypto_masterkey bytea DEFAULT NULL::bytea,
    crypto_chunksize bigint
);



CREATE TABLE public.a2obj_apps_files_folder (
    id character(16) NOT NULL,
    name character varying(255) DEFAULT NULL::character varying,
    description text,
    dates__created double precision NOT NULL,
    dates__modified double precision,
    dates__accessed double precision,
    counters__size bigint DEFAULT '0'::bigint NOT NULL,
    counters__pubvisits bigint DEFAULT '0'::bigint NOT NULL,
    counters__pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    parent character(16) DEFAULT NULL::bpchar,
    filesystem character(12) NOT NULL,
    files bigint DEFAULT '0'::bigint NOT NULL,
    folders bigint DEFAULT '0'::bigint NOT NULL,
    counters__subfiles bigint DEFAULT '0'::bigint NOT NULL,
    counters__subfolders bigint DEFAULT '0'::bigint NOT NULL,
    counters__subshares bigint DEFAULT '0'::bigint NOT NULL,
    likes bigint DEFAULT '0'::bigint NOT NULL,
    counters__likes bigint DEFAULT '0'::bigint NOT NULL,
    counters__dislikes bigint DEFAULT '0'::bigint NOT NULL,
    tags bigint DEFAULT '0'::bigint NOT NULL,
    comments bigint DEFAULT '0'::bigint NOT NULL,
    shares bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_like (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    value boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_limits_authentitytotal (
    id character(12) NOT NULL,
    object character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    dates__download double precision,
    dates__upload double precision,
    features__itemsharing boolean,
    features__share2everyone boolean,
    features__share2groups boolean,
    features__emailshare boolean,
    features__publicupload boolean,
    features__publicmodify boolean,
    features__randomwrite boolean,
    features__userstorage boolean,
    features__track_items smallint,
    features__track_dlstats smallint,
    counters__size bigint DEFAULT '0'::bigint NOT NULL,
    counters__items bigint DEFAULT '0'::bigint NOT NULL,
    counters__shares bigint DEFAULT '0'::bigint NOT NULL,
    counters_limits__size bigint,
    counters_limits__items bigint,
    counters_limits__shares bigint,
    counters__pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_limits_filesystemtotal (
    id character(12) NOT NULL,
    object character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    dates__download double precision,
    dates__upload double precision,
    features__itemsharing boolean,
    features__share2everyone boolean,
    features__share2groups boolean,
    features__publicupload boolean,
    features__publicmodify boolean,
    features__randomwrite boolean,
    features__track_items boolean,
    features__track_dlstats boolean,
    counters__size bigint DEFAULT '0'::bigint NOT NULL,
    counters__items bigint DEFAULT '0'::bigint NOT NULL,
    counters__shares bigint DEFAULT '0'::bigint NOT NULL,
    counters_limits__size bigint,
    counters_limits__items bigint,
    counters_limits__shares bigint,
    counters__pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_limits_timed (
    id character(12) NOT NULL,
    object character varying(64) NOT NULL,
    stats bigint DEFAULT '0'::bigint NOT NULL,
    dates__created double precision NOT NULL,
    timeperiod bigint NOT NULL,
    max_stats_age bigint,
    features__track_items boolean,
    features__track_dlstats boolean,
    counters_limits__pubdownloads bigint,
    counters_limits__bandwidth bigint
);



CREATE TABLE public.a2obj_apps_files_limits_timedstats (
    id character(12) NOT NULL,
    limitobj character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    dates__timestart bigint NOT NULL,
    iscurrent boolean,
    counters__size bigint DEFAULT '0'::bigint NOT NULL,
    counters__items bigint DEFAULT '0'::bigint NOT NULL,
    counters__shares bigint DEFAULT '0'::bigint NOT NULL,
    counters__pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_share (
    id character(16) NOT NULL,
    item character varying(64) NOT NULL,
    owner character(12) NOT NULL,
    dest character varying(64) DEFAULT NULL::character varying,
    label text,
    authkey text,
    password text,
    dates__created double precision NOT NULL,
    dates__accessed double precision,
    counters__accessed bigint DEFAULT '0'::bigint NOT NULL,
    counters_limits__accessed bigint,
    dates__expires bigint,
    features__read boolean NOT NULL,
    features__upload boolean NOT NULL,
    features__modify boolean NOT NULL,
    features__social boolean NOT NULL,
    features__reshare boolean NOT NULL,
    features__keepowner boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_storage_ftp (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    path text NOT NULL,
    username bytea,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea
);



CREATE TABLE public.a2obj_apps_files_storage_local (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    path text NOT NULL
);



CREATE TABLE public.a2obj_apps_files_storage_s3 (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    endpoint text NOT NULL,
    path_style boolean,
    port smallint,
    usetls boolean,
    region character varying(64) NOT NULL,
    bucket character varying(64) NOT NULL,
    accesskey bytea NOT NULL,
    accesskey_nonce bytea DEFAULT NULL::bytea,
    secretkey bytea,
    secretkey_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_storage_sftp (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    hostkey text NOT NULL,
    username bytea NOT NULL,
    password bytea,
    privkey bytea,
    keypass bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea,
    privkey_nonce bytea DEFAULT NULL::bytea,
    keypass_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_storage_smb (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    workgroup character varying(255) DEFAULT NULL::character varying,
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_storage_webdav (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    endpoint text NOT NULL,
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_tag (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    tag character varying(127) NOT NULL,
    dates__created double precision NOT NULL
);



ALTER TABLE ONLY public.a2obj_apps_files_accesslog
    ADD CONSTRAINT idx_213002_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_comment
    ADD CONSTRAINT idx_213014_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_config
    ADD CONSTRAINT idx_213020_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_file
    ADD CONSTRAINT idx_213026_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_filesystem_fsmanager
    ADD CONSTRAINT idx_213042_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_folder
    ADD CONSTRAINT idx_213051_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_like
    ADD CONSTRAINT idx_213075_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_authentitytotal
    ADD CONSTRAINT idx_213078_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtotal
    ADD CONSTRAINT idx_213086_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_timed
    ADD CONSTRAINT idx_213094_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_timedstats
    ADD CONSTRAINT idx_213098_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_share
    ADD CONSTRAINT idx_213106_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT idx_213114_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT idx_213121_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT idx_213127_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT idx_213135_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT idx_213145_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT idx_213154_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_tag
    ADD CONSTRAINT idx_213162_primary PRIMARY KEY (id);



CREATE INDEX idx_213002_account ON public.a2obj_apps_files_accesslog USING btree (account);



CREATE INDEX idx_213002_file ON public.a2obj_apps_files_accesslog USING btree (file);



CREATE INDEX idx_213002_folder ON public.a2obj_apps_files_accesslog USING btree (folder);



CREATE INDEX idx_213014_item ON public.a2obj_apps_files_comment USING btree (item);



CREATE INDEX idx_213014_owner_item ON public.a2obj_apps_files_comment USING btree (owner, item);



CREATE INDEX idx_213026_filesystem ON public.a2obj_apps_files_file USING btree (filesystem);



CREATE UNIQUE INDEX idx_213026_name ON public.a2obj_apps_files_file USING btree (name, parent);



CREATE INDEX idx_213026_owner ON public.a2obj_apps_files_file USING btree (owner);



CREATE INDEX idx_213026_parent ON public.a2obj_apps_files_file USING btree (parent);



CREATE INDEX idx_213042_name ON public.a2obj_apps_files_filesystem_fsmanager USING btree (name);



CREATE INDEX idx_213042_owner ON public.a2obj_apps_files_filesystem_fsmanager USING btree (owner);



CREATE UNIQUE INDEX idx_213042_owner_name ON public.a2obj_apps_files_filesystem_fsmanager USING btree (owner, name);



CREATE INDEX idx_213042_storage ON public.a2obj_apps_files_filesystem_fsmanager USING btree (storage);



CREATE INDEX idx_213051_filesystem ON public.a2obj_apps_files_folder USING btree (filesystem);



CREATE UNIQUE INDEX idx_213051_name_parent ON public.a2obj_apps_files_folder USING btree (name, parent);



CREATE INDEX idx_213051_owner ON public.a2obj_apps_files_folder USING btree (owner);



CREATE INDEX idx_213051_parent ON public.a2obj_apps_files_folder USING btree (parent);



CREATE INDEX idx_213075_item ON public.a2obj_apps_files_like USING btree (item);



CREATE UNIQUE INDEX idx_213075_owner_item ON public.a2obj_apps_files_like USING btree (owner, item);



CREATE UNIQUE INDEX idx_213078_object ON public.a2obj_apps_files_limits_authentitytotal USING btree (object);



CREATE UNIQUE INDEX idx_213086_object ON public.a2obj_apps_files_limits_filesystemtotal USING btree (object);



CREATE INDEX idx_213094_object ON public.a2obj_apps_files_limits_timed USING btree (object);



CREATE UNIQUE INDEX idx_213094_object_timeperiod ON public.a2obj_apps_files_limits_timed USING btree (object, timeperiod);



CREATE UNIQUE INDEX idx_213098_limitobj_iscurrent ON public.a2obj_apps_files_limits_timedstats USING btree (limitobj, iscurrent);



CREATE UNIQUE INDEX idx_213098_limitobj_timestart ON public.a2obj_apps_files_limits_timedstats USING btree (limitobj, dates__timestart);



CREATE INDEX idx_213106_item ON public.a2obj_apps_files_share USING btree (item);



CREATE UNIQUE INDEX idx_213106_item_owner_dest ON public.a2obj_apps_files_share USING btree (item, owner, dest);



CREATE INDEX idx_213106_owner ON public.a2obj_apps_files_share USING btree (owner);



CREATE INDEX idx_213114_filesystem ON public.a2obj_apps_files_storage_ftp USING btree (filesystem);



CREATE INDEX idx_213121_filesystem ON public.a2obj_apps_files_storage_local USING btree (filesystem);



CREATE INDEX idx_213127_filesystem ON public.a2obj_apps_files_storage_s3 USING btree (filesystem);



CREATE INDEX idx_213135_filesystem ON public.a2obj_apps_files_storage_sftp USING btree (filesystem);



CREATE INDEX idx_213145_filesystem ON public.a2obj_apps_files_storage_smb USING btree (filesystem);



CREATE INDEX idx_213154_filesystem ON public.a2obj_apps_files_storage_webdav USING btree (filesystem);



CREATE INDEX idx_213162_item ON public.a2obj_apps_files_tag USING btree (item);



CREATE UNIQUE INDEX idx_213162_item_tag ON public.a2obj_apps_files_tag USING btree (item, tag);



CREATE INDEX idx_213162_owner ON public.a2obj_apps_files_tag USING btree (owner);



