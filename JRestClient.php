<?PHP
/*- 
 * Copyright (c) 2017 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

class JRestClient {

   protected $url;
   protected $effectiveURL;
   protected $method;
   protected $collection;
   protected $ch;
   protected $headers;
   protected $queryHeaders;
   protected $body_raw;
   protected $http_code;
   protected $tls;

   function __construct($url, $collection = NULL, $tls = array()) {
      $this->url = $url;
      $this->collection = $collection;
      $this->tls = $tls;
      $this->authData = null;
   }
  
   protected function _tls() {
      if(isset($this->tls['verifypeer']) && !$this->tls['verifypeer']) {
         \curl_setopt($this->ch, \CURLOPT_SSL_VERIFYPEER, false);   
      } else {
         \curl_setopt($this->ch, \CURLOPT_SSL_VERIFYPEER, true);   
      }
   }

   protected function _init($url = NULL) {
      $this->ch = \curl_init();
      if(\is_null($url)) {
         $url = $this->url;
      }
      $this->headers = [];
      $this->queryHeaders = [];
      $this->effectiveURL = $url;
      \curl_setopt($this->ch, \CURLOPT_URL, $url);
      \curl_setopt($this->ch, \CURLOPT_RETURNTRANSFER, true);
      \curl_setopt($this->ch, \CURLOPT_HEADER, 1);
      $this->_tls();
   }

   protected function _build_url($elements = array(), $collection = NULL, $url = NULL) {
      $urls = array();
      if(\is_null($url)) {
         $urls[] = $this->url;
      } else {
         $urls[] = $url;
      }
     
      if(\is_null($collection)) {
         if(!\is_null($this->collection)) {
            $urls[] = \rawurlencode($this->collection);
         }
      } else {
         $urls[] = \rawurlencode($collection);
      }

      if(!empty($elements)) {
         foreach($elements as $e) {
            $urls[] = \rawurlencode($e);
         }
      }
      return \join('/', $urls) . '/';
   }

   function setCollection($collection) {
      $this->collection = $collection;
   }

   function setAuth ($auth) {
      $this->authData = $auth;
   }

   protected function exec() {
      $xreqid = false;
      if (count($this->queryHeaders) > 0) {
         $headers = [];
         foreach($this->queryHeaders as $k => $v) {
            $headers[] = "$k: $v";
            if (strtolower($k) === 'x-request-id') { $xreqid = true; }
         }
      }

      if (!$xreqid) {
         $headers[] = 'X-Request-Id: ' . uniqid();
      }
      if ($this->authData) {
         $headers[] = 'Authorization: ' . $this->authData;
      }
      curl_setopt($this->ch, \CURLOPT_HTTPHEADER, $headers);

      $ret = curl_exec($this->ch);
      $this->error = curl_error($this->ch);
      return $this->_return($ret);
   }

   function error () {
      return $this->error;
   }
   /* Add an element */
   function put($data, $id, $collection = NULL) {
      $this->method = 'PUT';
      $jdata = \json_encode($data);
      $this->_init($this->_build_url(array($id), $collection));
      \curl_setopt($this->ch, \CURLOPT_CUSTOMREQUEST, 'PUT');
      \curl_setopt($this->ch, \CURLOPT_POSTFIELDS, $jdata);
      $this->queryHeaders = [
               'Content-Type' => 'application/json',
               'Content-Length' => \strlen($jdata),
               'Content-MD5' => \md5($jdata)
            ];
      return $this->exec();
   }

   /* Update an element */
   function post($data, $collection = NULL) {
      $this->method = 'POST';
      $jdata = \json_encode($data);
      $this->_init($this->_build_url(NULL, $collection));
      \curl_setopt($this->ch, \CURLOPT_POST, TRUE);
      \curl_setopt($this->ch, \CURLOPT_POSTFIELDS, $jdata);
      $this->queryHeaders = [
            'Content-Type' => 'application/json',
            'Content-Length' => \strlen($jdata),
            'Content-MD5' => \md5($jdata)
         ];
      return $this->exec();
   }

   /* Edit an element */
   function patch($data, $id, $collection = NULL) {
      $this->method = 'PATCH';
      $jdata = \json_encode($data);
      $this->_init($this->_build_url(array($id), $collection));
      \curl_setopt($this->ch, \CURLOPT_CUSTOMREQUEST, 'PATCH');
      \curl_setopt($this->ch, \CURLOPT_POSTFIELDS, $jdata);
      $this->queryHeaders = [
               'Content-Type' => 'application/json',
               'Content-Length' => \strlen($jdata),
               'Content-MD5' => \md5($jdata)
            ];
      return $this->exec();
   }

   function direct($url) {
      $this->method = 'GET';
      $this->_init($url);
      \curl_setopt($this->ch, \CURLOPT_HTTPGET, TRUE);
      return $this->exec();
   }

   /* Get an element */
   function get($id, $collection = NULL) {
      $this->method = 'GET';
      $this->_init($this->_build_url(array($id), $collection));
      \curl_setopt($this->ch, \CURLOPT_HTTPGET, TRUE);
      return $this->exec();
   }

   /* Delete an element */
   function delete($id, $collection = NULL) {
      $this->method = 'DELETE';
      $this->_init($this->_build_url(array($id), $collection));
      \curl_setopt($this->ch, \CURLOPT_CUSTOMREQUEST, 'DELETE');
      return $this->exec();
   }
  
   /* Get all entry from a collection */ 
   function getCollection($collection = NULL) {
      $this->method = 'GET';
      $this->_init($this->_build_url(NULL, $collection));
      \curl_setopt($this->ch, \CURLOPT_HTTPGET, TRUE);
      return $this->exec();
   }

   /* Search in a collection, if no search term, return whole collection */
   function search($search = array(), $collection = NULL) {
      $this->method = 'GET';
      if(empty($search)) {
         return $this->getCollection($collection);
      }

      $query_elements = array();
      foreach($search as $k => $v) {
         if(\is_string($v)) {
            $query_elements[] = \urlencode($k) . '=' . \urlencode($v);
         } else if(is_array($v)) {
            foreach($v as $_v) {
               $query_elements[] = \urlencode($k) . '=' . \urlencode($_v);
            }
         }
      }
      $query_string = \implode('&', $query_elements);

      $this->_init($this->_build_url(NULL, $collection) . '?' . $query_string);
      \curl_setopt($this->ch, \CURLOPT_HTTPGET, TRUE);
      return $this->exec();
   }

   protected function _parse_header($header_txt) {
      $headers = \explode("\n", $header_txt);
      foreach($headers as $h) {
         $h = \trim($h);

         if(empty($h)) { continue; } 
         if(\substr($h, 0, 4) == 'HTTP') { continue; } 

         list($name, $value) = \explode(':', $h, 2);
         $name = \trim($name);
         if(isset($this->headers[$name])) {
            if(is_array($this->headers[$name])) {
               $this->headers[$name][] = $value;
            } else {
               $this->headers[$name] = array($this->headers, $value);
            }
         } else {
            $this->headers[$name] = $value;
         }
      }
   }

   function getVar($what) {
      switch(\strtolower($what)) {
         case 'collection': return $this->collection;
         case 'http_code': return \intval($this->http_code);
         case 'body_raw': return $this->body_raw;
         case 'body_sum': return \md5($this->body_raw);
      }

      if(isset($this->headers[$what])) {
         return $this->headers[$what];
      }

      return null;
   }

   protected function _return($txt) {
      $this->http_code = \curl_getinfo($this->ch, \CURLINFO_HTTP_CODE);
      $header_size = \curl_getinfo($this->ch, \CURLINFO_HEADER_SIZE);

      $header_txt = \substr($txt, 0, $header_size);
      $this->body_raw = \substr($txt, $header_size);
      \curl_close($this->ch);
      $this->_parse_header($header_txt);
      if(isset($this->header['Content-MD5'])) {
         if(\strcasecmp($this->getVar('body_sum'), $this->getVar('Content-MD5')) != 0) {
            echo 'MD5 Fail' . PHP_EOL;
         }
      }

      $json = \json_decode($this->getVar('body_raw'), TRUE);
      switch($this->getVar('http_code')) {
         default:
            return false;
         case 200:
            return $json;
      }
   }
}
?>
