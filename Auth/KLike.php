<?PHP

namespace artnum/Auth;

require('phpseclib/autoload.php');

/* a kerberos-like authentication but simpler */
class KLike {
  protected $userSt;
  protected $ticketSt;
  private $aesKeyLength = 192;
  private $kdSaltLen = 128; 
  private $kdIteration = 5000; /* this can be done on weak smartphone */
  private $kdAlgo = 'sha256';
  private $hmacAlgo = 'sha256';
  private $hashPrincipal = 'sha256';
  
  function __construct ($ticket, $user) {
    $this->userSt = $user;
    $this->ticketSt = $ticket;
  }

  private function randomBytes ($length) {
    return \phpseclib\Crypt\Random::string($length);
  }
  
  protected function genAuthKey () {
    return $this->randomBytes($this->aesKeyLen >> 3); /* divide by 8 */
  }

  protected function encrypt ($data, $key) {
    $cipher = new \phpseclib\Crypt\AES(\phpseclib\Crypt\AES::MODE_CTR);
    $cipher->setKey($key);
    $cipher->setKeyLength($this->aesKeylen);
    $cipher->setIV($this->randomBytes($cipher->getBlockLength() >> 3));
    return $cipher->encrypt($data);
  }

  protected function genTicket ($userid, $sesskey) {
    $ticket = [
      'time' => date('c', time()),
      'ip' => $_SERVER['REMOTE_ADDR'],
      'userid' => base64_encode($userid)
    ];
    $ticket = $this->encrypt(json_encode($ticket), $sesskey, $this->aesKeyLength);
  }

  protected function genSessionKey ($userkey) {
    return $this->encrypt($this->genAuthKey(), $userkey);
  }

  /* principal stored is a hashed version of principal. Why ?
   * principal is designed to be let free for the user to choose, we normalize Unicode value
   * hash it and store it. If user loose his principal, db will have mail stored next to.
   * Principal stored : SHA256(NFKC(P)).
   */
  protected function getPrincipalFromPrincipal ($principal) {
    $nPrincipal = \Normalizer::normalize($principal, \Normalizer::FORM_KC);
    return hash($this->hashPrincipal, $nPrincipal);
  }

  protected function protectUserPassword($clearTextPassword) {
    $salt = $this->randomBytes($saltLen >> 3);
    $password = hash_pbkdf2($this->kdAlgo, $clearTextPassword, $salt, $this->kdIteration, 0, true);
    return $password;
  }
  
  /* create user use pbkdf2 to store the key in a way that avoid having pw leak in case of db leak */
  protected function createUser ($principal, $clearTextPassword) {
    $hPrincipal = $this->getPrincipalFromPrincipal($principal);
    $password = $this->protectUsePassword($clearTextPassword);

    $this->userSt->save(null, $hPrincipal, $password);
  }

  protected function updatePassword ($principal, $newClearTextPassword, $hmacNewPassword) {
    $hPrincipal = $this->getPrincipalFromPrincipal($principal);
    $id = $this->userSt->getIdFromPrincipal($hPrincipal);
    $password = $this->userSt->getPassword($id);
    
    if (hash_equals(base64_decode($hmacNewPassword), hash_hmac($this->hmacAlgo, $newClearTextPassword, $password, true))) {
      $password = $this->protectUserPassword($newClearTextPassword);
      $this->userSt->save($id, $hPrincipal, $password);
    }
  }
}

namespace artnum\Auth\KLike;

class PDOUser {
  protected $pdo;
  protected $table = 'user';
  protected $cols = [
    '__ID__' => 'user_id',
    '__PRINCIPAL__' => 'user_principal',
    '__PASSWORD__' => 'user_password'
  ];
  protected $queries = [
    'CREATE' => 'INSERT INTO "__TABLE__" ("__PRINCIPAL__", "__PASSWORD__") VALUES (:principal, :password)',
    'UPDATE' => 'UPDATE "__TABLE__" SET "__PASSWORD__" = :password WHERE "__ID__" = :id',
    'GETBYPRINCIPAL' => 'SELECT "__ID__", "__PRINCIPAL__", "__PASSWORD__" FROM "__TABLE__" WHERE "__PRINCIPAL__" = :principal',
    'GETBYID' => 'SELECT "__ID__", "__PRINCIPAL__", "__PASSWORD__" FROM "__TABLE__" WHERE "__ID__" = :id'
  ];
  
  function __construct ($pdo, $table, $cols) {
    $this->pdo = $pdo;
    $this->table = $table;
    $this->cols = $cols;
    foreach ($this->queries as &$q) {
      $q = str_replace('__TABLE__', $this->table, $q);
      foreach ($this->cols as $name => $col) {
        $q = str_replace($name, $col, $q);
      }
    }
  }

  private getById ($id) {
    $st = $this->pdo->prepare($this->queries['GETBYID']);
    $st->bindParam(':id', $principal, \PDO::PARAM_INT);
    if (!$st || !$st->execute()) {
      return FALSE;
    }
    return $st->fetch(\PDO::FETCH_ASSOC);
  }
  
  private getByPrincipal ($principal) {
    $st = $this->pdo->prepare($this->queries['GETBYPRINCIPAL']);
    $st->bindParam(':principal', $principal, \PDO::PARAM_STR);
    if (!$st || !$st->execute()) {
      return FALSE;
    }
    return $st->fetch(\PDO::FETCH_ASSOC);
  }
  
  function getIdByPrincipal ($principal) {
    $row = $this->getByPrincipal($principal);
    if ($row !== FALSE) {
      return $row[$this->cols['__ID__']];
    }
    return FALSE;
  }

  function getPasswordById ($id) {
    $row = $this->getById($id);
    if ($row !== FALSE) {
      return $row[$this->cols['__PASSWORD__]];
    }
    return FALSE;
  }
  
  function save ($id, $principal, $password) {
    $st = null;

    if ($id === NULL) {
      $st = $this->pdo->prepare($this->queries['CREATE']);
      $st->bindParam(':principal', $principal, \PDO::PARAM_STR);
      $st->bindParam(':password', $password, \PDO::PARAM_STR);
    } else {
      $st = $this->pdo->prepare($this->queries['UPDATE']);
      $st->bindParam(':password', $password, \PDO::PARAM_STR);
    }
    
    if (!$st && !$this->pdo->beginTransaction()) {
      return FALSE;
    }
    if (!$st->execute()) {
      $this->pdo->rollback();
      return FALSE;
    }

    $row = $this->getByPrincipal($principal);
    if ($row === FALSE) {
      $this->pdo->rollback();
      return FALSE;
    }
    $this->pdo->commit();

    return $row[$this->cols['__ID__']];
  }
}

class MCTicket {
  private $TTL = 
}

?>
