--
-- PostgreSQL database dump
--

-- Dumped from database version 13.1
-- Dumped by pg_dump version 13.1

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
    id character(16) NOT NULL,
    datadir character varying(255),
    apps text NOT NULL,
    apiurl character varying(255),
    dates__created bigint NOT NULL,
    features__debug_log smallint NOT NULL,
    features__debug_http boolean NOT NULL,
    features__debug_file boolean NOT NULL,
    features__read_only smallint NOT NULL,
    features__enabled boolean NOT NULL,
    features__email boolean NOT NULL
);


--
-- Name: a2_objects_core_emailer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_emailer (
    id character(16) NOT NULL,
    type smallint NOT NULL,
    hosts text,
    username character varying(255),
    password text,
    from_address character varying(255) NOT NULL,
    from_name character varying(255),
    features__reply boolean,
    dates__created bigint NOT NULL
);


--
-- Name: a2_objects_core_exceptions_errorlogentry; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.a2_objects_core_exceptions_errorlogentry (
    id character(16) NOT NULL,
    "time" bigint NOT NULL,
    addr character varying(255) NOT NULL,
    agent character varying(255) NOT NULL,
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
-- Name: a2_objects_core_config idx_32378_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_config
    ADD CONSTRAINT idx_32378_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_emailer idx_32384_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_emailer
    ADD CONSTRAINT idx_32384_primary PRIMARY KEY (id);


--
-- Name: a2_objects_core_exceptions_errorlogentry idx_32390_primary; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.a2_objects_core_exceptions_errorlogentry
    ADD CONSTRAINT idx_32390_primary PRIMARY KEY (id);


--
-- Name: idx_32378_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32378_id ON public.a2_objects_core_config USING btree (id);


--
-- Name: idx_32378_id_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32378_id_2 ON public.a2_objects_core_config USING btree (id);


--
-- Name: idx_32384_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32384_id ON public.a2_objects_core_emailer USING btree (id);


--
-- Name: idx_32384_id_2; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32384_id_2 ON public.a2_objects_core_emailer USING btree (id);


--
-- Name: idx_32390_action; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32390_action ON public.a2_objects_core_exceptions_errorlogentry USING btree (action);


--
-- Name: idx_32390_addr; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32390_addr ON public.a2_objects_core_exceptions_errorlogentry USING btree (addr);


--
-- Name: idx_32390_app; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32390_app ON public.a2_objects_core_exceptions_errorlogentry USING btree (app);


--
-- Name: idx_32390_code; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32390_code ON public.a2_objects_core_exceptions_errorlogentry USING btree (code);


--
-- Name: idx_32390_id; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX idx_32390_id ON public.a2_objects_core_exceptions_errorlogentry USING btree (id);


--
-- Name: idx_32390_time; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_32390_time ON public.a2_objects_core_exceptions_errorlogentry USING btree ("time");


--
-- PostgreSQL database dump complete
--

