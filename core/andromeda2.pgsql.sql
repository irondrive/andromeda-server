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
-- Name: a2_objects_core_config; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_config (
    id character(12) NOT NULL,
    datadir text,
    apps text NOT NULL,
    dates__created double precision NOT NULL,
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
-- Name: a2_objects_core_config idx_44046_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_config
    ADD CONSTRAINT idx_44046_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_emailer idx_44052_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_emailer
    ADD CONSTRAINT idx_44052_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_exceptions_errorlogentry idx_44060_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_exceptions_errorlogentry
    ADD CONSTRAINT idx_44060_primary PRIMARY KEY (id);


--
-- Name: idx_44046_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_44046_id ON public.a2_objects_core_config USING btree (id);


--
-- Name: idx_44046_id_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_44046_id_2 ON public.a2_objects_core_config USING btree (id);


--
-- Name: idx_44052_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_44052_id ON public.a2_objects_core_emailer USING btree (id);


--
-- Name: idx_44052_id_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_44052_id_2 ON public.a2_objects_core_emailer USING btree (id);


--
-- Name: idx_44060_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_44060_action ON public.a2_objects_core_exceptions_errorlogentry USING btree (action);


--
-- Name: idx_44060_addr; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_44060_addr ON public.a2_objects_core_exceptions_errorlogentry USING btree (addr);


--
-- Name: idx_44060_app; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_44060_app ON public.a2_objects_core_exceptions_errorlogentry USING btree (app);


--
-- Name: idx_44060_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_44060_code ON public.a2_objects_core_exceptions_errorlogentry USING btree (code);


--
-- Name: idx_44060_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_44060_id ON public.a2_objects_core_exceptions_errorlogentry USING btree (id);


--
-- Name: idx_44060_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_44060_time ON public.a2_objects_core_exceptions_errorlogentry USING btree ("time");


--
-- PostgreSQL database dump complete
--

