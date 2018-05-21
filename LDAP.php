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

class LDAP  {

   protected $DB;
   protected $Suffix; 
   protected $Config;
   protected $Attribute;

   function __construct($db, $suffix, $attributes, $config) {
      $this->DB = $db;
      $this->Suffix = $suffix;
      $this->Attribute = $attributes;
      $this->Config = $config;
   }

   function dbtype() {
      return 'ldap';
   }

   function set_db($db) {
      $this->DB = $db;
   }

   function _dn($dn = NULL) {
      if(!is_null($this->Suffix)) {
         if(empty($dn) || is_null($dn)) {
            return $this->Suffix;
         }
         return $dn . ',' . $this->Suffix;      
      }

      return $dn;
   }

   function get($dn) {
      $c = $this->DB->readable();
      $res = ldap_read($c, $this->_dn($dn), '(objectclass=*)', $this->Attribute);
      if($res && ldap_count_entries($c, $res) == 1) {
         return ldap_first_entry($c, $res);
      }
      return NULL;
   }

   function read($dn) {
      $c = $this->DB->readable();
      $entry = $this->get(rawurldecode($dn));
      if($entry) {
         $_ber = null;
         $dn = ldap_get_dn($c, $entry);
         $dn = ldap_explode_dn($dn, 0);
         $ret = array('IDent' => rawurlencode($dn[0]));
         for($attr = ldap_first_attribute($c, $entry, $_ber); $attr !== FALSE; $attr = ldap_next_attribute($c, $entry, $_ber)) {
            if(in_array($attr, $this->Attribute)) {
               if(($value = ldap_get_values($c, $entry, $attr)) !== FALSE) {
                  if($value['count'] == 1) {
                     $value = $value[0];
                  } else {
                     unset($value['count']);
                  }
                  $ret[$attr] = $value;
               }
            }
         }
         return array($ret);
      }
      return array();
   }

   function delete($dn) {
      $c = $this->DB->writable();
      if($this->exists($dn)) {
         return ldap_delete($c, $this->_dn($dn));
      } 
   }

   function exists($dn) {
      $c = $this->DB->readable();
      $res = ldap_read($c, $this->_dn($dn), '(objectclass=*)', array('dn'));
      if($res && ldap_count_entries($c, $res) == 1) {
         return TRUE;
      }
      return FALSE;
   }

   function prepareSearch($searches) {
      $op = ''; $s = 0; $filter='';
      foreach($searches as $name => $terms) {
         if($name[0] == '_') { continue; }
         if(!is_array($terms)) {
            $terms = array($terms);
         }
         $f = '';
         foreach($terms as $search) {
            $value = substr($search, 1);
            switch($search[0]) {
               default:
                  $value = $search;
               case '=': $op = $name . '=' . trim($value); break;
               case '~': $op = $name . '~=' . trim($value); break;
               case '!': $op = '!(' . $name . '=' . trim($value) . ')'; break;
               case '-': $op = '!(' . $name . '=*)'; break;
               case '*': 
                  $op = $name . '=*'; break;
               case '<':
                     if($search[1] == '=') {
                        $value = trim(substr($value, 1));
                        $op = $name . '<=' . $value;
                     } else {
                        $op = $name . '<' . trim($value); 
                     }
                     break;
               case '>': 
                     if($search[1] == '=') {
                        $value = trim(substr($value, 1));
                        $op = $name . '>=' . $value;
                     } else {
                        $op = $name . '>' . trim($value); 
                     }
                     break;
            }
            $f .= '(' . $op . ')'; $s++;
         }
         if(count($terms) > 1) {
            if(!isset($searches['_' . $name])) {
               $filter .= '(&' . $f . ')';
            } else {
               switch(strtolower($searches['_' . $name])) {
                  default:
                  case 'and':
                     $filter .= '(&' . $f . ')'; break;
                  case 'or':
                     $filter .= '(|' . $f . ')'; break;
               }
            }
         } else {
            $filter .= $f;
         }
      }
      
      if($s == 1) {
      } else if($s > 1) {
         $filter = '(|' . $filter . ')';      
      } else {
         $filter = '(objectclass=*)';
      }

      return $filter;
   }

   function listing($options) {
      $c = $this->DB->readable();
      if(isset($options['search'])) {
            $filter = $this->prepareSearch($options['search']);
      }
      $res = ldap_list($c, $this->_dn(), $this->prepareSearch($options['search']), array('dn'));
      $ret = array();
      if($res) {
         for($e = ldap_first_entry($c, $res); $e; $e = ldap_next_entry($c, $e)) {
            $dn = ldap_get_dn($c, $e);
            $dn = ldap_explode_dn($dn, 0);
            $r = $this->read(rawurlencode($dn[0]))[0];
            $ret[] = $r;
         }
      }
      return $ret;
   }
}
?>
