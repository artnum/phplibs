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
Namespace artnum;
use \Exception;

class LDAP extends \artnum\JStore\OP {
  protected $DB;
  protected $base;
  protected $Config;
  protected $Attribute;

  function __construct($db, $base = NULL, $attributes = NULL, $config = NULL) {
    $this->DB = $db;
    $this->base = $base;
    $this->Config = $config;
    $this->Binary = [];
    $this->filterAttributes = [];
    
    if(isset($this->Config['binary']) && is_array($this->Config['binary'])) {
      foreach($this->Config['binary'] as $b) {
        $this->Binary[] = strtolower($b);
      }
    }
    
    if(is_array($attributes)) {
      $this->Attribute = array();
      foreach($attributes as $attr) {
        $this->Attribute[] = strtolower($attr);
      }
    } else {
      $this->Attribute = null;
    }
  }


  function setAttributeFilter($attributes = []) {
    $this->filterAttributes = $attributes;
  }

  function dbtype() {
    return 'ldap';
  }

  function set_db($db) {
    $this->DB = $db;
  }

  function get_db() {
    return $this->DB;
  }

  function getBase () {
    if ($this->base) {
      return $this->base;
    } else {
      if ($this->DB) {
        return $this->DB->getBase();
      }
    }
    return NULL;
  }
  
  function _dn($dn = NULL) {
    $ret = NULL;
    if(!is_null($this->getBase())) {
      if(empty($dn) || is_null($dn)) {
        $ret = $this->getBase();
      } else {
        if (strpos($dn, '=') === FALSE && $this->conf('rdnAttr')) {
          $dn = $this->conf('rdnAttr') . '=' . $dn;
        } else {
          list ($attr, $value) = explode('=', $dn, 2);
          $dn = $attr . '=' . $value;
        }
        $ret = $dn . ',' . $this->getBase();
      }
    }
    if ($ret != NULL) {
      return $ret;
    }

    return NULL;
  }

  function get($dn, $conn) {
    $dn = $this->_dn($dn);
    $res = @ldap_read($conn, $dn, '(objectclass=*)', $this->Attribute ?? ['*']);
    if ($res && ldap_count_entries($conn, $res) === 1) {
      return ldap_first_entry($conn, $res);
    }
    return NULL;
  }

  function _read($dn) {
    $c = $this->DB->readable();
    $dn = rawurldecode($dn);
    $res = @ldap_read($c, $this->_dn($dn), '(objectclass=*)', $this->Attribute ?? ['*']);
    if (!$res) { 
      switch (ldap_errno($c)) {
        case 0x20:
          return ['count' => 0];
        default:
          throw new Exception(ldap_error($c)); 
      }
    }
    if(ldap_count_entries($c, $res) !== 1) { 
      throw new Exception('Too many result');
    }
    $entry = $this->processEntry($c, ldap_first_entry($c, $res), $result);
    if (!$entry) { throw new Exception('Error processing entry'); }
    $this->response->start_output();
    $this->response->print($entry);
    return ['count' => 1];
    
  }

  function _delete($dn) {
    $result = ['count' => 0, 'id' => $dn];
    $c = $this->DB->writable();
    if($this->exists($dn)) {
      if (ldap_delete($c, $this->_dn($dn))) {
        $this->response->print(['id' => $dn]);
        $result['count']++;
      }
    }
    return $result;
  }

  function _exists($dn) {
    $c = $this->DB->readable();
    $res = ldap_read($c, $this->_dn($dn), '(objectclass=*)', array('dn'));
    if($res && ldap_count_entries($c, $res) == 1) {
      return TRUE;
    }
    return FALSE;
  }

  function isUnary ($op) {
    if (!is_string($op)) { return false; }
    switch ($op) {
      case '--':
      case '-': 
      case '**':
      case '*': return true;
      default: return false;
    }
    return false;
  }

  function startsWith ($h, $n) {
    if (function_exists('str_starts_with')) { return str_starts_with($h, $n); }
    return (string)$n !== '' && strncmp($h, $n, strlen($n)) === 0;
  }

  function endsWith ($h, $n) {
    if (function_exists('str_ends_width')) { return str_ends_with($h, $n); }
    return (string)$n !== '' && substr($h, -strlen($n)) === (string)$n;
  }

