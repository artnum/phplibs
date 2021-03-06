<?PHP
/*- 
 * Copyright (c) 2017 - 2020 Etienne Bagnoud <etienne@artisan-numerique.ch>
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
Namespace artnum;

class HTTPController extends \artnum\HTTP\CORS
{
  protected $Model;

  function __construct($model) {
    $this->Model = $model;
  }

  function headAction($req) {
    try {
      if($req->onCollection()) {
        return array(
          'last-id' => $this->Model->getLastId($req->getParameters()),
          'last-modification' => $this->Model->getTableLastMod());
      } else if($req->onItem()) {
        if($this->Model->exists($req->getItem())) {
          return array('last-modification' => $this->Model->getLastMod($req->getItem()),
                       'deleted' => $this->Model->getDeleteDate($req->getItem()), 'exists' => 1);
        } else {
          return array('error' => 'Does not exist', 'exists' => 0);
        }
      }
    } catch(Exception $e) {
      return array('error' => $e->getMessage());
    }
  }

  function getAction($req) {
    $retVal = array(
      'success' => false,
      'result' => new JStore\Result(),
      'msg' => ''
    );
    $run = 0;
    try {
      do {
        $continue = false;
        if($req->onCollection()) {
          $results = $this->Model->listing($req->getParameters());
        } else {
          $skip = false;
          $item = $req->getItem();
          if (substr($item, 0, 1) === '.') {
            $method = 'get' . ucfirst(strtolower(substr($item, 1)));
            if (method_exists($this->Model, $method)) {
              $results = $this->Model->$method($req->getParameters());
              $skip = true;
            }
          }
          if (!$skip) {
            if (!$req->multiple) {
              $results = $this->Model->read($req->getItem());
            } else {
              if (method_exists($this->Model, 'readMultiple')) {
                $results = $this->Model->readMultiple($req->getItem());
              } else {
                foreach ($req->getItem() as $item) {
                  $r = $this->Model->read($item);
                  if (is_array($r)) {
                    foreach ($r[0] as $i) {
                      $retVal['result']->addItem($i);
                    }
                  } else {
                    foreach ($r->getItems() as $i) {
                      $retVal['result']->addItem($i);
                    }
                  }
                }
              }
            }
          }
        }
        if($run < 15 && $req->getParameter('long') && $results[1] == 0) {
          $continue = true;
          sleep(1);
          $run++;
        }
      } while($continue);
      $retVal['success'] = true;
      if (is_array($results)) {
        $retVal['result']->setItems($results[0]);
        $retVal['result']->setCount($results[1]);
      } else {
        $retVal['result'] = $results;
      }
    } catch(Exception $e) {
      $retVal['msg'] = $e->getMessage();
    }
    return $retVal;
  }

  function patchAction ($req) {
    $retVal = array(
      'success' => false,
      'result' => new JStore\Result(),
      'msg' => 'No element selected'
    );
    if (!$req->onCollection()) {
      try {
        $result = $this->Model->write($req->getParameters(), $req->getItem());
        if ($result) {
          $retVal['success'] = true;
          if (is_array($result)) {
            $retVal['result']->setItems($result[0]);
            $retVal['result']->setCount($result[1]);
          } else {
            $retVal['result'] = $result;
          }
        } else {
          $retVal['msg'] = 'Write failed';
        }
      } catch(Exception $e) {
        $retVal['msg'] = 'Write failed';
      }
    }
    return $retVal;
  }

  function putAction($req) {
    if(!$req->onCollection()) {
      return $this->postAction($req);
    }
    return array('success' => false, 'msg' => 'No element selected', 'result' => new JStore\Result());
  }

  function postAction($req) {
    $retVal = array(
      'success' => false,
      'msg' => 'Generic error',
      'result' => new JStore\Result());
    if ($req->onItem() && $req->getItem() === '.search') {
      $ret = $this->Model->search($req->getParameters());
      if ($ret) {
        $retVal['success'] = true;
        if (is_array($result)) {
          $retVal['result']->setItems($result[0]);
          $retVal['result']->setCount($result[1]);
        } else {
          $retVal['result'] = $result;
        }
      }
    } else {
      try {
        $result = $this->Model->overwrite($req->getParameters(), $req->getItem());
        if ($result) {
          $retVal['success'] = true;
          if (is_array($result)) {
            $retVal['result']->setItems($result[0]);
            $retVal['result']->setCount($result[1]);
          } else {
            $retVal['result'] = $result;
          }
        } else {
          $retVal['msg'] = 'Write failed';
        }
      } catch(Exception $e) {
        $retVal['msg'] = $e->getMessage();
      }
    }
    return $retVal;
  }
  
  function deleteAction($req) {
    $retVal = array(
      'success' => false,
      'msg' => 'No element selected',
      'result' => new JStore\Result());
    if(!$req->onCollection()) {
      try {
        $result = $this->Model->delete($req->getItem());
        $retVal['success'] = true;
        if (is_array($result)) {
          $retVal['result']->setItems(array($req->getItem()));
          $retVal['result']->setCount(1);
        } else {
          $retVal['result'] = $result;
        }
      } catch(Exception $e) {
        $retVal['msg'] = $e->getMessage();
      }
    }
    return $retVal;
  }
}

?>
