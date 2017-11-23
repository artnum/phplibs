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
Namespace artnum\JStore;

class Generic { 
   public $db;
   public $request;
   protected $dbs;

   function __construct($http_request = NULL, $dont_run = false) {
      $this->dbs = array();
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
         $this->dbs[$type] = array();
      }

      $this->dbs[$type][] = $db;
   }

   function _db($m) {
      $type = $m->dbtype();
      if(isset($this->dbs[$type])) {
         if(count($this->dbs[$type]) == 1) {
            return $this->dbs[$type][0];
         } else {
            return $this->dbs[$type][rand(0, count($this->dbs[$type]) - 1)];
         }
      }

      return NULL;
   }


   function run() {
      if(ctype_alpha($this->request->getCollection())) {
         $model = '\\' . $this->request->getCollection() . 'Model';

         if(class_exists($model)) {
            try {
               $model = new $model(NULL, NULL);
               $model->set_db($this->_db($model));
            } catch(Exception $e) {
               $this->fail($e->getMessage());
            }
         
            $controller = '\\' . $this->request->getCollection() . 'Controller';
            if(! class_exists($controller)) {
               $controller = '\\artnum\HTTPController';
            }
            try {
               $controller = new $controller($model, NULL);
            } catch(Exception $e) {
               $this->fail($e->geMessage());
            }
      
            try {
               $action = strtolower($this->request->getVerb()) . 'Action';
               $results = $controller->$action($this->request);
               if(! $results) {
                  $results = array(); 
               }
               file_put_contents('php://output',
                     json_encode(array('type' => 'results', 'data' => $results)));
            } catch(Exception $e) {
               $this->fail($e->getMessage());
            }
         } else {
            $this->fail('Store doesn\'t exist');
         }
      } else {
         $this->fail('Collection not valid');
      }
   }

   function fail($message) {
      if(!is_string($message)) {
         $message = $message->getMessage();
      }
      \artnum\HTTP\Response::code(500); 
      file_put_contents('php://output', '{ type: "error", message: "' . $message . '"}');
      exit(-1); 
   }


}
?>
