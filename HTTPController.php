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

class HTTPController
{
   protected $Model;

   function __construct($model) {
      $this->Model = $model;
   }

   function headAction($req) {
      try {
         if($req->onCollection()) {
            return array('last-id' => $this->Model->getLastId($req->getParameters()), 'last-modification' => $this->Model->getTableLastMod());
         } else if($req->onItem()) {
            return array('last-modification' => $this->Model->getLastMod($req->getItem()),
               'deleted' => $this->Model->getDeleteDate($req->getItem()));
         }
      } catch(Exception $e) {
         return array('error' => $e->getMessage());
      }
   }

   function getAction($req) {
      $run = 0;
      try {
         do {
            $continue = false;
            if($req->onCollection()) {
               $results = $this->Model->listing($req->getParameters());
            } else if($req->onItem()) {
               if (!$req->multiple) {
                  $results = $this->Model->read($req->getItem());
               } else {
                  if (method_exists($this->Model, 'readMultiple')) {
                     $results = $this->Model->readMultiple($req->getItem());
                  } else {
                     $results = array();
                     foreach ($req->getItem() as $item) {
                        $results[] = $this->Model->read($item);
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
         return array('success' => true, 'data' =>  array($results[0], $results[1]), 'msg' => '');
      } catch(Exception $e) {
         return array('success' => false, 'data' => array(NULL, 0), 'msg' => $e->getMessage());
      }
   }

   function patchAction ($req) {
      if (!$req->onCollection()) {
         try {
            $result = $this->Model->write($req->getParameters());
            if ($result) {
               return array('success' => true, 'data' => $result, 'msg' => '');
            } else {
               return array('success' => false, 'data' => array(NULL, 0), 'msg' => 'Write failed');
            }
         } catch(Exception $e) {
            return array('success' => false, 'data' => array(NULL, 0), 'msg' => $e->getMessage());
         }
      }
      return array('success' => false, 'msg' => 'No element selected', 'data' => array(NULL, 0));
   }

   function putAction($req) {
      if(!$req->onCollection()) {
         return $this->postAction($req);
      }
      return array('success' => false, 'msg' => 'No element selected', 'data' => array(NULL, 0));
   }

   function postAction($req) {
      try {
         $result = $this->Model->overwrite($req->getParameters());
         if ($result) {
            return array('success' => true, 'data' => $result, 'msg' => '');
         } else {
            return array('success' => false, 'data' => array(NULL, 0), 'msg' => 'Write failed');
         }
      } catch(Exception $e) {
         return array('success' => false, 'data' => array(NULL, 0), 'msg' => $e->getMessage());
      }
   }
   
   function deleteAction($req) {
      if($req->onCollection()) {
         return array('success' => false, 'msg' => 'No element selected', 'data' => array(NULL, 0));
      }         
      try {
         $result = $this->Model->delete($req->getItem());
         return array('success' => true, 'msg' => 'Element deleted', 'data' => $result);
      } catch(Exception $e) {
         return array('success' => false, 'msg' => $e->getMessage(), 'data' => array(NULL, 0));
      }
   }
}

?>
