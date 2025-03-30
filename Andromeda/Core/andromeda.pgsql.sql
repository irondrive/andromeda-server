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
-- Name: a2obj_apps_core_actionlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_apps_core_actionlog (
    id character(20) NOT NULL,
    admin boolean,
    account character(12) DEFAULT NULL::bpchar,
    sudouser character(12) DEFAULT NULL::bpchar,
    client character(12) DEFAULT NULL::bpchar
);


--
-- Name: a2obj_core_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_core_config (
    id character(1) NOT NULL,
    version character varying(255) NOT NULL,
    datadir text,
    apps text NOT NULL,
    date_created double precision NOT NULL,
    actionlog_db smallint NOT NULL,
    actionlog_file boolean NOT NULL,
    actionlog_details smallint NOT NULL,
    debug smallint NOT NULL,
    debug_http boolean NOT NULL,
    debug_dblog boolean NOT NULL,
    debug_filelog boolean NOT NULL,
    metrics smallint NOT NULL,
    metrics_dblog boolean NOT NULL,
    metrics_filelog boolean NOT NULL,
    read_only boolean NOT NULL,
    enabled boolean NOT NULL,
    email boolean NOT NULL
);


--
-- Name: a2obj_core_emailer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_core_emailer (
    id character(8) NOT NULL,
    type smallint NOT NULL,
    hosts text,
    username character varying(255) DEFAULT NULL::character varying,
    password bytea,
    from_address character varying(255) NOT NULL,
    from_name character varying(255) DEFAULT NULL::character varying,
    use_reply boolean,
    date_created double precision NOT NULL
);


--
-- Name: a2obj_core_errors_errorlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_core_errors_errorlog (
    id character(12) NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    app character varying(255) DEFAULT NULL::character varying,
    action character varying(255) DEFAULT NULL::character varying,
    code bigint NOT NULL,
    file text NOT NULL,
    message text NOT NULL,
    trace_basic text,
    trace_full text,
    objects text,
    queries text,
    params text,
    hints text
);


--
-- Name: a2obj_core_logging_actionlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_core_logging_actionlog (
    id character(20) NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    errcode bigint,
    errtext text,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    authuser character varying(255) DEFAULT NULL::character varying,
    params text,
    files text,
    details text
);


--
-- Name: a2obj_core_logging_metricslog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2obj_core_logging_metricslog (
    id character(20) NOT NULL,
    actionlog character(20) DEFAULT NULL::bpchar,
    date_created double precision NOT NULL,
    peak_memory bigint NOT NULL,
    nincludes smallint NOT NULL,
    nobjects bigint NOT NULL,
    init_db_reads bigint NOT NULL,
    init_db_read_time double precision NOT NULL,
    init_db_writes bigint NOT NULL,
    init_db_write_time double precision NOT NULL,
    init_code_time double precision NOT NULL,
    init_autoloader_time double precision NOT NULL,
    init_total_time double precision NOT NULL,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    action_db_reads bigint NOT NULL,
    action_db_read_time double precision NOT NULL,
    action_db_writes bigint NOT NULL,
    action_db_write_time double precision NOT NULL,
    action_code_time double precision NOT NULL,
    action_autoloader_time double precision NOT NULL,
    action_total_time double precision NOT NULL,
    commit_db_reads bigint,
    commit_db_read_time double precision,
    commit_db_writes bigint,
    commit_db_write_time double precision,
    commit_code_time double precision,
    commit_autoloader_time double precision,
    commit_total_time double precision,
    db_reads bigint NOT NULL,
    db_read_time double precision NOT NULL,
    db_writes bigint NOT NULL,
    db_write_time double precision NOT NULL,
    code_time double precision NOT NULL,
    autoloader_time double precision NOT NULL,
    total_time double precision NOT NULL,
    gcstats text,
    rusage text,
    includes text,
    objects text,
    queries text,
    debughints text
);


--
-- Name: a2obj_apps_core_actionlog idx_178011_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_core_actionlog
    ADD CONSTRAINT idx_178011_primary PRIMARY KEY (id);


--
-- Name: a2obj_core_config idx_178153_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_core_config
    ADD CONSTRAINT idx_178153_primary PRIMARY KEY (id);


--
-- Name: a2obj_core_emailer idx_178158_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_core_emailer
    ADD CONSTRAINT idx_178158_primary PRIMARY KEY (id);


--
-- Name: a2obj_core_errors_errorlog idx_178165_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_core_errors_errorlog
    ADD CONSTRAINT idx_178165_primary PRIMARY KEY (id);


--
-- Name: a2obj_core_logging_actionlog idx_178172_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_core_logging_actionlog
    ADD CONSTRAINT idx_178172_primary PRIMARY KEY (id);


--
-- Name: a2obj_core_logging_metricslog idx_178178_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_core_logging_metricslog
    ADD CONSTRAINT idx_178178_primary PRIMARY KEY (id);


--
-- Name: idx_178165_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178165_action ON public.a2obj_core_errors_errorlog USING btree (action);


--
-- Name: idx_178165_addr; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178165_addr ON public.a2obj_core_errors_errorlog USING btree (addr);


--
-- Name: idx_178165_app; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178165_app ON public.a2obj_core_errors_errorlog USING btree (app);


--
-- Name: idx_178165_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178165_code ON public.a2obj_core_errors_errorlog USING btree (code);


--
-- Name: idx_178165_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178165_time ON public.a2obj_core_errors_errorlog USING btree ("time");


--
-- Name: idx_178172_addr; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178172_addr ON public.a2obj_core_logging_actionlog USING btree (addr);


--
-- Name: idx_178172_app_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178172_app_action ON public.a2obj_core_logging_actionlog USING btree (app, action);


--
-- Name: idx_178172_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178172_time ON public.a2obj_core_logging_actionlog USING btree ("time");


--
-- Name: idx_178178_actionlog; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_178178_actionlog ON public.a2obj_core_logging_metricslog USING btree (actionlog);


--
-- Name: idx_178178_app_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_178178_app_action ON public.a2obj_core_logging_metricslog USING btree (app, action);


--
-- Name: a2obj_apps_core_actionlog a2obj_apps_core_actionlog_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_apps_core_actionlog
    ADD CONSTRAINT a2obj_apps_core_actionlog_ibfk_1 FOREIGN KEY (id) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: a2obj_core_logging_metricslog a2obj_core_logging_metricslog_ibfk_1; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2obj_core_logging_metricslog
    ADD CONSTRAINT a2obj_core_logging_metricslog_ibfk_1 FOREIGN KEY (actionlog) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

