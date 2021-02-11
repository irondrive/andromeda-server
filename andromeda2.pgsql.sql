--
-- PostgreSQL database dump
--

-- Dumped from database version 12.5 (Ubuntu 12.5-0ubuntu0.20.10.1)
-- Dumped by pg_dump version 12.5 (Ubuntu 12.5-0ubuntu0.20.10.1)

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
-- Name: a2_objects_apps_files_comment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_comment (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    comment text NOT NULL,
    private boolean NOT NULL,
    dates__created bigint NOT NULL,
    dates__modified bigint NOT NULL
);


--
-- Name: a2_objects_apps_files_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_config (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    apiurl text,
    rwchunksize bigint NOT NULL,
    crchunksize bigint NOT NULL,
    features__timedstats boolean NOT NULL
);


--
-- Name: a2_objects_apps_files_file; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_file (
    id character(16) NOT NULL,
    name character varying(255) NOT NULL,
    dates__created bigint NOT NULL,
    dates__modified bigint,
    dates__accessed bigint,
    size bigint DEFAULT '0'::bigint NOT NULL,
    counters__downloads bigint DEFAULT '0'::bigint NOT NULL,
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
    dates__created bigint NOT NULL,
    type smallint NOT NULL,
    readonly boolean NOT NULL,
    storage character varying(64) NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    name character varying(127) DEFAULT NULL::character varying,
    crypto_masterkey bytea DEFAULT '\x4e554c4c'::bytea,
    crypto_chunksize bigint
);


--
-- Name: a2_objects_apps_files_folder; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_folder (
    id character(16) NOT NULL,
    name character varying(255) DEFAULT NULL::character varying,
    dates__created bigint NOT NULL,
    dates__modified bigint,
    dates__accessed bigint,
    counters__size bigint DEFAULT '0'::bigint NOT NULL,
    counters__visits bigint DEFAULT '0'::bigint NOT NULL,
    counters__downloads bigint DEFAULT '0'::bigint NOT NULL,
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
    dates__created bigint NOT NULL,
    value boolean NOT NULL
);


