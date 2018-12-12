MySQL DB Creation
-----------------

```sql
SET SQL_MODE=ANSI_QUOTES;
CREATE DATABASE IF NOT EXISTS "verrou" CHARACTER SET "utf8mb4" COLLATE "utf8mb4_unicode_ci";
USE "verrou";
CREATE TABLE IF NOT EXISTS "verrou" ("verrou_path" BLOB(32) NOT NULL, "verrou_key" BLOB(32) NULL, "verrou_state" INTEGER DEFAULT 0, "verrou_timestamp" INTEGER) CHARACTER SET "utf8mb4";
ALTER TABLE "verrou" ADD UNIQUE ("verrou_path"(32));
CREATE INDEX "idxVerrouPath" ON "verrou"("verrou_path"(32));
CREATE INDEX "idxVerrouKey" ON "verrou"("verrou_key"(32));
CREATE TABLE IF NOT EXISTS "cle" ("cle_id" INTEGER PRIMARY KEY AUTO_INCREMENT, "cle_data" BLOB) CHARACTER SET "utf8mb4";
``` 
