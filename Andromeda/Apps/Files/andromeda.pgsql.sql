--
-- PostgreSQL database dump
--

-- Dumped from database version 16.6 (Ubuntu 16.6-0ubuntu0.24.10.1)
-- Dumped by pg_dump version 16.6 (Ubuntu 16.6-0ubuntu0.24.10.1)

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
-- Name: a2obj_apps_files_actionlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_actionlog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar,
    item character(16) DEFAULT NULL::bpchar,
    parent character(16) DEFAULT NULL::bpchar,
    item_share character(16) DEFAULT NULL::bpchar,
    parent_share character(16) DEFAULT NULL::bpchar
);


--
-- Name: a2obj_apps_files_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_config (
    id character(1) NOT NULL,
    version character varying(255) NOT NULL,
    date_created double precision NOT NULL,
    apiurl text,
    rwchunksize bigint NOT NULL,
    crchunksize bigint NOT NULL,
    upload_maxsize bigint,
    timedstats boolean NOT NULL
);


--
-- Name: a2obj_apps_files_items_file; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_items_file (
    id character(16) NOT NULL
);


--
-- Name: a2obj_apps_files_items_folder; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_items_folder (
    id character(16) NOT NULL,
    count_subfiles bigint DEFAULT '0'::bigint NOT NULL,
    count_subfolders bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2obj_apps_files_items_item; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_items_item (
    id character(16) NOT NULL,
    size bigint NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    storage character(8) NOT NULL,
    parent character(16) DEFAULT NULL::bpchar,
    name character varying(255) DEFAULT NULL::character varying,
    isroot boolean,
    ispublic boolean,
    date_created double precision NOT NULL,
    date_modified double precision,
    date_accessed double precision,
    description text
);


--
-- Name: a2obj_apps_files_limits_accounttimed; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_accounttimed (
    id character(12) NOT NULL,
    account character(12) NOT NULL,
    timeperiod bigint NOT NULL,
    track_items boolean,
    track_dlstats boolean
);


--
-- Name: a2obj_apps_files_limits_accounttotal; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_accounttotal (
    id character(12) NOT NULL,
    account character(2) NOT NULL,
    emailshare boolean,
    userstorage boolean,
    track_items boolean,
    track_dlstats boolean
);


--
-- Name: a2obj_apps_files_limits_grouptimed; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_grouptimed (
    id character(12) NOT NULL,
    "group" character(12) NOT NULL,
    timeperiod bigint NOT NULL,
    track_items smallint,
    track_dlstats smallint
);


--
-- Name: a2obj_apps_files_limits_grouptotal; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_grouptotal (
    id character(12) NOT NULL,
    "group" character(12) NOT NULL,
    emailshare boolean,
    userstorage boolean,
    track_items smallint,
    track_dlstats smallint
);


--
-- Name: a2obj_apps_files_limits_storagetimed; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_storagetimed (
    id character(8) NOT NULL,
    storage character(8) NOT NULL,
    timeperiod bigint NOT NULL,
    track_items boolean,
    track_dlstats boolean
);


--
-- Name: a2obj_apps_files_limits_storagetotal; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_storagetotal (
    id character(8) NOT NULL,
    storage character(8) NOT NULL,
    track_items boolean,
    track_dlstats boolean
);


--
-- Name: a2obj_apps_files_limits_timed; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_timed (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    max_stats_age bigint,
    limit_pubdownloads bigint,
    limit_bandwidth bigint
);


--
-- Name: a2obj_apps_files_limits_timedstats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_timedstats (
    id character(12) NOT NULL,
    "limit" character(12) NOT NULL,
    date_created double precision NOT NULL,
    date_timestart bigint NOT NULL,
    iscurrent boolean,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_shares bigint DEFAULT '0'::bigint NOT NULL,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2obj_apps_files_limits_total; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_limits_total (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    date_download double precision,
    date_upload double precision,
    itemsharing boolean,
    share2everyone boolean,
    share2groups boolean,
    publicupload boolean,
    publicmodify boolean,
    randomwrite boolean,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_shares bigint DEFAULT '0'::bigint NOT NULL,
    limit_size bigint,
    limit_items bigint,
    limit_shares bigint,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2obj_apps_files_social_comment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_social_comment (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character(16) NOT NULL,
    value text NOT NULL,
    date_created double precision NOT NULL,
    date_modified double precision
);


--
-- Name: a2obj_apps_files_social_like; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_social_like (
    id character(12) NOT NULL,
    owner character(12) NOT NULL,
    item character(16) NOT NULL,
    date_created double precision NOT NULL,
    value boolean NOT NULL
);


--
-- Name: a2obj_apps_files_social_share; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_social_share (
    id character(16) NOT NULL,
    item character(16) NOT NULL,
    owner character(12) NOT NULL,
    dest character(12) DEFAULT NULL::bpchar,
    label text,
    authkey text,
    password text,
    date_created double precision NOT NULL,
    date_accessed double precision,
    count_accessed bigint DEFAULT '0'::bigint NOT NULL,
    limit_accessed bigint,
    date_expires double precision,
    can_read boolean NOT NULL,
    can_upload boolean NOT NULL,
    can_modify boolean NOT NULL,
    can_social boolean NOT NULL,
    can_reshare boolean NOT NULL,
    keepowner boolean NOT NULL
);


--
-- Name: a2obj_apps_files_social_tag; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_social_tag (
    id character(16) NOT NULL,
    owner character(12) NOT NULL,
    item character(16) NOT NULL,
    value character varying(127) NOT NULL,
    date_created double precision NOT NULL
);


--
-- Name: a2obj_apps_files_storage_ftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_storage_ftp (
    id character(8) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    username bytea,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea
);


--
-- Name: a2obj_apps_files_storage_local; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_storage_local (
    id character(8) NOT NULL,
    path text NOT NULL
);


--
-- Name: a2obj_apps_files_storage_s3; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_storage_s3 (
    id character(8) NOT NULL,
    endpoint text NOT NULL,
    path_style boolean,
    port smallint,
    usetls boolean,
    region character varying(64) NOT NULL,
    bucket character varying(64) NOT NULL,
    accesskey bytea,
    accesskey_nonce bytea DEFAULT NULL::bytea,
    secretkey bytea,
    secretkey_nonce bytea DEFAULT NULL::bytea
);


--
-- Name: a2obj_apps_files_storage_sftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_storage_sftp (
    id character(8) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    hostkey text,
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
    id character(8) NOT NULL,
    path text NOT NULL,
    hostname character varying(255) NOT NULL,
    workgroup character varying(255) DEFAULT NULL::character varying,
    username bytea,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);


--
-- Name: a2obj_apps_files_storage_storage; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_storage_storage (
    id character(8) NOT NULL,
    date_created double precision NOT NULL,
    fstype smallint NOT NULL,
    readonly boolean DEFAULT false NOT NULL,
    owner character(12) DEFAULT NULL::bpchar,
    name character varying(127) DEFAULT 'Default'::character varying NOT NULL,
    crypto_masterkey bytea DEFAULT NULL::bytea,
    crypto_chunksize bigint
);


--
-- Name: a2obj_apps_files_storage_webdav; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_storage_webdav (
    id character(8) NOT NULL,
    path text NOT NULL,
    endpoint text NOT NULL,
    username bytea NOT NULL,
    password bytea,
    username_nonce bytea DEFAULT NULL::bytea,
    password_nonce bytea DEFAULT NULL::bytea
);


--
-- Name: a2obj_apps_files_actionlog idx_153329_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_actionlog
    ADD CONSTRAINT idx_153329_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_config idx_153339_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_config
    ADD CONSTRAINT idx_153339_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_items_file idx_153344_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_file
    ADD CONSTRAINT idx_153344_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_items_folder idx_153347_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_folder
    ADD CONSTRAINT idx_153347_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_items_item idx_153352_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT idx_153352_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_accounttimed idx_153360_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT idx_153360_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_accounttotal idx_153363_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT idx_153363_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_grouptimed idx_153366_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT idx_153366_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_grouptotal idx_153369_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT idx_153369_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_storagetimed idx_153372_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetimed
    ADD CONSTRAINT idx_153372_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_storagetotal idx_153375_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetotal
    ADD CONSTRAINT idx_153375_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_timed idx_153378_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_timed
    ADD CONSTRAINT idx_153378_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_timedstats idx_153381_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_timedstats
    ADD CONSTRAINT idx_153381_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_limits_total idx_153389_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_total
    ADD CONSTRAINT idx_153389_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_social_comment idx_153397_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_comment
    ADD CONSTRAINT idx_153397_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_social_like idx_153402_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_like
    ADD CONSTRAINT idx_153402_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_social_share idx_153405_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT idx_153405_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_social_tag idx_153412_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_tag
    ADD CONSTRAINT idx_153412_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_ftp idx_153415_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT idx_153415_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_local idx_153421_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT idx_153421_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_s3 idx_153426_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT idx_153426_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_sftp idx_153433_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT idx_153433_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_smb idx_153442_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT idx_153442_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_storage idx_153450_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_storage
    ADD CONSTRAINT idx_153450_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_webdav idx_153459_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT idx_153459_primary PRIMARY KEY (id);


--
-- Name: idx_153329_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153329_account ON public.a2obj_apps_files_actionlog USING btree (account);


--
-- Name: idx_153329_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153329_item ON public.a2obj_apps_files_actionlog USING btree (item);


--
-- Name: idx_153352_name_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153352_name_parent ON public.a2obj_apps_files_items_item USING btree (name, parent);


--
-- Name: idx_153352_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153352_owner ON public.a2obj_apps_files_items_item USING btree (owner);


--
-- Name: idx_153352_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153352_parent ON public.a2obj_apps_files_items_item USING btree (parent);


--
-- Name: idx_153352_storage; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153352_storage ON public.a2obj_apps_files_items_item USING btree (storage);


--
-- Name: idx_153352_storage_isroot_ispublic; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153352_storage_isroot_ispublic ON public.a2obj_apps_files_items_item USING btree (storage, isroot, ispublic);


--
-- Name: idx_153352_storage_isroot_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153352_storage_isroot_owner ON public.a2obj_apps_files_items_item USING btree (storage, isroot, owner);


--
-- Name: idx_153360_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153360_account ON public.a2obj_apps_files_limits_accounttimed USING btree (account);


--
-- Name: idx_153360_account_timeperiod; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153360_account_timeperiod ON public.a2obj_apps_files_limits_accounttimed USING btree (account, timeperiod);


--
-- Name: idx_153363_account; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153363_account ON public.a2obj_apps_files_limits_accounttotal USING btree (account);


--
-- Name: idx_153366_group; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153366_group ON public.a2obj_apps_files_limits_grouptimed USING btree ("group");


--
-- Name: idx_153366_group_timeperiod; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153366_group_timeperiod ON public.a2obj_apps_files_limits_grouptimed USING btree ("group", timeperiod);


--
-- Name: idx_153369_group; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153369_group ON public.a2obj_apps_files_limits_grouptotal USING btree ("group");


--
-- Name: idx_153372_storage; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153372_storage ON public.a2obj_apps_files_limits_storagetimed USING btree (storage);


--
-- Name: idx_153372_storage_timeperiod; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153372_storage_timeperiod ON public.a2obj_apps_files_limits_storagetimed USING btree (storage, timeperiod);


--
-- Name: idx_153375_storage; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153375_storage ON public.a2obj_apps_files_limits_storagetotal USING btree (storage);


--
-- Name: idx_153381_limit_iscurrent; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153381_limit_iscurrent ON public.a2obj_apps_files_limits_timedstats USING btree ("limit", iscurrent);


--
-- Name: idx_153381_limit_timestart; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153381_limit_timestart ON public.a2obj_apps_files_limits_timedstats USING btree ("limit", date_timestart);


--
-- Name: idx_153397_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153397_item ON public.a2obj_apps_files_social_comment USING btree (item);


--
-- Name: idx_153397_owner_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153397_owner_item ON public.a2obj_apps_files_social_comment USING btree (owner, item);


--
-- Name: idx_153402_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153402_item ON public.a2obj_apps_files_social_like USING btree (item);


--
-- Name: idx_153402_owner_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153402_owner_item ON public.a2obj_apps_files_social_like USING btree (owner, item);


--
-- Name: idx_153405_dest; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153405_dest ON public.a2obj_apps_files_social_share USING btree (dest);


--
-- Name: idx_153405_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153405_item ON public.a2obj_apps_files_social_share USING btree (item);


--
-- Name: idx_153405_item_owner_dest; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153405_item_owner_dest ON public.a2obj_apps_files_social_share USING btree (item, owner, dest);


--
-- Name: idx_153405_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153405_owner ON public.a2obj_apps_files_social_share USING btree (owner);


--
-- Name: idx_153412_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153412_item ON public.a2obj_apps_files_social_tag USING btree (item);


--
-- Name: idx_153412_item_value; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153412_item_value ON public.a2obj_apps_files_social_tag USING btree (item, value);


--
-- Name: idx_153412_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153412_owner ON public.a2obj_apps_files_social_tag USING btree (owner);


--
-- Name: idx_153450_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153450_name ON public.a2obj_apps_files_storage_storage USING btree (name);


--
-- Name: idx_153450_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_153450_owner ON public.a2obj_apps_files_storage_storage USING btree (owner);


--
-- Name: idx_153450_owner_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_153450_owner_name ON public.a2obj_apps_files_storage_storage USING btree (owner, name);


--
-- Name: a2obj_apps_files_actionlog a2obj_apps_files_actionlog_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_actionlog
    ADD CONSTRAINT a2obj_apps_files_actionlog_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_items_file a2obj_apps_files_items_file_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_file
    ADD CONSTRAINT a2obj_apps_files_items_file_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_items_folder a2obj_apps_files_items_folder_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_folder
    ADD CONSTRAINT a2obj_apps_files_items_folder_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_items_item a2obj_apps_files_items_item_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT a2obj_apps_files_items_item_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_items_item a2obj_apps_files_items_item_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT a2obj_apps_files_items_item_ibfk_2 FOREIGN KEY (storage) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_items_item a2obj_apps_files_items_item_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT a2obj_apps_files_items_item_ibfk_3 FOREIGN KEY (parent) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_limits_accounttimed a2obj_apps_files_limits_accounttimed_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT a2obj_apps_files_limits_accounttimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_limits_accounttimed a2obj_apps_files_limits_accounttimed_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttimed
    ADD CONSTRAINT a2obj_apps_files_limits_accounttimed_ibfk_2 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_limits_accounttotal a2obj_apps_files_limits_accounttotal_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT a2obj_apps_files_limits_accounttotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_limits_accounttotal a2obj_apps_files_limits_accounttotal_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_accounttotal
    ADD CONSTRAINT a2obj_apps_files_limits_accounttotal_ibfk_2 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_limits_grouptimed a2obj_apps_files_limits_grouptimed_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT a2obj_apps_files_limits_grouptimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_limits_grouptimed a2obj_apps_files_limits_grouptimed_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptimed
    ADD CONSTRAINT a2obj_apps_files_limits_grouptimed_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_limits_grouptotal a2obj_apps_files_limits_grouptotal_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT a2obj_apps_files_limits_grouptotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_limits_grouptotal a2obj_apps_files_limits_grouptotal_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_grouptotal
    ADD CONSTRAINT a2obj_apps_files_limits_grouptotal_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_limits_storagetimed a2obj_apps_files_limits_storagetimed_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetimed
    ADD CONSTRAINT a2obj_apps_files_limits_storagetimed_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_limits_storagetimed a2obj_apps_files_limits_storagetimed_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetimed
    ADD CONSTRAINT a2obj_apps_files_limits_storagetimed_ibfk_2 FOREIGN KEY (storage) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_limits_storagetotal a2obj_apps_files_limits_storagetotal_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetotal
    ADD CONSTRAINT a2obj_apps_files_limits_storagetotal_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_limits_total(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_limits_storagetotal a2obj_apps_files_limits_storagetotal_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_storagetotal
    ADD CONSTRAINT a2obj_apps_files_limits_storagetotal_ibfk_2 FOREIGN KEY (storage) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_limits_timedstats a2obj_apps_files_limits_timedstats_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_limits_timedstats
    ADD CONSTRAINT a2obj_apps_files_limits_timedstats_ibfk_1 FOREIGN KEY ("limit") REFERENCES public.a2obj_apps_files_limits_timed(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_comment a2obj_apps_files_social_comment_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_comment
    ADD CONSTRAINT a2obj_apps_files_social_comment_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_comment a2obj_apps_files_social_comment_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_comment
    ADD CONSTRAINT a2obj_apps_files_social_comment_ibfk_2 FOREIGN KEY (item) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_like a2obj_apps_files_social_like_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_like
    ADD CONSTRAINT a2obj_apps_files_social_like_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_like a2obj_apps_files_social_like_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_like
    ADD CONSTRAINT a2obj_apps_files_social_like_ibfk_2 FOREIGN KEY (item) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_share a2obj_apps_files_social_share_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT a2obj_apps_files_social_share_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_share a2obj_apps_files_social_share_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT a2obj_apps_files_social_share_ibfk_2 FOREIGN KEY (item) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_share a2obj_apps_files_social_share_ibfk_3; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT a2obj_apps_files_social_share_ibfk_3 FOREIGN KEY (dest) REFERENCES public.a2obj_apps_accounts_policybase(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_tag a2obj_apps_files_social_tag_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_tag
    ADD CONSTRAINT a2obj_apps_files_social_tag_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_social_tag a2obj_apps_files_social_tag_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_tag
    ADD CONSTRAINT a2obj_apps_files_social_tag_ibfk_2 FOREIGN KEY (item) REFERENCES public.a2obj_apps_files_items_item(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_storage_storage a2obj_apps_files_storage_fsmanager_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_storage
    ADD CONSTRAINT a2obj_apps_files_storage_fsmanager_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_storage_ftp a2obj_apps_files_storage_ftp_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT a2obj_apps_files_storage_ftp_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_storage_local a2obj_apps_files_storage_local_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT a2obj_apps_files_storage_local_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_storage_s3 a2obj_apps_files_storage_s3_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT a2obj_apps_files_storage_s3_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_storage_sftp a2obj_apps_files_storage_sftp_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT a2obj_apps_files_storage_sftp_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_storage_smb a2obj_apps_files_storage_smb_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT a2obj_apps_files_storage_smb_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_storage_webdav a2obj_apps_files_storage_webdav_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT a2obj_apps_files_storage_webdav_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--


----------------------------------------------------------------------------------------------
--- NOTE pgloader DOES NOT transfer CHECK constraints from MySQL, copy these over manually ---
----------------------------------------------------------------------------------------------

ALTER TABLE ONLY public.a2obj_apps_files_items_item ADD CONSTRAINT CONSTRAINT_1
    CHECK (size >= 0);
    
ALTER TABLE ONLY public.a2obj_apps_files_items_item ADD CONSTRAINT CONSTRAINT_2
    CHECK (parent is null and name is null and isroot is not null and isroot = true or parent is not null and name is not null and isroot is null);

ALTER TABLE ONLY public.a2obj_apps_files_items_item ADD CONSTRAINT CONSTRAINT_3
    CHECK (owner is null and ispublic is not null and ispublic = true or owner is not null and ispublic is null);

