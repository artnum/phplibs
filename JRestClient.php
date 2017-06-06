<?PHP
class JRestClient {

   protected $url;
   protected $collection;
   protected $ch;
   protected $headers;
   protected $body_raw;
   protected $http_code;

   function __construct($url, $collection = NULL) {
      $this->url = $url;
      $this->collection = $collection;
   }
   
   protected function _init($url = NULL) {
      $this->ch = curl_init();
      if(is_null($url)) {
         $url = $this->url;
      }
      $this->headers = array();
      curl_setopt($this->ch, CURLOPT_URL, $url);
      curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($this->ch, CURLOPT_HEADER, 1);
   }

   protected function _build_url($elements = array(), $collection = NULL, $url = NULL) {
      $urls = array();
      if(is_null($url)) {
         $urls[] = $this->url;
      } else {
         $urls[] = $url;
      }
     
      if(is_null($collection)) {
         if(!is_null($this->collection)) {
            $urls[] = rawurlencode($this->collection);
         }
      } else {
         $urls[] = rawurlencode($collection);
      }

      if(!empty($elements)) {
         foreach($elements as $e) {
            $urls[] = rawurlencode($e);
         }
      }

      return join('/', $urls) . '/';
   }

   function setCollection($collection) {
      $this->collection = $collection;
   }

   protected function exec() {
      return $this->_return(curl_exec($this->ch)); 
   }

   function put($data, $id, $collection = NULL) {
      $jdata = json_encode($data);
      $this->_init($this->_build_url(array($id), $collection));
      curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, $jdata);
      curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
               'Content-Type: application/json',
               'Content-Length: ' . strlen($jdata),
               'Content-MD5: ' . md5($jdata)));
      return $this->exec();
   }

   function post($data, $collection = NULL) {
      $jdata = json_encode($data);
      $this->_init($this->_build_url(NULL, $collection));
      curl_setopt($this->ch, CURLOPT_POST, TRUE);
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, $jdata);
      curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
               'Content-Type: application/json',
               'Content-Length: ' . strlen($jdata),
               'Content-MD5: ' . md5($jdata)));
      return $this->exec();
   }

   function get($id, $collection = NULL) {
      $this->_init($this->_build_url(array($id), $collection));
      curl_setopt($this->ch, CURLOPT_HTTPGET, TRUE);
      return $this->exec();
   }

   function getCollection($collection = NULL) {
      $this->_init($this->_build_url(NULL, $collection));
      curl_setopt($this->ch, CURLOPT_HTTPGET, TRUE);
      return $this->exec();
   }

   function search($search = array(), $collection = NULL) {
      if(empty($search)) {
         return $this->getCollection($collection);
      }

      $query_elements = array();
      foreach($search as $k => $v) {
         if(is_string($v)) {
            $query_elements[] = urlencode($k) . '=' . urlencode($v);
         } else if(is_array($v)) {
            foreach($v as $_v) {
               $query_elements[] = urlencode($k) . '=' . urlencode($_v);
            }
         }
      }
      $query_string = implode('&', $query_elements);

      $this->_init($this->_build_url(NULL, $collection) . '?' . $query_string);
      curl_setopt($this->ch, CURLOPT_HTTPGET, TRUE);
      return $this->exec();
   }

   protected function _parse_header($header_txt) {
      $headers = explode("\n", $header_txt);
      foreach($headers as $h) {
         $h = trim($h);

         if(empty($h)) { continue; } 
         if(substr($h, 0, 4) == 'HTTP') { continue; } 

         list($name, $value) = explode(':', $h, 2);
         $name = trim($name);
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
      switch(strtolower($what)) {
         case 'collection': return $this->collection;
         case 'http_code': return intval($this->http_code);
         case 'body_raw': return $this->body_raw;
         case 'body_sum': return md5($this->body_raw);
      }

      if(isset($this->headers[$what])) {
         return $this->headers[$what];
      }

      return null;
   }

   protected function _return($txt) {
      $this->http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
      $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);

      $header_txt = substr($txt, 0, $header_size);
      $this->body_raw = substr($txt, $header_size);

      curl_close($this->ch);
      $this->_parse_header($header_txt);

      if(isset($this->header['Content-MD5'])) {
         if(strcasecmp($this->getVar('body_sum'), $this->getVar('Content-MD5')) != 0) {
            echo 'MD5 Fail' . PHP_EOL;
         }
      }

      $json = json_decode($this->getVar('body_raw'), TRUE);
      
      switch($this->getVar('http_code')) {
         default:
            return false;
         case 200:
            return $json;
      }
   }
}
?>