  function query ($body, &$params, &$count) {
    if ($params === null) { $params = []; }
    if ($count === null) { $count = 0; }
    $predicats = [];
    $relation = ' AND ';
    foreach ($body as $key => $value) {
      if (substr($key, 0, 1) === '#') {
        $effectiveKey = explode(':', $key)[0];
        switch (strtolower($effectiveKey)) {
          case '#or':
            $relation = '|';
            break;
          case '#and':
            $relation = '&';
            break;
          case '#not':
            $relation = '!';
            break;
        }
        
        $predicats[] = '(' . $relation . $this->query($value, $params, $count) . ')';
      } else {
        if (!is_array($value)) {
          if ($this->isUnary($value)) {
            $value = [$value];
          } else {
            $value = ['=', $value, gettype($value)];
          }
        }
        if (!$this->isUnary($value[0])) {
          if (count($value) === 1) {
            $value = ['=', $value[0], gettype($value[0])];
          } else if (count($value) === 2) {
            $value = [$value[0], $value[1], gettype($value[1])];
          }
        }
        $type = 'str';
        if (isset($value[2])) {
          switch (strtolower($value[2])) {
            case 'int':
            case 'integer':
              $type = 'int';
              break;
            default:
              $type = 'str';
              break;
          }
        }
        $novalue = false;
        $predicat = '';
        $anyDirection = 0;
        $effectiveKey = explode(':', $key)[0];
        $valuePlaceHolder = ':params' . str_pad($count, 4, '0', STR_PAD_LEFT);
        switch ($value[0]) {
          case '<=':
          case '>=':
          case '>':
          case '<':
          case '=': $predicat = $effectiveKey . $value[0] . $valuePlaceHolder;  break;
          case '~': $predicat = $effectiveKey . '=~' . $valuePlaceHolder; break;
          case '~~': $predicat = $effectiveKey . '=' . $valuePlaceHolder; $anyDirection = 3; break;
          case '>~': $predicat = $effectiveKey . '=' . $valuePlaceHolder; $anyDirection = 1; break;
          case '<~':  $predicat = $effectiveKey . '=' . $valuePlaceHolder; $anyDirection = 2; break;
          case '!~~': $predicat = '!(' . $effectiveKey . '=' . $valuePlaceHolder . ')'; $anyDirection = 3; break;
          case '!>~': $predicat = '!(' . $effectiveKey . '=' . $valuePlaceHolder . ')'; $anyDirection = 1; break;
          case '!<~':  $predicat = '!(' .$effectiveKey . '=' . $valuePlaceHolder . ')'; $anyDirection = 2; break;
          case '!=' : $predicat = '!(' . $effectiveKey . '=' . $valuePlaceHolder . ')'; break;
          case '--':
          case '-': 
            $predicat = '!(' . $effectiveKey . '=*)'; $novalue = true; break;
          case '**':
          case '*': 
            $predicat = $effectiveKey . '=*'; $novalue = true; break;
        }
        if (!$novalue) {
          $v = strval($value[1]);
          switch($type) {
            case 'int':
              $v = intval($value[1]);
              break;
            default:
              $v = str_replace(['*', '%'], ['[[ANY]]', '[[ANY]]'], $v);
              switch($anyDirection) {
                default: break;
                case 3: 
                  if (!$this->startsWith($v, '[[ANY]]') && !$this->endsWith($v, '[[ANY]]')) {
                    $v = '[[ANY]]' . $v . '[[ANY]]';
                  } else if ($this->startsWith($v, '[[ANY]]') && !$this->endsWith($v, '[[ANY]]')) {
                    $v = $v . '[[ANY]]';
                  } else if (!$this->startsWith($v, '[[ANY]]') && $this->endsWith($v, '[[ANY]]')) {
                    $v = '[[ANY]]' . $v;
                  }
                  break;
                case 2: 
                  if (!$this->startsWith($v, '[[ANY]]')) {
                    $v = '[[ANY]]' . $v;
                  }
                  break;
                case 1: 
                  if (!$this->endsWith($v, '[[ANY]]')) {
                    $v = $v . '[[ANY]]';
                  }
                  break;
              }
              break;
          }
          $params[':params' . str_pad($count, 4, '0', STR_PAD_LEFT)] = [$v, $type];
          $count++;
        }
        $predicats[] = '(' . $predicat . ')';
      }
    }
    return join('', $predicats);
  }

