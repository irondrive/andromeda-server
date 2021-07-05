--
-- PostgreSQL database dump
--

-- Dumped from database version 12.7 (Ubuntu 12.7-0ubuntu0.20.10.1)
-- Dumped by pg_dump version 12.7 (Ubuntu 12.7-0ubuntu0.20.10.1)

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

--
-- Name: a2_objects_apps_files_accesslog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_accesslog (
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


--
-- Name: a2_objects_apps_files_comment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_comment (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    comment text NOT NULL,
    dates__created double precision NOT NULL,
    dates__modified double precision NOT NULL
);


--
-- Name: a2_objects_apps_files_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_config (
    id character(12) NOT NULL,
    version character varying(255) NOT NULL,
    dates__created double precision NOT NULL,
    apiurl text,
    rwchunksize bigint NOT NULL,
    crchunksize bigint NOT NULL,
    upload_maxsize bigint,
    features__timedstats boolean NOT NULL
);


--
-- Name: a2_objects_apps_files_file; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_file (
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


--
-- Name: a2_objects_apps_files_filesystem_fsmanager; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_filesystem_fsmanager (
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


--
-- Name: a2_objects_apps_files_folder; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_folder (
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


--
-- Name: a2_objects_apps_files_like; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_like (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    value boolean NOT NULL
);


--
-- Name: a2_objects_apps_files_limits_authentitytotal; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_authentitytotal (
    id character(12) NOT NULL,
    object character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    dates__download double precision,
    dates__upload double precision,
    features__itemsharing boolean,
    features__shareeveryone boolean,
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


--
-- Name: a2_objects_apps_files_limits_authtotal; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_authtotal (
    id character(12) NOT NULL,
    object character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    dates__download double precision,
    dates__upload double precision,
    features__itemsharing boolean,
    features__shareeveryone boolean,
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
    counters__downloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2_objects_apps_files_limits_filesystemtotal; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_filesystemtotal (
    id character(12) NOT NULL,
    object character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    dates__download double precision,
    dates__upload double precision,
    features__itemsharing boolean,
    features__shareeveryone boolean,
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


--
-- Name: a2_objects_apps_files_limits_timed; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_timed (
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


--
-- Name: a2_objects_apps_files_limits_timedstats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_timedstats (
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


--
-- Name: a2_objects_apps_files_limits_total; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_total (
    id character(16) NOT NULL,
    object character varying(64) NOT NULL,
    dates__created bigint NOT NULL,
    dates__download bigint,
    dates__upload bigint,
    features__itemsharing boolean,
    features__shareeveryone boolean,
    features__emailshare boolean,
    features__publicupload boolean,
    features__publicmodify boolean,
    features__randomwrite boolean,
    features__userstorage boolean,
    features__track_items boolean,
    features__track_dlstats boolean,
    counters__size bigint DEFAULT '0'::bigint NOT NULL,
    counters__items bigint DEFAULT '0'::bigint NOT NULL,
    counters__shares bigint DEFAULT '0'::bigint NOT NULL,
    counters_limits__size bigint,
    counters_limits__items bigint,
    counters_limits__shares bigint,
    counters__downloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2_objects_apps_files_share; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_share (
    id character(16) NOT NULL,
    item character varying(64) NOT NULL,
    owner character(12) NOT NULL,
    dest character varying(64) DEFAULT NULL::character varying,
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


--
-- Name: a2_objects_apps_files_storage_ftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_ftp (
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


--
-- Name: a2_objects_apps_files_storage_local; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_local (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    path text NOT NULL
);


--
-- Name: a2_objects_apps_files_storage_s3; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_s3 (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    import_chunksize bigint,
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


--
-- Name: a2_objects_apps_files_storage_sftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_sftp (
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


--
-- Name: a2_objects_apps_files_storage_smb; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_smb (
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


--
-- Name: a2_objects_apps_files_storage_webdav; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_webdav (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    endpoint text NOT NULL,
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);


--
-- Name: a2_objects_apps_files_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_tag (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    tag character varying(127) NOT NULL,
    dates__created double precision NOT NULL
);


--
-- Name: a2_objects_apps_files_limits_total idx_25983_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_total
    ADD CONSTRAINT idx_25983_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_authtotal idx_43130_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_authtotal
    ADD CONSTRAINT idx_43130_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_accesslog idx_82553_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_accesslog
    ADD CONSTRAINT idx_82553_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_comment idx_82565_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_comment
    ADD CONSTRAINT idx_82565_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_config idx_82571_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_config
    ADD CONSTRAINT idx_82571_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_file idx_82577_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_file
    ADD CONSTRAINT idx_82577_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_filesystem_fsmanager idx_82593_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_filesystem_fsmanager
    ADD CONSTRAINT idx_82593_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_folder idx_82602_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_folder
    ADD CONSTRAINT idx_82602_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_like idx_82626_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_like
    ADD CONSTRAINT idx_82626_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_authentitytotal idx_82629_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_authentitytotal
    ADD CONSTRAINT idx_82629_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_filesystemtotal idx_82637_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_filesystemtotal
    ADD CONSTRAINT idx_82637_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_timed idx_82645_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_timed
    ADD CONSTRAINT idx_82645_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_timedstats idx_82649_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_timedstats
    ADD CONSTRAINT idx_82649_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_share idx_82657_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_share
    ADD CONSTRAINT idx_82657_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_ftp idx_82665_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_ftp
    ADD CONSTRAINT idx_82665_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_local idx_82672_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_local
    ADD CONSTRAINT idx_82672_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_s3 idx_82678_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_s3
    ADD CONSTRAINT idx_82678_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_sftp idx_82686_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_sftp
    ADD CONSTRAINT idx_82686_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_smb idx_82696_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_smb
    ADD CONSTRAINT idx_82696_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_webdav idx_82705_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_webdav
    ADD CONSTRAINT idx_82705_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_tag idx_82713_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_tag
    ADD CONSTRAINT idx_82713_primary PRIMARY KEY (id);


--
-- Name: idx_25983_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_25983_object ON public.a2_objects_apps_files_limits_total USING btree (object);


--
-- Name: idx_43130_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_43130_object ON public.a2_objects_apps_files_limits_authtotal USING btree (object);


--
-- Name: idx_82553_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82553_account ON public.a2_objects_apps_files_accesslog USING btree (account);


--
-- Name: idx_82553_file; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82553_file ON public.a2_objects_apps_files_accesslog USING btree (file);


--
-- Name: idx_82553_folder; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82553_folder ON public.a2_objects_apps_files_accesslog USING btree (folder);


--
-- Name: idx_82565_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82565_item ON public.a2_objects_apps_files_comment USING btree (item);


--
-- Name: idx_82565_owner_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82565_owner_item ON public.a2_objects_apps_files_comment USING btree (owner, item);


--
-- Name: idx_82577_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82577_filesystem ON public.a2_objects_apps_files_file USING btree (filesystem);


--
-- Name: idx_82577_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82577_name ON public.a2_objects_apps_files_file USING btree (name, parent);


--
-- Name: idx_82577_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82577_owner ON public.a2_objects_apps_files_file USING btree (owner);


--
-- Name: idx_82577_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82577_parent ON public.a2_objects_apps_files_file USING btree (parent);


--
-- Name: idx_82593_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82593_name ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (name);


--
-- Name: idx_82593_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82593_owner ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (owner);


--
-- Name: idx_82593_owner_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82593_owner_name ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (owner, name);


--
-- Name: idx_82593_storage; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82593_storage ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (storage);


--
-- Name: idx_82602_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82602_filesystem ON public.a2_objects_apps_files_folder USING btree (filesystem);


--
-- Name: idx_82602_name_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82602_name_parent ON public.a2_objects_apps_files_folder USING btree (name, parent);


--
-- Name: idx_82602_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82602_owner ON public.a2_objects_apps_files_folder USING btree (owner);


--
-- Name: idx_82602_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82602_parent ON public.a2_objects_apps_files_folder USING btree (parent);


--
-- Name: idx_82626_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82626_item ON public.a2_objects_apps_files_like USING btree (item);


--
-- Name: idx_82626_owner_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82626_owner_item ON public.a2_objects_apps_files_like USING btree (owner, item);


--
-- Name: idx_82629_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82629_object ON public.a2_objects_apps_files_limits_authentitytotal USING btree (object);


--
-- Name: idx_82637_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82637_object ON public.a2_objects_apps_files_limits_filesystemtotal USING btree (object);


--
-- Name: idx_82645_object; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82645_object ON public.a2_objects_apps_files_limits_timed USING btree (object);


--
-- Name: idx_82645_object_timeperiod; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82645_object_timeperiod ON public.a2_objects_apps_files_limits_timed USING btree (object, timeperiod);


--
-- Name: idx_82649_limitobj_iscurrent; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82649_limitobj_iscurrent ON public.a2_objects_apps_files_limits_timedstats USING btree (limitobj, iscurrent);


--
-- Name: idx_82649_limitobj_timestart; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82649_limitobj_timestart ON public.a2_objects_apps_files_limits_timedstats USING btree (limitobj, dates__timestart);


--
-- Name: idx_82657_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82657_item ON public.a2_objects_apps_files_share USING btree (item);


--
-- Name: idx_82657_item_owner_dest; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82657_item_owner_dest ON public.a2_objects_apps_files_share USING btree (item, owner, dest);


--
-- Name: idx_82657_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82657_owner ON public.a2_objects_apps_files_share USING btree (owner);


--
-- Name: idx_82665_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82665_filesystem ON public.a2_objects_apps_files_storage_ftp USING btree (filesystem);


--
-- Name: idx_82672_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82672_filesystem ON public.a2_objects_apps_files_storage_local USING btree (filesystem);


--
-- Name: idx_82678_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82678_filesystem ON public.a2_objects_apps_files_storage_s3 USING btree (filesystem);


--
-- Name: idx_82686_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82686_filesystem ON public.a2_objects_apps_files_storage_sftp USING btree (filesystem);


--
-- Name: idx_82696_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82696_filesystem ON public.a2_objects_apps_files_storage_smb USING btree (filesystem);


--
-- Name: idx_82705_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82705_filesystem ON public.a2_objects_apps_files_storage_webdav USING btree (filesystem);


--
-- Name: idx_82713_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82713_item ON public.a2_objects_apps_files_tag USING btree (item);


--
-- Name: idx_82713_item_tag; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_82713_item_tag ON public.a2_objects_apps_files_tag USING btree (item, tag);


--
-- Name: idx_82713_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_82713_owner ON public.a2_objects_apps_files_tag USING btree (owner);


--
-- PostgreSQL database dump complete
--

