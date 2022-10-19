<?PHP
/*- 
 * Copyright (c) 2017-2022 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

abstract class OP {
   protected $Config;

   function __construct($config) {
      $this->Config = $config;
      $this->response = null;
   }

   function set_response($response) {
      $this->response = $response;
   }

   function conf($name, $value = NULL) {
      if (!is_string($name)) {
         return NULL;
      }
      switch (strtolower($name)) {
         case 'idname':
            if (!is_null($value) && is_string($value)) {
               $this->IDName = $value;
            }
            return $this->IDName;
         case 'table':
            if (!is_null($value) && is_string($value)) {
               $this->Table = $value;
            }
            return $this->Table;
      }
      if (is_null($value)) {
         if(isset($this->Config[$name])) {
            return $this->Config[$name];
         }
      } else {
         $this->Config[$name] = $value;
         return $this->Config[$name];
      }

      return null;
   }

   function write($arg, $id = NULL) {
      if ($this->conf('readonly')) {
         return false;
      }
      return $this->_write($arg, $id);
   }

   function overwrite($arg, $id = NULL) {
      if ($this->conf('readonly')) {
         return false;
      }
      return $this->_overwrite($arg, $id);
   }

   function delete($arg) {
      if ($this->conf('readonly') || $this->conf('no-delete')) {
         return false;
      }
      return $this->_delete($arg);
   }

   function read($arg) {
      return $this->_read($arg);
   }
   function exists($arg) {
      return $this->_exists($arg);
   }

   function error($msg, $line = __LINE__, $file = __FILE__) {
      error_log("$file:$line:" . get_class($this) . ", $msg");
   }

   abstract protected function _write($arg);
   abstract protected function _overwrite($arg);
   abstract protected function _delete($arg);
   abstract protected function _read($arg);
   abstract protected function _exists($arg);
}