  function prepareSearch($searches) {
    $op = ''; $s = 0;
    if(! is_array($searches)) {
      return '(objectclass=*)';
    }
    $filter = array();
    foreach($searches as $name => $terms) {
      if($name[0] == '_') { continue; }
      if(!is_array($terms)) {
        $terms = array($terms);
      }
      $f = array();
      foreach($terms as $search) {
        $value = substr($search, 1);
        switch($search[0]) {
          default:
            $value = $search;
          case '=': $op = $name . '=__VALUE__'; break;
          case '~': $op = $name . '~=__VALUE__'; break;
          case '!': $op = '!(' . $name . '=__VALUE__)'; break;
          case '-': $op = '!(' . $name . '=*)'; break;
          case '*':
            if (strlen(trim($value)) > 0) {
              $op  = $name . '=*__VALUE__';
            } else {
              $op = $name . '=*';
            }
          break;
          case '<':
            if($search[1] == '=') {
              $value = substr($value, 1);
              $op = $name . '<=__VALUE__';
            } else {
              $op = $name . '<__VALUE__';
            }
            break;
          case '>':
            if($search[1] == '=') {
              $value = substr($value, 1);
              $op = $name . '>=__VALUE__';
            } else {
              $op = $name . '>__VALUE__';
            }
            break;
        }
        $op = str_replace('__VALUE__', str_replace('\\%', '%', preg_replace('/(?<!\\\\)%/', '*', trim($value))), $op);
        $f[] = $op; $s++;
      }
      if (count($f) == 1) {
        $filter[$name] = $f[0];
      } else if (count($f) > 1) {
        $x = '';
        foreach ($f as $_f) {
          $x .= '(' . $_f . ')';
        }
        $f = $x;
        if(!isset($searches['_' . $name])) {
          if (isset($searches['_or']) && $searches['_or']) {
            $filter[$name] = '|' . $f;
          } else {
            $filter[$name] = '&' . $f;
          }
        } else {
          switch(strtolower($searches['_' . $name])) {
            default:
            case 'and':
              $filter[$name] = '&' . $f; break;
            case 'or':
              $filter[$name] = '|' . $f; break;
          }
        }
      }
    }
    if (isset($searches['_rules']) && is_string($searches['_rules'])) {
      $rules = $searches['_rules'];
      foreach ($filter as $k => $v) {
        $rules = str_replace('_' . $k . '_', $v, $rules);
      }
      $filter = $rules;
    } else {
      foreach ($filter as &$f) {
        $f = '(' . $f . ')';
      }
      if($s == 1) {
        /* I used "current" but had troubles with on some PHP version. This seems to do the trick */
        foreach ($filter as $v) { $filter = $v; break; }
      } else if($s > 1) {
        $op = '|';
        if (isset($searches['_operator'])) {
          switch (strtolower($searches['_operator'])) {
            case 'or':
            default:
              $op = '|';
              break;
            case 'and':
              $op = '&';
              break;
          }
        }
        $filter = '('. $op . implode('', $filter) . ')';
      } else {
        $filter = '(objectclass=*)';
      }
    }

    return str_replace('**', '*', $filter);
  }

  function processEntry ($conn, $ldapEntry, &$result) {
    if (!$conn || !$ldapEntry) { return NULL; }
    $attributes = $this->Attribute;
    if (!is_array($attributes)) { $attributes = null; }
    $entry = array();
    $_ber = NULL;
    $dn = ldap_explode_dn(ldap_get_dn($conn, $ldapEntry), 0);
    $entry['IDent'] = rawurlencode($dn[0]);
    for (
      $attr = ldap_first_attribute($conn, $ldapEntry, $_ber);
      $attr !== FALSE;
      $attr = ldap_next_attribute($conn, $ldapEntry, $_ber)
    ) {
      if (in_array($attr, $this->filterAttributes)) { continue; }
      $attr = strtolower($attr);
      $checkAttr = $attr;
      $options = null;
      if (strpos($attr, ';')) {
        [$checkAttr, $options] = explode(';', $attr, 2);
      }
      if (!is_null($attributes) && !in_array($checkAttr, $attributes)) { continue; }

      $value = array();
      if (in_array($attr, $this->Binary)) {
        $val = ldap_get_values_len($conn, $ldapEntry, $attr);
        for ($i = 0; $i < $val['count']; $i++) {
          $value[] = base64_encode($val[$i]);
        }
      } else {
        $val = ldap_get_values($conn, $ldapEntry, $attr);
        unset($val['count']);
        $value = $val;
      }

      if (count($value) <= 0) { continue; }
      if (count($value) === 1) { $value = $value[0]; }

      if($options) {
        if (!isset($entry[$checkAttr . ';'])) {
          $entry[$checkAttr . ';'] = [];
        }
        $entry[$checkAttr . ';'][$options] = $value;
      } else {
        $entry[$attr] = $value;
      }
    }
    return $entry;
  }

