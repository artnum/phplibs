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
            return array('last-id' => $this->Model->getLastId($req->getParameters()));
         } else if($req->onItem()) {
            return array('last-modification' => $this->Model->getLastMod($req->getItem()));
         }
      } catch(Exception $e) {
         return array('error' => $e->getMessage());
      }
   }

   function getAction($req) {
      try {
         if($req->onCollection()) {
            return $this->Model->listing($req->getParameters());
         } else if($req->onItem()) {
            return $this->Model->read($req->getItem());
         }
      } catch(Exception $e) {
         return array('success' => false, 'msg' => $e->getMessage()); 
      }
   }

   function postAction($req) {
      try {
         $id = $this->Model->write($req->getParameters());
         return array('success' => true, 'id' => $id);
      } catch(Exception $e) {
         return array('success' => false, 'msg' => $e->getMessage()); 
      }
   }
   
   function deleteAction($req) {
      if($req->onCollection()) {
         return array('success' => false, 'msg' => 'No element selected');
      }         
      try {
         $this->Model->delete($req->getItem());
         return array('success' => true, 'msg' => 'Element deleted');
      } catch(Exception $e) {
         return array('success' => false, 'msg' => $e->getMessage());
      }
   }

   function putAction($req) {
      if(!$req->onCollection()) {
         return $this->postAction($req);      
      }
      return array('success' => false, 'msg' => 'No element selected');
   }
}

?>