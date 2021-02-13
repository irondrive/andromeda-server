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
    crypto_masterkey bytea DEFAULT NULL::bytea,
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
    username_nonce bytea DEFAULT NULL::bytea,
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
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea,
    keypass_nonce bytea DEFAULT NULL::bytea
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
    dates__created bigint NOT NULL
);


--
-- Name: a2_objects_apps_files_limits_total idx_25983_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_total
    ADD CONSTRAINT idx_25983_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_comment idx_39548_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_comment
    ADD CONSTRAINT idx_39548_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_config idx_39554_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_config
    ADD CONSTRAINT idx_39554_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_file idx_39560_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_file
    ADD CONSTRAINT idx_39560_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_filesystem_fsmanager idx_39573_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_filesystem_fsmanager
    ADD CONSTRAINT idx_39573_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_folder idx_39582_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_folder
    ADD CONSTRAINT idx_39582_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_like idx_39603_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_like
    ADD CONSTRAINT idx_39603_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_authtotal idx_39606_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_authtotal
    ADD CONSTRAINT idx_39606_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_filesystemtotal idx_39614_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_filesystemtotal
    ADD CONSTRAINT idx_39614_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_timed idx_39622_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_timed
    ADD CONSTRAINT idx_39622_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_timedstats idx_39626_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_timedstats
    ADD CONSTRAINT idx_39626_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_share idx_39634_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_share
    ADD CONSTRAINT idx_39634_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_ftp idx_39642_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_ftp
    ADD CONSTRAINT idx_39642_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_local idx_39650_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_local
    ADD CONSTRAINT idx_39650_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_sftp idx_39657_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_sftp
    ADD CONSTRAINT idx_39657_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_smb idx_39667_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_smb
    ADD CONSTRAINT idx_39667_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_tag idx_39677_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_tag
    ADD CONSTRAINT idx_39677_primary PRIMARY KEY (id);


--
-- Name: idx_25983_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_25983_object ON public.a2_objects_apps_files_limits_total USING btree (object);


--
-- Name: idx_39548_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39548_item ON public.a2_objects_apps_files_comment USING btree (item);


--
-- Name: idx_39548_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39548_owner ON public.a2_objects_apps_files_comment USING btree (owner);


--
-- Name: idx_39560_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39560_filesystem ON public.a2_objects_apps_files_file USING btree (filesystem);


--
-- Name: idx_39560_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39560_id ON public.a2_objects_apps_files_file USING btree (id);


--
-- Name: idx_39560_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39560_owner ON public.a2_objects_apps_files_file USING btree (owner);


--
-- Name: idx_39560_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39560_parent ON public.a2_objects_apps_files_file USING btree (parent);


--
-- Name: idx_39573_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39573_name ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (name);


--
-- Name: idx_39573_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39573_owner ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (owner);


--
-- Name: idx_39573_owner_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39573_owner_2 ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (owner, name);


--
-- Name: idx_39582_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39582_filesystem ON public.a2_objects_apps_files_folder USING btree (filesystem);


--
-- Name: idx_39582_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39582_id ON public.a2_objects_apps_files_folder USING btree (id);


--
-- Name: idx_39582_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39582_owner ON public.a2_objects_apps_files_folder USING btree (owner);


--
-- Name: idx_39582_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39582_parent ON public.a2_objects_apps_files_folder USING btree (parent);


--
-- Name: idx_39603_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39603_owner ON public.a2_objects_apps_files_like USING btree (owner, item);


--
-- Name: idx_39606_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39606_object ON public.a2_objects_apps_files_limits_authtotal USING btree (object);


--
-- Name: idx_39614_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39614_object ON public.a2_objects_apps_files_limits_filesystemtotal USING btree (object);


--
-- Name: idx_39622_object; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39622_object ON public.a2_objects_apps_files_limits_timed USING btree (object);


--
-- Name: idx_39622_object_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39622_object_2 ON public.a2_objects_apps_files_limits_timed USING btree (object, timeperiod);


--
-- Name: idx_39626_limitobj; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39626_limitobj ON public.a2_objects_apps_files_limits_timedstats USING btree (limitobj, dates__timestart);


--
-- Name: idx_39626_limitobj_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39626_limitobj_2 ON public.a2_objects_apps_files_limits_timedstats USING btree (limitobj, iscurrent);


--
-- Name: idx_39634_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39634_item ON public.a2_objects_apps_files_share USING btree (item, dest);


--
-- Name: idx_39634_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39634_owner ON public.a2_objects_apps_files_share USING btree (owner);


--
-- Name: idx_39642_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39642_id ON public.a2_objects_apps_files_storage_ftp USING btree (id);


--
-- Name: idx_39642_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39642_owner ON public.a2_objects_apps_files_storage_ftp USING btree (owner);


--
-- Name: idx_39650_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39650_owner ON public.a2_objects_apps_files_storage_local USING btree (owner);


--
-- Name: idx_39657_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39657_id ON public.a2_objects_apps_files_storage_sftp USING btree (id);


--
-- Name: idx_39667_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39667_id ON public.a2_objects_apps_files_storage_smb USING btree (id);


--
-- Name: idx_39677_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39677_item ON public.a2_objects_apps_files_tag USING btree (item, tag);


--
-- Name: idx_39677_item_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39677_item_2 ON public.a2_objects_apps_files_tag USING btree (item);


--
-- Name: idx_39677_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39677_owner ON public.a2_objects_apps_files_tag USING btree (owner);


--
-- PostgreSQL database dump complete
--

