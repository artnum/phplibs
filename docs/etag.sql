CREATE TABLE etag (
    resource BINARY(20) PRIMARY KEY,
    hash BINARY(20)
) CHARACTER SET ascii;