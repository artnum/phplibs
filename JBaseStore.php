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

class JBaseStore {
   protected $db;
   protected $request;

   function run() {
      if(ctype_alpha($this->request->getCollection())) {
         try {
            $model = '\\' . $this->request->getCollection() . 'Model';
            $model = new $model($this->db, NULL);

            $controller = '\\' . $this->request->getCollection() . 'Controller';
            $controller = new $controller($model, NULL);

            $view = '\\' . $this->request->getCollection() . 'View';
            $view = new $view($model, NULL);
         } catch(Exception $e) {
            $this->fail($e->getMessage());
         }
      
         try {
            $action = strtolower($this->request->getVerb()) . 'Action';
            $view->$action(
                  $controller->$action($this->request)
            );
         } catch(Exception $e) {
            $this->fail($e->getMessage());
         }
      } else {
         $this->fail('Collection not valid');
      }
   }

   function fail($message) {
      HTTPResponse::code(500); 
      file_put_contents('php://output', '{ type: "error", message: "' . $message . '"}');
      exit(-1); 
   }
}
?>
