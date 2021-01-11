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
-- Name: a2_objects_apps_accounts_account; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_account (
    id character(16) NOT NULL,
    username character varying(255) NOT NULL,
    fullname character varying(255),
    unlockcode character(16),
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
    master_key bytea,
    master_nonce bytea,
    master_salt bytea,
    password text,
    authsource character varying(64),
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
    id character(16) NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    manager character(16) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_auth_imap; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_auth_imap (
    id character(16) NOT NULL,
    protocol smallint NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    secauth boolean NOT NULL,
    manager character(16) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_auth_ldap; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_auth_ldap (
    id character(16) NOT NULL,
    hostname character varying(255) NOT NULL,
    secure boolean NOT NULL,
    userprefix character varying(255) NOT NULL,
    manager character(16) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_auth_manager; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_auth_manager (
    id character(16) NOT NULL,
    authsource character varying(64) NOT NULL,
    description character varying(255) NOT NULL,
    default_group character(16)
);


--
-- Name: a2_objects_apps_accounts_client; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_client (
    id character(16) NOT NULL,
    authkey text NOT NULL,
    lastaddr character varying(255) NOT NULL,
    useragent text NOT NULL,
    dates__active bigint DEFAULT '0'::bigint NOT NULL,
    dates__created bigint DEFAULT '0'::bigint NOT NULL,
    dates__loggedon bigint DEFAULT '0'::bigint NOT NULL,
    account character(16) NOT NULL,
    session character(16)
);


--
-- Name: a2_objects_apps_accounts_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_config (
    id character(16) NOT NULL,
    features__createaccount boolean NOT NULL,
    features__emailasusername boolean NOT NULL,
    features__requirecontact smallint NOT NULL,
    default_group character(16),
    dates__created bigint NOT NULL
);


--
-- Name: a2_objects_apps_accounts_contactinfo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_contactinfo (
    id character(16) NOT NULL,
    type smallint NOT NULL,
    info character varying(255) NOT NULL,
    valid boolean DEFAULT true NOT NULL,
    unlockcode character(16),
    dates__created bigint NOT NULL,
    account character(16) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_group (
    id character(16) NOT NULL,
    name character varying(255) NOT NULL,
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
    id character(16) NOT NULL,
    dates__created bigint NOT NULL,
    accounts character(16) NOT NULL,
    groups character(16) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_recoverykey; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_recoverykey (
    id character(16) NOT NULL,
    authkey text NOT NULL,
    dates__created bigint DEFAULT '0'::bigint NOT NULL,
    master_key bytea,
    master_nonce bytea,
    master_salt bytea,
    account character(16) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_session; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_session (
    id character(16) NOT NULL,
    authkey text NOT NULL,
    dates__active bigint DEFAULT '0'::bigint NOT NULL,
    dates__created bigint DEFAULT '0'::bigint NOT NULL,
    master_key bytea,
    master_nonce bytea,
    master_salt bytea,
    account character(16) NOT NULL,
    client character(16) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_twofactor; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_twofactor (
    id character(16) NOT NULL,
    comment text,
    secret bytea NOT NULL,
    nonce bytea,
    valid boolean DEFAULT false NOT NULL,
    dates__created bigint NOT NULL,
    account character(16) NOT NULL,
    usedtokens smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: a2_objects_apps_accounts_usedtoken; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_usedtoken (
    id character(16) NOT NULL,
    code character(16) NOT NULL,
    dates__created bigint NOT NULL,
    twofactor character(16) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_account idx_32197_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_account
    ADD CONSTRAINT idx_32197_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_ftp idx_32213_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_ftp
    ADD CONSTRAINT idx_32213_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_imap idx_32216_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_imap
    ADD CONSTRAINT idx_32216_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_ldap idx_32219_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_ldap
    ADD CONSTRAINT idx_32219_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_manager idx_32225_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_manager
    ADD CONSTRAINT idx_32225_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_client idx_32228_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_client
    ADD CONSTRAINT idx_32228_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_config idx_32237_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_config
    ADD CONSTRAINT idx_32237_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_contactinfo idx_32240_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_contactinfo
    ADD CONSTRAINT idx_32240_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_group idx_32244_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_group
    ADD CONSTRAINT idx_32244_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_groupjoin idx_32251_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_groupjoin
    ADD CONSTRAINT idx_32251_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_recoverykey idx_32254_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_recoverykey
    ADD CONSTRAINT idx_32254_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_session idx_32261_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_session
    ADD CONSTRAINT idx_32261_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_twofactor idx_32269_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_twofactor
    ADD CONSTRAINT idx_32269_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_usedtoken idx_32277_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_usedtoken
    ADD CONSTRAINT idx_32277_primary PRIMARY KEY (id);


--
-- Name: idx_32197_username; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32197_username ON public.a2_objects_apps_accounts_account USING btree (username);


--
-- Name: idx_32213_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32213_id ON public.a2_objects_apps_accounts_auth_ftp USING btree (id);


--
-- Name: idx_32216_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32216_id ON public.a2_objects_apps_accounts_auth_imap USING btree (id);


--
-- Name: idx_32219_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32219_id ON public.a2_objects_apps_accounts_auth_ldap USING btree (id);


--
-- Name: idx_32225_authsource*objectpoly*Apps\\Accounts\\Auth\\Source; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_32225_authsource*objectpoly*Apps\\Accounts\\Auth\\Source" ON public.a2_objects_apps_accounts_auth_manager USING btree (authsource);


--
-- Name: idx_32225_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32225_id ON public.a2_objects_apps_accounts_auth_manager USING btree (id);


--
-- Name: idx_32228_account*object*Apps\\Accounts\\Account*clients; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_32228_account*object*Apps\\Accounts\\Account*clients" ON public.a2_objects_apps_accounts_client USING btree (account);


--
-- Name: idx_32228_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32228_id ON public.a2_objects_apps_accounts_client USING btree (id);


--
-- Name: idx_32228_session*object*Apps\\Accounts\\Session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_32228_session*object*Apps\\Accounts\\Session" ON public.a2_objects_apps_accounts_client USING btree (session);


--
-- Name: idx_32237_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32237_id ON public.a2_objects_apps_accounts_config USING btree (id);


--
-- Name: idx_32240_account*object*Apps\\Accounts\\Account*aliases; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_32240_account*object*Apps\\Accounts\\Account*aliases" ON public.a2_objects_apps_accounts_contactinfo USING btree (account);


--
-- Name: idx_32240_alias; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32240_alias ON public.a2_objects_apps_accounts_contactinfo USING btree (info);


--
-- Name: idx_32240_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32240_id ON public.a2_objects_apps_accounts_contactinfo USING btree (id);


--
-- Name: idx_32240_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32240_type ON public.a2_objects_apps_accounts_contactinfo USING btree (type);


--
-- Name: idx_32244_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32244_id ON public.a2_objects_apps_accounts_group USING btree (id);


--
-- Name: idx_32244_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32244_name ON public.a2_objects_apps_accounts_group USING btree (name);


--
-- Name: idx_32251_accounts*object*Apps\\Accounts\\Account*groups; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_32251_accounts*object*Apps\\Accounts\\Account*groups" ON public.a2_objects_apps_accounts_groupjoin USING btree (accounts);


--
-- Name: idx_32251_groups*object*Apps\\Accounts\\Group*accounts; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_32251_groups*object*Apps\\Accounts\\Group*accounts" ON public.a2_objects_apps_accounts_groupjoin USING btree (groups);


--
-- Name: idx_32251_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32251_id ON public.a2_objects_apps_accounts_groupjoin USING btree (id);


--
-- Name: idx_32254_account*object*Apps\\Accounts\\Account*recoverykeys; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_32254_account*object*Apps\\Accounts\\Account*recoverykeys" ON public.a2_objects_apps_accounts_recoverykey USING btree (account);


--
-- Name: idx_32254_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32254_id ON public.a2_objects_apps_accounts_recoverykey USING btree (id);


--
-- Name: idx_32261_aid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32261_aid ON public.a2_objects_apps_accounts_session USING btree (account);


--
-- Name: idx_32261_cid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32261_cid ON public.a2_objects_apps_accounts_session USING btree (client);


--
-- Name: idx_32269_account*object*Apps\\Accounts\\Account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_32269_account*object*Apps\\Accounts\\Account" ON public.a2_objects_apps_accounts_twofactor USING btree (account);


--
-- Name: idx_32277_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32277_id ON public.a2_objects_apps_accounts_usedtoken USING btree (id);


--
-- PostgreSQL database dump complete
--

