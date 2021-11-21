

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


CREATE TABLE public.a2obj_apps_accounts_accesslog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar
);



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



CREATE TABLE public.a2obj_apps_accounts_auth_ftp (
    id character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    manager character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_auth_imap (
    id character(12) NOT NULL,
    protocol smallint NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    secauth boolean NOT NULL,
    manager character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_auth_ldap (
    id character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    secure boolean NOT NULL,
    userprefix character varying(255) NOT NULL,
    manager character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_auth_manager (
    id character(12) NOT NULL,
    enabled smallint NOT NULL,
    authsource character varying(64) NOT NULL,
    description text,
    default_group character(12) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_accounts_client (
    id character(12) NOT NULL,
    name character varying(255) DEFAULT NULL::character varying,
    authkey text NOT NULL,
    lastaddr character varying(255) NOT NULL,
    useragent text NOT NULL,
    dates__active double precision DEFAULT '0'::double precision,
    dates__created double precision DEFAULT '0'::double precision NOT NULL,
    dates__loggedon double precision DEFAULT '0'::double precision NOT NULL,
    account character(12) NOT NULL,
    session character(12) DEFAULT NULL::bpchar
);



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



CREATE TABLE public.a2obj_apps_accounts_groupjoin (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    accounts character(12) NOT NULL,
    groups character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_recoverykey (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    dates__created double precision DEFAULT '0'::double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    account character(12) NOT NULL
);



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



CREATE TABLE public.a2obj_apps_accounts_usedtoken (
    id character(12) NOT NULL,
    code character(6) NOT NULL,
    dates__created double precision NOT NULL,
    twofactor character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_whitelist (
    id character(12) NOT NULL,
    dates__created double precision NOT NULL,
    type smallint NOT NULL,
    value character varying(127) NOT NULL
);



ALTER TABLE ONLY public.a2obj_apps_accounts_accesslog
    ADD CONSTRAINT idx_212885_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_account
    ADD CONSTRAINT idx_212891_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_auth_ftp
    ADD CONSTRAINT idx_212908_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_auth_imap
    ADD CONSTRAINT idx_212911_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_auth_ldap
    ADD CONSTRAINT idx_212914_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_auth_manager
    ADD CONSTRAINT idx_212920_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_client
    ADD CONSTRAINT idx_212927_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT idx_212938_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_contact
    ADD CONSTRAINT idx_212943_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_group
    ADD CONSTRAINT idx_212951_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_groupjoin
    ADD CONSTRAINT idx_212958_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_recoverykey
    ADD CONSTRAINT idx_212961_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_session
    ADD CONSTRAINT idx_212971_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_twofactor
    ADD CONSTRAINT idx_212981_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_usedtoken
    ADD CONSTRAINT idx_212990_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_whitelist
    ADD CONSTRAINT idx_212993_primary PRIMARY KEY (id);



CREATE INDEX idx_212885_account ON public.a2obj_apps_accounts_accesslog USING btree (account);



CREATE INDEX idx_212891_authsource ON public.a2obj_apps_accounts_account USING btree (authsource);



CREATE INDEX idx_212891_fullname ON public.a2obj_apps_accounts_account USING btree (fullname);



CREATE UNIQUE INDEX idx_212891_username ON public.a2obj_apps_accounts_account USING btree (username);



CREATE INDEX idx_212920_authsource ON public.a2obj_apps_accounts_auth_manager USING btree (authsource);



CREATE INDEX idx_212927_account ON public.a2obj_apps_accounts_client USING btree (account);



CREATE INDEX idx_212927_dates__active_account ON public.a2obj_apps_accounts_client USING btree (dates__active, account);



CREATE INDEX idx_212927_session ON public.a2obj_apps_accounts_client USING btree (session);



CREATE INDEX idx_212943_account ON public.a2obj_apps_accounts_contact USING btree (account);



CREATE INDEX idx_212943_info ON public.a2obj_apps_accounts_contact USING btree (info);



CREATE UNIQUE INDEX idx_212943_prefer ON public.a2obj_apps_accounts_contact USING btree (usefrom, account);



CREATE UNIQUE INDEX idx_212943_type_info ON public.a2obj_apps_accounts_contact USING btree (type, info);



CREATE UNIQUE INDEX idx_212951_name ON public.a2obj_apps_accounts_group USING btree (name);



CREATE INDEX idx_212958_accounts ON public.a2obj_apps_accounts_groupjoin USING btree (accounts);



CREATE INDEX idx_212958_groups ON public.a2obj_apps_accounts_groupjoin USING btree (groups);



CREATE UNIQUE INDEX idx_212958_pair ON public.a2obj_apps_accounts_groupjoin USING btree (accounts, groups);



CREATE INDEX idx_212961_account ON public.a2obj_apps_accounts_recoverykey USING btree (account);



CREATE INDEX idx_212971_aid ON public.a2obj_apps_accounts_session USING btree (account);



CREATE INDEX idx_212971_cid ON public.a2obj_apps_accounts_session USING btree (client);



CREATE INDEX idx_212971_dates__active_account ON public.a2obj_apps_accounts_session USING btree (dates__active, account);



CREATE INDEX idx_212981_account ON public.a2obj_apps_accounts_twofactor USING btree (account);



CREATE INDEX idx_212990_dates__created ON public.a2obj_apps_accounts_usedtoken USING btree (dates__created);



CREATE INDEX idx_212990_twofactor ON public.a2obj_apps_accounts_usedtoken USING btree (twofactor);



CREATE UNIQUE INDEX idx_212993_type ON public.a2obj_apps_accounts_whitelist USING btree (type, value);



