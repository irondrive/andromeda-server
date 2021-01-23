--
-- PostgreSQL database dump
--

-- Dumped from database version 13.1
-- Dumped by pg_dump version 13.1

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
    owner character(12),
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
    owner character(12),
    name character varying(127),
    crypto_masterkey bytea,
    crypto_chunksize bigint
);


--
-- Name: a2_objects_apps_files_folder; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_folder (
    id character(16) NOT NULL,
    name character varying(255),
    dates__created bigint NOT NULL,
    dates__modified bigint,
    dates__accessed bigint,
    counters__size bigint DEFAULT '0'::bigint NOT NULL,
    counters__visits bigint DEFAULT '0'::bigint NOT NULL,
    counters__downloads bigint DEFAULT '0'::bigint NOT NULL,
    counters__bandwidth bigint DEFAULT '0'::bigint NOT NULL,
    owner character(12),
    parent character(16),
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
    value smallint NOT NULL
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
    timeperiod bigint NOT NULL,
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
    dest character varying(64),
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
    owner character(12),
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    path text NOT NULL,
    username bytea,
    password bytea,
    username_nonce bytea,
    password_nonce bytea
);


--
-- Name: a2_objects_apps_files_storage_local; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_local (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    filesystem character(12) NOT NULL,
    owner character(12),
    path text NOT NULL
);


--
-- Name: a2_objects_apps_files_storage_sftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_sftp (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    filesystem character(12) NOT NULL,
    owner character(12),
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    username bytea NOT NULL,
    password bytea,
    privkey text,
    pubkey text,
    keypass bytea,
    hostauth boolean,
    username_nonce bytea,
    password_nonce bytea,
    keypass_nonce bytea
);


--
-- Name: a2_objects_apps_files_storage_smb; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_files_storage_smb (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    filesystem character(12) NOT NULL,
    owner character(12),
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    workgroup character varying(255),
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea,
    password_nonce bytea
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
-- Name: a2_objects_apps_files_limits_total idx_41446_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_total
    ADD CONSTRAINT idx_41446_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_comment idx_41732_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_comment
    ADD CONSTRAINT idx_41732_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_config idx_41738_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_config
    ADD CONSTRAINT idx_41738_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_file idx_41741_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_file
    ADD CONSTRAINT idx_41741_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_filesystem_fsmanager idx_41753_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_filesystem_fsmanager
    ADD CONSTRAINT idx_41753_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_folder idx_41759_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_folder
    ADD CONSTRAINT idx_41759_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_like idx_41777_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_like
    ADD CONSTRAINT idx_41777_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_authtotal idx_41780_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_authtotal
    ADD CONSTRAINT idx_41780_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_filesystemtotal idx_41788_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_filesystemtotal
    ADD CONSTRAINT idx_41788_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_timed idx_41796_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_timed
    ADD CONSTRAINT idx_41796_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_limits_timedstats idx_41800_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_limits_timedstats
    ADD CONSTRAINT idx_41800_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_share idx_41808_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_share
    ADD CONSTRAINT idx_41808_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_ftp idx_41815_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_ftp
    ADD CONSTRAINT idx_41815_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_local idx_41821_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_local
    ADD CONSTRAINT idx_41821_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_sftp idx_41827_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_sftp
    ADD CONSTRAINT idx_41827_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_storage_smb idx_41833_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_storage_smb
    ADD CONSTRAINT idx_41833_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_files_tag idx_41839_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_files_tag
    ADD CONSTRAINT idx_41839_primary PRIMARY KEY (id);


--
-- Name: idx_41446_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41446_object ON public.a2_objects_apps_files_limits_total USING btree (object);


--
-- Name: idx_41732_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41732_item ON public.a2_objects_apps_files_comment USING btree (item);


--
-- Name: idx_41732_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41732_owner ON public.a2_objects_apps_files_comment USING btree (owner);


--
-- Name: idx_41741_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41741_filesystem ON public.a2_objects_apps_files_file USING btree (filesystem);


--
-- Name: idx_41741_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41741_id ON public.a2_objects_apps_files_file USING btree (id);


--
-- Name: idx_41741_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41741_owner ON public.a2_objects_apps_files_file USING btree (owner);


--
-- Name: idx_41741_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41741_parent ON public.a2_objects_apps_files_file USING btree (parent);


--
-- Name: idx_41753_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41753_name ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (name);


--
-- Name: idx_41753_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41753_owner ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (owner);


--
-- Name: idx_41753_owner_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41753_owner_2 ON public.a2_objects_apps_files_filesystem_fsmanager USING btree (owner, name);


--
-- Name: idx_41759_filesystem; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41759_filesystem ON public.a2_objects_apps_files_folder USING btree (filesystem);


--
-- Name: idx_41759_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41759_id ON public.a2_objects_apps_files_folder USING btree (id);


--
-- Name: idx_41759_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41759_owner ON public.a2_objects_apps_files_folder USING btree (owner);


--
-- Name: idx_41759_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41759_parent ON public.a2_objects_apps_files_folder USING btree (parent);


--
-- Name: idx_41777_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41777_owner ON public.a2_objects_apps_files_like USING btree (owner, item);


--
-- Name: idx_41780_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41780_object ON public.a2_objects_apps_files_limits_authtotal USING btree (object);


--
-- Name: idx_41788_object; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41788_object ON public.a2_objects_apps_files_limits_filesystemtotal USING btree (object);


--
-- Name: idx_41796_object; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41796_object ON public.a2_objects_apps_files_limits_timed USING btree (object);


--
-- Name: idx_41796_object_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41796_object_2 ON public.a2_objects_apps_files_limits_timed USING btree (object, timeperiod);


--
-- Name: idx_41800_limitobj; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41800_limitobj ON public.a2_objects_apps_files_limits_timedstats USING btree (limitobj, dates__timestart);


--
-- Name: idx_41800_limitobj_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41800_limitobj_2 ON public.a2_objects_apps_files_limits_timedstats USING btree (limitobj, iscurrent);


--
-- Name: idx_41808_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41808_item ON public.a2_objects_apps_files_share USING btree (item, dest);


--
-- Name: idx_41808_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41808_owner ON public.a2_objects_apps_files_share USING btree (owner);


--
-- Name: idx_41815_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41815_id ON public.a2_objects_apps_files_storage_ftp USING btree (id);


--
-- Name: idx_41815_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41815_owner ON public.a2_objects_apps_files_storage_ftp USING btree (owner);


--
-- Name: idx_41821_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41821_owner ON public.a2_objects_apps_files_storage_local USING btree (owner);


--
-- Name: idx_41827_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41827_id ON public.a2_objects_apps_files_storage_sftp USING btree (id);


--
-- Name: idx_41833_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41833_id ON public.a2_objects_apps_files_storage_smb USING btree (id);


--
-- Name: idx_41839_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_41839_item ON public.a2_objects_apps_files_tag USING btree (item, tag);


--
-- Name: idx_41839_item_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41839_item_2 ON public.a2_objects_apps_files_tag USING btree (item);


--
-- Name: idx_41839_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_41839_owner ON public.a2_objects_apps_files_tag USING btree (owner);


--
-- PostgreSQL database dump complete
--

