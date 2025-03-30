--
-- PostgreSQL database dump
--

-- Dumped from database version 16.8 (Ubuntu 16.8-0ubuntu0.24.10.1)
-- Dumped by pg_dump version 16.8 (Ubuntu 16.8-0ubuntu0.24.10.1)

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
    periodicstats boolean NOT NULL
);


--
-- Name: a2obj_apps_files_items_file; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_items_file (
    id character(16) NOT NULL,
    size bigint NOT NULL
);


--
-- Name: a2obj_apps_files_items_folder; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_items_folder (
    id character(16) NOT NULL,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_subfiles bigint DEFAULT '0'::bigint NOT NULL,
    count_subfolders bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2obj_apps_files_items_item; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_items_item (
    id character(16) NOT NULL,
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
-- Name: a2obj_apps_files_policy_periodic; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_periodic (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    timestart bigint NOT NULL,
    timeperiod bigint NOT NULL,
    max_stats_age bigint,
    limit_pubdownloads bigint,
    limit_bandwidth bigint
);


--
-- Name: a2obj_apps_files_policy_periodicaccount; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_periodicaccount (
    id character(12) NOT NULL,
    account character(12) NOT NULL,
    track_items boolean,
    track_dlstats boolean
);


--
-- Name: a2obj_apps_files_policy_periodicgroup; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_periodicgroup (
    id character(12) NOT NULL,
    "group" character(12) NOT NULL,
    track_items smallint,
    track_dlstats smallint
);


--
-- Name: a2obj_apps_files_policy_periodicstats; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_periodicstats (
    id character(12) NOT NULL,
    "limit" character(12) NOT NULL,
    dateidx bigint NOT NULL,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2obj_apps_files_policy_periodicstorage; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_periodicstorage (
    id character(8) NOT NULL,
    storage character(8) NOT NULL,
    track_items boolean,
    track_dlstats boolean
);


--
-- Name: a2obj_apps_files_policy_standard; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_standard (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    date_download double precision,
    date_upload double precision,
    can_itemshare boolean,
    can_share2groups boolean,
    can_publicupload boolean,
    can_publicmodify boolean,
    can_randomwrite boolean,
    limit_size bigint,
    limit_items bigint,
    count_size bigint DEFAULT '0'::bigint NOT NULL,
    count_items bigint DEFAULT '0'::bigint NOT NULL,
    count_pubdownloads bigint DEFAULT '0'::bigint NOT NULL,
    count_bandwidth bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2obj_apps_files_policy_standardaccount; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_standardaccount (
    id character(12) NOT NULL,
    account character(12) NOT NULL,
    can_emailshare boolean,
    can_userstorage boolean,
    track_items boolean,
    track_dlstats boolean
);


--
-- Name: a2obj_apps_files_policy_standardgroup; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_standardgroup (
    id character(12) NOT NULL,
    "group" character(12) NOT NULL,
    can_emailshare boolean,
    can_userstorage boolean,
    track_items smallint,
    track_dlstats smallint
);


--
-- Name: a2obj_apps_files_policy_standardstorage; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_files_policy_standardstorage (
    id character(8) NOT NULL,
    storage character(8) NOT NULL,
    track_items boolean,
    track_dlstats boolean
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
    hostkey bytea,
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
-- Name: a2obj_apps_files_actionlog idx_179435_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_actionlog
    ADD CONSTRAINT idx_179435_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_config idx_179445_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_config
    ADD CONSTRAINT idx_179445_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_items_file idx_179450_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_file
    ADD CONSTRAINT idx_179450_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_items_folder idx_179453_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_folder
    ADD CONSTRAINT idx_179453_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_items_item idx_179459_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_items_item
    ADD CONSTRAINT idx_179459_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_periodic idx_179467_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodic
    ADD CONSTRAINT idx_179467_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_periodicaccount idx_179470_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicaccount
    ADD CONSTRAINT idx_179470_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_periodicgroup idx_179473_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicgroup
    ADD CONSTRAINT idx_179473_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_periodicstats idx_179476_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicstats
    ADD CONSTRAINT idx_179476_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_periodicstorage idx_179483_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicstorage
    ADD CONSTRAINT idx_179483_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_standard idx_179486_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standard
    ADD CONSTRAINT idx_179486_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_standardaccount idx_179493_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardaccount
    ADD CONSTRAINT idx_179493_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_standardgroup idx_179496_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardgroup
    ADD CONSTRAINT idx_179496_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_policy_standardstorage idx_179499_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardstorage
    ADD CONSTRAINT idx_179499_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_social_comment idx_179502_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_comment
    ADD CONSTRAINT idx_179502_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_social_like idx_179507_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_like
    ADD CONSTRAINT idx_179507_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_social_share idx_179510_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_share
    ADD CONSTRAINT idx_179510_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_social_tag idx_179517_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_social_tag
    ADD CONSTRAINT idx_179517_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_ftp idx_179520_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_ftp
    ADD CONSTRAINT idx_179520_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_local idx_179526_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_local
    ADD CONSTRAINT idx_179526_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_s3 idx_179531_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_s3
    ADD CONSTRAINT idx_179531_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_sftp idx_179538_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_sftp
    ADD CONSTRAINT idx_179538_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_smb idx_179547_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_smb
    ADD CONSTRAINT idx_179547_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_storage idx_179555_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_storage
    ADD CONSTRAINT idx_179555_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_files_storage_webdav idx_179564_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_webdav
    ADD CONSTRAINT idx_179564_primary PRIMARY KEY (id);


--
-- Name: idx_179435_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179435_account ON public.a2obj_apps_files_actionlog USING btree (account);


--
-- Name: idx_179435_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179435_item ON public.a2obj_apps_files_actionlog USING btree (item);


--
-- Name: idx_179459_name_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179459_name_parent ON public.a2obj_apps_files_items_item USING btree (name, parent);


--
-- Name: idx_179459_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179459_owner ON public.a2obj_apps_files_items_item USING btree (owner);


--
-- Name: idx_179459_parent; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179459_parent ON public.a2obj_apps_files_items_item USING btree (parent);


--
-- Name: idx_179459_storage; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179459_storage ON public.a2obj_apps_files_items_item USING btree (storage);


--
-- Name: idx_179459_storage_isroot_ispublic; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179459_storage_isroot_ispublic ON public.a2obj_apps_files_items_item USING btree (storage, isroot, ispublic);


--
-- Name: idx_179459_storage_isroot_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179459_storage_isroot_owner ON public.a2obj_apps_files_items_item USING btree (storage, isroot, owner);


--
-- Name: idx_179470_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179470_account ON public.a2obj_apps_files_policy_periodicaccount USING btree (account);


--
-- Name: idx_179473_group; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179473_group ON public.a2obj_apps_files_policy_periodicgroup USING btree ("group");


--
-- Name: idx_179476_limit_dateidx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179476_limit_dateidx ON public.a2obj_apps_files_policy_periodicstats USING btree ("limit", dateidx);


--
-- Name: idx_179483_storage; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179483_storage ON public.a2obj_apps_files_policy_periodicstorage USING btree (storage);


--
-- Name: idx_179493_account; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179493_account ON public.a2obj_apps_files_policy_standardaccount USING btree (account);


--
-- Name: idx_179496_group; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179496_group ON public.a2obj_apps_files_policy_standardgroup USING btree ("group");


--
-- Name: idx_179499_storage; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179499_storage ON public.a2obj_apps_files_policy_standardstorage USING btree (storage);


--
-- Name: idx_179502_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179502_item ON public.a2obj_apps_files_social_comment USING btree (item);


--
-- Name: idx_179502_owner_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179502_owner_item ON public.a2obj_apps_files_social_comment USING btree (owner, item);


--
-- Name: idx_179507_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179507_item ON public.a2obj_apps_files_social_like USING btree (item);


--
-- Name: idx_179507_owner_item; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179507_owner_item ON public.a2obj_apps_files_social_like USING btree (owner, item);


--
-- Name: idx_179510_dest; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179510_dest ON public.a2obj_apps_files_social_share USING btree (dest);


--
-- Name: idx_179510_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179510_item ON public.a2obj_apps_files_social_share USING btree (item);


--
-- Name: idx_179510_item_owner_dest; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179510_item_owner_dest ON public.a2obj_apps_files_social_share USING btree (item, owner, dest);


--
-- Name: idx_179510_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179510_owner ON public.a2obj_apps_files_social_share USING btree (owner);


--
-- Name: idx_179517_item; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179517_item ON public.a2obj_apps_files_social_tag USING btree (item);


--
-- Name: idx_179517_item_value; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179517_item_value ON public.a2obj_apps_files_social_tag USING btree (item, value);


--
-- Name: idx_179517_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179517_owner ON public.a2obj_apps_files_social_tag USING btree (owner);


--
-- Name: idx_179555_name; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179555_name ON public.a2obj_apps_files_storage_storage USING btree (name);


--
-- Name: idx_179555_owner; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_179555_owner ON public.a2obj_apps_files_storage_storage USING btree (owner);


--
-- Name: idx_179555_owner_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_179555_owner_name ON public.a2obj_apps_files_storage_storage USING btree (owner, name);


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
-- Name: a2obj_apps_files_policy_periodicaccount a2obj_apps_files_policy_periodicaccount_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicaccount
    ADD CONSTRAINT a2obj_apps_files_policy_periodicaccount_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_policy_periodic(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_policy_periodicaccount a2obj_apps_files_policy_periodicaccount_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicaccount
    ADD CONSTRAINT a2obj_apps_files_policy_periodicaccount_ibfk_2 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_policy_periodicgroup a2obj_apps_files_policy_periodicgroup_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicgroup
    ADD CONSTRAINT a2obj_apps_files_policy_periodicgroup_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_policy_periodic(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_policy_periodicgroup a2obj_apps_files_policy_periodicgroup_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicgroup
    ADD CONSTRAINT a2obj_apps_files_policy_periodicgroup_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_policy_periodicstats a2obj_apps_files_policy_periodicstats_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicstats
    ADD CONSTRAINT a2obj_apps_files_policy_periodicstats_ibfk_1 FOREIGN KEY ("limit") REFERENCES public.a2obj_apps_files_policy_periodic(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_policy_periodicstorage a2obj_apps_files_policy_periodicstorage_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicstorage
    ADD CONSTRAINT a2obj_apps_files_policy_periodicstorage_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_policy_periodic(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_policy_periodicstorage a2obj_apps_files_policy_periodicstorage_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_periodicstorage
    ADD CONSTRAINT a2obj_apps_files_policy_periodicstorage_ibfk_2 FOREIGN KEY (storage) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_policy_standardaccount a2obj_apps_files_policy_standardaccount_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardaccount
    ADD CONSTRAINT a2obj_apps_files_policy_standardaccount_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_policy_standard(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_policy_standardaccount a2obj_apps_files_policy_standardaccount_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardaccount
    ADD CONSTRAINT a2obj_apps_files_policy_standardaccount_ibfk_2 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_policy_standardgroup a2obj_apps_files_policy_standardgroup_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardgroup
    ADD CONSTRAINT a2obj_apps_files_policy_standardgroup_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_policy_standard(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_policy_standardgroup a2obj_apps_files_policy_standardgroup_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardgroup
    ADD CONSTRAINT a2obj_apps_files_policy_standardgroup_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_files_policy_standardstorage a2obj_apps_files_policy_standardstorage_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardstorage
    ADD CONSTRAINT a2obj_apps_files_policy_standardstorage_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_files_policy_standard(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_files_policy_standardstorage a2obj_apps_files_policy_standardstorage_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_policy_standardstorage
    ADD CONSTRAINT a2obj_apps_files_policy_standardstorage_ibfk_2 FOREIGN KEY (storage) REFERENCES public.a2obj_apps_files_storage_storage(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


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
-- Name: a2obj_apps_files_storage_storage a2obj_apps_files_storage_storage_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_files_storage_storage
    ADD CONSTRAINT a2obj_apps_files_storage_storage_ibfk_1 FOREIGN KEY (owner) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


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

ALTER TABLE ONLY public.a2obj_apps_files_items_file ADD CONSTRAINT CONSTRAINT_1
    CHECK (size >= 0);
    
ALTER TABLE ONLY public.a2obj_apps_files_items_item ADD CONSTRAINT CONSTRAINT_1
    CHECK (parent is null and name is null and isroot is not null and isroot = true or parent is not null and name is not null and isroot is null);

ALTER TABLE ONLY public.a2obj_apps_files_items_item ADD CONSTRAINT CONSTRAINT_2
    CHECK (owner is null and ispublic is not null and ispublic = true or owner is not null and ispublic is null);

ALTER TABLE ONLY public.a2obj_apps_files_storage_storage ADD CONSTRAINT CONSTRAINT_1
    CHECK (crypto_masterkey is null and crypto_chunksize is null or crypto_masterkey is not null and crypto_chunksize is not null)
