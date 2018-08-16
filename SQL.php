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
   protected $Request = array(
      'delete' => 'DELETE FROM "\\Table" WHERE "\\IDName" = :id LIMIT 1',
      'readMultiple' => 'SELECT * FROM "\\Table" WHERE "\\IDName" IN (\\IDS)',
      'get' => 'SELECT * FROM "\\Table" WHERE "\\IDName" = :id',
      'getLastId' => 'SELECT MAX("\\IDName") FROM "\\Table"',
      'getTableLastMod' => 'SELECT MAX("\\mtime") FROM "\\Table"',
      'getDeleteDate' => 'SELECT "\\delete" FROM "\\Table" WHERE "\\IDName" = :id',
      'getLastMod' => 'SELECT "\\mtime" FROM "\\Table" WHERE "\\IDName" = :id',
      'listing' => 'SELECT "\\IDName" FROM "\\Table"',
      'exists' => 'SELECT "\\IDName" FROM "\\Table" WHERE "\\IDName" = :id',
      'create' =>'INSERT INTO "\\Table" ( \\COLTXT ) VALUES ( \\VALTXT )',
      'update' => 'UPDATE "\\Table" SET \\COLVALTXT WHERE "\\IDName" = :\\IDName LIMIT 1'
   );

   function __construct($db, $table, $id_name, $config) {
      $this->DB = $db;
      $this->Config = $config;
      $this->conf('Table', $table);
      $this->conf('IDName', $id_name);
      $this->DataLayer = new Data();
   } 
   
   function set_db($db) {
      $this->DB = $db;
   }

   function dbtype() {
      return 'sql';
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

   function set_req($name, $value) {
      if (is_string($name) && is_string($value)) {
         $this->Request[$name] = $value;
      }
   }

   function req ($name, $vars = array()) {
      if (isset($this->Request[$name])) {
         $req = $this->Request[$name];
         if (!preg_match_all('/\\\\[a-zA-Z]+/', $req, $matches)) {
            return '';
         }

         if (!(count($matches[0]) > 0)) {
            return $req;
         }

         $replaced = array();
         foreach ($matches[0] as $var) {
            if (in_array($var, $replaced)) {
               continue;
            }
            $replaced[] = $var;
            if ($this->conf(substr($var, 1))) {
               $req = str_replace($var, $this->conf(substr($var, 1)), $req);
               continue;
            }

            if($vars[substr($var, 1)]) {
               $req = str_replace($var, $vars[substr($var, 1)], $req);
               continue;
            }
         }

         return $req;
      }

      return '';
   }

   function delete($id) {
      if (!$this->conf('delete')) {
         try {
            $st = $this->DB->prepare($this->req('delete'));
            $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $st->bindParam(':id', $id, $bind_type);
         } catch (\Exception $e) {
            return false;
         }
         return $st->execute();
      } else {
         $data = array($this->IDName => $id);
         if (!$this->conf('delete.ts')) {
            $data[$this->conf('delete')] = $this->DataLayer->datetime(time());
         } else {
            $data[$this->conf('delete')] = $now; 
         }
         if($this->conf('mtime')) {
            if (!$this->conf('mtime.ts')) {
               $data[$this->conf('mtime')] = $this->DataLayer->datetime(time()); 
            } else {
               $data[$this->conf('mtime')] = $now;
            }
         }
         try {
            return $this->update($data) ? TRUE : FALSE;
         } catch (\Exception $e) {
            return FALSE;
         }
      }
   }

   function readMultiple ($ids) {
      if (! ctype_digit($ids[0])) {
         for ($i = 0; $i < count($ids); $i++) {
            $ids[$i] = '\'' . $ids[$i] . '\'';
         }
      }
      try {
         $st = $this->DB->prepare($this->req('readMultiple', array('IDS' => implode(',', $ids))));
         $data = array();
         if ($st->execute()) {
            while (($row = $st->fetch(\PDO::FETCH_ASSOC))) {
               $row = $this->unprefix($row);
               $row = $this->_postprocess($row);
               $data[] = $row;
            }

            return $data;
         }
      } catch(\Exception $e) {
         return NULL;
      }

      return NULL;
   }

   function get($id) {
      try {
         $st = $this->DB->prepare($this->req('get'));
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
      try {
         $st = $this->DB->prepare($this->req('getLastId'));
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
         $val = new \DateTime($val);
         return $val->getTimestamp();
      } catch(\Exception $e) {
         return 0;
      }
   }

   function getTableLastMod() {
      if($this->conf('mtime')) {
         try {
            $st = $this->DB->prepare($this->req('getTableLastMod'));
            if($st->execute()) {
               return $this->_timestamp($st->fetch(\PDO::FETCH_NUM)[0]);
            }
         } catch( \Exception $e) {
            return '0';
         }
      }

      return '0';

   }

   function getDeleteDate($item) {
      if (!$this->exists($item)) {
         return '1';
      }
      if (!is_null($this->conf('delete'))) {
         try {
            $st = $this->DB->prepare($this->req('getDeleteDate'));
            if ($st) {
               $bind_type = ctype_digit($item) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
               $st->bindParam(':id', $item, $bind_type);
               if($st->execute()) {
                  return $this->_timestamp($st->fetch(\PDO::FETCH_NUM)[0]);
               }
            }
         } catch(\Exception $e) {
            return '0';
         }
      } else {
         return '0';
      }
   }

   function getLastMod($item) {
      if(!is_null($this->conf('mtime'))) {
         try {
            $st = $this->DB->prepare($this->req('getLastMod'));
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
         $no_value = false;
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
            $s[$name] = $this->conf('Table') . '_' . $name  . $op;
         } else {
            $str = $this->conf('Table') . '_' . $name . $op;
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
            $o[] = $this->conf('Table') . '_' . $attr . ' ' . $dir;
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
      $pre_statement = $this->prepare_statement($this->req('listing'), $options);
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

   function unprefix($entry, $table = NULL) {
      $useTable = $this->conf('Table');
      if (!is_null($table)) {
         $useTable = $table;
      }
      $unprefixed = array();
      foreach($entry as $k => $v) {
         $s = explode('_', $k, 2);
         if(count($s) <= 1) {
            $unprefixed[$k] = $v;
         } else {
            /* if the prefix is from a different table, it means we are onto join request (or alike), so create subcategory */
            if (strcasecmp($s[0], $useTable) != 0) {
               if (!isset($unprefixed[$s[0]])) {
                  $unprefixed[$s[0]] = array();
               }

               $unprefixed[$s[0]][$s[1]] = $v;
            } else {
               $unprefixed[$s[1]] = $v;
            }
         }
      }

      return $unprefixed;
   }

   function _postprocess ($entry) {
      $dt = $this->conf('datetime');
      foreach ($entry as $k => $v) {
         if (is_array($dt) && in_array($k, $dt)) {
            $entry[$k] = $this->DataLayer->datetime($value);
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
      try {
         $st = $this->DB->prepare($this->req('exists'));
         $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
         $st->bindParam(':id', $id, $bind_type);
         if($st->execute()) {
            if($st->fetch()) {
               return TRUE;
            }
         } else {
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

      try {
         $st = $this->DB->prepare($this->req('create', array('COLTXT' => $columns_txt, 'VALTXT' => $values_txt)));
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
      
      try {
         $st = $this->DB->prepare($this->req('update', array('COLVALTXT' => $columns_values_txt)));
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

   function write($data) {
      $prefixed = array();
      
      foreach($data as $k => $v) {
         $prefixed[$this->conf('Table') . '_' . $k] = $v;
      }

      if(!is_null($this->conf('mtime'))) {
         if (!$this->conf('mtime.ts')) {
            $prefixed[$this->conf('mtime')] = $this->DataLayer->datetime(time());
         } else {
            $prefixed[$this->conf('mtime')] = time();
         }
      }

      /* Write to an item undelete it, except if specified no to do so */
      if (!is_null($this->conf('delete')) && !$this->conf('delete.no-auto-undelete')) {
         $prefixed[$this->conf('delete')] = NULL;
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
