<?PHP
/*- 
 * Copyright (c) 2018-2020 Etienne Bagnoud <etienne@artisan-numerique.ch>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
 * OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
 * SUCH DAMAGE.
 */
Namespace artnum\JStore;
define('AUTH_NONE', 0);
define('AUTH_AVAILABLE', 1);
define('AUTH_OK', 10);
class Generic { 
  public $db;
  public $request;
  protected $dbs;
  protected $auths;
  protected $session;
  protected $nosession;
  protected $signature;
  protected $lockManager;
  protected $data;
  protected $postprocessFunctions = [];
  
  function __construct($http_request = NULL, $dont_run = false, $options = []) {
    $this->dbs = [];
    $this->auths = [];
    $this->signature = null;
    $this->_tstart = microtime(true);
    $this->lockManager = null;
    $this->data = [];
    
    if(isset($options['session'])) {
      $this->session = $options['session'];
    } else {
      $this->session = new Session();
    }

    if (!empty($options['postprocess']) && is_array($options['postprocess'])) {
      foreach ($options['postprocess'] as $fn) {
        if (is_callable($fn)) {
          $this->postprocessFunctions[] = $fn;
        }
      }
    }
    
    if(is_null($http_request)) {
      try {
        $this->request = new \artnum\HTTP\JsonRequest();
      } catch(Exception $e) {
        $this->fail($e->getMessage());
      }
    } else {
      $this->request = $http_request;
    }

    if( ! $dont_run) {
      $this->run();
    }
  }

  function add_db($type, $db) {
    if(!isset($this->dbs[$type])) {
      $this->dbs[$type] = [];
    }

    $this->dbs[$type][] = $db;
  }

  function add_auth($auth_source, $user, $serversig) {
    $duplicate = false;
    $auth_source = new $auth_source($this->session, $user, $serversig);
    foreach($this->auths as $auth) {
      if(strcmp($auth->getName(), $auth_source->getName()) == 0) {
        $duplicate = true;
      }
    }

    if($duplicate) {
      return false;
    } else {
      $this->auths[] = $auth_source;
    }
    return true;
  }

  function set($name, $value) {
    switch(strtolower($name)) {
      default:
        $this->data[$name] = $value;
        break;
      case 'lockmanager':
        $this->lockManager = $value;
        break;
    }
  }

  function _db($m) {
    $type = $m->dbtype();
    if(!is_array($type)) {
      if(isset($this->dbs[$type])) {
        if(count($this->dbs[$type]) == 1) {
          return $this->dbs[$type][0];
        } else {
          return $this->dbs[$type][rand(0, count($this->dbs[$type]) - 1)];
        }
      }
    } else {
      $dbs = [];
      foreach($type as $t) {
        if(isset($this->dbs[$t])) {
          if(count($this->dbs[$t]) == 1) {
            $dbs[$t] = $this->dbs[$t][0];
          } else {
            $dbs[$t] = $this->dbs[$t][rand(0, count($this->dbs[$t]) - 1)];
          }
        }
      }

      return $dbs;
    }

    return NULL;
  }

  /* Internal query */
  private function internal () {
    switch(strtolower(substr($this->request->getCollection(), 1))) {
      case 'auth':
        if(! $this->request->onItem()) {
          $this->fail('Authentication must have an object');
        } else {
          $validation = false;
          foreach($this->auths as $auth) {
            $validation = $auth->handle($this->request);
            if($validation) {
              break;
            }
          }

          if(!$validation) {
            $this->fail('Authentication module fail');
          }
          file_put_contents('php://output', json_encode($validation));
        }
        break;
      case 'lock':
        if (! $this->lockManager) {
          $this->fail('No lock manager');
        }
        if (! $this->request->onItem()) {
          $this->fail('Lock must have an object to lock');
        } else {
          if (is_string($this->lockManager) && strcasecmp($this->lockManager, 'void') === 0) {
            switch($this->request->getParameter('operation')) {
              case 'lock':
                $lock = array('state' => 'acquired', 'key' => '-', 'timeout' => 0, 'error' => false); break;
              case 'unlock':
                $lock = array('state' => 'unlocked', 'key' => '', 'tiemout' => 0, 'error' => false); break;
              default:
              case 'state':
                $lock = array('state' => 'unlocked', 'key' => '', 'tiemout' => 0, 'error' => false); break;
            }
          } else {
            if (strtolower($this->request->getVerb()) != 'post') {
              $this->fail('Lock only have a POST inteface');
            }
            $lockOp = array('on' => $this->request->getItem(), 'operation' => '', 'key' => '');
            foreach($lockOp as $k => $v) {
              if ($this->request->hasParameter($k)) {
                $lockOp[$k] = $this->request->getParameter($k);
              }
            }
            $lock = $this->lockManager->request($lockOp);
            if (!$lock) {
              $this->fail('Lock module fail');
            }
          }
          file_put_contents('php://output', json_encode($lock));
        }
        break;
    }
  }

