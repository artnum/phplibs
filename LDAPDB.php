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

class LDAPDB {

  protected $Selected;
  protected $Res;
  protected $base;
  
  function close() {
    foreach (array( 'ro', 'rw') as $type) {
      foreach ($this->Res[$type] as $conn) {
        ldap_close($conn['_']);
      }
    }
    $this->Res = array('ro' => array(), 'rw' => array());
  }

  function __destruct() {
    $this->close();
  }

  function getRootDSE($conn) {
    if (($rRoot = ldap_read($conn, '', '(objectclass=*)', array('+'))) !== FALSE) {
      return ldap_get_entries($conn, $rRoot);
    }
  }
     
  function __construct($servers, $base = NULL) {
    $this->base = $base;
    
    if(! is_array($servers)) {
      $servers = array($servers);
    }
   
    $this->Res = array('ro' => array(), 'rw' => array());

    foreach($servers as $s) {
      $res = ldap_connect($s['uri']);
      $dse = $this->getRootDSE($res);
      if ($dse['count'] < 1) { continue; }

      /* set version */
      $ldapVersion = 3;
      if (!empty($dse[0]['supportedldapversion']) &&
          $dse[0]['supportedldapversion']['count'] > 0) {
        $ldapVersion = $dse[0]['supportedldapversion'][0];
      }
      ldap_set_option($res, \LDAP_OPT_PROTOCOL_VERSION, 3);

      /* verify if apply to base or set base */
      if (empty($dse[0]['namingcontexts'])) { error_log('Empty namingcontexts'); continue; }
      if ($dse[0]['namingcontexts']['count'] < 1) { error_log('Empty namingcontexts'); continue; }
      if ($this->base !== null) {
        $valid = false;
        for($i = 0; $i < $dse[0]['namingcontexts']['count']; $i++) {
          if (strstr($this->base, $dse[0]['namingcontexts'][$i])) {
            $valid = true;
            break;
          }
        }
        if (!$valid) { error_log('No valid namingcontexts');  continue; }
      } else {
        /* base is set to the first naming context of the first server */
        $this->base = $dse[0]['namingcontexts'][0];
      }
     
      if($res) {
        if(ldap_bind($res, $s['dn'], $s['password'])) {
          if(isset($s['ro']) && $s['ro']) {
            $this->Res['ro'][] = array('_' => $res, 'server' => $s);
          } else {
            $this->Res['rw'][] = array('_' => $res, 'server' => $s);
          }
        }        
      }
    }

    if (empty($this->Res['ro']) && empty($this->Res['rw'])) {
      throw new \Error('No valid server');
    }
  }

  function _con($t = 'ro') {
    if(isset($this->Selected[$t])) { return $this->Selected[$t]['_']; }

    $type = $t;
    if(!isset($this->Res[$type])) {
      $type = 'rw'; /* there should be, at least, one rw connection */ 
      if(!isset($this->Res[$type])) { return NULL; }
    }

    if(count($this->Res[$type]) === 1) {
      $this->Selected[$t] = $this->Res[$type][0];
    } else {
      if (empty($this->Res[$type])) {
        $type = $type === 'ro' ? 'rw' : 'ro';
        if (empty($this->Res[$type])) { return NULL; }
      }
      $this->Selected[$t] = $this->Res[$type][rand(0, count($this->Res[$type]) - 1)];
    }

    return $this->Selected[$t]['_'];
  }

  function getBase() {
    return $this->base;
  }
  
  function readable() {
    return $this->_con('ro');
  }

  function writable() {
    return $this->_con('rw');
  }
}

?>
