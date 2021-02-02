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
-- Name: a2_objects_apps_accounts_account; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_account (
    id character(12) NOT NULL,
    username character varying(127) NOT NULL,
    fullname character varying(255) DEFAULT NULL::character varying,
    unlockcode character(8) DEFAULT NULL::bpchar,
    dates__created bigint DEFAULT '0'::bigint NOT NULL,
    dates__passwordset bigint DEFAULT '0'::bigint NOT NULL,
    dates__loggedon bigint DEFAULT '0'::bigint NOT NULL,
    dates__active bigint DEFAULT '0'::bigint NOT NULL,
    dates__modified bigint,
    max_session_age bigint,
    max_password_age bigint,
    features__admin boolean,
    features__enabled boolean,
    features__forcetf boolean,
    features__allowcrypto boolean,
    counters_limits__sessions smallint,
    counters_limits__contactinfos smallint,
    counters_limits__recoverykeys smallint,
    comment text,
    master_key bytea DEFAULT '\x4e554c4c'::bytea,
    master_nonce bytea DEFAULT '\x4e554c4c'::bytea,
    master_salt bytea DEFAULT '\x4e554c4c'::bytea,
    password text,
    authsource character varying(64) DEFAULT NULL::character varying,
    groups smallint DEFAULT '0'::smallint NOT NULL,
    sessions smallint DEFAULT '0'::smallint NOT NULL,
    contactinfos smallint DEFAULT '0'::smallint NOT NULL,
    clients smallint DEFAULT '0'::smallint NOT NULL,
    twofactors smallint DEFAULT '0'::smallint NOT NULL,
    recoverykeys smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: a2_objects_apps_accounts_auth_ftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_auth_ftp (
    id character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    manager character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_auth_imap; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_auth_imap (
    id character(12) NOT NULL,
    protocol smallint NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    secauth boolean NOT NULL,
    manager character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_auth_ldap; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_auth_ldap (
    id character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    secure boolean NOT NULL,
    userprefix character varying(255) NOT NULL,
    manager character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_auth_manager; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_auth_manager (
    id character(12) NOT NULL,
    authsource character varying(64) NOT NULL,
    description text NOT NULL,
    default_group character(12) DEFAULT NULL::bpchar
);


--
-- Name: a2_objects_apps_accounts_client; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_client (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    lastaddr character varying(255) NOT NULL,
    useragent text NOT NULL,
    dates__active bigint DEFAULT '0'::bigint NOT NULL,
    dates__created bigint DEFAULT '0'::bigint NOT NULL,
    dates__loggedon bigint DEFAULT '0'::bigint NOT NULL,
    account character(12) NOT NULL,
    session character(12) DEFAULT NULL::bpchar
);


--
-- Name: a2_objects_apps_accounts_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_config (
    id character(12) NOT NULL,
    features__createaccount boolean NOT NULL,
    features__emailasusername boolean NOT NULL,
    features__requirecontact smallint NOT NULL,
    default_group character(12) DEFAULT NULL::bpchar,
    dates__created bigint NOT NULL
);


--
-- Name: a2_objects_apps_accounts_contactinfo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_contactinfo (
    id character(12) NOT NULL,
    type smallint NOT NULL,
    info character varying(127) NOT NULL,
    valid boolean DEFAULT true NOT NULL,
    unlockcode character(8) DEFAULT NULL::bpchar,
    dates__created bigint NOT NULL,
    account character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_group (
    id character(12) NOT NULL,
    name character varying(127) NOT NULL,
    comment text,
    priority smallint NOT NULL,
    dates__created bigint NOT NULL,
    dates__modified bigint,
    features__admin boolean,
    features__enabled boolean,
    features__forcetf boolean,
    features__allowcrypto boolean,
    counters_limits__sessions smallint,
    counters_limits__contactinfos smallint,
    counters_limits__recoverykeys smallint,
    max_session_age bigint,
    max_password_age bigint,
    accounts bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2_objects_apps_accounts_groupjoin; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_groupjoin (
    id character(12) NOT NULL,
    dates__created bigint NOT NULL,
    accounts character(12) NOT NULL,
    groups character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_recoverykey; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_recoverykey (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    dates__created bigint DEFAULT '0'::bigint NOT NULL,
    master_key bytea DEFAULT '\x4e554c4c'::bytea,
    master_nonce bytea DEFAULT '\x4e554c4c'::bytea,
    master_salt bytea DEFAULT '\x4e554c4c'::bytea,
    account character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_session; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_session (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    dates__active bigint DEFAULT '0'::bigint NOT NULL,
    dates__created bigint DEFAULT '0'::bigint NOT NULL,
    master_key bytea DEFAULT '\x4e554c4c'::bytea,
    master_nonce bytea DEFAULT '\x4e554c4c'::bytea,
    master_salt bytea DEFAULT '\x4e554c4c'::bytea,
    account character(12) NOT NULL,
    client character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_twofactor; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_twofactor (
    id character(12) NOT NULL,
    comment text,
    secret bytea NOT NULL,
    nonce bytea DEFAULT '\x4e554c4c'::bytea,
    valid boolean DEFAULT false NOT NULL,
    dates__created bigint NOT NULL,
    account character(12) NOT NULL,
    usedtokens smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: a2_objects_apps_accounts_usedtoken; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_usedtoken (
    id character(12) NOT NULL,
    code character(6) NOT NULL,
    dates__created bigint NOT NULL,
    twofactor character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_account idx_29707_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_account
    ADD CONSTRAINT idx_29707_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_ftp idx_29729_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_ftp
    ADD CONSTRAINT idx_29729_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_imap idx_29732_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_imap
    ADD CONSTRAINT idx_29732_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_ldap idx_29735_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_ldap
    ADD CONSTRAINT idx_29735_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_manager idx_29741_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_manager
    ADD CONSTRAINT idx_29741_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_client idx_29748_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_client
    ADD CONSTRAINT idx_29748_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_config idx_29758_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_config
    ADD CONSTRAINT idx_29758_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_contactinfo idx_29762_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_contactinfo
    ADD CONSTRAINT idx_29762_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_group idx_29767_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_group
    ADD CONSTRAINT idx_29767_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_groupjoin idx_29774_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_groupjoin
    ADD CONSTRAINT idx_29774_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_recoverykey idx_29777_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_recoverykey
    ADD CONSTRAINT idx_29777_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_session idx_29787_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_session
    ADD CONSTRAINT idx_29787_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_twofactor idx_29798_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_twofactor
    ADD CONSTRAINT idx_29798_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_usedtoken idx_29807_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_usedtoken
    ADD CONSTRAINT idx_29807_primary PRIMARY KEY (id);


--
-- Name: idx_29707_username; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29707_username ON public.a2_objects_apps_accounts_account USING btree (username);


--
-- Name: idx_29729_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29729_id ON public.a2_objects_apps_accounts_auth_ftp USING btree (id);


--
-- Name: idx_29732_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29732_id ON public.a2_objects_apps_accounts_auth_imap USING btree (id);


--
-- Name: idx_29735_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29735_id ON public.a2_objects_apps_accounts_auth_ldap USING btree (id);


--
-- Name: idx_29741_authsource*objectpoly*Apps\\Accounts\\Auth\\Source; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_29741_authsource*objectpoly*Apps\\Accounts\\Auth\\Source" ON public.a2_objects_apps_accounts_auth_manager USING btree (authsource);


--
-- Name: idx_29741_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29741_id ON public.a2_objects_apps_accounts_auth_manager USING btree (id);


--
-- Name: idx_29748_account*object*Apps\\Accounts\\Account*clients; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_29748_account*object*Apps\\Accounts\\Account*clients" ON public.a2_objects_apps_accounts_client USING btree (account);


--
-- Name: idx_29748_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29748_id ON public.a2_objects_apps_accounts_client USING btree (id);


--
-- Name: idx_29748_session*object*Apps\\Accounts\\Session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_29748_session*object*Apps\\Accounts\\Session" ON public.a2_objects_apps_accounts_client USING btree (session);


--
-- Name: idx_29758_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29758_id ON public.a2_objects_apps_accounts_config USING btree (id);


--
-- Name: idx_29762_account*object*Apps\\Accounts\\Account*aliases; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_29762_account*object*Apps\\Accounts\\Account*aliases" ON public.a2_objects_apps_accounts_contactinfo USING btree (account);


--
-- Name: idx_29762_alias; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29762_alias ON public.a2_objects_apps_accounts_contactinfo USING btree (info);


--
-- Name: idx_29762_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29762_id ON public.a2_objects_apps_accounts_contactinfo USING btree (id);


--
-- Name: idx_29762_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_29762_type ON public.a2_objects_apps_accounts_contactinfo USING btree (type);


--
-- Name: idx_29767_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29767_id ON public.a2_objects_apps_accounts_group USING btree (id);


--
-- Name: idx_29767_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29767_name ON public.a2_objects_apps_accounts_group USING btree (name);


--
-- Name: idx_29774_account; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29774_account ON public.a2_objects_apps_accounts_groupjoin USING btree (accounts, groups);


--
-- Name: idx_29774_accounts*object*Apps\\Accounts\\Account*groups; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_29774_accounts*object*Apps\\Accounts\\Account*groups" ON public.a2_objects_apps_accounts_groupjoin USING btree (accounts);


--
-- Name: idx_29774_groups*object*Apps\\Accounts\\Group*accounts; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_29774_groups*object*Apps\\Accounts\\Group*accounts" ON public.a2_objects_apps_accounts_groupjoin USING btree (groups);


--
-- Name: idx_29774_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_29774_id ON public.a2_objects_apps_accounts_groupjoin USING btree (id);


--
-- Name: idx_29777_account*object*Apps\\Accounts\\Account*recoverykeys; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_29777_account*object*Apps\\Accounts\\Account*recoverykeys" ON public.a2_objects_apps_accounts_recoverykey USING btree (account);


--
-- Name: idx_29777_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_29777_id ON public.a2_objects_apps_accounts_recoverykey USING btree (id);


--
-- Name: idx_29787_aid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_29787_aid ON public.a2_objects_apps_accounts_session USING btree (account);


--
-- Name: idx_29787_cid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_29787_cid ON public.a2_objects_apps_accounts_session USING btree (client);


--
-- Name: idx_29798_account*object*Apps\\Accounts\\Account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_29798_account*object*Apps\\Accounts\\Account" ON public.a2_objects_apps_accounts_twofactor USING btree (account);


--
-- Name: idx_29807_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_29807_id ON public.a2_objects_apps_accounts_usedtoken USING btree (id);


--
-- PostgreSQL database dump complete
--

