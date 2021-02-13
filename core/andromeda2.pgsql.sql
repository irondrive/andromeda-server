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
    dates__created bigint NOT NULL,
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
    dates__created bigint NOT NULL
);


--
-- Name: a2_objects_core_exceptions_errorlogentry; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_exceptions_errorlogentry (
    id character(12) NOT NULL,
    "time" bigint NOT NULL,
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
-- Name: a2_objects_core_config idx_39680_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_config
    ADD CONSTRAINT idx_39680_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_emailer idx_39686_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_emailer
    ADD CONSTRAINT idx_39686_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_exceptions_errorlogentry idx_39694_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_exceptions_errorlogentry
    ADD CONSTRAINT idx_39694_primary PRIMARY KEY (id);


--
-- Name: idx_39680_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39680_id ON public.a2_objects_core_config USING btree (id);


--
-- Name: idx_39680_id_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39680_id_2 ON public.a2_objects_core_config USING btree (id);


--
-- Name: idx_39686_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39686_id ON public.a2_objects_core_emailer USING btree (id);


--
-- Name: idx_39686_id_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39686_id_2 ON public.a2_objects_core_emailer USING btree (id);


--
-- Name: idx_39694_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39694_action ON public.a2_objects_core_exceptions_errorlogentry USING btree (action);


--
-- Name: idx_39694_addr; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39694_addr ON public.a2_objects_core_exceptions_errorlogentry USING btree (addr);


--
-- Name: idx_39694_app; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39694_app ON public.a2_objects_core_exceptions_errorlogentry USING btree (app);


--
-- Name: idx_39694_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39694_code ON public.a2_objects_core_exceptions_errorlogentry USING btree (code);


--
-- Name: idx_39694_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_39694_id ON public.a2_objects_core_exceptions_errorlogentry USING btree (id);


--
-- Name: idx_39694_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_39694_time ON public.a2_objects_core_exceptions_errorlogentry USING btree ("time");


--
-- PostgreSQL database dump complete
--

