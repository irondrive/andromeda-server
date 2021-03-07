--
-- PostgreSQL database dump
--

-- Dumped from database version 12.6 (Ubuntu 12.6-0ubuntu0.20.10.1)
-- Dumped by pg_dump version 12.6 (Ubuntu 12.6-0ubuntu0.20.10.1)

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
    dates__created double precision DEFAULT '0'::double precision NOT NULL,
    dates__passwordset double precision DEFAULT '0'::double precision NOT NULL,
    dates__loggedon double precision DEFAULT '0'::double precision NOT NULL,
    dates__active double precision DEFAULT '0'::double precision NOT NULL,
    dates__modified double precision,
    session_timeout bigint,
    max_password_age bigint,
    features__admin boolean,
    features__disabled smallint,
    features__forcetf boolean,
    features__allowcrypto boolean,
    features__accountsearch smallint,
    features__groupsearch smallint,
    features__userdelete boolean,
    counters_limits__sessions smallint,
    counters_limits__contacts smallint,
    counters_limits__recoverykeys smallint,
    comment text,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    password text,
    authsource character varying(64) DEFAULT NULL::character varying,
    groups smallint DEFAULT '0'::smallint NOT NULL,
    sessions smallint DEFAULT '0'::smallint NOT NULL,
    contacts smallint DEFAULT '0'::smallint NOT NULL,
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
    name character varying(255) DEFAULT NULL::character varying,
    authkey text NOT NULL,
    lastaddr character varying(255) NOT NULL,
    useragent text NOT NULL,
    dates__active double precision DEFAULT '0'::double precision NOT NULL,
    dates__created double precision DEFAULT '0'::double precision NOT NULL,
    dates__loggedon double precision DEFAULT '0'::double precision NOT NULL,
    account character(12) NOT NULL,
    session character(12) DEFAULT NULL::bpchar
);


--
-- Name: a2_objects_apps_accounts_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_config (
    id character(12) NOT NULL,
    features__createaccount boolean NOT NULL,
    features__usernameiscontact boolean NOT NULL,
    features__requirecontact smallint NOT NULL,
    default_group character(12) DEFAULT NULL::bpchar,
    dates__created double precision NOT NULL
);


