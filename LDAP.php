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

class LDAP extends \artnum\JStore\OP {
  protected $DB;
  protected $Suffix;
  protected $Config;
  protected $Attribute;

  function __construct($db, $suffix, $attributes, $config) {
    $this->DB = $db;
    $this->Suffix = $suffix;
    $this->Config = $config;
    $this->Binary = array();

    if(is_array($this->Config['binary'])) {
      foreach($this->Config['binary'] as $b) {
        array_push($this->Binary, strtolower($b));
      }
    }
    
    if(is_array($attributes)) {
      $this->Attribute = array();
      foreach($attributes as $attr) {
        array_push($this->Attribute, strtolower($attr));
      }
    } else {
      $this->Attribute = NULL;
    }
  }

  function dbtype() {
    return 'ldap';
  }

  function set_db($db) {
    $this->DB = $db;
  }

  function _dn($dn = NULL) {
    $ret = NULL;
    if(!is_null($this->Suffix)) {
      if(empty($dn) || is_null($dn)) {
        $ret = $this->Suffix;
      } else {
        $ret = $dn . ',' . $this->Suffix;
      }
    }

    if ($ret != NULL) {
      return $ret;
    }

    return NULL;
  }

  function get($dn, $conn) {
    $res = @ldap_read($conn, $this->_dn($dn), '(objectclass=*)', $this->Attribute);
    if($res && ldap_count_entries($conn, $res) == 1) {
      return ldap_first_entry($conn, $res);
    }
    return NULL;
  }

  function _read($dn) {
    $c = $this->DB->readable();
    $entry = $this->get(rawurldecode($dn), $c);
    if($entry) {
      $_ber = null;
      $dn = ldap_get_dn($c, $entry);
      $dn = ldap_explode_dn($dn, 0);
      $ret = array('IDent' => rawurlencode($dn[0]));
      for($attr = ldap_first_attribute($c, $entry, $_ber); $attr !== FALSE; $attr = ldap_next_attribute($c, $entry, $_ber)) {
        $attr = strtolower($attr); $keep = true;
        if(! is_null($this->Attribute) && ! in_array($attr, $this->Attribute)) {
          $keep == false;
        }
        if($keep) {
          $value = FALSE;
          if(in_array($attr, $this->Binary)) {
            $value = ldap_get_values_len($c, $entry, $attr);
            for($i = 0; $i < $value['count']; $i++) {
              $value[$i] = base64_encode($value[$i]);
            }
          } else {
            $value = ldap_get_values($c, $entry, $attr);
          }

          if($value !== FALSE) {
            if($value['count'] == 1) {
              $value = $value[0];
            } else {
              unset($value['count']);
            }
            $ret[$attr] = $value;
          }
        }
      }
      return array(array($ret), 1);
    }
    return array(NULL, 0);
  }

  function _delete($dn) {
    $c = $this->DB->writable();
    if($this->exists($dn)) {
      return array(ldap_delete($c, $this->_dn($dn)), 0);
    }
  }

  function _exists($dn) {
    $c = $this->DB->readable();
    $res = ldap_read($c, $this->_dn($dn), '(objectclass=*)', array('dn'));
    if($res && ldap_count_entries($c, $res) == 1) {
      return TRUE;
    }
    return FALSE;
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
          $filter[$name] = '&' . $f;
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
    return $filter;
  }

  function listing($options) {
    $c = $this->DB->readable();
    if(isset($options['search'])) {
      $filter = $this->prepareSearch($options['search']);
    } else {
      $filter = '(objectclass=*)';
    }
    $res = ldap_list($c, $this->_dn(), $filter, array('dn'));
    $ret = array();
    if($res) {
      for($e = ldap_first_entry($c, $res); $e; $e = ldap_next_entry($c, $e)) {
        $dn = ldap_get_dn($c, $e);
        $dn = ldap_explode_dn($dn, 0);
        $r = $this->read(rawurlencode($dn[0]));
        if ($r[1] == 1) {
          $ret[] = $r[0][0];
        }
      }
    }
    return array($ret, count($ret));
  }

  function buildDn ($rdnValue, $sub = null) {
    $base = $this->Suffix;
    $rdnAttr = $this->conf('rdnAttr');

    if ($sub != null) {
      if (is_array($sub)) {
        $subTxt = join(',', $sub);
      } else {
        $subTxt = $sub;
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
      hash_update($ctx, $v);
    }
    return hash_final($ctx);
  }
  
  function do_write ($data, $overwrite = false) {
    $conn = $this->DB->writable();

    $singleValue = $this->conf('singleValue');
    if (!$singleValue) { $singleValue = array(); }

    $binaryAttrs = $this->conf('binary');
    if (!$binaryAttrs) { $binaryAttrs = array(); }

    $attrsToBuild = $this->conf('toBuild');
    if (!$attrsToBuild) { $attrsToBuild = array(); }
    
    if (isset($data['IDent'])) {
      $entry = $this->get(rawurldecode($data['IDent']), $conn);
      $dn = ldap_get_dn($conn, $entry);
      $mods = array();
      $entryVal = array();
      $ber = null;
      for ($attr = ldap_first_attribute($conn, $entry, $ber); $attr !== FALSE; $attr = ldap_next_attribute($conn, $entry, $ber)) {
        $attr = strtolower($attr);
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
      
      foreach ($mods as $mod) {
        if (in_array($mod['attrib'], $singleValue) &&
            $mod['modtype'] !== LDAP_MODIFY_BATCH_REMOVE_ALL && count($mod['values']) > 1) {
          $mod['values'] = array($mod['values'][0]);
        }
        switch ($mod['modtype']) {
          case LDAP_MODIFY_BATCH_REMOVE_ALL:
            ldap_mod_del($conn, $dn, $mod['attrib']); break;
          case LDAP_MODIFY_BATCH_ADD:
            ldap_mod_add($conn, $dn, array($mod['attrib'] => $mod['values'])); break;
          case LDAP_MODIFY_BATCH_REPLACE:
            ldap_mod_replace($conn, $dn, array($mod['attrib'] => $mod['values'])); break;
        }
      }
    } else {
      $rdnVal = $this->getRdnValue($data);
      $dn = $this->buildDn($rdnVal);
      $entry = array('objectclass' => $this->conf('objectclass'), $this->conf('rdnAttr') => array($rdnVal));
      foreach ($data as $k => $v) {
        switch ($k) {
          case 'dn':
          case 'IDent': continue;
            break;
          default:
            if (!isset($entry[$k])) {
              $entry[$k] = array();
            }
            if (in_array($k, $singleValue) && count($entry[$k]) === 1) {
              continue;
            }

            array_push($entry[$k], $v);
            break;
        }
      }

      foreach ($attrsToBuild as $k => $v) {
        $value = $v;
        foreach ($entry as $_k => $_v) {
          $value = str_replace('%' . $_k, $_v[0], $value);
        }
        $entry[$k] = array($value);
      }

      print_r($entry);
      ldap_add($conn, $dn, $entry);
    }
  }
  
  function _write ($data) {
    $this->do_write($data);
    return null;
  }
  function _overwrite ($data) {
    $this->do_write($data, true);
    return null;
  }
}
?>
