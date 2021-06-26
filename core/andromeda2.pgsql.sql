--
-- PostgreSQL database dump
--

-- Dumped from database version 12.7 (Ubuntu 12.7-0ubuntu0.20.10.1)
-- Dumped by pg_dump version 12.7 (Ubuntu 12.7-0ubuntu0.20.10.1)

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
-- Name: a2_objects_core_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_config (
    id character(12) NOT NULL,
    datadir text,
    apps text NOT NULL,
    dates__created double precision NOT NULL,
    features__requestlog_db smallint NOT NULL,
    features__requestlog_file boolean NOT NULL,
    features__requestlog_details smallint NOT NULL,
    features__debug smallint NOT NULL,
    features__debug_http boolean NOT NULL,
    features__debug_dblog boolean NOT NULL,
    features__debug_filelog boolean NOT NULL,
    features__read_only smallint NOT NULL,
    features__enabled boolean NOT NULL,
    features__email boolean NOT NULL
);


--
-- Name: a2_objects_core_emailer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_emailer (
    id character(12) NOT NULL,
    type smallint NOT NULL,
    hosts text,
    username character varying(255) DEFAULT NULL::character varying,
    password text,
    from_address character varying(255) NOT NULL,
    from_name character varying(255) DEFAULT NULL::character varying,
    features__reply boolean,
    dates__created double precision NOT NULL
);


--
-- Name: a2_objects_core_exceptions_errorlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_exceptions_errorlog (
    id character(12) NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    app character varying(255) DEFAULT NULL::character varying,
    action character varying(255) DEFAULT NULL::character varying,
    code character varying(255) NOT NULL,
    file text NOT NULL,
    message text NOT NULL,
    trace_basic text,
    trace_full text,
    objects text,
    queries text,
    params text,
    log text
);


--
-- Name: a2_objects_core_exceptions_errorlogentry; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_exceptions_errorlogentry (
    id character(12) NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    code character varying(255) NOT NULL,
    file text NOT NULL,
    message text NOT NULL,
    trace_basic text,
    trace_full text,
    objects text,
    queries text,
    params text,
    log text
);


--
-- Name: a2_objects_core_logging_actionlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_logging_actionlog (
    id character(20) NOT NULL,
    request character(20) NOT NULL,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    applog character varying(64) DEFAULT NULL::character varying,
    details text
);


--
-- Name: a2_objects_core_logging_requestlog; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_logging_requestlog (
    id character(20) NOT NULL,
    actions smallint DEFAULT '0'::smallint NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    errcode smallint,
    errtext text
);


--
-- Name: a2_objects_core_exceptions_errorlogentry idx_56396_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_exceptions_errorlogentry
    ADD CONSTRAINT idx_56396_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_config idx_67899_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_config
    ADD CONSTRAINT idx_67899_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_emailer idx_67905_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_emailer
    ADD CONSTRAINT idx_67905_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_exceptions_errorlog idx_67913_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_exceptions_errorlog
    ADD CONSTRAINT idx_67913_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_logging_actionlog idx_67921_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_logging_actionlog
    ADD CONSTRAINT idx_67921_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_logging_requestlog idx_67928_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_logging_requestlog
    ADD CONSTRAINT idx_67928_primary PRIMARY KEY (id);


--
-- Name: idx_56396_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_56396_action ON public.a2_objects_core_exceptions_errorlogentry USING btree (action);


--
-- Name: idx_56396_addr; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_56396_addr ON public.a2_objects_core_exceptions_errorlogentry USING btree (addr);


--
-- Name: idx_56396_app; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_56396_app ON public.a2_objects_core_exceptions_errorlogentry USING btree (app);


--
-- Name: idx_56396_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_56396_code ON public.a2_objects_core_exceptions_errorlogentry USING btree (code);


--
-- Name: idx_56396_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_56396_id ON public.a2_objects_core_exceptions_errorlogentry USING btree (id);


--
-- Name: idx_56396_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_56396_time ON public.a2_objects_core_exceptions_errorlogentry USING btree ("time");


--
-- Name: idx_67899_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_67899_id ON public.a2_objects_core_config USING btree (id);


--
-- Name: idx_67899_id_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67899_id_2 ON public.a2_objects_core_config USING btree (id);


--
-- Name: idx_67905_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_67905_id ON public.a2_objects_core_emailer USING btree (id);


--
-- Name: idx_67905_id_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67905_id_2 ON public.a2_objects_core_emailer USING btree (id);


--
-- Name: idx_67913_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67913_action ON public.a2_objects_core_exceptions_errorlog USING btree (action);


--
-- Name: idx_67913_addr; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67913_addr ON public.a2_objects_core_exceptions_errorlog USING btree (addr);


--
-- Name: idx_67913_app; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67913_app ON public.a2_objects_core_exceptions_errorlog USING btree (app);


--
-- Name: idx_67913_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67913_code ON public.a2_objects_core_exceptions_errorlog USING btree (code);


--
-- Name: idx_67913_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_67913_id ON public.a2_objects_core_exceptions_errorlog USING btree (id);


--
-- Name: idx_67913_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67913_time ON public.a2_objects_core_exceptions_errorlog USING btree ("time");


--
-- Name: idx_67921_applog; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67921_applog ON public.a2_objects_core_logging_actionlog USING btree (applog);


--
-- Name: idx_67921_request; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_67921_request ON public.a2_objects_core_logging_actionlog USING btree (request);


--
-- PostgreSQL database dump complete
--

