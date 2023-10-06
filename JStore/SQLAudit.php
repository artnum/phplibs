<?php
namespace artnum\JStore;

use PDO;
use \artnum\HTTP\JsonRequest;
use Exception;

class SQLAudit {
    protected $pdo;
    protected $log_fail;
    protected $log_read;
    protected $log_body;

    function __construct(PDO $pdo, $body = false, $read = false, $fail = false) {
        $this->pdo = $pdo;
        $this->log_fail = $fail;
        $this->log_read = $read;
        $this->log_body = $body;
    }

    function req () {
        $stmt = $this->pdo->prepare('
            INSERT INTO audit (userid, time, action, url, collection, item, body)
            VALUES (:userid, :time, :action, :url, :collection, :item, :body)');
        $stmt->bindValue(':time', time(), PDO::PARAM_INT);
        return $stmt;
    }

    function get_item_action (string $action, string $collection, string $item) {
        try {
            $stmt = $this->pdo->prepare('
                SELECT userid, time, action, url, collection, item
                FROM audit
                WHERE action = :action AND collection = :collection AND item = :item
                ORDER BY time DESC
                LIMIT 1');
            $stmt->bindValue(':action', $action, PDO::PARAM_STR);
            $stmt->bindValue(':collection', $collection, PDO::PARAM_STR);
            $stmt->bindValue(':item', $item, PDO::PARAM_STR);
            if (!$stmt->execute()) {
                return false;
            }
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log(sprintf('%s (%d) [%s:%d]', $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }

    function new_action (
            string $action,
            string $collection,
            string $item,
            mixed $userid,
            string $url = '',
            string $body = ''
        ) {
        try {
            $stmt = $this->req();
            $stmt->bindValue(':collection', $collection, PDO::PARAM_STR);
            $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userid);
            $stmt->bindValue(':action', strtoupper($action), PDO::PARAM_STR);
            $stmt->bindValue(':url', $url, PDO::PARAM_STR);
            $stmt->bindValue(':body',$body, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log(sprintf('%s (%d) [%s:%d]', $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }

    function audit (JsonRequest $request, Response $response, $userid) {
        try {
            if (!$response->succeed() && !$this->log_fail) { return; }
            $item = $response->getItemId();
            if ($item === -1) { $item = $request->getItem(); }
            switch ($request->getVerb()) {
                case 'OPTIONS':
                case 'HEAD':
                case 'GET':
                    if (!$this->log_read) { return ; }
                    if (!$request->onItem()) {
                        $stmt = $this->req();
                        $stmt->bindValue(':collection', $request->getCollection(), PDO::PARAM_STR);
                        $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
                        $stmt->bindValue(':userid', $userid);
                        $stmt->bindValue(':action', 'SEARCH', PDO::PARAM_STR);
                        break;
                    }
                    $stmt = $this->req();
                    $stmt->bindValue(':collection', $request->getCollection(), PDO::PARAM_STR);
                    $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $userid);
                    $stmt->bindValue(':action', 'READ', PDO::PARAM_STR);
                    break;
                case 'POST':
                    if (!$request->onItem()) {
                        $stmt = $this->req();
                        $stmt->bindValue(':collection', $request->getCollection(), PDO::PARAM_STR);
                        $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
                        $stmt->bindValue(':userid', $userid);
                        $stmt->bindValue(':action', 'CREATE', PDO::PARAM_STR);
                        break;
                    }
                    switch($request->getItem()) {
                        default:
                            $stmt = $this->req();
                            $stmt->bindValue(':collection', $request->getCollection(), PDO::PARAM_STR);
                            $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
                            $stmt->bindValue(':userid', $userid);
                            $stmt->bindValue(':action', 'MODIFY', PDO::PARAM_STR);
                            break;
                        case '_restore':
                            $stmt = $this->req();
                            $stmt->bindValue(':collection', $request->getCollection(), PDO::PARAM_STR);
                            $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
                            $stmt->bindValue(':userid', $userid);
                            $stmt->bindValue(':action', 'RESTORE', PDO::PARAM_STR);
                            break;
                        case '_query':
                            if (!$this->log_read) { return; }
                            $stmt = $this->req();
                            $stmt->bindValue(':collection', $request->getCollection(), PDO::PARAM_STR);
                            $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
                            $stmt->bindValue(':userid', $userid);
                            $stmt->bindValue(':action', 'SEARCH', PDO::PARAM_STR);
                            break;
                    }
                    break;
                case 'PUT':
                case 'PATCH':
                    $stmt = $this->req();
                    $stmt->bindValue(':collection', $request->getCollection(), PDO::PARAM_STR);
                    $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $userid);
                    $stmt->bindValue(':action', 'MODIFY', PDO::PARAM_STR);
                    break;
                case 'DELETE':
                    $stmt = $this->req();
                    $stmt->bindValue(':collection', $request->getCollection(), PDO::PARAM_STR);
                    $stmt->bindValue(':item', strval($item), PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $userid);
                    $stmt->bindValue(':action', 'DELETE', PDO::PARAM_STR);
                    break;
            }
            if ($this->log_body) {
                $body = gzencode(json_encode($request->getBody()), 9, FORCE_GZIP);
                if ($body === false) { $stmt->bindValue(':body', 'NULL', PDO::PARAM_NULL); }
                else { $stmt->bindValue(':body', $body, PDO::PARAM_STR); }
            } else {
                $stmt->bindValue(':body', 'NULL', PDO::PARAM_NULL);
            }
            $stmt->bindValue(':url', $request->getUrl(), PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            error_log(sprintf('%s (%d) [%s:%d]', $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }
}