  function search($body, $options) {
    $result = ['count' => 0];
    $c = $this->DB->readable();

    $params = [];
    $count = 0;
    $filter = $this->query($body, $params, $count);

    $limit = 0;
    if (!empty($options['limit']) && ctype_digit(($options['limit']))) {
      $limit = intval($options['limit']);
    }

    foreach ($params as $k => $v) {
      $filter = str_replace($k, ldap_escape($v[0], '', LDAP_ESCAPE_FILTER), $filter);
    }
    $filter = str_replace('[[ANY]]', '*', $filter);

    $res = @ldap_list($c, $this->_dn(), $filter, $this->Attribute ?? [ '*' ], 0, $limit);
    if(!$res) { throw new Exception(ldap_error($c)); }
    
    $this->response->start_output();
    for($e = ldap_first_entry($c, $res); $e; $e = ldap_next_entry($c, $e)) {
      $entry = $this->processEntry($c, $e, $result);
      if (is_null($entry)) { continue; }
      $this->response->print($entry);
      $result['count']++;
    }
  
    return $result;
  }

  function listing($options) {
    $result = ['count' => 0];
    $c = $this->DB->readable();
    if(!empty($options['search'])) {
      $filter = $this->prepareSearch($options['search']);
    } else {
      $filter = '(objectclass=*)';
    }
    $limit = 0;
    if (!empty($options['limit']) && ctype_digit(($options['limit']))) {
      $limit = intval($options['limit']);
    }
    $res = @ldap_list($c, $this->_dn(), $filter, $this->Attribute ?? [ '*' ], 0, $limit);
    if(!$res) { throw new Exception(ldap_error($c)); }
    
    $this->response->start_output();
    for($e = ldap_first_entry($c, $res); $e; $e = ldap_next_entry($c, $e)) {
      $this->response->print($this->processEntry($c, $e, $result));
      $result['count']++;
    }
  
    return $result;
  }

  function buildDn ($rdnValue, $sub = null) {
    $base = $this->getBase();
    $rdnAttr = $this->conf('rdnAttr');

    if ($sub != null) {
      if (is_array($sub)) {
        $sub = join(',', $sub);
      } else {
        $sub = $sub;
      }
      $base = sprintf('%s,%s', $sub, $base);
    }
    
    if ($base && $rdnAttr && $rdnValue) {
      return sprintf('%s=%s,%s', $rdnAttr, $rdnValue, $base);
    }
    return null;
  }
  
  /* when extending this should be rewritten */
  function getRdnValue ($data) {
    $ctx = hash_init('sha1',  HASH_HMAC, date('c'));
    foreach ($data as $k => $v) {
      hash_update($ctx, $k);
      if (is_array($v)) {
        foreach ($v as $_v) {
          hash_update($ctx, $_v);  
        }
      } else {
        hash_update($ctx, $v);
      }
    }
    return hash_final($ctx);
  }
  