--
-- Name: a2_objects_apps_files_limits_authtotal; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_authtotal (
    id character(12) NOT NULL,
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
    dates__created bigint NOT NULL,
    dates__download bigint,
    dates__upload bigint,
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
    counters__downloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2_objects_apps_files_limits_timed; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_timed (
    id character(12) NOT NULL,
    object character varying(64) NOT NULL,
    stats bigint DEFAULT '0'::bigint NOT NULL,
    dates__created bigint NOT NULL,
    timeperiod bigint NOT NULL,
    max_stats_age bigint,
    features__track_items boolean,
    features__track_dlstats boolean,
    counters_limits__downloads bigint,
    counters_limits__bandwidth bigint
);


--
-- Name: a2_objects_apps_files_limits_timedstats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_limits_timedstats (
    id character(12) NOT NULL,
    limitobj character varying(64) NOT NULL,
    dates__created bigint NOT NULL,
    dates__timestart bigint NOT NULL,
    iscurrent boolean,
    counters__size bigint DEFAULT '0'::bigint NOT NULL,
    counters__items bigint DEFAULT '0'::bigint NOT NULL,
    counters__shares bigint DEFAULT '0'::bigint NOT NULL,
    counters__downloads bigint DEFAULT '0'::bigint NOT NULL,
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
    dates__created bigint NOT NULL,
    dates__accessed bigint,
    counters__accessed bigint DEFAULT '0'::bigint NOT NULL,
    counters_limits__accessed bigint,
    dates__expires bigint,
    features__read boolean NOT NULL,
    features__upload boolean NOT NULL,
    features__modify boolean NOT NULL,
    features__social boolean NOT NULL,
    features__reshare boolean NOT NULL
);


--
-- Name: a2_objects_apps_files_storage_ftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_ftp (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    filesystem character(12) NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    path text NOT NULL,
    username bytea,
    password bytea,
    username_nonce bytea DEFAULT '\x4e554c4c'::bytea,
    password_nonce bytea
);


--
-- Name: a2_objects_apps_files_storage_local; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_local (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    filesystem character(12) NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    path text NOT NULL
);


--
-- Name: a2_objects_apps_files_storage_sftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_sftp (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    filesystem character(12) NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    username bytea NOT NULL,
    password bytea,
    privkey text,
    pubkey text,
    keypass bytea,
    hostauth boolean,
    username_nonce bytea DEFAULT '\x4e554c4c'::bytea,
    password_nonce bytea DEFAULT '\x4e554c4c'::bytea,
    keypass_nonce bytea DEFAULT '\x4e554c4c'::bytea
);


--
-- Name: a2_objects_apps_files_storage_smb; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_smb (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    filesystem character(12) NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    workgroup character varying(255) DEFAULT NULL::character varying,
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea DEFAULT '\x4e554c4c'::bytea,
    password_nonce bytea DEFAULT '\x4e554c4c'::bytea
);


--
-- Name: a2_objects_apps_files_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_tag (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character varying(64) NOT NULL,
    tag character varying(127) NOT NULL,
    dates__created bigint NOT NULL
);


--
-- Name: a2_objects_apps_files_limits_total idx_25983_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_total
    ADD CONSTRAINT idx_25983_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_comment idx_30985_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_comment
    ADD CONSTRAINT idx_30985_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_config idx_30991_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_config
    ADD CONSTRAINT idx_30991_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_file idx_30997_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_file
    ADD CONSTRAINT idx_30997_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_filesystem_fsmanager idx_31010_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_filesystem_fsmanager
    ADD CONSTRAINT idx_31010_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_folder idx_31019_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_folder
    ADD CONSTRAINT idx_31019_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_like idx_31040_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_like
    ADD CONSTRAINT idx_31040_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_authtotal idx_31043_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_authtotal
    ADD CONSTRAINT idx_31043_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_filesystemtotal idx_31051_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_filesystemtotal
    ADD CONSTRAINT idx_31051_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_timed idx_31059_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_timed
    ADD CONSTRAINT idx_31059_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_timedstats idx_31063_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_timedstats
    ADD CONSTRAINT idx_31063_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_share idx_31071_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_share
    ADD CONSTRAINT idx_31071_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_ftp idx_31079_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_ftp
    ADD CONSTRAINT idx_31079_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_local idx_31087_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_local
    ADD CONSTRAINT idx_31087_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_sftp idx_31094_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_sftp
    ADD CONSTRAINT idx_31094_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_smb idx_31104_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_smb
    ADD CONSTRAINT idx_31104_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_tag idx_31114_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_tag
    ADD CONSTRAINT idx_31114_primary PRIMARY KEY (id);


--
-- Name: idx_25983_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_25983_object ON public.a2_objects_apps_files_limits_total USING btree (object);


--
-- Name: idx_30985_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_30985_item ON public.a2_objects_apps_files_comment USING btree (item);


--
-- Name: idx_30985_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_30985_owner ON public.a2_objects_apps_files_comment USING btree (owner);


--
-- Name: idx_30997_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_30997_filesystem ON public.a2_objects_apps_files_file USING btree (filesystem);


--
-- Name: idx_30997_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_30997_id ON public.a2_objects_apps_files_file USING btree (id);


--
-- Name: idx_30997_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_30997_owner ON public.a2_objects_apps_files_file USING btree (owner);


--
-- Name: idx_30997_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_30997_parent ON public.a2_objects_apps_files_file USING btree (parent);


--
-- Name: idx_31010_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31010_name ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (name);


--
-- Name: idx_31010_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31010_owner ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (owner);


--
-- Name: idx_31010_owner_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31010_owner_2 ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (owner, name);


--
-- Name: idx_31019_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31019_filesystem ON public.a2_objects_apps_files_folder USING btree (filesystem);


--
-- Name: idx_31019_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31019_id ON public.a2_objects_apps_files_folder USING btree (id);


--
-- Name: idx_31019_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31019_owner ON public.a2_objects_apps_files_folder USING btree (owner);


--
-- Name: idx_31019_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31019_parent ON public.a2_objects_apps_files_folder USING btree (parent);


--
-- Name: idx_31040_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31040_owner ON public.a2_objects_apps_files_like USING btree (owner, item);


--
-- Name: idx_31043_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31043_object ON public.a2_objects_apps_files_limits_authtotal USING btree (object);


--
-- Name: idx_31051_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31051_object ON public.a2_objects_apps_files_limits_filesystemtotal USING btree (object);


--
-- Name: idx_31059_object; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31059_object ON public.a2_objects_apps_files_limits_timed USING btree (object);


--
-- Name: idx_31059_object_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31059_object_2 ON public.a2_objects_apps_files_limits_timed USING btree (object, timeperiod);


--
-- Name: idx_31063_limitobj; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31063_limitobj ON public.a2_objects_apps_files_limits_timedstats USING btree (limitobj, dates__timestart);


--
-- Name: idx_31063_limitobj_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31063_limitobj_2 ON public.a2_objects_apps_files_limits_timedstats USING btree (limitobj, iscurrent);


--
-- Name: idx_31071_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31071_item ON public.a2_objects_apps_files_share USING btree (item, dest);


--
-- Name: idx_31071_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31071_owner ON public.a2_objects_apps_files_share USING btree (owner);


--
-- Name: idx_31079_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31079_id ON public.a2_objects_apps_files_storage_ftp USING btree (id);


--
-- Name: idx_31079_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31079_owner ON public.a2_objects_apps_files_storage_ftp USING btree (owner);


--
-- Name: idx_31087_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31087_owner ON public.a2_objects_apps_files_storage_local USING btree (owner);


--
-- Name: idx_31094_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31094_id ON public.a2_objects_apps_files_storage_sftp USING btree (id);


--
-- Name: idx_31104_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31104_id ON public.a2_objects_apps_files_storage_smb USING btree (id);


--
-- Name: idx_31114_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_31114_item ON public.a2_objects_apps_files_tag USING btree (item, tag);


--
-- Name: idx_31114_item_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31114_item_2 ON public.a2_objects_apps_files_tag USING btree (item);


--
-- Name: idx_31114_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_31114_owner ON public.a2_objects_apps_files_tag USING btree (owner);


--
-- PostgreSQL database dump complete
--

