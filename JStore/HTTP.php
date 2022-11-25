<?PHP
/*- 
 * Copyright (c) 2017 - 2022 Etienne Bagnoud <etienne@artisan-numerique.ch>
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
use \Exception;

class HTTP extends \artnum\HTTP\CORS
{
  protected $Model;

  function __construct($model, $response) {
    parent::__construct();
    $this->Model = $model;
    $this->response = $response;
  }

  function optionsAction() {
    $this->response->clear_output();
    parent::optionsAction();
    $this->response->start_output();
    $body = ['options' => []];
    if (method_exists($this->Model, 'getOptions')) {
      $body['options'] = $this->Model->getOptions();
    }
    $this->response->print($body);
    $this->response->stop_output();
    return ['count' => 0];
  }

  function headAction($req) {
    $this->response->clear_output();
    $this->setCorsHeaders();
    try {
      if($req->onCollection()) {
        $this->response->header('JSTORE-Last-Id', method_exists($this->Model, 'getLastId') ? $this->Model->getLastId($req->getParameters()) : -1);
        $this->response->header('JSTORE-Last-Modification', method_exists($this->Model, 'getTableLastMod') ? $this->Model->getTableLastMod() : -1);
      } else if($req->onItem()) {
        if($this->Model->exists($req->getItem())) {
          $this->response->header('JSTORE-Last-Modification', method_exists($this->Model, 'getLastMod') ? $this->Model->getLastMod($req->getItem()) : -1);
          $this->response->header('JSTORE-Deleted', method_exists($this->Model, 'getDeleteDate') ? $this->Model->getDeleteDate($req->getItem()) : -1);
          $this->response->header('JSTORE-Exists', 1);
        } else {
          $this->response->code(404);
          $this->response->header('JSTORE-Error', 'Does not exist');
          $this->response->header('JSTORE-Exists', 0);
        }
      }
    } catch(\Exception $e) {
      $this->response->header('JSTORE-Error', $e->getMessage());
    } finally {
      $this->response->output();
      return ['count' => 0];
    }
  }

  function getOperation($req) {
    switch($req->getVerb()) {
      case 'POST':
        if ($req->onCollection()) { return ACL::LEVEL_CREATE; }
        if ($req->onItem() && $req->getItem() === '_query') { return ACL::LEVEL_SEARCH; }
        if ($req->onItem() && $req->getItem() === '_restore') { return ACL::LEVEL_CREATE; }
        if ($req->onItem()) { return ACL::LEVEL_UPDATE; }
        return ACL::LEVEL_CREATE;
      case 'PUT': // fall through
      case 'PATCH':
        if ($req->onItem()) { return ACL::LEVEL_UPDATE; }
        return ACL::LEVEL_CREATE;
      case 'DELETE':
        return ACL::LEVEL_DELETE;
      case 'GET':
        if ($req->onCollection()) { return ACL::LEVEL_SEARCH; }
        return ACL::LEVEL_READ;
      case 'HEAD':
        return ACL::LEVEL_READ;
      case 'OPTIONS':
        return ACL::LEVEL_ANY;
    }
  }

  function getOwner($req) {
    $id = null;
    if ($req->onItem()) { $id = $req->getItem(); }
    if ($id === '_query') { $id = null; }
    return $this->Model->get_owner($req->getParameters(), $id);
  }

  function getAction($req) {
    $this->setCorsHeaders();
    if($req->onCollection()) {
      return $this->Model->listing($req->getParameters());
    } 
        
    $item = $req->getItem();
    if (substr($item, 0, 1) === '.') {
      $method = 'get' . ucfirst(strtolower(substr($item, 1)));
      if (method_exists($this->Model, $method)) {
        return $this->Model->$method($req->getParameters());
      }
    }

    return $this->Model->read($req->getItem());      
  }

  function patchAction ($req) {
    $this->setCorsHeaders();
    if($req->onCollection()) { throw new Exception('Not available'); }
    $id = $req->getItem();
    return $this->Model->write($req->getParameters(), $id);
  }

  function putAction($req) {
    $this->setCorsHeaders();
    if($req->onCollection()) { throw new Exception('Not available'); }
    return $this->postAction($req);
  }

  function postAction($req) {
    $this->setCorsHeaders();
    if ($req->onItem()) {
      $item = $req->getItem();
      switch($item) {
        case '.search':
          return $this->Model->search($req->getParameters());
        case '_query': 
          if (!is_callable([$this->Model, 'query'])) { 
            throw new Exception('Not available', 501);
          }
          return $this->Model->search($req->getBody(), $req->getParameters());
        case '_restore':
          if (!is_callable([$this->Model, 'restore'])) {
            throw new Exception('Not available', 501);
          }
          return $this->Model->restore($req->getBody(), $req->getParameters());
        default: 
          if (substr($item, 0, 1) === '.') {
            $method = 'get' . ucfirst(strtolower(substr($item, 1)));
            if (!method_exists($this->Model, $method)) {
              throw new Exception('Not available');
            }
            return $this->Model->$method($req->getParameters());
          }
      }     
      return $this->Model->overwrite($req->getParameters(), $item);

    }
    return $this->Model->write($req->getParameters());
  }
  
  function deleteAction($req) {
    $this->setCorsHeaders();
    if($req->onCollection()) { throw new Exception('Not available'); }
    $item = $req->getItem();
    return $this->Model->delete($item);         
  }
}

?>