  /* postprocess is here to allow to take supplementary action after the request
   * has been processed and output is done.
   * Return value is not taken into account
   */
  function postprocess() {
    try {
      foreach ($this->postprocessFunctions as $fn) {
        $fn($this->request);
      }
    } catch(Exception $e) {
      error_log('Postprocess error : ' . $e->getMessage());
    }
  }

        
  private function _t() {
    header('X-Artnum-execution-us: ' . intval((microtime(true) - $this->_tstart) * 1000000));
  }

  function run() {
    $start = microtime(true);
    $this->session->start();

    /* In some case, Firefox send twice the same request. It seems that this
     * behavior is triggered by extension (in my case firebug). I have seen
     * this happen where no firebug was used so it might be linked to some
     * extension. Anyway this mitigate this strange behavior.
     */
    switch (strtolower($this->request->getVerb())) {
      case 'post': case 'put': case 'delete': case 'patch':
        $history = $this->session->get('req-history');
        if (!$history) {
          $history = array($this->request->getId() => $start);
        } else {
          if (isset($history[$this->request->getId()])) {
            error_log('Duplicate request with id : ' . $this->request->getId());
            return 0;
          }

          $history[$this->request->getId()] = $start;
        }

        foreach($history as $k => $v) {
          /* 15 min */
          if (round($start - floatval($v)) > 900)  {
            unset($history[$k]);
          }
        }

        $this->session->set('req-history', $history);
        break;
    }

    if (substr($this->request->getCollection(), 0, 1) == '.') {
      $ret = $this->internal();
      $this->session->close();
      return $ret;
    } 

    /* run user code */
    if(!ctype_alpha($this->request->getCollection())) {
      $this->fail('Collection not valid');
    }

    /* authorize request, if available 

     */
    $authorize = AUTH_NONE;
    foreach($this->auths as $auth) {
      if($auth->verify($this->request)) {
        $authorize = AUTH_AVAILABLE;
        if($auth->authorize($this->request)) {
          $this->fail('Forbidden', 403);
        } else {
          $authorize = AUTH_OK;
        }
        break;
      }
    }
    if ($authorize != AUTH_NONE && $authorize != AUTH_OK) {
      $this->fail('Forbidden', 403);
    }

    /* prepare model */
    $model = '\\' . $this->request->getCollection() . 'Model';
    if(!class_exists($model)) {
      $this->fail('Store doesn\'t exist');
    }
    
    try {
      $model = new $model(NULL, NULL);
      $model->set_db($this->_db($model));
      $controller = new \artnum\JStore\HTTP($model, NULL);
      $this->session->close();
      
      $results = array('success' => false, 'type' => 'results', 'data' => null, 'length' => 0);
      $action = strtolower($this->request->getVerb()) . 'Action';
      $results = $controller->$action($this->request);
      if ($results['success']) {
        $results['msg'] = 'OK';
      }
      switch(strtolower($this->request->getVerb())) {
        default:
          if (isset($results['result'])) {
            $body = json_encode(array(
              'success' => $results['success'],
              'type' => 'results',
              'message' => $results['msg'],
              'data' => $results['result']->getItems(),
              'length' => $results['result']->getCount()
            ));
          } else {
            $body = json_encode(array(
              'success' => $results['success'],
              'type' => 'results',
              'message' => $results['msg'],
              'data' => $results['data'][0],
              'length' => $results['data'][1]
            ));
          }

          $reqId = $this->request->getClientReqId();
          if ($reqId) {
            header('X-Request-ID: ' . $reqId);
          } else {
            $reqId = '';
          }

          $this->_t();
          file_put_contents('php://output', $body);
          $this->postprocess();
          if (isset($results['result'])) {
            if ($results['result']->countError() > 0) {
              foreach ($results['result']->getError() as $error) {
                error_log(sprintf('%d ReqID[%s]/%s/%s@%s:%s +%s+',
                                  $error['time'],
                                  $reqId,
                                  $this->request->url_elements[0],
                                  isset($this->request->url_elements[1]) ? $this->request->url_elements[1] : '',
                                  $error['file'],
                                  $error['line'],
                                  addslashes($error['message'])), 0);
              }
            }
          }
          break;
        case 'head':
          foreach($results as $k => $v) {
            header('X-Artnum-' . $k . ': ' . $v);
          }
          if (!isset($results['exists']) || !$results['exists']) {
            \artnum\HTTP\Response::code(404);
          }
          $this->_t();
          $this->postprocess();
          break;
      }
    } catch(Exception $e) {
      $this->fail($e->getMessage());
    }
  }

  function fail($message, $code = 500) {
    if(!is_string($message)) {
      $message = $message->getMessage();
    }
    \artnum\HTTP\Response::code($code); 
    $body = json_encode([
      'success' => false,
      'type' => 'error',
      'message' => $message,
      'data' => [],
      'length' => 0]);
    $this->_t();
    file_put_contents('php://output', $body);
    exit(-1); 
  }
}
?>
