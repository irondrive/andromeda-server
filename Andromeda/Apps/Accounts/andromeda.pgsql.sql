--
-- PostgreSQL database dump
--


-- Dumped from database version 17.6 (Ubuntu 17.6-0ubuntu0.25.04.1)
-- Dumped by pg_dump version 17.6 (Ubuntu 17.6-0ubuntu0.25.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
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
-- Name: a2obj_apps_accounts_account; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_account (
    id character(12) NOT NULL,
    username character varying(127) NOT NULL,
    fullname character varying(255) DEFAULT NULL::character varying,
    date_passwordset double precision,
    date_loggedon double precision,
    date_active double precision,
    ssenc_key bytea DEFAULT NULL::bytea,
    ssenc_nonce bytea DEFAULT NULL::bytea,
    passkey_salt bytea DEFAULT NULL::bytea,
    authkey bytea DEFAULT NULL::bytea,
    authsource character(8) DEFAULT NULL::bpchar,
    e2ee_master bytea DEFAULT NULL::bytea,
    e2ee_private bytea DEFAULT NULL::bytea,
    e2ee_public bytea DEFAULT NULL::bytea
);


--
-- Name: a2obj_apps_accounts_actionlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_actionlog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar
);


--
-- Name: a2obj_apps_accounts_authsource_external; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_authsource_external (
    id character(8) NOT NULL,
    enabled smallint NOT NULL,
    description text,
    date_created double precision,
    default_group character(12) DEFAULT NULL::bpchar
);


--
-- Name: a2obj_apps_accounts_authsource_ftp; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_authsource_ftp (
    id character(8) NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL
);


--
-- Name: a2obj_apps_accounts_authsource_imap; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_authsource_imap (
    id character(8) NOT NULL,
    protocol smallint NOT NULL,
    hostname character varying(255) NOT NULL,
    port smallint,
    implssl boolean NOT NULL,
    anycert boolean NOT NULL,
    secauth boolean NOT NULL
);


--
-- Name: a2obj_apps_accounts_authsource_ldap; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_authsource_ldap (
    id character(8) NOT NULL,
    hostname character varying(255) NOT NULL,
    secure boolean NOT NULL,
    userprefix character varying(255) NOT NULL
);


--
-- Name: a2obj_apps_accounts_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_config (
    id character(1) NOT NULL,
    version character varying(255) NOT NULL,
    pepper bytea NOT NULL,
    createaccount smallint NOT NULL,
    usernameiscontact boolean NOT NULL,
    requirecontact smallint NOT NULL,
    default_group character(12) DEFAULT NULL::bpchar,
    default_auth character(8) DEFAULT NULL::bpchar,
    date_created double precision NOT NULL
);


--
-- Name: a2obj_apps_accounts_group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_group (
    id character(12) NOT NULL,
    name character varying(127) NOT NULL,
    priority smallint NOT NULL
);