  function do_write ($data, $overwrite = false, &$id = null) {
    $conn = $this->DB->writable();

    $singleValue = $this->conf('singleValue');
    if (!$singleValue) { $singleValue = array(); }

    $binaryAttrs = $this->conf('binary');
    if (!$binaryAttrs) { $binaryAttrs = array(); }

    $attrsToBuild = $this->conf('toBuild');
    if (!$attrsToBuild) { $attrsToBuild = array(); }
    
    if (isset($data['IDent'])) {
      $ident = rawurldecode($data['IDent']);
      $id = $ident;
      $entry = $this->get($ident, $conn);
      if ($entry === NULL) { throw new Exception('Unknown entry'); }
      $dn = ldap_get_dn($conn, $entry);
      $mods = array();
      $entryVal = array();
      $ber = null;
      for ($attr = ldap_first_attribute($conn, $entry, $ber); $attr; $attr = ldap_next_attribute($conn, $entry, $ber)) {
        $attr = strtolower($attr);
        /* request for delete of attribute */
        if (isset($data['-' . $attr])) {
          $mods[] = ['attrib' => $attr, 'modtype' => LDAP_MODIFY_BATCH_REMOVE_ALL];
          unset($data['-' . $attr]);
          continue;
        }
        if (!isset($data[$attr])) { continue; } // skip attribute not yet set
        if (!is_array($data[$attr])) { $data[$attr] = array($data[$attr]); }
        
        /* decode binary attributes from base64 */
        if (in_array($attr, $binaryAttrs)) {
          $entryVal[$attr] = ldap_get_values_len($conn, $entry, $attr);
          foreach ($data[$attr] as &$v) {
            $v = base64_decode($v);
          }
        } else {
          $entryVal[$attr] = ldap_get_values($conn, $entry, $attr);
        }

        if ($overwrite) {
          if (!isset($data[$attr])) {
            $mods[] = array('attrib' => $attr, 'modtype' => LDAP_MODIFY_BATCH_REMOVE_ALL);
          } else {
            $mods[] = array('attrib' => $attr, 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => $data[$attr]);
          }
          unset($data[$attr]);
        } else {
          if (isset($data[$attr])) {
            $mods[] = array('attrib' => $attr, 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => $data[$attr]);
          }
          unset($data[$attr]);
        }
      }

      foreach ($data as $k => $v) {
        if ($k === 'IDent') { continue; }
        $mods[] = array('attrib' => $k, 'modtype' => LDAP_MODIFY_BATCH_ADD, 'values' => $v);
      }     
      
      $modResults = [];
      $fullSuccess = true;
      foreach ($mods as $mod) {
        if (in_array($mod['attrib'], $singleValue) &&
            $mod['modtype'] !== LDAP_MODIFY_BATCH_REMOVE_ALL && count($mod['values']) > 1) {
          $mod['values'] = array($mod['values'][0]);
        }
        $r = false;
        switch ($mod['modtype']) {
          case LDAP_MODIFY_BATCH_REMOVE_ALL:
            $r = ldap_mod_del($conn, $dn, [$mod['attrib'] => []]);
            $modResults[] = [$mod['attrib'] => $r, 'op' => 'remove']; break;
          case LDAP_MODIFY_BATCH_ADD:
            $r = @ldap_mod_add($conn, $dn, array($mod['attrib'] => $mod['values']));
            $modResults[] = [$mod['attrib'] => $r, 'op' => 'add']; break;
          case LDAP_MODIFY_BATCH_REPLACE:
            $r = @ldap_mod_replace($conn, $dn, array($mod['attrib'] => $mod['values']));
            $modResults[] = [$mod['attrib'] => $r, 'op' => 'modify']; break;
        }
        if ($r === false) { $fullSuccess = false; }
      }
      $this->response->start_output();
      $this->repsonse->print(['IDent' => $ident, 'success' => $fullSuccess, 'op' => 'edit', 'details' => $modResults]);
      return ['count' => 1, 'id' => $ident];
    } else {
      $rdnVal = $this->getRdnValue($data);
      $id = $rdnVal;
      $dn = $this->buildDn($rdnVal);
      if (is_callable($this->conf('objectclass'))) {
        $entry = ['objectclass' => $this->conf('objectclass')($data), $this->conf('rdnAttr') => array($rdnVal)];
      } else {
        $entry = ['objectclass' => $this->conf('objectclass'), $this->conf('rdnAttr') => array($rdnVal)];
      }
      foreach ($data as $k => $v) {
        switch ($k) {
          case 'dn':
          case 'IDent': break;
          default:
            if (!isset($entry[$k])) {
              $entry[$k] = array();
            }
            if (in_array($k, $singleValue) && count($entry[$k]) === 1) {
              break;
            }
            if (is_array($v)) {
              foreach ($v as $_v) {
                $entry[$k][] = $_v;
              }
            } else {
              $entry[$k][] = $v;
            }
            break;
        }
      }
      foreach ($attrsToBuild as $k => $v) {
        $value = $v;
        foreach ($entry as $_k => $_v) {
          $value = str_replace('%' . strtolower($_k), $_v[0], $value);
        }
        $entry[$k] = array($value);
      }
      if (@ldap_add($conn, $dn, $entry)) {
        $dn = ldap_explode_dn($dn, 0);
        $ident = rawurlencode($dn[0]);
        $this->response->start_output();
        $this->response->print(['IDent' => $ident, 'success' => true, 'op' => 'add']);
        return ['count' => 1, 'id' => $ident];
      } else {
        throw new Exception(ldap_error($conn));
      }
    }
  }
  
  function get_owner ($data, $id = null) {
    if ($id === null) { return -1; }
    return -1;
  }

  function _write ($data, &$id = null) {
    return $this->do_write($data, false, $id);
  }
  function _overwrite ($data, &$id = null) {
    return $this->do_write($data, true, $id);
  }
}
?>
