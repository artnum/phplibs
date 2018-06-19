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
   
   function set_db($db) {
      $this->DB = $db;
   }

   function dbtype() {
      return 'sql';
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
      if (!$this->conf('delete')) {
         $pre_statement = sprintf('DELETE FROM `%s` WHERE %s = :id LIMIT 1', 
            $this->Table, $this->IDName);
         try {
            $st = $this->DB->prepare($pre_statement);
            $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $st->bindParam(':id', $id, $bind_type);
         } catch (\Exception $e) {
            return false;
         }
         return $st->execute();
      } else {
         if (!$this->conf('delete.ts')) {
            $now = new \DateTime('now', new \DateTimeZone('UTC')); $now = $now->format('c');
         } else {
            $now = time();
         }

         try {
            return $this->update(array($this->IDName => $id, $this->conf('delete') => $now)) ? TRUE : FALSE;
         } catch (\Exception $e) {
            return FALSE;
         }
      }
   }

   function get($id) {
      $pre_statement = sprintf('SELECT * FROM `%s` WHERE %s = :id', 
            $this->Table, $this->IDName);
      try {
         $st = $this->DB->prepare($pre_statement);
         $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
         $st->bindParam(':id', $id, $bind_type);
         if($st->execute()) {
            $data = $st->fetch(\PDO::FETCH_ASSOC);
            if($data != FALSE) {
               return $data;
            }
         }
      } catch (\Exception $e) {
         return NULL;
      }

      return NULL;
   }

   function getLastId($params) {
      $pre_statement = sprintf('SELECT MAX(`%s`) FROM `%s`', $this->IDName, $this->Table);

      try {
         $st = $this->DB->prepare($pre_statement);
         if($st->execute()) {
            return $st->fetch(\PDO::FETCH_NUM)[0];
         }
      } catch (\Exception $e) {
         return '0';
      }

      return '0';
   }

   function _timestamp ($date) {
      $val = $date;
      if (is_null($date)) { return 0; }
      if (empty($date)) { return 0; }
      if (is_numeric($date)) { $val = '@' . $date; }

      try {
         $val = new DateTime($val);
         return $val->getTimestamp();
      } catch(\Exception $e) {
         return 0;
      }
   }

   function getTableLastMod() {
      if($this->conf('mtime')) {
         $pre_statement = sprintf('SELECT MAX(`%s`) FROM `%s`', $this->conf('mtime'), $this->Table);
         try {
            $st = $this->DB->prepare($pre_statement);
            if($st->execute()) {
               return $this->_timestamp($st->fetch(\PDO::FETCH_NUM)[0]);
            }
         } catch( \Exception $e) {
            return '0';
         }
      }

      return '0';

   }

   function getLastMod($item) {
      if(!is_null($this->conf('mtime'))) {
         $pre_statement = sprintf('SELECT `%s` FROM `%s` WHERE `%s` = :id', $this->conf('mtime'), $this->Table, $this->IDName);
         
         try {
            $st = $this->DB->prepare($pre_statement);
            if($st) {
               $bind_type = ctype_digit($item) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
               $st->bindParam(':id', $item, $bind_type);
               if($st->execute()) {
                  return $this->_timestamp($st->fetch(\PDO::FETCH_NUM)[0]);
               }
            }
         } catch (\Exception $e) {
            return '0';   
         }
      }
      return '0';
   }

   function prepareSearch($searches) {
      $op = ''; $no_value = false; $s = array();
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
            $o[] = $this->Table . '_' . $attr . ' ' . $dir;
         }
      }

      if( !empty($o)) {
         return ' ORDER BY ' . implode(', ', $o);
      }

      return '';
   }

   function prepare_statement($statement, $options) {
      if(isset($options['search']) && ! empty($options['search'])) {
         $statement .= ' ' . $this->prepareSearch($options['search']);
      }

      if(isset($options['sort']) && ! empty($options['sort'])) {
         $statement .= ' ' . $this->prepareSort($options['sort']);
      }
     
      if(isset($options['limit']) && ! empty($options['limit'])) { 
         $statement .= ' ' . $this->prepareLimit($options['limit']);
      }

      return $statement;
   }

   function listing($options) {
      $pre_statement = $this->prepare_statement(sprintf('SELECT `%s` FROM `%s`', 
            $this->IDName, $this->Table), $options);

      try { 
         $st = $this->DB->prepare($pre_statement);
         if($st->execute()) {
            $data = $st->fetchAll(\PDO::FETCH_ASSOC);
            $return = array();
            foreach($data as $d) {
               $x = $this->unprefix($this->get($d[$this->IDName]));
               $return[] = $this->_postprocess($x);
            }
            return $return;
         }
      } catch(\Exception $e) {
         return NULL;
      }

      return NULL;
   }

   function unprefix($entry) {
      $unprefixed = array();
      foreach($entry as $k => $v) {
         $s = explode('_', $k, 2);
         if(count($s) <= 1) {
            $unprefixed[$k] = $v;
         } else {
            $unprefixed[$s[1]] = $v;
         }
      }

      return $unprefixed;
   }

   function _postprocess ($entry) {
      $dt = $this->conf('datetime');
      foreach ($entry as $k => $v) {
         if (is_array($dt) && in_array($k, $dt)) {
            $entry[$k] = $this->_datetime($v);
         }
      }

      return $entry;
   }

   function read($id) {
      $entry = $this->get($id);
      if($entry) {
         $unprefixed = $this->unprefix($entry);
         return $this->_postprocess($unprefixed);
      }
      return array();
   }

   function exists($id) {
      $pre_statement = sprintf('SELECT `%s` FROM `%s` WHERE %s = :id',
            $this->IDName, $this->Table, $this->IDName);
      
      try {
         $st = $this->DB->prepare($pre_statement);
         $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
         $st->bindParam(':id', $id, $bind_type);
         if($st->execute()) {
            if($st->rowCount()==1) {
               return TRUE;
            }
         }
      } catch(\Exception $e) {
         return FALSE;
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

      try {
         $st = $this->DB->prepare($pre_statement);
         foreach($data as $k_data => &$v_data) {
            $bind_type = \PDO::PARAM_STR;
            if(is_null($v_data)) { $bind_type = \PDO::PARAM_NULL; }
            else if(ctype_digit($v_data)) { $bind_type = \PDO::PARAM_INT; }
            else if(is_bool($v_data)) { $bind_type = \PDO::PARAM_BOOL; }

            $st->bindParam(':' . $k_data, $v_data, $bind_type);
         }

         $transaction = false;
         if($this->conf('auto-increment')) {
            $this->DB->beginTransaction();
            $transaction = true;
         }

         if(! $st->execute()) {
            if($transaction) {
               $this->DB->rollback();
            }

            return FALSE;
         }
         if($this->conf('auto-increment')) {
            $idx = $this->DB->lastInsertId();
            $this->DB->commit();
            return $idx;
         } else {
            return $data[$this->IDName];
         }
      } catch (\Exception $e) {
         return FALSE;      
      }
   }
   
   function update($data) {
      $columns = array();
      $values = array();


      $id = $data[$this->IDName];
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
      try {
         $st = $this->DB->prepare($pre_statement);
         foreach($data as $k_data => &$v_data) {
            $bind_type = \PDO::PARAM_STR;
            if(is_null($v_data)) { $bind_type = \PDO::PARAM_NULL; }
            else if(ctype_digit($v_data)) { $bind_type = \PDO::PARAM_INT; }
            else if(is_bool($v_data)) { $bind_type = \PDO::PARAM_BOOL; }

            $st->bindParam(':' . $k_data, $v_data, $bind_type);
         }
         $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
         $st->bindParam(':' . $this->IDName, $id, $bind_type);
         $ex = $st->execute();
         if(! $ex) {
            return FALSE;
         }
         return $id;
      } catch(\Exception $e) {
         return FALSE;
      }
   }

   function _datetime ($value) {
      if (!is_string($value)) { return $value; }
      if (is_null($value) || empty($value)) { return $value; }
      if (is_numeric($value)) { $value = '@'. $value; }
      try {
         $dt = new \DateTime($value);
         $dt->setTimeZone(new \DateTimeZone('UTC'));
         $value = $dt->format('c');
         $ret = preg_replace('/\+[0:]+$/', 'Z', $value);
         if(!is_null($ret)) {
            return $ret;
         } else {
            return $value;
         }
      } catch (\Exception $e) {
         /* return value if cannot be parsed */
         return $value;
      }
   }

   function write($data) {
      $prefixed = array();
      
      foreach($data as $k => $v) {
         $prefixed[$this->Table . '_' . $k] = $v;
      }

      if(!is_null($this->conf('mtime'))) {
         if (!$this->conf('mtime.ts')) {
            $now = new \DateTime('now', new \DateTimeZone('UTC')); $now = $now->format('c');
            $prefixed[$this->conf('mtime')] = $now;
         } else {
            $prefixed[$this->conf('mtime')] = time();
         }
      }

      if(!isset($prefixed[$this->IDName]) || empty($prefixed[$this->IDName])) {
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
