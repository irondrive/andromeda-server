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
-- Name: a2obj_apps_accounts_accesslog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_accesslog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar
);


--
-- Name: a2obj_apps_accounts_account; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_account (
    id character(12) NOT NULL,
    username character varying(127) NOT NULL,
    fullname character varying(255) DEFAULT NULL::character varying,
    dates__created double precision NOT NULL,
    dates__passwordset double precision,
    dates__loggedon double precision,
    dates__active double precision,
    dates__modified double precision,
    session_timeout bigint,
    client_timeout bigint,
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
-- Name: a2obj_apps_accounts_auth_ftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_auth_ftp (
    id character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    manager character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_auth_imap; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_auth_imap (
    id character(12) NOT NULL,
    protocol smallint NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    secauth boolean NOT NULL,
    manager character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_auth_ldap; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_auth_ldap (
    id character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    secure boolean NOT NULL,
    userprefix character varying(255) NOT NULL,
    manager character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_auth_manager; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_auth_manager (
    id character(12) NOT NULL,
    enabled smallint NOT NULL,
    authsource character varying(64) NOT NULL,
    description text,
    default_group character(12) DEFAULT NULL::bpchar
);


--
-- Name: a2obj_apps_accounts_client; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_client (
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
-- Name: a2obj_apps_accounts_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_config (
    id character(12) NOT NULL,
    version character varying(255) NOT NULL,
    features__createaccount smallint NOT NULL,
    features__usernameiscontact boolean NOT NULL,
    features__requirecontact smallint NOT NULL,
    default_group character(12) DEFAULT NULL::bpchar,
    default_auth character(12) DEFAULT NULL::bpchar,
    dates__created double precision NOT NULL
);


--
-- Name: a2obj_apps_accounts_contact; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_contact (
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
-- Name: a2obj_apps_accounts_group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_group (
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
    client_timeout bigint,
    max_password_age bigint,
    accounts bigint DEFAULT '0'::bigint NOT NULL
);


--
-- Name: a2obj_apps_accounts_groupjoin; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_groupjoin (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    accounts character(12) NOT NULL,
    groups character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_recoverykey; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_recoverykey (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    dates__created double precision DEFAULT '0'::double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    account character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_session; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_session (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    dates__active double precision,
    dates__created double precision DEFAULT '0'::double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    account character(12) NOT NULL,
    client character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_twofactor; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_twofactor (
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
-- Name: a2obj_apps_accounts_usedtoken; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_usedtoken (
    id character(12) NOT NULL,
    code character(6) NOT NULL,
    dates__created double precision NOT NULL,
    twofactor character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_whitelist; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_whitelist (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    type smallint NOT NULL,
    value character varying(127) NOT NULL
);


--
-- Name: a2obj_apps_accounts_accesslog idx_130441_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_accesslog
    ADD CONSTRAINT idx_130441_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_account idx_130447_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_account
    ADD CONSTRAINT idx_130447_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_auth_ftp idx_130464_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_auth_ftp
    ADD CONSTRAINT idx_130464_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_auth_imap idx_130467_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_auth_imap
    ADD CONSTRAINT idx_130467_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_auth_ldap idx_130470_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_auth_ldap
    ADD CONSTRAINT idx_130470_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_auth_manager idx_130476_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_auth_manager
    ADD CONSTRAINT idx_130476_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_client idx_130483_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_client
    ADD CONSTRAINT idx_130483_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_config idx_130494_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT idx_130494_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_contact idx_130499_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_contact
    ADD CONSTRAINT idx_130499_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_group idx_130507_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_group
    ADD CONSTRAINT idx_130507_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_groupjoin idx_130514_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_groupjoin
    ADD CONSTRAINT idx_130514_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_recoverykey idx_130517_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_recoverykey
    ADD CONSTRAINT idx_130517_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_session idx_130527_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_session
    ADD CONSTRAINT idx_130527_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_twofactor idx_130537_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_twofactor
    ADD CONSTRAINT idx_130537_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_usedtoken idx_130546_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_usedtoken
    ADD CONSTRAINT idx_130546_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_whitelist idx_130549_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_whitelist
    ADD CONSTRAINT idx_130549_primary PRIMARY KEY (id);


--
-- Name: idx_130441_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130441_account ON public.a2obj_apps_accounts_accesslog USING btree (account);


--
-- Name: idx_130447_authsource; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130447_authsource ON public.a2obj_apps_accounts_account USING btree (authsource);


--
-- Name: idx_130447_fullname; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130447_fullname ON public.a2obj_apps_accounts_account USING btree (fullname);


--
-- Name: idx_130447_username; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_130447_username ON public.a2obj_apps_accounts_account USING btree (username);


--
-- Name: idx_130476_authsource; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130476_authsource ON public.a2obj_apps_accounts_auth_manager USING btree (authsource);


--
-- Name: idx_130483_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130483_account ON public.a2obj_apps_accounts_client USING btree (account);


--
-- Name: idx_130483_dates__active_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130483_dates__active_account ON public.a2obj_apps_accounts_client USING btree (dates__active, account);


--
-- Name: idx_130483_session; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130483_session ON public.a2obj_apps_accounts_client USING btree (session);


--
-- Name: idx_130499_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130499_account ON public.a2obj_apps_accounts_contact USING btree (account);


--
-- Name: idx_130499_info; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130499_info ON public.a2obj_apps_accounts_contact USING btree (info);


--
-- Name: idx_130499_prefer; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_130499_prefer ON public.a2obj_apps_accounts_contact USING btree (usefrom, account);


--
-- Name: idx_130499_type_info; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_130499_type_info ON public.a2obj_apps_accounts_contact USING btree (type, info);


--
-- Name: idx_130507_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_130507_name ON public.a2obj_apps_accounts_group USING btree (name);


--
-- Name: idx_130514_accounts; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130514_accounts ON public.a2obj_apps_accounts_groupjoin USING btree (accounts);


--
-- Name: idx_130514_groups; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130514_groups ON public.a2obj_apps_accounts_groupjoin USING btree (groups);


--
-- Name: idx_130514_pair; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_130514_pair ON public.a2obj_apps_accounts_groupjoin USING btree (accounts, groups);


--
-- Name: idx_130517_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130517_account ON public.a2obj_apps_accounts_recoverykey USING btree (account);


--
-- Name: idx_130527_aid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130527_aid ON public.a2obj_apps_accounts_session USING btree (account);


--
-- Name: idx_130527_cid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130527_cid ON public.a2obj_apps_accounts_session USING btree (client);


--
-- Name: idx_130527_dates__active_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130527_dates__active_account ON public.a2obj_apps_accounts_session USING btree (dates__active, account);


--
-- Name: idx_130537_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130537_account ON public.a2obj_apps_accounts_twofactor USING btree (account);


--
-- Name: idx_130546_dates__created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130546_dates__created ON public.a2obj_apps_accounts_usedtoken USING btree (dates__created);


--
-- Name: idx_130546_twofactor; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_130546_twofactor ON public.a2obj_apps_accounts_usedtoken USING btree (twofactor);


--
-- Name: idx_130549_type; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_130549_type ON public.a2obj_apps_accounts_whitelist USING btree (type, value);


--
-- PostgreSQL database dump complete
--

