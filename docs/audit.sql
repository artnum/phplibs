CREATE TABLE audit (
    uid INTEGER UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    userid INTEGER UNSIGNED NOT NULL DEFAULT 0,
    time INTEGER UNSIGNED NOT NULL DEFAULT 0,
    action ENUM('CREATE', 'DELETE', 'RESTORE', 'MODIFY', 'SEARCH', 'READ') NOT NULL,
    collection CHAR(80) NOT NULL DEFAULT '',
    item CHAR(80) NOT NULL DEFAULT '',
    body BLOB DEFAULT NULL
) CHARACTER SET ascii;