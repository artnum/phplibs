<?php
namespace artnum\JStore;

use PDO;
use \artnum\HTTP\JsonRequest;
use Exception;

class SQLAudit {
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
            INSERT INTO audit (userid, time, action, collection, item, body)
            VALUES (:userid, :time, :action, :collection, :item, :body)');
        $stmt->bindValue(':time', time(), PDO::PARAM_INT);
        return $stmt;
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
            $stmt->execute();
        } catch (Exception $e) {
            error_log(sprintf('%s (%d) [%s:%d]', $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }
}