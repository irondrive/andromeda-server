--
-- PostgreSQL database dump
--

-- Dumped from database version 12.7 (Ubuntu 12.7-0ubuntu0.20.10.1)
-- Dumped by pg_dump version 13.4 (Ubuntu 13.4-0ubuntu0.21.04.1)

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
-- Name: a2obj_apps_files_accesslog; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_comment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_comment (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    comment text NOT NULL,
    dates__created double precision NOT NULL,
    dates__modified double precision NOT NULL
);


--
-- Name: a2obj_apps_files_config; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_file; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_filesystem_fsmanager; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_folder; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_like; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_like (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    dates__created double precision NOT NULL,
    value boolean NOT NULL
);


--
-- Name: a2obj_apps_files_limits_authentitytotal; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_limits_filesystemtotal; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_limits_timed; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_limits_timedstats; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_share; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_storage_ftp; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_storage_local; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_storage_local (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    filesystem character(12) NOT NULL,
    path text NOT NULL
);


--
-- Name: a2obj_apps_files_storage_s3; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_storage_sftp; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_storage_smb; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_storage_webdav; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_files_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_tag (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    tag character varying(127) NOT NULL,
    dates__created double precision NOT NULL
);


--
-- Name: a2obj_apps_files_accesslog idx_131136_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_accesslog
    ADD CONSTRAINT idx_131136_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_comment idx_131148_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_comment
    ADD CONSTRAINT idx_131148_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_config idx_131154_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_config
    ADD CONSTRAINT idx_131154_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_file idx_131160_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_file
    ADD CONSTRAINT idx_131160_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_filesystem_fsmanager idx_131176_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_filesystem_fsmanager
    ADD CONSTRAINT idx_131176_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_folder idx_131185_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_folder
    ADD CONSTRAINT idx_131185_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_like idx_131209_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_like
    ADD CONSTRAINT idx_131209_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_authentitytotal idx_131212_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_authentitytotal
    ADD CONSTRAINT idx_131212_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_filesystemtotal idx_131220_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_filesystemtotal
    ADD CONSTRAINT idx_131220_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_timed idx_131228_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_timed
    ADD CONSTRAINT idx_131228_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_timedstats idx_131232_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_timedstats
    ADD CONSTRAINT idx_131232_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_share idx_131240_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_share
    ADD CONSTRAINT idx_131240_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_ftp idx_131248_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT idx_131248_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_local idx_131255_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT idx_131255_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_s3 idx_131261_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT idx_131261_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_sftp idx_131269_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT idx_131269_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_smb idx_131279_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT idx_131279_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_webdav idx_131288_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT idx_131288_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_tag idx_131296_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_tag
    ADD CONSTRAINT idx_131296_primary PRIMARY KEY (id);


--
-- Name: idx_131136_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131136_account ON public.a2obj_apps_files_accesslog USING btree (account);


--
-- Name: idx_131136_file; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131136_file ON public.a2obj_apps_files_accesslog USING btree (file);


--
-- Name: idx_131136_folder; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131136_folder ON public.a2obj_apps_files_accesslog USING btree (folder);


--
-- Name: idx_131148_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131148_item ON public.a2obj_apps_files_comment USING btree (item);


--
-- Name: idx_131148_owner_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131148_owner_item ON public.a2obj_apps_files_comment USING btree (owner, item);


--
-- Name: idx_131160_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131160_filesystem ON public.a2obj_apps_files_file USING btree (filesystem);


--
-- Name: idx_131160_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131160_name ON public.a2obj_apps_files_file USING btree (name, parent);


--
-- Name: idx_131160_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131160_owner ON public.a2obj_apps_files_file USING btree (owner);


--
-- Name: idx_131160_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131160_parent ON public.a2obj_apps_files_file USING btree (parent);


--
-- Name: idx_131176_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131176_name ON public.a2obj_apps_files_filesystem_fsmanager USING btree (name);


--
-- Name: idx_131176_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131176_owner ON public.a2obj_apps_files_filesystem_fsmanager USING btree (owner);


--
-- Name: idx_131176_owner_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131176_owner_name ON public.a2obj_apps_files_filesystem_fsmanager USING btree (owner, name);


--
-- Name: idx_131176_storage; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131176_storage ON public.a2obj_apps_files_filesystem_fsmanager USING btree (storage);


--
-- Name: idx_131185_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131185_filesystem ON public.a2obj_apps_files_folder USING btree (filesystem);


--
-- Name: idx_131185_name_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131185_name_parent ON public.a2obj_apps_files_folder USING btree (name, parent);


--
-- Name: idx_131185_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131185_owner ON public.a2obj_apps_files_folder USING btree (owner);


--
-- Name: idx_131185_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131185_parent ON public.a2obj_apps_files_folder USING btree (parent);


--
-- Name: idx_131209_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131209_item ON public.a2obj_apps_files_like USING btree (item);


--
-- Name: idx_131209_owner_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131209_owner_item ON public.a2obj_apps_files_like USING btree (owner, item);


--
-- Name: idx_131212_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131212_object ON public.a2obj_apps_files_limits_authentitytotal USING btree (object);


--
-- Name: idx_131220_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131220_object ON public.a2obj_apps_files_limits_filesystemtotal USING btree (object);


--
-- Name: idx_131228_object; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131228_object ON public.a2obj_apps_files_limits_timed USING btree (object);


--
-- Name: idx_131228_object_timeperiod; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131228_object_timeperiod ON public.a2obj_apps_files_limits_timed USING btree (object, timeperiod);


--
-- Name: idx_131232_limitobj_iscurrent; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131232_limitobj_iscurrent ON public.a2obj_apps_files_limits_timedstats USING btree (limitobj, iscurrent);


--
-- Name: idx_131232_limitobj_timestart; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131232_limitobj_timestart ON public.a2obj_apps_files_limits_timedstats USING btree (limitobj, dates__timestart);


--
-- Name: idx_131240_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131240_item ON public.a2obj_apps_files_share USING btree (item);


--
-- Name: idx_131240_item_owner_dest; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131240_item_owner_dest ON public.a2obj_apps_files_share USING btree (item, owner, dest);


--
-- Name: idx_131240_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131240_owner ON public.a2obj_apps_files_share USING btree (owner);


--
-- Name: idx_131248_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131248_filesystem ON public.a2obj_apps_files_storage_ftp USING btree (filesystem);


--
-- Name: idx_131255_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131255_filesystem ON public.a2obj_apps_files_storage_local USING btree (filesystem);


--
-- Name: idx_131261_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131261_filesystem ON public.a2obj_apps_files_storage_s3 USING btree (filesystem);


--
-- Name: idx_131269_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131269_filesystem ON public.a2obj_apps_files_storage_sftp USING btree (filesystem);


--
-- Name: idx_131279_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131279_filesystem ON public.a2obj_apps_files_storage_smb USING btree (filesystem);


--
-- Name: idx_131288_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131288_filesystem ON public.a2obj_apps_files_storage_webdav USING btree (filesystem);


--
-- Name: idx_131296_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131296_item ON public.a2obj_apps_files_tag USING btree (item);


--
-- Name: idx_131296_item_tag; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_131296_item_tag ON public.a2obj_apps_files_tag USING btree (item, tag);


--
-- Name: idx_131296_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_131296_owner ON public.a2obj_apps_files_tag USING btree (owner);


--
-- PostgreSQL database dump complete
--