--
-- Name: a2_objects_apps_accounts_contact; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_contact (
    id character(12) NOT NULL,
    type smallint NOT NULL,
    info character varying(127) NOT NULL,
    valid boolean DEFAULT false NOT NULL,
    usefrom boolean,
    public boolean DEFAULT false NOT NULL,
    authkey text,
    dates__created double precision NOT NULL,
    account character(12) NOT NULL
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
    dates__created double precision NOT NULL,
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
    dates__created double precision NOT NULL,
    dates__modified double precision,
    features__admin boolean,
    features__disabled boolean,
    features__forcetf boolean,
    features__allowcrypto boolean,
    features__accountsearch smallint,
    features__groupsearch smallint,
    features__userdelete boolean,
    counters_limits__sessions smallint,
    counters_limits__contacts smallint,
    counters_limits__recoverykeys smallint,
    session_timeout bigint,
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
    dates__created double precision DEFAULT '0'::double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    account character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_session; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_session (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    dates__created double precision DEFAULT '0'::double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
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
    nonce bytea DEFAULT NULL::bytea,
    valid boolean DEFAULT false NOT NULL,
    dates__created double precision NOT NULL,
    dates__used double precision,
    account character(12) NOT NULL,
    usedtokens smallint DEFAULT '0'::smallint NOT NULL
);


--
-- Name: a2_objects_apps_accounts_usedtoken; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_apps_accounts_usedtoken (
    id character(12) NOT NULL,
    code character(6) NOT NULL,
    dates__created double precision NOT NULL,
    twofactor character(12) NOT NULL
);


--
-- Name: a2_objects_apps_accounts_contactinfo idx_47458_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_contactinfo
    ADD CONSTRAINT idx_47458_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_account idx_53646_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_account
    ADD CONSTRAINT idx_53646_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_ftp idx_53667_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_ftp
    ADD CONSTRAINT idx_53667_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_imap idx_53670_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_imap
    ADD CONSTRAINT idx_53670_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_ldap idx_53673_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_ldap
    ADD CONSTRAINT idx_53673_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_auth_manager idx_53679_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_auth_manager
    ADD CONSTRAINT idx_53679_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_client idx_53686_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_client
    ADD CONSTRAINT idx_53686_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_config idx_53697_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_config
    ADD CONSTRAINT idx_53697_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_contact idx_53701_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_contact
    ADD CONSTRAINT idx_53701_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_group idx_53709_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_group
    ADD CONSTRAINT idx_53709_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_groupjoin idx_53716_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_groupjoin
    ADD CONSTRAINT idx_53716_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_recoverykey idx_53719_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_recoverykey
    ADD CONSTRAINT idx_53719_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_session idx_53729_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_session
    ADD CONSTRAINT idx_53729_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_twofactor idx_53739_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_twofactor
    ADD CONSTRAINT idx_53739_primary PRIMARY KEY (id);


--
-- Name: a2_objects_apps_accounts_usedtoken idx_53748_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_apps_accounts_usedtoken
    ADD CONSTRAINT idx_53748_primary PRIMARY KEY (id);


--
-- Name: idx_47458_account*object*Apps\\Accounts\\Account*aliases; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_47458_account*object*Apps\\Accounts\\Account*aliases" ON public.a2_objects_apps_accounts_contactinfo USING btree (account);


--
-- Name: idx_47458_alias; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_47458_alias ON public.a2_objects_apps_accounts_contactinfo USING btree (info);


--
-- Name: idx_47458_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_47458_id ON public.a2_objects_apps_accounts_contactinfo USING btree (id);


--
-- Name: idx_47458_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_47458_type ON public.a2_objects_apps_accounts_contactinfo USING btree (type);


--
-- Name: idx_53646_fullname; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_53646_fullname ON public.a2_objects_apps_accounts_account USING btree (fullname);


--
-- Name: idx_53646_username; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53646_username ON public.a2_objects_apps_accounts_account USING btree (username);


--
-- Name: idx_53667_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53667_id ON public.a2_objects_apps_accounts_auth_ftp USING btree (id);


--
-- Name: idx_53670_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53670_id ON public.a2_objects_apps_accounts_auth_imap USING btree (id);


--
-- Name: idx_53673_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53673_id ON public.a2_objects_apps_accounts_auth_ldap USING btree (id);


--
-- Name: idx_53679_authsource*objectpoly*Apps\\Accounts\\Auth\\Source; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_53679_authsource*objectpoly*Apps\\Accounts\\Auth\\Source" ON public.a2_objects_apps_accounts_auth_manager USING btree (authsource);


--
-- Name: idx_53679_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53679_id ON public.a2_objects_apps_accounts_auth_manager USING btree (id);


--
-- Name: idx_53686_account*object*Apps\\Accounts\\Account*clients; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_53686_account*object*Apps\\Accounts\\Account*clients" ON public.a2_objects_apps_accounts_client USING btree (account);


--
-- Name: idx_53686_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53686_id ON public.a2_objects_apps_accounts_client USING btree (id);


--
-- Name: idx_53686_session*object*Apps\\Accounts\\Session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_53686_session*object*Apps\\Accounts\\Session" ON public.a2_objects_apps_accounts_client USING btree (session);


--
-- Name: idx_53697_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53697_id ON public.a2_objects_apps_accounts_config USING btree (id);


--
-- Name: idx_53701_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_53701_account ON public.a2_objects_apps_accounts_contact USING btree (account);


--
-- Name: idx_53701_info; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_53701_info ON public.a2_objects_apps_accounts_contact USING btree (info);


--
-- Name: idx_53701_prefer; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53701_prefer ON public.a2_objects_apps_accounts_contact USING btree (usefrom, account);


--
-- Name: idx_53701_type_2; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53701_type_2 ON public.a2_objects_apps_accounts_contact USING btree (type, info);


--
-- Name: idx_53709_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53709_id ON public.a2_objects_apps_accounts_group USING btree (id);


--
-- Name: idx_53709_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53709_name ON public.a2_objects_apps_accounts_group USING btree (name);


--
-- Name: idx_53716_account; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53716_account ON public.a2_objects_apps_accounts_groupjoin USING btree (accounts, groups);


--
-- Name: idx_53716_accounts*object*Apps\\Accounts\\Account*groups; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_53716_accounts*object*Apps\\Accounts\\Account*groups" ON public.a2_objects_apps_accounts_groupjoin USING btree (accounts);


--
-- Name: idx_53716_groups*object*Apps\\Accounts\\Group*accounts; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_53716_groups*object*Apps\\Accounts\\Group*accounts" ON public.a2_objects_apps_accounts_groupjoin USING btree (groups);


--
-- Name: idx_53716_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_53716_id ON public.a2_objects_apps_accounts_groupjoin USING btree (id);


--
-- Name: idx_53719_account*object*Apps\\Accounts\\Account*recoverykeys; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_53719_account*object*Apps\\Accounts\\Account*recoverykeys" ON public.a2_objects_apps_accounts_recoverykey USING btree (account);


--
-- Name: idx_53719_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_53719_id ON public.a2_objects_apps_accounts_recoverykey USING btree (id);


--
-- Name: idx_53729_aid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_53729_aid ON public.a2_objects_apps_accounts_session USING btree (account);


--
-- Name: idx_53729_cid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_53729_cid ON public.a2_objects_apps_accounts_session USING btree (client);


--
-- Name: idx_53739_account*object*Apps\\Accounts\\Account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX "idx_53739_account*object*Apps\\Accounts\\Account" ON public.a2_objects_apps_accounts_twofactor USING btree (account);


--
-- Name: idx_53748_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_53748_id ON public.a2_objects_apps_accounts_usedtoken USING btree (id);


--
-- PostgreSQL database dump complete
--

