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

class LDAPConns {
   protected $conns;

   function __construct() {
      $this->conns = array( 'ro' => array(), 'rw' => array() );
   }

   function connect($uri, $ro = false) {
      $conn = ldap_connect($uri);
      if($conn) {
         $dse = $this->_fetch_rootdse($conn);
         if(!is_null($dse)) {
            $dst = 'rw';
            if($ro) { $dst = 'ro'; }
      
            $this->_set_protocol($conn, $dse);      
            array_push($this->conns[$dst], array('conn' => $conn, 'dse' => $dse, 'uri' => $uri, 'ro' => $ro)); 
         }
      }
   }

   private function _set_protocol($conn, $dse) {
      $version = 3;
      if(isset($dse['supportedldapversion']) && $dse['supportedldapversion']['count'] > 0) {
         $version = $dse['supportedldapversion'][0];
      }
      ldap_set_option($conn, \LDAP_OPT_PROTOCOL_VERSION, $version);
   }

   private function _fetch_rootdse($conn) {
      $res = @ldap_read($conn, '', '(objectclass=*)', array('+'));
      if($res) {
         $entries = ldap_get_entries($conn, $res);
         if(is_array($entries) && $entries['count'] > 0) {
            return $entries[0];
         }
      } 
      return null;
   } 

   function conn($base, $ro = false) {
      $resources = $this->_get_by_naming_context($base);
      if(!empty($resources)) {
         foreach($resources as $r) {
            if($r['ro'] == $ro) {
               return $r['conn'];
            }
         }
      } 

      return NULL;
   }

   private function _get_by_naming_context($base) {
      $r = array();
      foreach($this->conns as $conn) {
         foreach($conn as $c) {
            $dse = $c['dse'];
            if(isset($dse['namingcontexts'])) {
               for($i = 0; $i < $dse['namingcontexts']['count']; $i++) {
                  if(strcmp($base, $dse['namingcontexts'][$i]) == 0) {
                     array_push($r, $c);
                  }
               }
            }
         }
      }
      return $r;
   }

   function none() {
      if(empty($this->conns['ro']) && empty($this->conns['rw'])) { return TRUE; }
      return FALSE;
   }
}
?>