--
-- Name: a2obj_apps_accounts_groupjoin; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_groupjoin (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    account character(12) NOT NULL,
    "group" character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_policybase; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_policybase (
    id character(12) NOT NULL,
    comment text,
    date_created double precision NOT NULL,
    date_pmodified double precision,
    admin boolean,
    disabled boolean,
    forcetf boolean,
    allowcrypto boolean,
    accountsearch smallint,
    groupsearch smallint,
    userdelete boolean,
    limit_clients smallint,
    limit_contacts smallint,
    limit_recoverykeys smallint,
    session_timeout bigint,
    client_timeout bigint,
    max_password_age bigint
);


--
-- Name: a2obj_apps_accounts_resource_client; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_resource_client (
    id character(12) NOT NULL,
    name character varying(255) DEFAULT NULL::character varying,
    authkey bytea NOT NULL,
    lastaddr character varying(255) NOT NULL,
    useragent text NOT NULL,
    date_created double precision NOT NULL,
    date_active double precision,
    date_loggedon double precision,
    account character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_resource_contact; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_resource_contact (
    id character(12) NOT NULL,
    type smallint NOT NULL,
    address character varying(127) NOT NULL,
    isfrom boolean,
    public boolean DEFAULT false NOT NULL,
    authkey bytea DEFAULT NULL::bytea,
    date_created double precision NOT NULL,
    account character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_resource_recoverykey; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_resource_recoverykey (
    id character(12) NOT NULL,
    authkey bytea NOT NULL,
    date_created double precision NOT NULL,
    ssenc_key bytea DEFAULT NULL::bytea,
    ssenc_nonce bytea DEFAULT NULL::bytea,
    account character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_resource_registerallow; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_resource_registerallow (
    id character(12) NOT NULL,
    date_created double precision NOT NULL,
    type smallint NOT NULL,
    value character varying(127) NOT NULL
);


--
-- Name: a2obj_apps_accounts_resource_session; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_resource_session (
    id character(12) NOT NULL,
    authkey bytea NOT NULL,
    date_active double precision,
    date_created double precision NOT NULL,
    ssenc_key bytea DEFAULT NULL::bytea,
    ssenc_nonce bytea DEFAULT NULL::bytea,
    account character(12) NOT NULL,
    client character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_resource_twofactor; Type: TABLE; Schema: public; Owner: -
--

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


--
-- Name: a2obj_apps_accounts_resource_usedtoken; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_accounts_resource_usedtoken (
    id character(12) NOT NULL,
    code character(6) NOT NULL,
    date_created double precision NOT NULL,
    twofactor character(12) NOT NULL
);


--
-- Name: a2obj_apps_accounts_account idx_51507_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_account
    ADD CONSTRAINT idx_51507_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_actionlog idx_51521_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_actionlog
    ADD CONSTRAINT idx_51521_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_authsource_external idx_51527_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_external
    ADD CONSTRAINT idx_51527_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_authsource_ftp idx_51533_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_ftp
    ADD CONSTRAINT idx_51533_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_authsource_imap idx_51536_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_imap
    ADD CONSTRAINT idx_51536_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_authsource_ldap idx_51539_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_ldap
    ADD CONSTRAINT idx_51539_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_config idx_51544_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT idx_51544_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_group idx_51551_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_group
    ADD CONSTRAINT idx_51551_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_groupjoin idx_51554_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_groupjoin
    ADD CONSTRAINT idx_51554_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_policybase idx_51557_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_policybase
    ADD CONSTRAINT idx_51557_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_resource_client idx_51562_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_client
    ADD CONSTRAINT idx_51562_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_resource_contact idx_51568_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_contact
    ADD CONSTRAINT idx_51568_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_resource_recoverykey idx_51575_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_recoverykey
    ADD CONSTRAINT idx_51575_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_resource_registerallow idx_51582_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_registerallow
    ADD CONSTRAINT idx_51582_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_resource_session idx_51585_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_session
    ADD CONSTRAINT idx_51585_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_resource_twofactor idx_51592_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_twofactor
    ADD CONSTRAINT idx_51592_primary PRIMARY KEY (id);


--
-- Name: a2obj_apps_accounts_resource_usedtoken idx_51599_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_usedtoken
    ADD CONSTRAINT idx_51599_primary PRIMARY KEY (id);


--
-- Name: idx_51507_authsource; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51507_authsource ON public.a2obj_apps_accounts_account USING btree (authsource);


--
-- Name: idx_51507_fullname; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51507_fullname ON public.a2obj_apps_accounts_account USING btree (fullname);


--
-- Name: idx_51507_username; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_51507_username ON public.a2obj_apps_accounts_account USING btree (username);


--
-- Name: idx_51521_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51521_account ON public.a2obj_apps_accounts_actionlog USING btree (account);


--
-- Name: idx_51527_default_group; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51527_default_group ON public.a2obj_apps_accounts_authsource_external USING btree (default_group);


--
-- Name: idx_51544_default_auth; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51544_default_auth ON public.a2obj_apps_accounts_config USING btree (default_auth);


--
-- Name: idx_51544_default_group; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51544_default_group ON public.a2obj_apps_accounts_config USING btree (default_group);


--
-- Name: idx_51551_name; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_51551_name ON public.a2obj_apps_accounts_group USING btree (name);


--
-- Name: idx_51554_account_group; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_51554_account_group ON public.a2obj_apps_accounts_groupjoin USING btree (account, "group");


--
-- Name: idx_51554_accounts; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51554_accounts ON public.a2obj_apps_accounts_groupjoin USING btree (account);


--
-- Name: idx_51554_groups; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51554_groups ON public.a2obj_apps_accounts_groupjoin USING btree ("group");


--
-- Name: idx_51562_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51562_account ON public.a2obj_apps_accounts_resource_client USING btree (account);


--
-- Name: idx_51562_date_active_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51562_date_active_account ON public.a2obj_apps_accounts_resource_client USING btree (date_active, account);


--
-- Name: idx_51568_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51568_account ON public.a2obj_apps_accounts_resource_contact USING btree (account);


--
-- Name: idx_51568_account_type_isfrom; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_51568_account_type_isfrom ON public.a2obj_apps_accounts_resource_contact USING btree (isfrom, account, type);


--
-- Name: idx_51568_address; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51568_address ON public.a2obj_apps_accounts_resource_contact USING btree (address);


--
-- Name: idx_51568_type_address; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_51568_type_address ON public.a2obj_apps_accounts_resource_contact USING btree (type, address);


--
-- Name: idx_51575_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51575_account ON public.a2obj_apps_accounts_resource_recoverykey USING btree (account);


--
-- Name: idx_51582_type_value; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_51582_type_value ON public.a2obj_apps_accounts_resource_registerallow USING btree (type, value);


--
-- Name: idx_51585_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51585_account ON public.a2obj_apps_accounts_resource_session USING btree (account);


--
-- Name: idx_51585_client; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_51585_client ON public.a2obj_apps_accounts_resource_session USING btree (client);


--
-- Name: idx_51585_date_active_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51585_date_active_account ON public.a2obj_apps_accounts_resource_session USING btree (date_active, account);


--
-- Name: idx_51592_account; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51592_account ON public.a2obj_apps_accounts_resource_twofactor USING btree (account);


--
-- Name: idx_51599_date_created; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51599_date_created ON public.a2obj_apps_accounts_resource_usedtoken USING btree (date_created);


--
-- Name: idx_51599_twofactor; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_51599_twofactor ON public.a2obj_apps_accounts_resource_usedtoken USING btree (twofactor);


--
-- Name: a2obj_apps_accounts_account a2obj_apps_accounts_account_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_account
    ADD CONSTRAINT a2obj_apps_accounts_account_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_policybase(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_accounts_account a2obj_apps_accounts_account_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_account
    ADD CONSTRAINT a2obj_apps_accounts_account_ibfk_2 FOREIGN KEY (authsource) REFERENCES public.a2obj_apps_accounts_authsource_external(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_actionlog a2obj_apps_accounts_actionlog_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_actionlog
    ADD CONSTRAINT a2obj_apps_accounts_actionlog_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_accounts_authsource_external a2obj_apps_accounts_authsource_external_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_external
    ADD CONSTRAINT a2obj_apps_accounts_authsource_external_ibfk_1 FOREIGN KEY (default_group) REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: a2obj_apps_accounts_authsource_ftp a2obj_apps_accounts_authsource_ftp_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_ftp
    ADD CONSTRAINT a2obj_apps_accounts_authsource_ftp_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_authsource_external(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_accounts_authsource_imap a2obj_apps_accounts_authsource_imap_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_imap
    ADD CONSTRAINT a2obj_apps_accounts_authsource_imap_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_authsource_external(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_accounts_authsource_ldap a2obj_apps_accounts_authsource_ldap_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_authsource_ldap
    ADD CONSTRAINT a2obj_apps_accounts_authsource_ldap_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_authsource_external(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_accounts_config a2obj_apps_accounts_config_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT a2obj_apps_accounts_config_ibfk_1 FOREIGN KEY (default_group) REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: a2obj_apps_accounts_config a2obj_apps_accounts_config_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_config
    ADD CONSTRAINT a2obj_apps_accounts_config_ibfk_2 FOREIGN KEY (default_auth) REFERENCES public.a2obj_apps_accounts_authsource_external(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: a2obj_apps_accounts_group a2obj_apps_accounts_group_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_group
    ADD CONSTRAINT a2obj_apps_accounts_group_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_apps_accounts_policybase(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_apps_accounts_groupjoin a2obj_apps_accounts_groupjoin_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_groupjoin
    ADD CONSTRAINT a2obj_apps_accounts_groupjoin_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_groupjoin a2obj_apps_accounts_groupjoin_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_groupjoin
    ADD CONSTRAINT a2obj_apps_accounts_groupjoin_ibfk_2 FOREIGN KEY ("group") REFERENCES public.a2obj_apps_accounts_group(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_resource_client a2obj_apps_accounts_resource_client_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_client
    ADD CONSTRAINT a2obj_apps_accounts_resource_client_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_resource_contact a2obj_apps_accounts_resource_contact_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_contact
    ADD CONSTRAINT a2obj_apps_accounts_resource_contact_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_resource_recoverykey a2obj_apps_accounts_resource_recoverykey_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_recoverykey
    ADD CONSTRAINT a2obj_apps_accounts_resource_recoverykey_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_resource_session a2obj_apps_accounts_resource_session_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_session
    ADD CONSTRAINT a2obj_apps_accounts_resource_session_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_resource_session a2obj_apps_accounts_resource_session_ibfk_2; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_session
    ADD CONSTRAINT a2obj_apps_accounts_resource_session_ibfk_2 FOREIGN KEY (client) REFERENCES public.a2obj_apps_accounts_resource_client(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_resource_twofactor a2obj_apps_accounts_resource_twofactor_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_twofactor
    ADD CONSTRAINT a2obj_apps_accounts_resource_twofactor_ibfk_1 FOREIGN KEY (account) REFERENCES public.a2obj_apps_accounts_account(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: a2obj_apps_accounts_resource_usedtoken a2obj_apps_accounts_resource_usedtoken_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_accounts_resource_usedtoken
    ADD CONSTRAINT a2obj_apps_accounts_resource_usedtoken_ibfk_1 FOREIGN KEY (twofactor) REFERENCES public.a2obj_apps_accounts_resource_twofactor(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--


