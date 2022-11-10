<?PHP
/*- 
 * Copyright (c) 2018-2022 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

use Exception;

class Generic { 
  public $db;
  public $request;
  protected $dbs;
  protected $signature;
  protected $lockManager;
  protected $data;
  protected $postprocessFunctions = [];
  
  function __construct($http_request = NULL, $dont_run = false, $options = []) {
    $this->dbs = [];
    $this->signature = null;
    $this->lockManager = null;
    $this->data = [];
    $this->response = new \artnum\JStore\Response();
    $this->acl = null;

    $this->collection = '';
    $this->user = -1;
    $this->model = null;
    $this->controller = null;

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
      } catch(\Exception $e) {
        $this->fail($this->response, $e->getMessage());
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
  private function internal ($response) {
    switch(strtolower(substr($this->request->getCollection(), 1))) {
      case 'lock':
        if (! $this->lockManager) {
          $this->fail($response, 'No lock manager');
        }
        if (! $this->request->onItem()) {
          $this->fail($response, 'Lock must have an object to lock');
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
              $this->fail($response, 'Lock only have a POST inteface');
            }
            $lockOp = array('on' => $this->request->getItem(), 'operation' => '', 'key' => '');
            foreach($lockOp as $k => $v) {
              if ($this->request->hasParameter($k)) {
                $lockOp[$k] = $this->request->getParameter($k);
              }
            }
            $lock = $this->lockManager->request($lockOp);
            if (!$lock) {
              $this->fail($response, 'Lock module fail');
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
    } catch(\Exception $e) {
      error_log('Postprocess error : ' . $e->getMessage());
    }
  }

  function getCollection() {
    return $this->collection;
  }

  function getOperation() {
    return $this->controller->getOperation($this->request);
  }

  function getOwner() {
    return $this->controller->getOwner($this->request);
  }

  function setAcl(ACL $acl) {
    $this->acl = $acl;
  }


  function init($conf = null) {
    /* run user code */
    if(!ctype_alpha($this->request->getCollection())) {
      $this->fail($this->response, 'Collection not valid');
    }

    /* prepare model */
    $this->collection = $this->request->getCollection();
    $model = '\\' . $this->collection . 'Model';
    if(!class_exists($model)) {
      $this->fail($this->response, 'Store doesn\'t exist');
    }

    $this->model = new $model(null, $conf);
    $this->model->set_response($this->response);
    $this->model->set_db($this->_db($this->model));
    $this->controller = new \artnum\JStore\HTTP($this->model, $this->response);
  }

  function run() {
    $response = $this->response;
    if ($this->acl) {
      $this->model->setAttributeFilter($this->acl->getCurrentAttributesFilter());
    }

    if (substr($this->request->getCollection(), 0, 1) == '.') {
      $ret = $this->internal($response);
      return $ret;
    } 
    
    try {
      if (method_exists($this->model, 'getCacheOpts')) {
        $copts = $this->model->getCacheOpts();
        if (!empty($copts['age']) && is_int($copts['age'])) {
          $maxage = ', max-age=' . $copts['age'];
          if ($copts['public']) {
            $response->header('Cache-Control', 'public' . $maxage);
          } else {
            $response->header('Cache-Control', 'private' . $maxage);
          }
        } else {
          $response->header('Cache-Control', 'no-store, max-age=0');
        }
      } else {
        $response->header('Cache-Control', 'no-store, max-age=0');
      }
      
      $response->header('Content-Type', 'application/json');
      $response->echo('{"data":[');
      $results = array('success' => false, 'type' => 'results', 'data' => null, 'length' => 0);
      $action = strtolower($this->request->getVerb()) . 'Action';
      $results = $this->controller->$action($this->request);

      /*if (!empty($this->model->getOperation()[0])) {
        error_log(sprintf('%d, %s: %s/%s' , time(), $this->model->getOperation()[0], $this->collection, $this->model->getOperation()[1]));
      }*/

      $reqId = $this->request->getClientReqId();
      if (!$reqId) {
        $reqId = '';
      }
      
      $response->echo(
        sprintf('],"success":true,"type":"results","store":"%s","message":"OK","length":%d}',
          $this->request->getCollection(),
          $results['count'])
      );
      $response->stop_output();

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
    } catch(Exception $e) {
      $this->fail($response, $e->getMessage());
    }
  }

  function fail($response, $message, $code = 500) {
    if ($message instanceof Exception) {
        $c = $message->getCode();
        if ($c !== 0) { $code = $c; }
        $message = $message->getMessage();
    }
    http_response_code($code);
    header('Content-Type: application/json', true);
    if ($response->isOutputClean()) {
      $response->code($code);
      $response->echo(sprintf('{"data":[],"success":false,"type":"error","message":%s,"length":0}', json_encode($message)));
    } else {
      $response->echo(sprintf('],"success":false,"type":"error","message":%s,"length":0,"http-code":%d}', json_encode($message), $code));
    }
    exit(0);
  }
}
?>
