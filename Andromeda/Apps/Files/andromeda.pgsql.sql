

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
    obj_account character(12) DEFAULT NULL::bpchar,
    obj_sudouser character(12) DEFAULT NULL::bpchar,
    obj_client character(12) DEFAULT NULL::bpchar,
    obj_file character(16) DEFAULT NULL::bpchar,
    obj_folder character(16) DEFAULT NULL::bpchar,
    obj_parent character(16) DEFAULT NULL::bpchar,
    obj_file_share character(16) DEFAULT NULL::bpchar,
    obj_folder_share character(16) DEFAULT NULL::bpchar,
    obj_parent_share character(16) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_files_comment (
    id character(16) NOT NULL,
    obj_owner character(12) NOT NULL,
    obj_item character varying(64) NOT NULL,
    comment text NOT NULL,
    date_created double precision NOT NULL,
    date_modified double precision NOT NULL
);



CREATE TABLE public.a2obj_apps_files_config (
    id character(12) NOT NULL,
    version character varying(255) NOT NULL,
    date_created double precision NOT NULL,
    apiurl text,
    rwchunksize bigint NOT NULL,
    crchunksize bigint NOT NULL,
    upload_maxsize bigint,
    timedstats boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_file (
    id character(16) NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    date_created double precision NOT NULL,
    date_modified double precision,
    date_accessed double precision,
    size bigint DEFAULT '0'::bigint NOT NULL,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL,
    obj_owner character(12) DEFAULT NULL::bpchar,
    obj_parent character(16) NOT NULL,
    obj_filesystem character(12) NOT NULL,
    objs_likes bigint DEFAULT '0'::bigint NOT NULL,
    count_likes bigint DEFAULT '0'::bigint NOT NULL,
    count_dislikes bigint DEFAULT '0'::bigint NOT NULL,
    objs_tags bigint DEFAULT '0'::bigint NOT NULL,
    objs_comments bigint DEFAULT '0'::bigint NOT NULL,
    objs_shares bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_filesystem_fsmanager (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    type smallint NOT NULL,
    readonly boolean NOT NULL,
    obj_storage character varying(64) NOT NULL,
    obj_owner character(12) DEFAULT NULL::bpchar,
    name character varying(127) DEFAULT NULL::character varying,
    crypto_masterkey bytea DEFAULT NULL::bytea,
    crypto_chunksize bigint
);



CREATE TABLE public.a2obj_apps_files_folder (
    id character(16) NOT NULL,
    name character varying(255) DEFAULT NULL::character varying,
    description text,
    date_created double precision NOT NULL,
    date_modified double precision,
    date_accessed double precision,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_pubvisits bigint DEFAULT '0'::bigint NOT NULL,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL,
    obj_owner character(12) DEFAULT NULL::bpchar,
    obj_parent character(16) DEFAULT NULL::bpchar,
    obj_filesystem character(12) NOT NULL,
    objs_files bigint DEFAULT '0'::bigint NOT NULL,
    objs_folders bigint DEFAULT '0'::bigint NOT NULL,
    count_subfiles bigint DEFAULT '0'::bigint NOT NULL,
    count_subfolders bigint DEFAULT '0'::bigint NOT NULL,
    count_subshares bigint DEFAULT '0'::bigint NOT NULL,
    objs_likes bigint DEFAULT '0'::bigint NOT NULL,
    count_likes bigint DEFAULT '0'::bigint NOT NULL,
    count_dislikes bigint DEFAULT '0'::bigint NOT NULL,
    objs_tags bigint DEFAULT '0'::bigint NOT NULL,
    objs_comments bigint DEFAULT '0'::bigint NOT NULL,
    objs_shares bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_like (
    id character(16) NOT NULL,
    obj_owner character(12) NOT NULL,
    obj_item character varying(64) NOT NULL,
    date_created double precision NOT NULL,
    value boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_limits_authentitytotal (
    id character(12) NOT NULL,
    obj_object character varying(64) NOT NULL,
    date_created double precision NOT NULL,
    date_download double precision,
    date_upload double precision,
    itemsharing boolean,
    share2everyone boolean,
    share2groups boolean,
    emailshare boolean,
    publicupload boolean,
    publicmodify boolean,
    randomwrite boolean,
    userstorage boolean,
    track_items smallint,
    track_dlstats smallint,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_shares bigint DEFAULT '0'::bigint NOT NULL,
    limit_size bigint,
    limit_items bigint,
    limit_shares bigint,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_limits_filesystemtotal (
    id character(12) NOT NULL,
    obj_object character varying(64) NOT NULL,
    date_created double precision NOT NULL,
    date_download double precision,
    date_upload double precision,
    itemsharing boolean,
    share2everyone boolean,
    share2groups boolean,
    publicupload boolean,
    publicmodify boolean,
    randomwrite boolean,
    track_items boolean,
    track_dlstats boolean,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_shares bigint DEFAULT '0'::bigint NOT NULL,
    limit_size bigint,
    limit_items bigint,
    limit_shares bigint,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_limits_timed (
    id character(12) NOT NULL,
    obj_object character varying(64) NOT NULL,
    objs_stats bigint DEFAULT '0'::bigint NOT NULL,
    date_created double precision NOT NULL,
    timeperiod bigint NOT NULL,
    max_stats_age bigint,
    track_items boolean,
    track_dlstats boolean,
    limit_pubdownloads bigint,
    limit_bandwidth bigint
);



CREATE TABLE public.a2obj_apps_files_limits_timedstats (
    id character(12) NOT NULL,
    obj_limitobj character varying(64) NOT NULL,
    date_created double precision NOT NULL,
    date_timestart bigint NOT NULL,
    iscurrent boolean,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_shares bigint DEFAULT '0'::bigint NOT NULL,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_files_share (
    id character(16) NOT NULL,
    obj_item character varying(64) NOT NULL,
    obj_owner character(12) NOT NULL,
    obj_dest character varying(64) DEFAULT NULL::character varying,
    label text,
    authkey text,
    password text,
    date_created double precision NOT NULL,
    date_accessed double precision,
    count_accessed bigint DEFAULT '0'::bigint NOT NULL,
    limit_accessed bigint,
    date_expires bigint,
    read boolean NOT NULL,
    upload boolean NOT NULL,
    modify boolean NOT NULL,
    social boolean NOT NULL,
    reshare boolean NOT NULL,
    keepowner boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_files_storage_ftp (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    obj_filesystem character(12) NOT NULL,
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
    date_created double precision NOT NULL,
    obj_filesystem character(12) NOT NULL,
    path text NOT NULL
);



CREATE TABLE public.a2obj_apps_files_storage_s3 (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    obj_filesystem character(12) NOT NULL,
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
    date_created double precision NOT NULL,
    obj_filesystem character(12) NOT NULL,
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
    date_created double precision NOT NULL,
    obj_filesystem character(12) NOT NULL,
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
    date_created double precision NOT NULL,
    obj_filesystem character(12) NOT NULL,
    endpoint text NOT NULL,
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);



CREATE TABLE public.a2obj_apps_files_tag (
    id character(16) NOT NULL,
    obj_owner character(12) NOT NULL,
    obj_item character varying(64) NOT NULL,
    tag character varying(127) NOT NULL,
    date_created double precision NOT NULL
);



ALTER TABLE ONLY public.a2obj_apps_files_accesslog
    ADD CONSTRAINT idx_282999_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_comment
    ADD CONSTRAINT idx_283011_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_config
    ADD CONSTRAINT idx_283017_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_file
    ADD CONSTRAINT idx_283023_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_filesystem_fsmanager
    ADD CONSTRAINT idx_283039_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_folder
    ADD CONSTRAINT idx_283048_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_like
    ADD CONSTRAINT idx_283072_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_authentitytotal
    ADD CONSTRAINT idx_283075_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtotal
    ADD CONSTRAINT idx_283083_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_timed
    ADD CONSTRAINT idx_283091_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_limits_timedstats
    ADD CONSTRAINT idx_283095_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_share
    ADD CONSTRAINT idx_283103_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT idx_283111_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT idx_283118_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT idx_283124_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT idx_283132_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT idx_283142_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT idx_283151_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_files_tag
    ADD CONSTRAINT idx_283159_primary PRIMARY KEY (id);



CREATE INDEX idx_282999_account ON public.a2obj_apps_files_accesslog USING btree (obj_account);



CREATE INDEX idx_282999_file ON public.a2obj_apps_files_accesslog USING btree (obj_file);



CREATE INDEX idx_282999_folder ON public.a2obj_apps_files_accesslog USING btree (obj_folder);



CREATE INDEX idx_283011_item ON public.a2obj_apps_files_comment USING btree (obj_item);



CREATE INDEX idx_283011_owner_item ON public.a2obj_apps_files_comment USING btree (obj_owner, obj_item);



CREATE INDEX idx_283023_filesystem ON public.a2obj_apps_files_file USING btree (obj_filesystem);



CREATE UNIQUE INDEX idx_283023_name ON public.a2obj_apps_files_file USING btree (name, obj_parent);



CREATE INDEX idx_283023_owner ON public.a2obj_apps_files_file USING btree (obj_owner);



CREATE INDEX idx_283023_parent ON public.a2obj_apps_files_file USING btree (obj_parent);



CREATE INDEX idx_283039_name ON public.a2obj_apps_files_filesystem_fsmanager USING btree (name);



CREATE INDEX idx_283039_owner ON public.a2obj_apps_files_filesystem_fsmanager USING btree (obj_owner);



CREATE UNIQUE INDEX idx_283039_owner_name ON public.a2obj_apps_files_filesystem_fsmanager USING btree (obj_owner, name);



CREATE INDEX idx_283039_storage ON public.a2obj_apps_files_filesystem_fsmanager USING btree (obj_storage);



CREATE INDEX idx_283048_filesystem ON public.a2obj_apps_files_folder USING btree (obj_filesystem);



CREATE UNIQUE INDEX idx_283048_name_parent ON public.a2obj_apps_files_folder USING btree (name, obj_parent);



CREATE INDEX idx_283048_owner ON public.a2obj_apps_files_folder USING btree (obj_owner);



CREATE INDEX idx_283048_parent ON public.a2obj_apps_files_folder USING btree (obj_parent);



CREATE INDEX idx_283072_item ON public.a2obj_apps_files_like USING btree (obj_item);



CREATE UNIQUE INDEX idx_283072_owner_item ON public.a2obj_apps_files_like USING btree (obj_owner, obj_item);



CREATE UNIQUE INDEX idx_283075_object ON public.a2obj_apps_files_limits_authentitytotal USING btree (obj_object);



CREATE UNIQUE INDEX idx_283083_object ON public.a2obj_apps_files_limits_filesystemtotal USING btree (obj_object);



CREATE INDEX idx_283091_object ON public.a2obj_apps_files_limits_timed USING btree (obj_object);



CREATE UNIQUE INDEX idx_283091_object_timeperiod ON public.a2obj_apps_files_limits_timed USING btree (obj_object, timeperiod);



CREATE UNIQUE INDEX idx_283095_limitobj_iscurrent ON public.a2obj_apps_files_limits_timedstats USING btree (obj_limitobj, iscurrent);



CREATE UNIQUE INDEX idx_283095_limitobj_timestart ON public.a2obj_apps_files_limits_timedstats USING btree (obj_limitobj, date_timestart);



CREATE INDEX idx_283103_item ON public.a2obj_apps_files_share USING btree (obj_item);



CREATE UNIQUE INDEX idx_283103_item_owner_dest ON public.a2obj_apps_files_share USING btree (obj_item, obj_owner, obj_dest);



CREATE INDEX idx_283103_owner ON public.a2obj_apps_files_share USING btree (obj_owner);



CREATE INDEX idx_283111_filesystem ON public.a2obj_apps_files_storage_ftp USING btree (obj_filesystem);



CREATE INDEX idx_283118_filesystem ON public.a2obj_apps_files_storage_local USING btree (obj_filesystem);



CREATE INDEX idx_283124_filesystem ON public.a2obj_apps_files_storage_s3 USING btree (obj_filesystem);



CREATE INDEX idx_283132_filesystem ON public.a2obj_apps_files_storage_sftp USING btree (obj_filesystem);



CREATE INDEX idx_283142_filesystem ON public.a2obj_apps_files_storage_smb USING btree (obj_filesystem);



CREATE INDEX idx_283151_filesystem ON public.a2obj_apps_files_storage_webdav USING btree (obj_filesystem);



CREATE INDEX idx_283159_item ON public.a2obj_apps_files_tag USING btree (obj_item);



CREATE UNIQUE INDEX idx_283159_item_tag ON public.a2obj_apps_files_tag USING btree (obj_item, tag);



CREATE INDEX idx_283159_owner ON public.a2obj_apps_files_tag USING btree (obj_owner);



