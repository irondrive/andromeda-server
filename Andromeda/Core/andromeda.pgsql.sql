

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


CREATE TABLE public.a2obj_core_config (
    id character(12) NOT NULL,
    version character varying(255) NOT NULL,
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
    features__metrics smallint NOT NULL,
    features__metrics_dblog boolean NOT NULL,
    features__metrics_filelog boolean NOT NULL,
    features__read_only boolean NOT NULL,
    features__enabled boolean NOT NULL,
    features__email boolean NOT NULL
);



CREATE TABLE public.a2obj_core_emailer (
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



CREATE TABLE public.a2obj_core_exceptions_errorlog (
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



CREATE TABLE public.a2obj_core_logging_actionlog (
    id character(20) NOT NULL,
    request character(20) NOT NULL,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    applog character varying(64) DEFAULT NULL::character varying,
    details text
);



CREATE TABLE public.a2obj_core_logging_actionmetrics (
    id character(20) NOT NULL,
    request character(20) NOT NULL,
    actionlog character(20) NOT NULL,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    dates__created double precision NOT NULL,
    stats__db_reads bigint NOT NULL,
    stats__db_read_time double precision NOT NULL,
    stats__db_writes bigint NOT NULL,
    stats__db_write_time double precision NOT NULL,
    stats__code_time double precision NOT NULL,
    stats__total_time double precision NOT NULL,
    stats__queries text
);



CREATE TABLE public.a2obj_core_logging_commitmetrics (
    id character(20) NOT NULL,
    request character(20) NOT NULL,
    dates__created double precision NOT NULL,
    stats__db_reads bigint NOT NULL,
    stats__db_read_time double precision NOT NULL,
    stats__db_writes bigint NOT NULL,
    stats__db_write_time double precision NOT NULL,
    stats__code_time double precision NOT NULL,
    stats__total_time double precision NOT NULL
);



CREATE TABLE public.a2obj_core_logging_requestlog (
    id character(20) NOT NULL,
    actions smallint DEFAULT '0'::smallint NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    errcode smallint,
    errtext text
);



CREATE TABLE public.a2obj_core_logging_requestmetrics (
    id character(20) NOT NULL,
    actions smallint DEFAULT '0'::smallint NOT NULL,
    commits smallint DEFAULT '0'::smallint NOT NULL,
    requestlog character(20) DEFAULT NULL::bpchar,
    dates__created double precision NOT NULL,
    peak_memory bigint NOT NULL,
    nincludes smallint NOT NULL,
    nobjects bigint NOT NULL,
    construct__db_reads bigint NOT NULL,
    construct__db_read_time double precision NOT NULL,
    construct__db_writes bigint NOT NULL,
    construct__db_write_time double precision NOT NULL,
    construct__code_time double precision NOT NULL,
    construct__total_time double precision NOT NULL,
    construct__queries text,
    total__db_reads bigint NOT NULL,
    total__db_read_time double precision NOT NULL,
    total__db_writes bigint NOT NULL,
    total__db_write_time double precision NOT NULL,
    total__code_time double precision NOT NULL,
    total__total_time double precision NOT NULL,
    gcstats text,
    rusage text,
    includes text,
    objects text,
    queries text,
    debuglog text
);



ALTER TABLE ONLY public.a2obj_core_config
    ADD CONSTRAINT idx_213165_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_emailer
    ADD CONSTRAINT idx_213171_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_exceptions_errorlog
    ADD CONSTRAINT idx_213179_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_actionlog
    ADD CONSTRAINT idx_213187_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_actionmetrics
    ADD CONSTRAINT idx_213194_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_commitmetrics
    ADD CONSTRAINT idx_213200_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_requestlog
    ADD CONSTRAINT idx_213203_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_requestmetrics
    ADD CONSTRAINT idx_213210_primary PRIMARY KEY (id);



CREATE INDEX idx_213179_action ON public.a2obj_core_exceptions_errorlog USING btree (action);



CREATE INDEX idx_213179_addr ON public.a2obj_core_exceptions_errorlog USING btree (addr);



CREATE INDEX idx_213179_app ON public.a2obj_core_exceptions_errorlog USING btree (app);



CREATE INDEX idx_213179_code ON public.a2obj_core_exceptions_errorlog USING btree (code);



CREATE INDEX idx_213179_time ON public.a2obj_core_exceptions_errorlog USING btree ("time");



CREATE INDEX idx_213187_app_action ON public.a2obj_core_logging_actionlog USING btree (app, action);



CREATE INDEX idx_213187_applog ON public.a2obj_core_logging_actionlog USING btree (applog);



CREATE INDEX idx_213187_request ON public.a2obj_core_logging_actionlog USING btree (request);



CREATE INDEX idx_213194_app_action ON public.a2obj_core_logging_actionmetrics USING btree (app, action);



CREATE INDEX idx_213194_request ON public.a2obj_core_logging_actionmetrics USING btree (request);



CREATE INDEX idx_213200_request ON public.a2obj_core_logging_commitmetrics USING btree (request);



