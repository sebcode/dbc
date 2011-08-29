
/* liste aller user */
CREATE TABLE users (
	id INTEGER PRIMARY KEY
	,name TEXT UNIQUE
	,password_hash TEXT
	,filelist_mtime INTEGER NOT NULL DEFAULT 0
);

INSERT INTO users (name, password_hash) VALUES ('seb', '098f6bcd4621d373cade4e832627b4f6');

/* liste aller dateien fuer alle user */
CREATE TABLE userfiles (
	id INTEGER PRIMARY KEY
	,user_id INTEGER
	,filename TEXT
	,mtime INTEGER
	,file_id INTEGER
);

CREATE TABLE files (
	id INTEGER PRIMARY KEY
	,size INTEGER
	,hash TEXT
);

CREATE TABLE files_chunks (
	file_id INTEGER
	,chunk_id INTEGER
	,position INTEGER
);

/* position eines chunks auf der platte */
CREATE TABLE chunks (
	id INTEGER PRIMARY KEY
	,hash TEXT UNIQUE
	/* first 2 chars of hash, i.e. storage filename */
	,hash12 TEXT
	,size INTEGER
	/* position (in 1 MB schritten) im entsprechenden daten-file */
	/* 0 wenn noch nicht existiert */
	,position INTEGER
);

/*
datenfiles:

data/<erstes zeichen chunk-hash>.dat

*/
