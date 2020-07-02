<?PHP
/*- 
 * Copyright (c) 2017-2019 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

class SQL extends \artnum\JStore\OP {
  protected $DB;
  protected $Table;
  protected $IDName;
  protected $Request = array(
     'delete' => 'DELETE FROM "\\Table" WHERE "\\IDName" = :id',
     'get' => 'SELECT * FROM "\\Table"',
     'getLastId' => 'SELECT MAX("\\IDName") FROM "\\Table"',
     'getTableLastMod' => 'SELECT MAX("\\mtime") FROM "\\Table"',
     'getDeleteDate' => 'SELECT "\\delete" FROM "\\Table" WHERE "\\IDName" = :id',
     'getLastMod' => 'SELECT "\\mtime" FROM "\\Table" WHERE "\\IDName" = :id',
     'exists' => 'SELECT "\\IDName" FROM "\\Table" WHERE "\\IDName" = :id',
     'create' =>'INSERT INTO "\\Table" ( \\COLTXT ) VALUES ( \\VALTXT )',
     'update' => 'UPDATE "\\Table" SET \\COLVALTXT WHERE "\\IDName" = :\\IDName',
     'count' => 'SELECT COUNT(*) FROM \\Table'
   );

  function __construct($db, $table, $id_name, $config) {
    parent::__construct($config);
    $this->DB = array();
      $this->RODB = array();
    $this->RRobin = 0;
    $this->WRRobin = 0;
    if (!is_null($db)) {
         $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      $this->DB[0] = $db;
    }
    $this->Config = $config;
    $this->conf('Table', $table);
      $this->conf('IDName', $id_name);
    $this->DataLayer = new Data();
  }

  function add_db($db, $readonly = false) {
    if (is_null($db)) { return; }
    try {
      $attr = $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) {
      error_log('Not pdo object');
      return;
    }
    if ($attr) {
      if (!$readonly) {
        $this->DB[] = $db;
      } else {
        $this->RODB[] = $db;
      }
    }
  }

  function set_db($db) {
    if (is_null($db)) { return; }
    try {
      $attr = $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    } catch (Exception $e) {
      error_log('Not pdo object');
      return;
    }
    if ($attr) {
      $this->DB[0] = $db;
    }
  }

  function get_db($readonly = false) {
    if ($readonly && !empty($this->RODB)) {
      if ($this->RRobin >= count($this->RODB)) { $this->RRobin = 0; }
      return $this->RODB[$this->RRobin++];
    } else {
      if ($this->WRRobin >= count($this->DB)) { $this->WRRobin = 0; }
      return $this->DB[$this->WRRobin++];
    }
  }

  function dbtype() {
    return 'sql';
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

  function _delete($id) {
    $result = new \artnum\JStore\Result();
    if (!$this->conf('delete')) {
      try {
        $st = $this->get_db(false)->prepare($this->req('delete'));
        $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
        $st->bindParam(':id', $id, $bind_type);
        $result->addItem([$id => $st->execute()]);
      } catch (\Exception $e) {
        $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
        $result->addItem([$id => false]);
      }
    } else {
      $data = array($this->IDName => $id);
      if (!$this->conf('delete.ts')) {
        $data[$this->conf('delete')] = $this->DataLayer->datetime(time());
      } else {
        $data[$this->conf('delete')] = time();
      }
      if($this->conf('mtime')) {
        if (!$this->conf('mtime.ts')) {
          $data[$this->conf('mtime')] = $this->DataLayer->datetime(time());
        } else {
          $data[$this->conf('mtime')] = time();
        }
      }
      try {
        $result->addItem([$id => $this->update($data) == true]);
      } catch (\Exception $e) {
        $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
        $result->addItem([$id => false]);
      }
    }
    return $result;
  }
  
  function _remove_same_value ($array) {
    $new = array();
    foreach ($array as $v) {
      if (!in_array($v, $new)) {
        $new[] = $v;
      }
    }
    return $new;
  }

  function getLastId($params) {
    try {
      $st = $this->get_db(true)->prepare($this->req('getLastId'));
      if($st->execute()) {
        return $st->fetch(\PDO::FETCH_NUM)[0];
      }
    } catch (\Exception $e) {
      $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
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
      $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
      return 0;
    }
  }

  function getTableLastMod() {
    if($this->conf('mtime')) {
      try {
        $st = $this->get_db(true)->prepare($this->req('getTableLastMod'));
        if($st->execute()) {
          if ($this->conf('mtime.ts')) {
            return (int)$st->fetch(\PDO::FETCH_NUM)[0];
          } else {
            return $this->_timestamp($st->fetch(\PDO::FETCH_NUM)[0]);
          }
        }
      } catch( \Exception $e) {
        $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
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
        $st = $this->get_db(true)->prepare($this->req('getDeleteDate'));
        if ($st) {
          $bind_type = ctype_digit($item) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
          $st->bindParam(':id', $item, $bind_type);
          if($st->execute()) {
            return $this->_timestamp($st->fetch(\PDO::FETCH_NUM)[0]);
          }
        }
      } catch(\Exception $e) {
        $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
        return '0';
      }
    } else {
      return '0';
    }
  }

  function getLastMod($item) {
    if(!is_null($this->conf('mtime'))) {
      try {
        $st = $this->get_db(true)->prepare($this->req('getLastMod'));
        if($st) {
          $bind_type = ctype_digit($item) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
          $st->bindParam(':id', $item, $bind_type);
          if($st->execute()) {
            if ($this->conf('mtime.ts')) {
              return (int)$st->fetch(\PDO::FETCH_NUM)[0];
            } else {
              return $this->_timestamp($st->fetch(\PDO::FETCH_NUM)[0]);
            }
          }
        }
      } catch (\Exception $e) {
        $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
        return '0';
      }
    }
    return '0';
  }

  function sqlFunction ($function, $arguments, $operator) {
    $argc = count($arguments);
    switch ($function) {
        /* return part of string. index (arg 1) is at 0 instead of 1 as in mysql */
      case 'substr':
        if ($argc < 1 || $argc > 2) { return FALSE; }
        if (!ctype_digit($arguments[0])) { return FALSE; }
        if ($argc == 1) {
          return sprintf('SUBSTR(__FIELDNAME__, 0, %d) %s __VALUE__', intval($arguments[0]) + 1, $operator);
        } else {
          if (!ctype_digit($arguments[1])) { return FALSE; }
          return sprintf('SUBSTR(__FIELDNAME__, %d, %d) %s __VALUE__', intval($arguments[0]) + 1, intval($arguments[1]), $operator);
        }        
      case 'left':
      case 'right':
        if ($argc != 1 || !ctype_digit($arguments[0])) { return FALSE; }
        return sprintf('%s(__FIELDNAME__, %d) %s __VALUE__', strtoupper($function), intval($arguments[0]), $operator);
    }
  }

  function prepareSearch($searches) {
    $whereClause = '';
    $op = ''; $no_value = false; $s = array();
    foreach($searches as $name => $search) {
      if($name == '_rules') { continue; }
      if (!is_array($search)) {
        $_s = array($search);
      } else {
        $_s = $search;
      }
      $count = 0;
      foreach($_s as $search) {
        $value = substr($search, 1);
        switch($search[0]) {
          case '@':
            if (!preg_match('/^@(\w+)\[(.*)\]((?:=|<=|>=|<>|!=|<|>))(.*)$/', $search, $matches)) {
              $value = $search;
            } else {
              $function = strtolower($matches[1]);
              $arguments = array_map('trim', explode(',', $matches[2]));
              $operator = $matches[3];
              $value = $matches[4];
              $op = $this->sqlFunction($function, $arguments, $operator);
              if ($op === FALSE) { $value = $search; }
            }
            break;
          default:
            $value = $search;
          case '=': $op = '__FIELDNAME__ = __VALUE__'; break;
          case '~': $op = '__FIELDNAME__ LIKE __VALUE__'; break;
          case '!': $op = '__FIELDNAME__ <> __VALUE__'; break;
          case '-': 
            if (strlen($search) === 1) {
              $op = '__FIELDNAME__ IS NULL';
            } else {
              $op = '(__FIELDNAME__ IS NULL OR __FIELDNAME__ = \'\')';
            }
          break;
          case '*': 
            if (strlen($search) === 1) {
              $op = '__FIELDNAME__ IS NOT NULL';
            } else {
              $op = '(__FIELDNAME__ IS NOT NULL OR __FIELDNAME__ <> \'\')';
            }
            break;
          case '<':
            if($search[1] == '=') {
              $value = substr($value, 1);
              $op = '__FIELDNAME__ <= __VALUE__';
            } else {
              $op = '__FIELDNAME__ < __VALUE__';
            }
            break;
          case '>':
            if($search[1] == '=') {
              $value = substr($value, 1);
              $op = '__FIELDNAME__ >= __VALUE__';
            } else {
              $op = '__FIELDNAME__ > __VALUE__';
            }
            break;
        }
        $value = trim($value);

        /* Process field name */
        $fieldname = $name;
        if (($pos = strpos($name, ':', true)) !== FALSE) {
          $fieldname = substr($name, 0, $pos);
        }
        $tablename = $this->conf('Table');
        if ($fieldname[0] === '@') {
          $fieldname = substr($fieldname, 1);
        } else {
          if (strpos($fieldname, '_', true) !== FALSE) {
            list ($tablename, $fieldname) = explode('_', $fieldname, 2);
            $fieldname = '"' . $tablename . '"."' . $tablename . '_' . $fieldname .'"';
          } else {
            $fieldname = '"' . $this->conf('Table') . '"."' . $this->conf('Table') . '_' . $fieldname .'"';
          }
        }

        if (!isset($s[$name])) {
          $s[$name] = array();
        }

        $s[$name][$count] = str_replace('__VALUE__', is_numeric($value) ? $value : '\'' . $value . '\'', str_replace('__FIELDNAME__', $fieldname, $op));
        $count++;
      }
    }

    foreach($s as &$_s) {
      if (count($_s) === 1) {
        $_s = $_s[0];
      } else {
        $_s = implode(' AND ', $_s);
      }
    }

    if(count($s)>0) {
      if(! isset($searches['_rules'])) {
        $whereClause = 'WHERE ' . implode(' AND ', $s);
      } else {
        $rule = explode(' ', $searches['_rules']);
        foreach ($rule as &$r) {
          if (preg_match('/([a-zA-Z0-9_:]+)/', $r, $matches)) {
            if (!empty($s[$matches[0]])) {
              $r = str_replace($matches[0], $s[$matches[0]], $r);
            }
          }
        }
        $whereClause = 'WHERE ' . implode(' ', $rule);
      }
    }

    return $whereClause;
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
    if(! empty($options['search']) || !empty($options['s'])) {
      $statement .= ' ' . $this->prepareSearch(empty($options['search']) ? $options['s'] : $options['search']);
    }

    if(! empty($options['sort'])) {
      $statement .= ' ' . $this->prepareSort($options['sort']);
    }
    
    if(! empty($options['limit'])) {
      $statement .= ' ' . $this->prepareLimit($options['limit']);
    }
    return $statement;
  }

  function getCount ($options) {
    if (! empty($options['limit'])) {
      unset($options['limit']);
    }
    $pre = $this->prepare_statement($this->req('count'), $options);
    try {
      $res = $this->get_db(false)->query($pre);
      $data = $res->fetch();
      return array($data[0], $data[0]);
    } catch(\Exception $e) {
      $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
      return array(NULL, 0);
    }
    return array(NULL, 0);
  }

  function unprefix($entry, $table = NULL) {
    $nullTable = array();
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
          if (!isset($nullTable['_' . $s[0]])) {
            $nullTable['_' . $s[0]] = true;
          }
          if (!isset($unprefixed['_' . $s[0]])) {
            $unprefixed['_' . $s[0]] = array();
          }

          if (!is_null($v)) {
            $nullTable['_' . $s[0]] = false;
          }
          $unprefixed['_' . $s[0]][$s[1]] = $v;
        } else {
          $unprefixed[$s[1]] = $v;
        }
      }
    }

    foreach($nullTable as $k => $v) {
      if ($v) {
        $unprefixed[$k] = null;
      }
    }

    return $unprefixed;
  }

  function _postprocess ($entry) {
    $dt = $this->conf('datetime');
    $private = $this->conf('private') ? $this->conf('private') : array();
    foreach ($entry as $k => $v) {
      if ($this->conf('postprocess') && is_callable($this->conf('postprocess'))) {
        $entry[$k] = \call_user_func($this->conf('postprocess'), $k, $v);
      }
      if (in_array($k, $private)) {
        $entry[$k] = null;
        unset ($entry[$k]);
        continue;
      }
      if (is_array($dt) && in_array($k, $dt)) {
        $entry[$k] = $this->DataLayer->datetime($v);
      }
    }

    return $entry;
  }

  function extendEntry ($entry, &$result) {
    return $entry;
  }
  
  function listing($options) {
    $result = new \artnum\JStore\Result();
    $ids = array();
    $pre_statement = $this->prepare_statement($this->req('get'), $options);
    try {
      $st = $this->get_db(true)->prepare($pre_statement);
      if(!$st->execute()) {
        $result->addError($st->errorInfo()[2], $st->errorInfo());
      } else{
        $data = $st->fetchAll(\PDO::FETCH_ASSOC);
        foreach($data as $d) {
          if (!in_array($d[$this->IDName], $ids)) {
            $id = $d[$this->IDName];
            $d = $this->_postprocess($this->unprefix($d));
            $d = $this->extendEntry($d, $result);
            $result->addItem($d);
            $ids[] = $id;
          }
        }
      }
    } catch(\Exception $e) {
      $result->addError($e->getMessage(), $e);
    }

    return $result;
  }

  function _read($id) {
    $result = new \artnum\JStore\Result();
    $results = $this->listing(array('search' => array(str_replace($this->Table . '_', '', $this->IDName) => $id)));

    if (is_array($results)) {
      if ($results[1] !== 1) {
        $result->addError('Not one result as expected');
      } else {
        $result->setItems($results[0][0]);
        $result->setCount(1);
      }
    } else {
      $result->copyError($results);
      if($results->getCount() !== 1) {
        $result->addError('Not one result as expected');
      } else {
        $result->setItems($results->getItems()[0]);
        $result->setCount(1);
      }
    }

    return $result;
  }

  function _exists($id) {
    try {
      $st = $this->get_db(true)->prepare($this->req('exists'));
      $bind_type = ctype_digit($id) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
      $st->bindParam(':id', $id, $bind_type);
      if($st->execute()) {
        if($st->fetch()) {
          return TRUE;
        }
      }
    } catch(\Exception $e) {
      $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
      return FALSE;
    }

    return FALSE;
  }

  function _type($col, $value) {
    $types = $this->conf('force-type');
    if (!is_null($types) && isset($types[$col])) {
      switch(strtolower($types[$col])) {
        case 'str':  case 'string': case 'char': case 'character':
        default:
          return \PDO::PARAM_STR;
        case 'numeric': case 'number': case 'integer': case 'int': case 'num':
          return \PDO::PARAM_INT;
        case 'bool': case 'boolean':
          return \PDO::PARAM_BOOL;
      }
    }

    return NULL;
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
      $db = $this->get_db(false);
      $st = $db->prepare($this->req('create', array('COLTXT' => $columns_txt, 'VALTXT' => $values_txt)));
      foreach($data as $k_data => &$v_data) {
        $bind_type = $this->_type($k_data, $v_data);
        if (is_null($bind_type)) {
          $bind_type = \PDO::PARAM_STR;
          if(is_null($v_data)) { $bind_type = \PDO::PARAM_NULL; }
          else if(ctype_digit($v_data)) { $bind_type = \PDO::PARAM_INT; }
          else if(is_bool($v_data)) { $bind_type = \PDO::PARAM_BOOL; }
        }

        $st->bindParam(':' . $k_data, $v_data, $bind_type);
      }

      $transaction = false;
      if($this->conf('auto-increment')) {
        $db->beginTransaction();
        $transaction = true;
      }

      if(! $st->execute()) {
        if($transaction) {
          $db->rollback();
        }

        return FALSE;
      }
      if($this->conf('auto-increment')) {
        $idx = $db->lastInsertId($this->IDName);
        $db->commit();
        return $idx;
      } else {
        return $data[$this->IDName];
      }
    } catch (\Exception $e) {
      $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
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
      $db = $this->get_db(false);
      $st = $db->prepare($this->req('update', array('COLVALTXT' => $columns_values_txt)));
      foreach($data as $k_data => &$v_data) {
        $bind_type = $this->_type($k_data, $v_data);
        if (is_null($bind_type)) {
          $bind_type = \PDO::PARAM_STR;
          if(is_null($v_data)) { $bind_type = \PDO::PARAM_NULL; }
          else if(ctype_digit($v_data)) { $bind_type = \PDO::PARAM_INT; }
          else if(is_bool($v_data)) { $bind_type = \PDO::PARAM_BOOL; }
        }

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
      $this->error('Database error : ' . $e->getMessage(), __LINE__, __FILE__);
      return FALSE;
    }
  }

  function _overwrite($data, $id = NULL) {
    $defaults = $this->conf('defaults');
    if (is_array($defaults)) {
      foreach($defaults as $k => $v) {
        if (!isset($data[$k])) {
          $data[$k] = $v;
        }
      }
    }
    return $this->write($data, $id);
  }

  function _write($data, $id = NULL) {
    $result = new \artnum\JStore\Result();
    $prefixed = array();
    $ignored = is_array($this->conf('ignored')) ? $this->conf('ignored') : array();
    foreach($data as $k => $v) {
      if (in_array($k, $ignored)) {
        continue;
      }
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

    if(empty($prefixed[$this->IDName])) {
      if (!is_null($this->conf('create'))) {
        if (!$this->conf('create.ts')) {
          $prefixed[$this->conf('create')] = $this->DataLayer->datetime(time());
        } else {
          $prefixed[$this->conf('create')] = time();
        }
      }
      $result->addItem(['id' => $this->create($prefixed)]);
    } else {
      $result->addItem(['id' => $this->update($prefixed)]);
    }

    return $result;
  }

  function uid($data) {
    return \artnum\genId(serialize($data));
  }
}
?>
