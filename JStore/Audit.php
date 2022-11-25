<?php
namespace artnum\JStore;

use \artnum\HTTP\JsonRequest;

class Audit {
    protected $log_fail;
    protected $log_read;
    protected $log_body;
    function __construct($body = false, $read = false, $fail = false) {
        $this->log_fail = $fail;
        $this->log_read = $read;
        $this->log_body = $body;
        openlog('jstore', LOG_PID, LOG_LOCAL7);
    }

    function __destruct() {
        closelog();   
    }

    function audit (JsonRequest $request, Response $response, $userid) {
        if (!$response->succeed() && !$this->log_fail) { return; }
        $message = '';
        $item = $response->getItemId();
        if ($item === -1) { $item = $request->getItem(); }

        switch ($request->getVerb()) {
            case 'OPTIONS':
            case 'HEAD':
            case 'GET':
                if (!$this->log_read) { return ; }
                if (!$request->onItem()) { 
                    $message = sprintf('SEARCH %s BY %d', $request->getCollection(), $item, $userid); 
                    break;
                } 
                $message = sprintf('READ %s/%d BY %d', $request->getCollection(), $item, $userid);
                break;
            case 'POST':
                if (!$request->onItem()) {
                    $message = sprintf('CREATE %s/%s BY %d', $request->getCollection(), $item, $userid);
                    break;
                }
                switch($request->getItem()) {
                    default:
                        $message = sprintf('MODIFY %s/%s BY %d', $request->getCollection(), $item, $userid);
                        break;
                    case '_restore':
                        $message = sprintf('RESTORE %s/%s BY %d', $request->getCollection(), $item, $userid);
                        break;
                    case '_query':
                        if (!$this->log_read) { return; }
                        $message = sprintf('SEARCH %s BY %d', $request->getCollection(), $item, $userid);
                        break;
                }
                break;
            case 'PUT':
            case 'PATCH':
                $message = sprintf('MODIFY %s/%s BY %d', $request->getCollection(), $item, $userid);
                break;
            case 'DELETE':
                $message = sprintf('DELETE %s/%s BY %d', $request->getCOllection(), $item, $userid);
                break;
        }
        if ($this->log_body) {
            $message .= ' BODY ' . json_encode($request->getBody());
        }
        syslog(LOG_INFO, $message);    
    }
}