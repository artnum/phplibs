MySQL DB Creation
-----------------

```sql
create table verrou (verrou_path BLOB(32) NOT NULL, verrou_key BLOB(32) NULL, verrou_state INTEGER DEFAULT 0, verrou_timestamp INTEGER);
alter table verrou add unique (verrou_path(32));
create index idxVerrouPath on verrou(verrou_path(32));
create index idxVerrouKey on verrou(verrou_key(32));
create table cle (cle_id INTEGER PRIMARY KEY AUTO_INCREMENT, cle_data BLOB);
``` 
