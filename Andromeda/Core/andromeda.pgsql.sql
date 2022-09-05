

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
    id character(1) NOT NULL,
    version character varying(255) NOT NULL,
    datadir text,
    apps text NOT NULL,
    date_created double precision NOT NULL,
    requestlog_db smallint NOT NULL,
    requestlog_file boolean NOT NULL,
    requestlog_details smallint NOT NULL,
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



CREATE TABLE public.a2obj_core_emailer (
    id character(4) NOT NULL,
    type smallint NOT NULL,
    hosts text,
    username character varying(255) DEFAULT NULL::character varying,
    password text,
    from_address character varying(255) NOT NULL,
    from_name character varying(255) DEFAULT NULL::character varying,
    use_reply boolean,
    date_created double precision NOT NULL
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
    hints text
);



CREATE TABLE public.a2obj_core_logging_actionlog (
    id character(20) NOT NULL,
    requestlog character(20) NOT NULL,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    inputs text,
    details text
);



CREATE TABLE public.a2obj_core_logging_actionmetrics (
    id character(20) NOT NULL,
    requestmet character(20) NOT NULL,
    actionlog character(20) DEFAULT NULL::bpchar,
    app character varying(255) NOT NULL,
    action character varying(255) NOT NULL,
    db_reads bigint NOT NULL,
    db_read_time double precision NOT NULL,
    db_writes bigint NOT NULL,
    db_write_time double precision NOT NULL,
    code_time double precision NOT NULL,
    total_time double precision NOT NULL,
    queries text
);



CREATE TABLE public.a2obj_core_logging_commitmetrics (
    id character(20) NOT NULL,
    requestmet character(20) NOT NULL,
    db_reads bigint NOT NULL,
    db_read_time double precision NOT NULL,
    db_writes bigint NOT NULL,
    db_write_time double precision NOT NULL,
    code_time double precision NOT NULL,
    total_time double precision NOT NULL
);



CREATE TABLE public.a2obj_core_logging_requestlog (
    id character(20) NOT NULL,
    "time" double precision NOT NULL,
    addr character varying(255) NOT NULL,
    agent text NOT NULL,
    errcode character varying(255) DEFAULT NULL::character varying,
    errtext text
);



CREATE TABLE public.a2obj_core_logging_requestmetrics (
    id character(20) NOT NULL,
    requestlog character(20) DEFAULT NULL::bpchar,
    date_created double precision NOT NULL,
    peak_memory bigint NOT NULL,
    nincludes smallint NOT NULL,
    nobjects bigint NOT NULL,
    init_db_reads bigint NOT NULL,
    init_db_read_time double precision NOT NULL,
    init_db_writes bigint NOT NULL,
    init_db_write_time double precision NOT NULL,
    init_code_time double precision NOT NULL,
    init_total_time double precision NOT NULL,
    init_queries text,
    db_reads bigint NOT NULL,
    db_read_time double precision NOT NULL,
    db_writes bigint NOT NULL,
    db_write_time double precision NOT NULL,
    code_time double precision NOT NULL,
    total_time double precision NOT NULL,
    gcstats text,
    rusage text,
    includes text,
    objects text,
    queries text,
    debughints text
);



ALTER TABLE ONLY public.a2obj_core_config
    ADD CONSTRAINT idx_19859_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_emailer
    ADD CONSTRAINT idx_19864_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_exceptions_errorlog
    ADD CONSTRAINT idx_19871_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_actionlog
    ADD CONSTRAINT idx_19878_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_actionmetrics
    ADD CONSTRAINT idx_19883_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_commitmetrics
    ADD CONSTRAINT idx_19889_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_requestlog
    ADD CONSTRAINT idx_19892_primary PRIMARY KEY (id);



ALTER TABLE ONLY public.a2obj_core_logging_requestmetrics
    ADD CONSTRAINT idx_19898_primary PRIMARY KEY (id);



CREATE INDEX idx_19871_action ON public.a2obj_core_exceptions_errorlog USING btree (action);



CREATE INDEX idx_19871_addr ON public.a2obj_core_exceptions_errorlog USING btree (addr);



CREATE INDEX idx_19871_app ON public.a2obj_core_exceptions_errorlog USING btree (app);



CREATE INDEX idx_19871_code ON public.a2obj_core_exceptions_errorlog USING btree (code);



CREATE INDEX idx_19871_time ON public.a2obj_core_exceptions_errorlog USING btree ("time");



CREATE INDEX idx_19878_app_action ON public.a2obj_core_logging_actionlog USING btree (app, action);



CREATE INDEX idx_19878_requestlog ON public.a2obj_core_logging_actionlog USING btree (requestlog);



CREATE UNIQUE INDEX idx_19883_actionlog ON public.a2obj_core_logging_actionmetrics USING btree (actionlog);



CREATE INDEX idx_19883_app_action ON public.a2obj_core_logging_actionmetrics USING btree (app, action);



CREATE INDEX idx_19883_requestmet ON public.a2obj_core_logging_actionmetrics USING btree (requestmet);



CREATE INDEX idx_19889_requestmet ON public.a2obj_core_logging_commitmetrics USING btree (requestmet);



CREATE UNIQUE INDEX idx_19898_requestlog ON public.a2obj_core_logging_requestmetrics USING btree (requestlog);



ALTER TABLE ONLY public.a2obj_core_logging_actionlog
    ADD CONSTRAINT a2obj_core_logging_actionlog_ibfk_1 FOREIGN KEY (requestlog) REFERENCES public.a2obj_core_logging_requestlog(id) ON UPDATE RESTRICT ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_core_logging_actionmetrics
    ADD CONSTRAINT a2obj_core_logging_actionmetrics_ibfk_1 FOREIGN KEY (requestmet) REFERENCES public.a2obj_core_logging_requestmetrics(id) ON UPDATE RESTRICT ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_core_logging_actionmetrics
    ADD CONSTRAINT a2obj_core_logging_actionmetrics_ibfk_2 FOREIGN KEY (actionlog) REFERENCES public.a2obj_core_logging_actionlog(id) ON UPDATE RESTRICT ON DELETE SET NULL;



ALTER TABLE ONLY public.a2obj_core_logging_commitmetrics
    ADD CONSTRAINT a2obj_core_logging_commitmetrics_ibfk_1 FOREIGN KEY (requestmet) REFERENCES public.a2obj_core_logging_requestmetrics(id) ON UPDATE RESTRICT ON DELETE CASCADE;



ALTER TABLE ONLY public.a2obj_core_logging_requestmetrics
    ADD CONSTRAINT a2obj_core_logging_requestmetrics_ibfk_1 FOREIGN KEY (requestlog) REFERENCES public.a2obj_core_logging_requestlog(id) ON UPDATE RESTRICT ON DELETE SET NULL;



