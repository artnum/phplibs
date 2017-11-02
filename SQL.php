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

require('id.php');

class SQL {
   protected $DB;
   protected $Table;
   protected $Config;
   protected $IDName;

   function __construct($db, $table, $id_name, $config) {
      $this->DB = $db;
      $this->Table = $table;
      $this->IDName = $id_name;
      $this->Config = $config;
   } 

   private function dbtype() {
      return 'pdo';
   }

   function conf($name, $value = null) {
      if(is_null($value)) {
         if(isset($this->Config[$name])) {
            return $this->Config[$name];
         }
      } else {
         $this->Config[$name] = $value;
         return $this->Config[$name];
      }

      return null;
   }

   function delete($id) {
      $pre_statement = sprintf('DELETE FROM `%s` WHERE %s = :id LIMIT 1', 
            $this->Table, $this->IDName);
      $st = $this->DB->prepare($pre_statement);
      $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
      $st->bindParam(':id', $id, $bind_type);
      return $st->execute();
   }

   function get($id) {
      $pre_statement = sprintf('SELECT * FROM `%s` WHERE %s = :id', 
            $this->Table, $this->IDName);
      $st = $this->DB->prepare($pre_statement);
      $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
      $st->bindParam(':id', $id, $bind_type);
      if($st->execute()) {
         $data = $st->fetch(\PDO::FETCH_ASSOC);
         if($data != FALSE) {
            return $data;
         }
      }
      return NULL;
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
   function prepareLimit($limit) {
      if(ctype_digit($limit)) {
         return ' LIMIT ' . $limit;
      } else {
         list($offset, $limit) = explode(',', $limit);
         $offset = trim($offset); $limit = trim($limit);
         if(ctype_digit($offset) && ctype_digit($limit)) {
            return ' LIMIT ' . $limit . ' OFFSET ' . $offset;
         }
      }

      return '';
   }

   function prepareSort($sort) {
      $o = array();
      foreach($sort as $attr => $dir) {
         $dir = strtoupper($dir);
         if($dir == 'ASC' || $dir == 'DESC') {
            $o[] = $attr . ' ' . $dir;
         }
      }

      if( !empty($o)) {
         return ' ORDER BY ' . implode(', ', $o);
      }

      return '';
   }

   function listing($options) {
      $pre_statement = sprintf('SELECT `%s` FROM `%s`', 
            $this->IDName, $this->Table);

      if(isset($options['search']) && ! empty($options['search'])) {
         $pre_statement .= ' ' . $this->prepareSearch($options['search']);
      }

      if(isset($options['sort']) && ! empty($options['sort'])) {
         $pre_statement .= ' ' . $this->prepareSort($options['sort']);
      }
     
      if(isset($options['limit']) && ! empty($options['limit'])) { 
         $pre_statement .= ' ' . $this->prepareLimit($options['limit']);
      }
      
      $st = $this->DB->prepare($pre_statement);
      if($st->execute()) {
         $data = $st->fetchAll(\PDO::FETCH_ASSOC);
         $return = array();
         foreach($data as $d) {
            $return[] = $this->read($d[$this->IDName])[0];
         }
         return $return;
      }
      return NULL;
   }

   function read($id) {
      $entry = $this->get($id);
      if($entry) {
         $e = array();
         foreach($entry as $k => $v) {
            $k = explode('_', $k);
            $e[$k[1]] = $v;
         }
         return array($e);
      }
      return array();
   }

   function exists($id) {
      $pre_statement = sprintf('SELECT `%s` FROM `%s` WHERE %s = :id',
            $this->IDName, $this->Table, $this->IDName);
      $st = $this->DB->prepare($pre_statement);
      $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
      $st->bindParam(':id', $id, $bind_type);
      if($st->execute()) {
         if($st->rowCount()==1) {
            return TRUE;
         }
      }
      return FALSE;
   }

   function create($data) {
      $columns = array();
      $values = array();

      if(! $this->conf('auto-increment')) {
         if(!isset($data[$this->IDName]) || empty($data[$this->IDName])) {
            $data[$this->IDName] = $this->uid($data);
         }
      }

      foreach(array_keys($data) as $c) { 
         $values[] = ':' . $c; $columns[] = '`' . $c . '`';
      }
      $columns_txt = implode(',', $columns);
      $values_txt = implode(',', $values);

      $pre_statement = sprintf('INSERT INTO `%s` ( %s ) VALUES ( %s )',
            $this->Table, $columns_txt, $values_txt);
      $st = $this->DB->prepare($pre_statement);
      foreach($data as $k_data => &$v_data) {
         $bind_type = \PDO::PARAM_STR;
         if(is_null($v_data)) { $bind_type = \PDO::PARAM_NULL; }
         else if(ctype_digit($v_data)) { $bind_type = \PDO::PARAM_INT; }
         else if(is_bool($v_data)) { $bind_type = \PDO::PARAM_BOOL; }

         $st->bindParam(':' . $k_data, $v_data, $bind_type);
      }
      if($this->conf('auto-increment')) {
         $this->DB->beginTransaction();
      }

      if(! $st->execute()) {
         throw new \Exception('DB Write failed');
      }
     

      if($this->conf('auto-increment')) {
         $idx = $this->DB->lastInsertId();
         $this->DB->commit();
         return $idx;
      } else {
         return $data[$this->IDName];
      }
   }
   
   function update($data) {
      $columns = array();
      $values = array();


      if(isset($data[$this->IDName])) {
         unset($data[$this->IDName]);
      }

      foreach(array_keys($data) as $c) { 
         if(strcmp($c, $this->IDName) != 0) {
            $columns_values[] = '`' . $c . '` = :'.$c;
         }
      }
      $columns_values_txt = implode(',', $columns_values);
      
      $pre_statement = sprintf('UPDATE `%s` SET %s WHERE %s = :%s LIMIT 1',
            $this->Table, $columns_values_txt, $this->IDName, $this->IDName);
      $st = $this->DB->prepare($pre_statement);
      foreach($data as $k_data => &$v_data) {
         $bind_type = \PDO::PARAM_STR;
         if(is_null($v_data)) { $bind_type = \PDO::PARAM_NULL; }
         else if(ctype_digit($v_data)) { $bind_type = \PDO::PARAM_INT; }
         else if(is_bool($v_data)) { $bind_type = \PDO::PARAM_BOOL; }

         $st->bindParam(':' . $k_data, $v_data, $bind_type);
      }
      $bind_type = ctype_digit($data[$this->IDName]) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
      $st->bindParam(':id', $data[$this->IDName], $bind_type);
      if(! $st->execute()) {
         return FALSE;
      }
      return $data[$this->IDName];
   }

   function write($data) {
      $prefixed = array();
      
      foreach($data as $k => $v) {
         $prefixed[$this->Table . '_' . $k] = $v;
      }
      if(!isset($prefixed) || empty($prefixed[$this->IDName])) {
         return $this->create($prefixed);
      } else {
         return $this->update($prefixed);
      }
   }

   function uid($data) {
         return \artnum\genId(serialize($data));
   }
}

?>