<?PHP

namespace artnum\Auth;

require('phpseclib/autoload.php');

class Menshen
{
  protected $certStore;
  protected $storePriv;

  function __construct($store, $storePrivateData = null)
  {
    $this->Auth = [];
    $this->certStore = $store;
    $this->storePriv = $storePrivateData;
    $this->certStore->init($this->storePriv);
  }

  function check()
  {
    try {
      if (!$this->certStore->beginCheck($this->storePriv)) {
        return false;
      }

      $auth = $this->getAuth();
      if (empty($auth)) {
        return false;
      }
      
      $pkey = $this->certStore->getPublicKey($auth['cid'], $this->storePriv);
      if (empty($pkey)) {
        return false;
      }

      $rsa = new \phpseclib\Crypt\RSA();
      if (!$rsa->loadKey($pkey)) {
        return false;
      }

      $mid = $this->getMID();
      if (empty($mid)) {
        return false;
      }

      $rsa->setHash($auth['dgt']);
      $rsa->setMGFHash($auth['mgf']);
      $rsa->setSaltLength($auth['sle']);
      $rsa->setSignatureMode(\phpseclib\Crypt\RSA::SIGNATURE_PSS);

      $success = $rsa->verify($mid, $auth['sig']);
      if (!$this->certStore->endCheck($auth['cid'], $success, $this->storePriv)) {
        return false;
      }
      $this->Auth = $auth;

      return $success;
    } catch (\Exception $e) {
      echo $e->getMessage();
      error_log('Menshen::"' . $e->getMessage() . '"');
      return false;
    }
  }

  public function getCID() {
    if (!empty($this->Auth) && !empty($this->Auth['cid'])) {
      return $this->Auth['cid'];
    }
    return null;
  }

  protected function getAuth()
  {
    $args = [
      'cid' => false, /* client id */
      'sig' => false, /* signature */
      'dgt' => 'sha256', /* digest */
      'sle' => 0, /* saltlen */
      'mgf' => 'sha256'
    ];

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
      $auth = strtolower(trim($_SERVER['HTTP_AUTHORIZATION']));
      if (substr($auth, 0, 8) === 'menshen ') {
        $authstr = explode(',', substr($auth, 8));
        foreach ($authstr as $p) {
          $_p = explode('=', $p);
          $k = trim($_p[0]);
          $v = trim($_p[1]);
          switch ($k) {
            case 'cid':
            case 'sle':
              $args[$k] = $v;
              break;
            case 'sig':
              $args[$k] = hex2bin($v);
              break;
            case 'mgf':
            case 'dgt':
              switch ($v) {
                case 'md2':
                case 'md5':
                case 'sha1':
                case 'sha256':
                case 'sha384':
                case 'sha512':
                  $args[$k] = $v;
                  break;
              }
          }
        }

        if ($args['cid'] === false || $args['sig'] === false) {
          return [];
        }

        return $args;
      }
    }
    return [];
  }

  protected function getMID()
  {
    if (
      !empty($_SERVER['REQUEST_METHOD']) &&
      !empty($_SERVER['HTTP_X_REQUEST_ID']) &&
      !empty($_SERVER['REQUEST_URI'])
    ) {
      return sprintf(
        '%s|%s|%s',
        strtolower(trim($_SERVER['REQUEST_METHOD'])),
        $_SERVER['REQUEST_URI'],
        strtolower(trim($_SERVER['HTTP_X_REQUEST_ID']))
      );
    }
    return '';
  }
}


namespace artnum\Auth\Menshen;

interface CertStore
{
  public function init($privateData);
  public function beginCheck($privateData);
  public function endCheck($clientId, $success, $privateData);
  public function getPublicKey($clientId, $privateData);
}


/* == DB ==
CREATE TABLE IF NOT EXISTS "menshen" ("clientid" INTEGER PRIMARY KEY AUTO_INCREMENT, "pkcs8" TEXT NOT NULL);
*/
class PDOStore implements CertStore
{
  protected $db;
  protected $tname;

  function __construct($pdoConn, $tableName)
  {
    $this->db = $pdoConn;
    $this->tname = $tableName;
  }

  function init($privateData)
  {
    return true;
  }

  function beginCheck($privateData)
  {
    return true;
  }

  function endCheck($clientId, $success, $privateData)
  {
    return true;
  }

  function getPublicKey($clientId, $privateData)
  {
    $st = $this->db->prepare('SELECT * FROM "' . $this->tname . '" WHERE "clientid" = :cid');
    $st->bindParam(':cid', $clientId, \PDO::PARAM_INT);
    if ($st->execute()) {
      if (($row = $st->fetch()) !== false) {
        return $row['pkcs8'];
      }
    }

    return false;
  }
}
?>
