

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


CREATE TABLE public.a2obj_apps_accounts_actionlog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_accounts_authsource_ftp (
    id character(8) NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_authsource_imap (
    id character(8) NOT NULL,
    protocol smallint NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    secauth boolean NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_authsource_ldap (
    id character(8) NOT NULL,
    hostname character varying(255) NOT NULL,
    secure boolean NOT NULL,
    userprefix character varying(255) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_authsource_source (
    id character(8) NOT NULL,
    enabled smallint NOT NULL,
    description text,
    default_group character(12) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_accounts_config (
    id character(1) NOT NULL,
    version character varying(255) NOT NULL,
    createaccount smallint NOT NULL,
    usernameiscontact boolean NOT NULL,
    requirecontact smallint NOT NULL,
    default_group character(12) DEFAULT NULL::bpchar,
    default_auth character(8) DEFAULT NULL::bpchar,
    date_created double precision NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_entity_account (
    id character(12) NOT NULL,
    username character varying(127) NOT NULL,
    fullname character varying(255) DEFAULT NULL::character varying,
    date_passwordset double precision,
    date_loggedon double precision,
    date_active double precision,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    password text,
    authsource character(8) DEFAULT NULL::bpchar
);



CREATE TABLE public.a2obj_apps_accounts_entity_authentity (
    id character(12) NOT NULL,
    comment text,
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
    max_password_age bigint
);



CREATE TABLE public.a2obj_apps_accounts_entity_group (
    id character(12) NOT NULL,
    name character varying(127) NOT NULL,
    priority smallint NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_entity_groupjoin (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    account character(12) NOT NULL,
    "group" character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_resource_client (
    id character(12) NOT NULL,
    name character varying(255) DEFAULT NULL::character varying,
    authkey text NOT NULL,
    lastaddr character varying(255) NOT NULL,
    useragent text NOT NULL,
    date_created double precision NOT NULL,
    date_active double precision,
    date_loggedon double precision,
    account character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_resource_contact (
    id character(12) NOT NULL,
    type smallint NOT NULL,
    info character varying(127) NOT NULL,
    valid boolean DEFAULT false NOT NULL,
    usefrom boolean,
    public boolean DEFAULT false NOT NULL,
    authkey text,
    date_created double precision NOT NULL,
    account character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_resource_recoverykey (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    date_created double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    account character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_resource_session (
    id character(12) NOT NULL,
    authkey text NOT NULL,
    date_active double precision,
    date_created double precision NOT NULL,
    master_key bytea DEFAULT NULL::bytea,
    master_nonce bytea DEFAULT NULL::bytea,
    master_salt bytea DEFAULT NULL::bytea,
    account character(12) NOT NULL,
    client character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_resource_twofactor (
    id character(12) NOT NULL,
    comment text,
    secret bytea NOT NULL,
    nonce bytea DEFAULT NULL::bytea,
    valid boolean DEFAULT false NOT NULL,
    date_created double precision NOT NULL,
    date_used double precision,
    account character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_resource_usedtoken (
    id character(12) NOT NULL,
    code character(6) NOT NULL,
    date_created double precision NOT NULL,
    twofactor character(12) NOT NULL
);



CREATE TABLE public.a2obj_apps_accounts_resource_whitelist (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    type smallint NOT NULL,
    value character varying(127) NOT NULL
);



ALTER TABLE ONLY public.a2obj_apps_accounts_actionlog
    ADD CONSTRAINT idx_20667_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_ftp
    ADD CONSTRAINT idx_20673_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_imap
    ADD CONSTRAINT idx_20676_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_ldap
    ADD CONSTRAINT idx_20679_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_source
    ADD CONSTRAINT idx_20684_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT idx_20690_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_account
    ADD CONSTRAINT idx_20695_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_authentity
    ADD CONSTRAINT idx_20705_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_group
    ADD CONSTRAINT idx_20710_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_groupjoin
    ADD CONSTRAINT idx_20713_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_client
    ADD CONSTRAINT idx_20716_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_contact
    ADD CONSTRAINT idx_20722_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_recoverykey
    ADD CONSTRAINT idx_20729_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_session
    ADD CONSTRAINT idx_20737_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_twofactor
    ADD CONSTRAINT idx_20745_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_usedtoken
    ADD CONSTRAINT idx_20752_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_whitelist
    ADD CONSTRAINT idx_20755_primary PRIMARY KEY (id);



CREATE INDEX idx_20667_account ON public.a2obj_apps_accounts_actionlog USING btree (account);



CREATE INDEX idx_20684_default_group ON public.a2obj_apps_accounts_authsource_source USING btree (default_group);



CREATE INDEX idx_20690_default_auth ON public.a2obj_apps_accounts_config USING btree (default_auth);



CREATE INDEX idx_20690_default_group ON public.a2obj_apps_accounts_config USING btree (default_group);



CREATE INDEX idx_20695_authsource ON public.a2obj_apps_accounts_entity_account USING btree (authsource);



CREATE INDEX idx_20695_fullname ON public.a2obj_apps_accounts_entity_account USING btree (fullname);



CREATE UNIQUE INDEX idx_20695_username ON public.a2obj_apps_accounts_entity_account USING btree (username);



CREATE UNIQUE INDEX idx_20710_name ON public.a2obj_apps_accounts_entity_group USING btree (name);



CREATE UNIQUE INDEX idx_20713_account_group ON public.a2obj_apps_accounts_entity_groupjoin USING btree (account, "group");



CREATE INDEX idx_20713_accounts ON public.a2obj_apps_accounts_entity_groupjoin USING btree (account);



CREATE INDEX idx_20713_groups ON public.a2obj_apps_accounts_entity_groupjoin USING btree ("group");



CREATE INDEX idx_20716_account ON public.a2obj_apps_accounts_resource_client USING btree (account);



CREATE INDEX idx_20716_date_active_account ON public.a2obj_apps_accounts_resource_client USING btree (date_active, account);



CREATE INDEX idx_20722_account ON public.a2obj_apps_accounts_resource_contact USING btree (account);



CREATE INDEX idx_20722_info ON public.a2obj_apps_accounts_resource_contact USING btree (info);



CREATE UNIQUE INDEX idx_20722_prefer ON public.a2obj_apps_accounts_resource_contact USING btree (usefrom, account);



CREATE UNIQUE INDEX idx_20722_type_info ON public.a2obj_apps_accounts_resource_contact USING btree (type, info);



CREATE INDEX idx_20729_account ON public.a2obj_apps_accounts_resource_recoverykey USING btree (account);



CREATE INDEX idx_20737_account ON public.a2obj_apps_accounts_resource_session USING btree (account);



CREATE UNIQUE INDEX idx_20737_client ON public.a2obj_apps_accounts_resource_session USING btree (client);



CREATE INDEX idx_20737_date_active_account ON public.a2obj_apps_accounts_resource_session USING btree (date_active, account);



CREATE INDEX idx_20745_account ON public.a2obj_apps_accounts_resource_twofactor USING btree (account);



CREATE INDEX idx_20752_date_created ON public.a2obj_apps_accounts_resource_usedtoken USING btree (date_created);



CREATE INDEX idx_20752_twofactor ON public.a2obj_apps_accounts_resource_usedtoken USING btree (twofactor);



CREATE UNIQUE INDEX idx_20755_type ON public.a2obj_apps_accounts_resource_whitelist USING btree (type, value);



ALTER TABLE ONLY public.a2obj_apps_accounts_actionlog
    ADD CONSTRAINT a2obj_apps_accounts_actionlog_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE RESTRICT ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_ftp
    ADD CONSTRAINT a2obj_apps_accounts_authsource_ftp_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_authsource_source(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_imap
    ADD CONSTRAINT a2obj_apps_accounts_authsource_imap_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_authsource_source(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_ldap
    ADD CONSTRAINT a2obj_apps_accounts_authsource_ldap_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_authsource_source(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_source
    ADD CONSTRAINT a2obj_apps_accounts_authsource_source_ibfk_1 FOREIGN KEY (default_group) REFERENCES public.a2obj_apps_accounts_entity_group(id) ON UPDATE RESTRICT ON DELETE SET NULL;



ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT a2obj_apps_accounts_config_ibfk_1 FOREIGN KEY (default_group) REFERENCES public.a2obj_apps_accounts_entity_group(id) ON UPDATE RESTRICT ON DELETE SET NULL;



ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT a2obj_apps_accounts_config_ibfk_2 FOREIGN KEY (default_auth) REFERENCES public.a2obj_apps_accounts_authsource_source(id) ON UPDATE RESTRICT ON DELETE SET NULL;



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_account
    ADD CONSTRAINT a2obj_apps_accounts_entity_account_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_entity_authentity(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_account
    ADD CONSTRAINT a2obj_apps_accounts_entity_account_ibfk_2 FOREIGN KEY (authsource) REFERENCES public.a2obj_apps_accounts_authsource_source(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_group
    ADD CONSTRAINT a2obj_apps_accounts_entity_group_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_entity_authentity(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_groupjoin
    ADD CONSTRAINT a2obj_apps_accounts_entity_groupjoin_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_entity_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_entity_groupjoin
    ADD CONSTRAINT a2obj_apps_accounts_entity_groupjoin_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_entity_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_client
    ADD CONSTRAINT a2obj_apps_accounts_resource_client_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_entity_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_contact
    ADD CONSTRAINT a2obj_apps_accounts_resource_contact_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_entity_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_recoverykey
    ADD CONSTRAINT a2obj_apps_accounts_resource_recoverykey_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_entity_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_session
    ADD CONSTRAINT a2obj_apps_accounts_resource_session_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_entity_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_session
    ADD CONSTRAINT a2obj_apps_accounts_resource_session_ibfk_2 FOREIGN KEY (client) REFERENCES public.a2obj_apps_accounts_resource_client(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_twofactor
    ADD CONSTRAINT a2obj_apps_accounts_resource_twofactor_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_entity_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



ALTER TABLE ONLY public.a2obj_apps_accounts_resource_usedtoken
    ADD CONSTRAINT a2obj_apps_accounts_resource_usedtoken_ibfk_1 FOREIGN KEY (twofactor) REFERENCES public.a2obj_apps_accounts_resource_twofactor(id) ON UPDATE RESTRICT ON DELETE RESTRICT;



