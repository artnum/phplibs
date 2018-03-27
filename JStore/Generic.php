<?PHP
/*- 
 * Copyright (c) 2018 Etienne Bagnoud <etienne@artisan-numerique.ch>
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
   protected $auths;
   protected $authpath;
   protected $session;
   protected $nosession;

   function __construct($http_request = NULL, $dont_run = false, $options = array()) {
      $this->dbs = array();
      $this->auths = array();
      $this->authpath = 'auth';

      if(isset($options['session'])) {
         $this->session = $options['session'];
      } else {
         $this->session = new Session();
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
         $this->dbs[$type] = array();
      }

      $this->dbs[$type][] = $db;
   }

   function add_auth($auth_source) {
      $duplicate = false;
      foreach($this->auths as $auth) {
         if(strcmp($auth->getName(), $auth_source->getName()) == 0) {
            $duplicate = true;
         }
      }

      if($duplicate) {
         return false;
      } else {
         array_push($this->auths, $auth_source);
      }
      return true;
   }

   function set_authpath($path) {
      if(ctype_alpha($path)) {
         $this->authpath = $path;
      }
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
      $this->session->start();

      if(ctype_alpha($this->request->getCollection())) {
         if(strcmp($this->request->getCollection(), $this->authpath) == 0) {
            if(! $this->request->onItem()) {
               $this->fail('Authentication must have an object');
            } else {
               $validation = false;
               foreach($this->auths as $auth) {
                  $validation = $auth->authenticate($this->request);
                  if($validation) {
                     break;
                  }
               }

               if(!$validation) {
                  $this->fail('Not valid');
               }
               setcookie('artnum-authid', $validation, time() + 3600, '/');
            }
         } else {
            $valid_auth = null;
            foreach($this->auths as $auth) {
               if($auth->verify($this->request)) {
                  $valid_auth = $auth;
                  break;
               }
            }
            if( ! is_null($valid_auth) && count($this->auths) > 0) {
               if(! $valid_auth && count($this->auths) > 0) {
                  $this->fail('Forbidden', 403);
               } else {
                  if(! $auth->authorize($this->request)) {
                     $this->fail('Forbidden', 403);
                  }
               }
            }

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
                  switch(strtolower($this->request->getVerb())) {
                  default:
                        header('Content-Encoding: gzip');
                        file_put_contents('php://output', 
                           gzencode( 
                              json_encode(array('type' => 'results', 'data' => $results))
                           )
                        );
                        break;
                     case 'head':
                        foreach($results as $k => $v) {
                           header('X-Artnum-' . $k . ': ' . $v);
                        }
                        break;
                  }
               } catch(Exception $e) {
                  $this->fail($e->getMessage());
               }
            } else {
               $this->fail('Store doesn\'t exist');
            }
         }
      } else {
         $this->fail('Collection not valid');
      }
   }

   function fail($message, $code = 500) {
      if(!is_string($message)) {
         $message = $message->getMessage();
      }
      \artnum\HTTP\Response::code($code); 
      file_put_contents('php://output', '{ type: "error", message: "' . $message . '"}');
      exit(-1); 
   }


}
?>
