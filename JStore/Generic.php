<?PHP
/*- 
 * Copyright (c) 2018-2023 Etienne Bagnoud <etienne@artisan-numerique.ch>
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
  protected $conf;
  protected $dbs;
  protected $signature;
  protected $lockManager;
  protected $data;
  protected $postprocessFunctions = [];
  protected $collection;
  protected $user = -1;
  protected $model;
  protected $controller;
  protected $acl = null;
  protected $response;
  protected $etag = null;
  
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

  function setEtag ($etag) {
    $this->etag = $etag;
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

  function setEtagOnCollection ($url) {
    $parts = explode('/', $url);
    array_pop($parts);
    $url = join('/', $parts);
    $this->etag->set($url);
  }

  function cacheHeaders () {
    if (!method_exists($this->model, 'getCacheOpts')) {
      $this->response->header('Cache-Control', 'no-store, max-age=0');
      return;
    }

    $copts = $this->model->getCacheOpts();
    if (!empty($copts['age']) && is_int($copts['age'])) {
      $maxage = ', max-age=' . $copts['age'];
      if ($copts['public']) {
        $this->response->header('Cache-Control', 'public' . $maxage);
        return;
      }
      $this->response->header('Cache-Control', 'private' . $maxage);
      return;
    }
    $this->response->header('Cache-Control', 'no-store, max-age=0');
  }

  function init($conf = null) {
    $this->conf = $conf;
    if ($conf->get('debug')) { 
      error_log('Start request ' . (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); 
    
    }

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
    try {
      $response = $this->response;
      if ($this->acl) {
        $this->model->setAttributeFilter($this->acl->getCurrentAttributesFilter());
      }
    
 
      $this->cacheHeaders();
      $response->header('Content-Type', 'application/json');

      if ($this->request->onItem() && method_exists($this->model, $this->request->getItem())) {
        $action = $this->request->getItem();
        $response->echo('{"data":[');
        $results = $this->model->$action($this->request);
      } else {
        $action = strtolower($this->request->getVerb()) . 'Action';
        if ($this->etag) {
          switch ($action) {
            case 'headAction':
            case 'getAction':
              $etag = $this->etag->get($this->request->getUrl());
              if (empty($etag)){ 
                $etag = $this->etag->set($this->request->getUrl());
              }
              $response->header('ETag', '"' . $etag . '"');
              break;
          }
        }
        $response->echo('{"data":[');
        $results = $this->controller->$action($this->request);
      }

      $response->echo(
        sprintf('],"success":true,"type":"results","store":"%s","idname":"%s","message":"OK","length":%d, "softErrors":%s%s}',
          $this->collection,
          $this->model->getIDName(),
          $results['count'],
          json_encode($response->getSoftErrors()),
          $this->conf->get('debug') ? ',"debug":' . $response->printDebug() : '')
      );
      $response->stop_output();

      if ($this->etag) {
        switch($action) {
          case 'postAction': 
            if ($this->request->onCollection()) { $this->etag->set($this->request->getUrl()); }
            break;
          case 'putAction':
          case 'patchAction':
            // put action can be used as post so it changes the collection itself
            if ($this->request->onCollection()) { $this->etag->set($this->request->getUrl()); break; }
            $this->etag->set($this->request->getUrl());
            $this->setEtagOnCollection($this->request->getUrl());
            break;
          case 'deleteAction':
            if ($this->request->onCollection()) { break; }
            $this->etag->delete($this->request->getUrl());
            $this->setEtagOnCollection($this->request->getUrl());
        }
      }

      $this->postprocess();
    } catch(Exception $e) {
      error_log($e->getMessage());
      $this->fail($response, $e->getMessage());
    } 
    if ($this->conf->get('debug')) { error_log('Request done'); }
    return [$this->request, $response];
  }

  function fail($response, $message, $code = 500) {
    if ($message instanceof \Exception) {
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
