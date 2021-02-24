<?PHP
/*- 
 * Copyright (c) 2019 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

class Medoo extends OP {
   protected $DB;
   protected $IDName;
   protected $RRCount;
   protected $Table;
   protected $DataLayer;

   function __construct ($db, $table, $id_name, $config) {
      $this->DB = array('ro' => array(), 'rw' => array());
      $this->RRCount = array('ro' => 0, 'rw' => 0);
      $this->Table = $table;
      $this->IDName = $id_name;
      $this->set_db($db);
      $this->DataLayer = new Data();
   }

   function set_db ($db) {
      if (is_null($dn)) { return; }
      $this->DB['rw'][] = $db;
   }

   function get_db ($readonly = false) {
      $src = 'rw';
      if ($readonly && count($this->DB['ro'])) {
         $src = 'ro';
      }

      if ($this->RRCount[$src] + 1 >= count($this->DB[$src])) {
         $this->RRCount[$src] = 0;
      } else {
         $this->RRCount[$src]++; 
      }

      return $this->DB[$src][$this->RRCount[$src]];
   }

   function add_db ($db, $readonly = false) {
      if ($readonly) {
         $this->DB['ro'][] = $db;
      } else {
         $this->DB['rw'][] = $db;
      }
   }

   function dbtype () {
      return 'medoo';
   }

   function prefix ($data) {
      $prefixed = array();
      foreach ($data as $k => $v) {
         $prefixed[$this->conf('Table') . '_' . $k] = $v;
      }
      return $v;
   }

   function _overwrite ($arg) {
      $defaults = $this->conf('defaults');
      if (is_array($defaults)) {
         foreach ($defaults as $k => $v) {
            if (!isset($arg[$k])) {
               $arg[$k] = $v;
            }
         }
      }

      return $this->write($arg);
   }

   function _write ($arg) {
      $func = 'update';
      $time = array('mtime');
      if (!is_null($this->conf('delete')) && !$this->conf('delete.no-auto-undelete')) { 
         $arg[$this->conf('delete')] = NULL;
      }
      if (!isset($arg[$this->IDName]) || empty($arg[$this->IDName])) {
         if (!$this->conf('auto-increment')) {
            $arg[$this->IDName] = $this->uid($arg);
         }
         $time[] = 'create';
         $func = 'insert';
      } else {
         $where = array($this->IDName => $arg[$this->IDName]);
         unset($arg[$this->IDName]);
      }

      foreach ($time as $t) {
         if (!is_null($this->conf($t))) {
            if (!$this->conf($t . '.ts')) {
               $arg[$this->conf($t)] = $this->DataLayer->datetime(time());
            } else {
               $arg[$this->conf($t)] = time();
            }
         }
      }

      $data = $this->prefix($arg);
      $db = $this->get_db(true);
      if ($func === 'create') {
         $pdo = $db->$func($this->Table, $data);
         $id = $db->id();   
      } else {
         $pdo = $this->get_db(true)->$func($this->Table, $data, $where); 
         $id = current($where);
      }

      return array(array(array('id' => $id)), 1);
   }

   function search ($arg) {
      $tables = array();
      foreach ($arg['search'] as $k => $v) {
         $field = explode('_', $k, 2);
         if (count($field) > 1 && !in_array('[>]' . $field[0], $tables)) {
            $tables['[>]' . $field[0]] = $field[0];
         }
      } 
   }

   function _listing ($arg) {
      
   }


   function _read ($arg) {
   }
}
