<?php

Namespace artnum\JStore;

use Error;
use Exception;
use PDO;

class ETag {
    protected $pdo;
    protected $table;
    protected $tld_level;

    function __construct($pdo, $table = 'etag', $tld_level = 2) {
        $this->pdo = $pdo;
        $this->table = $table;        
        $this->tld_level = $tld_level;
    }

    function geturlquery ($query) {
        $parts = explode('&', $query);
        $parts = array_filter($parts, function ($element) {
            /* access_token is for auth and might change for the same user */
            if (strpos($element, 'access_token=') === 0) { return false; }
            return true;
        });
        if (empty($parts)) { return ''; }
        /* sort to allow query begin like ?length=10&time=20 or ?time=20&length=10 */
        sort($parts, SORT_STRING);
        return '?' . implode('&', $parts);
    }

    function hashurl ($url) {
        $url = filter_var($url, FILTER_VALIDATE_URL);
        $parsed = parse_url($url);
        $host = [];
        $hostParts = explode('.', $parsed['host']);
        for ($i = 0; $i < $this->tld_level; $i++) {
            array_unshift($host, array_pop($hostParts));
        }
        /* needed to allow hosts like localhost or any strange setup */
        $host = array_filter($host, function ($e) { return (empty($e) ? false : true); });
        $url = implode('.', $host);
        if (isset($parsed['path']) && $parsed['path'] !== null) {  $url .= str_replace('//', '/', $parsed['path']); }
        if (isset($parsed['query']) && $parsed['query'] !== null ) { $url .= $this->geturlquery($parsed['query']); }
        error_log($url);
        return sha1(str_replace('//', '/', $url), true);
    }

    function set ($url) {
        try {
            $resource = $this->hashurl($url);
            $hash = hash_hmac('sha1', random_bytes(20), $resource, true);

            $stmt = $this->pdo->prepare(sprintf('INSERT INTO %s (resource, hash) VALUES (:resource, :hash) ON DUPLICATE KEY UPDATE hash = :hash;', $this->table));
            $stmt->bindValue(':resource', $resource, PDO::PARAM_LOB);
            $stmt->bindValue(':hash', $hash, PDO::PARAM_LOB);

            if(!$stmt->execute()) { throw new \Exception(); }
            return bin2hex($hash);
        } catch(\Exception $e) {
            error_log(__LINE__ . ' ' . $e->getMessage());
            throw new \Exception('Cannot store etag ' . $this->pdo->errorInfo()[2], 0, $e);
        }
    }

    function get ($url) {
        try {
            $resource = $this->hashurl($url);
            $stmt = $this->pdo->prepare(sprintf('SELECT hash FROM %s WHERE resource = :resource', $this->table));
            $stmt->bindValue(':resource', $resource, PDO::PARAM_LOB);
            if (!$stmt->execute()) { return ''; }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { return ''; }
            return bin2hex($row['hash']);
        } catch(\Exception $e) {
            error_log(__LINE__ . ' ' .$e->getMessage());
            throw new \Exception('Cannot get etag '  . $this->pdo->errorInfo()[2], 0, $e);
        }
    }

    function delete($url) {
        try {
            $resource = $this->hashurl($url);
            
            $stmt = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE resource = :resource', $this->table));
            $stmt->bindValue(':resource', $resource, PDO::PARAM_LOB);
            return $stmt->execute();
        } catch (\Exception $e) {
            error_log($e->getMessage());
            throw new \Exception('Cannot delete etag ' . $this->pdo->errorInfo()[2], 0, $e);
        }
    }
}