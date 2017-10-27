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
   protected $IDName;
   protected $Config;

   function __construct($servers, $suffix, $id_name, $config) {
      $this->DB = new LDAPDB($servers);
      $this->Suffix = $suffix;
      $this->IDName = $id_name;
      $this->Config = $config;
   }

   function get($dn) {
      $c = $this->DB->readable();
      $res = ldap_read($c, $dn, '(objectclass=*)');
      if($res && ldap_count_entries($c, $res) == 1) {
         $data = ldap_get_entries($c, $res);
         if($data['count'] == 1) {
            return $data[0];
         }
      }
      return NULL;
   }

   function read($dn) {
      $entry = $this->get($dn);
      if($entry) {
         $e = array();
         foreach($entry as $k => $v) {
            if($entry[$k]['count'] > 1) {

               $_k = explode(';', $k);
               if(count($_k) > 1) {
                  $k = $_k[0];
               }

               $e[$k] = array();
               for($i = 0; $i < $entry[$k]['count']; $i++) {
                  $e[$k][] = $entry[$k][$i];
               }
            } else {
               $e[$k] = $entry[$k][0];
            }
         }  
         return array($e);
      }
      return array();
   }

   function delete($dn) {
      $c = $this->DB->writable();
      if($this->exists($dn)) {
         return ldap_delete($c, $dn);
      } 
   }

   function exists($dn) {
      $c = $this->DB->readable();
      $res = ldap_read($c, $dn, '(objectclass=*)', array('dn'));
      if($res && ldap_count_entries($c, $res) == 1) {
         return TRUE;
      }
      return FALSE;
   }

   function prepareSearch($searches) {
      $op = ''; $no_value = false;
      foreach($searches as $name => $search) {
         if($name == '_rules') { continue; }
         $value = substr($search, 1);
         switch($search[0]) {
            default:
               $value = $search;
            case '=': $op = ' = '; break;
            case '~': $op = ' LIKE '; break;
            case '!': $op = ' <> '; break;
            case '-': $op = ' IS NULL'; $no_value = TRUE; break;
            case '*': $op = ' IS NOT NULL'; $no_value = TRUE; break;
            case '<':
                  if($search[1] == '=') {
                     $value = substr($value, 1);
                     $op = ' <= ';
                  } else {
                     $op = ' < ';
                  }
                  break;
            case '>': 
                  if($search[1] == '=') {
                     $value = substr($value, 1);
                     $op = ' >= ';
                  } else {
                     $op = ' > ';
                  }
                  break;
         }    
         $value = trim($value); 
         if($no_value) {
            $s[$name] = $this->Table . '_' . $name  . $op;   
         } else {
            $str = $this->Table . '_' . $name . $op;
            if(is_numeric($value)) {
               $str .= $value;
            } else {
               $str .= '"' . $value . '"';
            }
            $s[$name] = $str;
         }
      }
      
      if(count($s)>0) {
         if(! isset($searches['_rules'])) {
            return 'WHERE ' . implode(' AND ', $s);
         } else {
            $exp =  'WHERE ' . $searches['_rules'];
            foreach($s as $k => $v) {
               $exp = str_replace($k, $v, $exp);
            }
            return $exp;
         }
      } else {
         return '';
      }
   }

   function listing($options) {
      $c = $this->DB->readable();
      $res = ldap_search($c, '', );
   }
}
?>
