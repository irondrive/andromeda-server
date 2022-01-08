

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
    obj_account character(12) DEFAULT NULL::bpchar,
    obj_sudouser character(12) DEFAULT NULL::bpchar,
    obj_client character(12) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_accounts_account (
    id character(12) NOT NULL,
    username character varying(127) NOT NULL,
    fullname character varying(255) DEFAULT NULL::character varying,
    date_created double precision NOT NULL,
    date_passwordset double precision,
    date_loggedon double precision,
    date_active double precision,
    date_modified double precision,
    session_timeout bigint,
    client_timeout bigint,
    max_password_age bigint,
    admin boolean,
    disabled smallint,
    forcetf boolean,
    allowcrypto boolean,
    accountsearch smallint,
    groupsearch smallint,
    userdelete boolean,
    limit_sessions smallint,
    limit_contacts smallint,
    limit_recoverykeys smallint,
    comment text,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    password text,
    obj_authsource character varying(64) DEFAULT NULL::character varying,
    objs_groups smallint DEFAULT '0'::smallint NOT NULL,
    objs_sessions smallint DEFAULT '0'::smallint NOT NULL,
    objs_contacts smallint DEFAULT '0'::smallint NOT NULL,
    objs_clients smallint DEFAULT '0'::smallint NOT NULL,
    objs_twofactors smallint DEFAULT '0'::smallint NOT NULL,
    objs_recoverykeys smallint DEFAULT '0'::smallint NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_auth_ftp (
    id character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    obj_manager character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_auth_imap (
    id character(12) NOT NULL,
    protocol smallint NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    secauth boolean NOT NULL,
    obj_manager character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_auth_ldap (
    id character(12) NOT NULL,
    hostname character varying(255) NOT NULL,
    secure boolean NOT NULL,
    userprefix character varying(255) NOT NULL,
    obj_manager character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_auth_manager (
    id character(12) NOT NULL,
    enabled smallint NOT NULL,
    obj_authsource character varying(64) NOT NULL,
    description text,
    obj_default_group character(12) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_accounts_client (
    id character(12) NOT NULL,
    name character varying(255) DEFAULT NULL::character varying,
    authkey text NOT NULL,
    lastaddr character varying(255) NOT NULL,
    useragent text NOT NULL,
    date_active double precision DEFAULT '0'::double precision,
    date_created double precision DEFAULT '0'::double precision NOT NULL,
    date_loggedon double precision DEFAULT '0'::double precision NOT NULL,
    obj_account character(12) NOT NULL,
    obj_session character(12) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_accounts_config (
    id character(12) NOT NULL,
    version character varying(255) NOT NULL,
    createaccount smallint NOT NULL,
    usernameiscontact boolean NOT NULL,
    requirecontact smallint NOT NULL,
    obj_default_group character(12) DEFAULT NULL::bpchar,
    obj_default_auth character(12) DEFAULT NULL::bpchar,
    date_created double precision NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_contact (
    id character(12) NOT NULL,
    type smallint NOT NULL,
    info character varying(127) NOT NULL,
    valid boolean DEFAULT false NOT NULL,
    usefrom boolean,
    public boolean DEFAULT false NOT NULL,
    authkey text,
    date_created double precision NOT NULL,
    obj_account character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_group (
    id character(12) NOT NULL,
    name character varying(127) NOT NULL,
    comment text,
    priority smallint NOT NULL,
    date_created double precision NOT NULL,
    date_modified double precision,
    admin boolean,
    disabled boolean,
    forcetf boolean,
    allowcrypto boolean,
    accountsearch smallint,
    groupsearch smallint,
    userdelete boolean,
    limit_sessions smallint,
    limit_contacts smallint,
    limit_recoverykeys smallint,
    session_timeout bigint,
    client_timeout bigint,
    max_password_age bigint,
    objs_accounts bigint DEFAULT '0'::bigint NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_groupjoin (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    objs_accounts character(12) NOT NULL,
    objs_groups character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_recoverykey (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    date_created double precision DEFAULT '0'::double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    obj_account character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_session (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    date_active double precision,
    date_created double precision DEFAULT '0'::double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    obj_account character(12) NOT NULL,
    obj_client character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_twofactor (
    id character(12) NOT NULL,
    comment text,
    secret bytea NOT NULL,
    nonce bytea DEFAULT NULL::bytea,
    valid boolean DEFAULT false NOT NULL,
    date_created double precision NOT NULL,
    date_used double precision,
    obj_account character(12) NOT NULL,
    objs_usedtokens smallint DEFAULT '0'::smallint NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_usedtoken (
    id character(12) NOT NULL,
    code character(6) NOT NULL,
    date_created double precision NOT NULL,
    obj_twofactor character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_whitelist (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    type smallint NOT NULL,
    value character varying(127) NOT NULL
);



ALTER TABLE ONLY public.a2obj_apps_accounts_accesslog
    ADD CONSTRAINT idx_282882_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_account
    ADD CONSTRAINT idx_282888_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_auth_ftp
    ADD CONSTRAINT idx_282905_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_auth_imap
    ADD CONSTRAINT idx_282908_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_auth_ldap
    ADD CONSTRAINT idx_282911_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_auth_manager
    ADD CONSTRAINT idx_282917_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_client
    ADD CONSTRAINT idx_282924_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT idx_282935_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_contact
    ADD CONSTRAINT idx_282940_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_group
    ADD CONSTRAINT idx_282948_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_groupjoin
    ADD CONSTRAINT idx_282955_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_recoverykey
    ADD CONSTRAINT idx_282958_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_session
    ADD CONSTRAINT idx_282968_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_twofactor
    ADD CONSTRAINT idx_282978_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_usedtoken
    ADD CONSTRAINT idx_282987_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_whitelist
    ADD CONSTRAINT idx_282990_primary PRIMARY KEY (id);



CREATE INDEX idx_282882_account ON public.a2obj_apps_accounts_accesslog USING btree (obj_account);



CREATE INDEX idx_282888_authsource ON public.a2obj_apps_accounts_account USING btree (obj_authsource);



CREATE INDEX idx_282888_fullname ON public.a2obj_apps_accounts_account USING btree (fullname);



CREATE UNIQUE INDEX idx_282888_username ON public.a2obj_apps_accounts_account USING btree (username);



CREATE INDEX idx_282917_authsource ON public.a2obj_apps_accounts_auth_manager USING btree (obj_authsource);



CREATE INDEX idx_282924_account ON public.a2obj_apps_accounts_client USING btree (obj_account);



CREATE INDEX idx_282924_date_active_account ON public.a2obj_apps_accounts_client USING btree (date_active, obj_account);



CREATE INDEX idx_282924_session ON public.a2obj_apps_accounts_client USING btree (obj_session);



CREATE INDEX idx_282940_account ON public.a2obj_apps_accounts_contact USING btree (obj_account);



CREATE INDEX idx_282940_info ON public.a2obj_apps_accounts_contact USING btree (info);



CREATE UNIQUE INDEX idx_282940_prefer ON public.a2obj_apps_accounts_contact USING btree (usefrom, obj_account);



CREATE UNIQUE INDEX idx_282940_type_info ON public.a2obj_apps_accounts_contact USING btree (type, info);



CREATE UNIQUE INDEX idx_282948_name ON public.a2obj_apps_accounts_group USING btree (name);



CREATE INDEX idx_282955_accounts ON public.a2obj_apps_accounts_groupjoin USING btree (objs_accounts);



CREATE INDEX idx_282955_groups ON public.a2obj_apps_accounts_groupjoin USING btree (objs_groups);



CREATE UNIQUE INDEX idx_282955_pair ON public.a2obj_apps_accounts_groupjoin USING btree (objs_accounts, objs_groups);



CREATE INDEX idx_282958_account ON public.a2obj_apps_accounts_recoverykey USING btree (obj_account);



CREATE INDEX idx_282968_account ON public.a2obj_apps_accounts_session USING btree (obj_account);



CREATE INDEX idx_282968_client ON public.a2obj_apps_accounts_session USING btree (obj_client);



CREATE INDEX idx_282968_date_active_account ON public.a2obj_apps_accounts_session USING btree (date_active, obj_account);



CREATE INDEX idx_282978_account ON public.a2obj_apps_accounts_twofactor USING btree (obj_account);



CREATE INDEX idx_282987_date_created ON public.a2obj_apps_accounts_usedtoken USING btree (date_created);



CREATE INDEX idx_282987_twofactor ON public.a2obj_apps_accounts_usedtoken USING btree (obj_twofactor);



CREATE UNIQUE INDEX idx_282990_type ON public.a2obj_apps_accounts_whitelist USING btree (type, value);



