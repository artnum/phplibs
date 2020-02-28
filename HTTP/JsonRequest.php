<?PHP
/*- 
 * Copyright (c) 2017 - 2020 Etienne Bagnoud <etienne@artnum.ch>
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
Namespace artnum\HTTP;

class JsonRequest extends Path
{
  public $parameters;
  public $verb;
  public $protocol;
  public $client;
  public $multiple;
  public $items;
  public $http_headers = array();
  public $clientReqId = null;
  private $hashCtx;

  function __construct()
  {
    parent::__construct();

    /* this work with Collection/Item only. Item as a path is useful, so
     * join back Collection/i/t/em into [Collection][i/t/ek]
     */
    if (count($this->url_elements) > 2) {
      $collection = array_shift($this->url_elements);
      $item = implode('/', $this->url_elements);
      $this->url_elements = array($collection, $item);
    }

    /* fix algo to sha256 */
    $this->hashCtx = hash_init('sha256');

    $this->multiple = false;
    $this->items = array();
    $this->verb = $_SERVER['REQUEST_METHOD'];
    $this->protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
    $this->client = $_SERVER['REMOTE_ADDR'];

    hash_update($this->hashCtx, $this->verb);
    hash_update($this->hashCtx, $this->protocol);
    hash_update($this->hashCtx, $this->client);

    $this->parseParams();

    if ($this->onItem()) {
      if ($this->getItem()[0] == '|') {
        $this->items = explode('|', substr($this->getItem(), 1));
        $this->multiple = true;
      }
    }

    foreach ($_SERVER as $k => $v) {
      switch ($k) {
        case 'HTTP_X_ARTNUM_REQID':
          /* use X-Request-Id if available, X-Artnum-ReqID is obsolete */
          if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) { break; } 
        case 'HTTP_X_REQUEST_ID':
          hash_update($this->hashCtx, 'X-Request-Id: '. $_SERVER['HTTP_X_REQUEST_ID']);
          $this->clientReqId = $_SERVER['HTTP_X_REQUEST_ID'];
          break;
      }
      if (substr_compare($k, 'HTTP', 0, 4, TRUE) === 0) {
        $k = strtolower(str_replace('_', '-', substr($k, 0, 5)));
        hash_update($this->hashCtx, $k . ': ' .$v);
        $this->http_headers[$k] = $v;
      }
    }

    $this->reqid = hash_final($this->hashCtx, FALSE);
  }

  function getId() {
    return bin2hex($this->reqid);
  }

  function getRawId() {
    return $this->reqid;
  }

  function getClient()  {
    return $this->client;
  }

  function getProtocol() {
    return $this->protocol;
  }

  function getVerb() {
    return $this->verb;
  }

  function getClientReqId() {
    return is_null($this->clientReqId) ? false : base64_encode($this->clientReqId);
  }

  function onCollection() {
    if(count($this->url_elements) == 1) return true;
    return false;
    
  }

  function onItem() {
    if(count($this->url_elements) == 2) return true;
    return false;
  }

  function getItem() {
    if ($this->onItem()) {
      if ($this->multiple) {
        return $this->items;
      } else {
        return $this->url_elements[1];
      }
    }
    return NULL; 
  }

  function getCollection() {
    if($this->onCollection() or $this->onItem()) return $this->url_elements[0];
    return NULL;
  }
  
  function hasParameters()
  {
    if(count($this->parameters) > 0) return true;
    return false;
  }

  function hasParameter($name) {
    if(isset($this->parameters[$name])) {
      return true;
    }
    return false;
  }

  function getParameter($name) {
    if($this->hasParameter($name)) {
      return $this->parameters[$name];
    }
    return NULL;
  }

  function getParameters() {
    return $this->parameters;
  }

  function setParameter($name, $value) {
    $this->parameters[$name] = $value;
  }

  function _parse_str($str) {
    $arr = array();

    if(empty($str)) return array();

    $pairs = explode('&', $str);
    
    if($pairs == FALSE) return array();
    if(empty($pairs)) return array();
    
    foreach ($pairs as $i) {
      /* to support flag we look for '=' if there's none it's a flag
       * and flag can be negated with ! at pos 0
       */
      list($name,$value) = strpos($i, '=') === FALSE ? [$i, !(strpos($i, '!') === 0)] : explode('=', $i, 2);

      if (strlen($name) > 1 && strpos($name, '!') === 0) {
        $name = substr($name, 1);
      }
      $name = urldecode($name);
      if (is_string($value)) { $value = urldecode($value); }

      if (strcmp($name, '_qid') === 0) {
        $this->clientReqId = $value;
        continue;
      }

      /* Special case. When item is identified by a path ( /my/item/id ), a parameter name '!' can be used :
         https://example.com/store/Collection/?!=/my/item/id
       */
      if($name == '!') {
        $this->url_elements[] = $value;
        continue;
      }

      /* also deal with php array */
      $name = str_replace(array('[', ']'), '', $name);

      $group = '';
      if(strstr($name, '.')) {
        list ($group, $name) = explode('.', $name, 2);
      }

      $ref =& $arr;
      if(!empty($group)) {
        
        if(!isset($arr[$group])) { 
          $arr[$group] = array();
        }

        $ref =& $arr[$group];
      }

      if( isset($ref[$name]) ) { 
        if( is_array($ref[$name]) ) { 
          $ref[$name][] = $value; 
        } else { 
          $ref[$name] = array($ref[$name], $value); 
        } 
      } else { 
        $ref[$name] = $value; 
      } 
    }

    return $arr; 
  }

  function parseParams()
  {
    $params = array();

    if(isset($_SERVER['QUERY_STRING'])) {
      hash_update($this->hashCtx, $_SERVER['QUERY_STRING']);
      $params = $this->_parse_str($_SERVER['QUERY_STRING']);
    }

    $content = file_get_contents('php://input');

    if($content != FALSE) {
      hash_update($this->hashCtx, $content);
      if(isset($_SERVER['CONTENT_TYPE']) && strcmp($_SERVER['CONTENT_TYPE'], "application/x-www-form-urlencoded") == 0) {
        foreach($this->_parse_str($content) as $_k => $_v) {
          if(!empty($params[$_k])) {
            if(is_array($params[$_k])) {
              $params[$_k][] = $_v;
            } else {
              $params[$_k] = array($params[$_k], $_v);
            }
          } else {
            $params[$_k] = $_v;
          }
        }
      }  else {
        $json_root = json_decode($content, true);
        if($json_root) {
          foreach($json_root as $_k => $_v) {
            if (strcmp($_k, '_qid') === 0) {
              if (is_string($_v)) {
                $this->clientReqId = $_v;
              }
              continue;
            }
            $params[$_k] = $_v;
          }         
        }
      }
    }
    
    $this->parameters = $params;
  }
}
?>
