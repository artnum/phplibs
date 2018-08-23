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

class User {
   function __construct($pdo, $table, $cols) {
      $this->PDO = $pdo;
      $this->Table = $table;
      $this->Cols = $cols;
      if (!isset($cols['username']) ||
         !isset($cols['key'])
      ) {
         throw new Exception('Invalid parameters');
      }
   }

   function setkey($user, $key) {
      /* todo */
   }

   function fail($op, $user) {
      /* todo */
      return 0;
   }

   function getkey ($user) {
      $pre_statment = sprintf('SELECT * FROM "%s" WHERE "%s" = :user', $this->Table, $this->Cols['username']);
      try {
         $st = $this->PDO->prepare($pre_statment);
         if ($st) {
            if ($st->bindParam(':user', $user, \PDO::PARAM_STR)) {
               if ($st->execute()) {
                  if (($res = $st->fetchAll())) {
                     if (count($res) == 1) {
                        $ret = array('key' => $res[0][$this->Cols['key']]);
                        if (isset($this->Cols['salt'])) {
                           $ret['salt'] = $res[0][$this->Cols['salt']];
                        }
                        if (isset($this->Cols['iteration'])) {
                           $ret['iteration'] = $res[0][$this->Cols['iteration']];
                        }

                        return $ret;
                     }
                  }
               }
            }
         }
         return false;
      } catch(Exception $e) {
         throw $e;
      }

   }
}

?>
