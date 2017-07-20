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

class HTTPJsonRequest
{
   public $url_elements;
   public $parameters;
   public $verb;

   function __construct()
   {
      $this->verb = $_SERVER['REQUEST_METHOD'];
      $this->url_elements = array();

      $url_elements = explode('/', $_SERVER['PATH_INFO']);
      foreach($url_elements as $e) {
         if(! empty($e)) {
            $this->url_elements[] = $e;
         }
      }

      $this->parseParams();
   }

   function getVerb() {
      return $this->verb;
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
      if($this->onItem()) return $this->url_elements[1];
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
         list($name,$value) = explode('=', $i, 2);
         
         $name = urldecode($name);
         $value = urldecode($value);
         /* also deal with php array */
         $name = str_replace(array('[', ']'), '', $name);

         if( isset($arr[$name]) ) { 
            if( is_array($arr[$name]) ) { 
               $arr[$name][] = $value; 
            } else { 
               $arr[$name] = array($arr[$name], $value); 
            } 
         } else { 
            $arr[$name] = $value; 
         } 
      }

      return $arr; 
   }

   function parseParams()
   {
      $params = array();

      if(isset($_SERVER['QUERY_STRING'])) {
         $params = $this->_parse_str($_SERVER['QUERY_STRING']);
      }
      
      $content = file_get_contents('php://input');
      if($content != FALSE) {
         $json_root = json_decode($content, true);
         if($json_root) {
            foreach($json_root as $_k => $_v) {
               $params[$_k] = $_v;
            }         
         }
      }
   
      $this->parameters = $params;
   }
}
?>
