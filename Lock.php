<?PHP
Namespace artnum;

/* Use sqlite as backend as it works on OCFS2 clustered filesystem */
class Lock {
   protected $Type;
   protected $RKey;
   protected $DB;
   protected $Timeout;

   const LOCK_NONE = 0;
   const LOCK_SHARED = 25; // NOT USED
   const LOCK_EXCLUSIVE = 50;

   function __construct ($params, $tmpdir = null) {
      $this->Crypto = new \artnum\Crypto();
      $this->RKey = false;

      if (is_string($params)) {
         /* legacy sqlite */
         $params = array('dbtype' => 'legacy-sqlite', 'project' => $params, 'tmpdir' => $tmpdir);
      }

      switch ($params['dbtype']) {
      default:
      case 'pdo':
         $this->Type = 'pdo';
         if (!isset($params['db'])) {
            throw new Exception('No database specified');
         }
         $this->DB = $params['db'];
         switch ($this->DB->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
         case 'mysql':
            $this->DB->query('SET SQL_MODE=ANSI_QUOTES;');
            break;
         }
         $this->_getOrGenKey();
         break;
      case 'legacy-sqlite':
         $this->Type = 'sqlite';
         /* original implementation sqlite */
         if (isset($params['project'])) {
            $this->_sqlite($params['project'], isset($params['tmpdir']) ? $params['tmpdir'] : NULL);
         }
         break;
      }
   }

   function _getOrGenKey () {
      $stmt = $this->DB->prepare('SELECT "cle_data" FROM "cle" ORDER BY "cle_id" DESC;');
      if ($stmt->execute()) {
         $key = $stmt->fetch();
         if ($key) {
            $this->RKey = $key[0];
         }
      }

      if ($this->RKey == FALSE) {
         $this->RKey = $this->Crypto->random(64);
         $stmt = $this->DB->prepare('INSERT INTO "cle" ( "cle_data" ) VALUES ( :cledata );');
         $stmt->bindValue(':cledata', $this->RKey, \PDO::PARAM_LOB);
         if (!$stmt->execute()) {
            error_log('Cannot store key into database : ' . var_export($stmt->errorInfo(), true));
         }
      }
   }

   function _sqlite ($project, $tmpdir = NULL) {
      $file = $project .'-verrou.sqlite';
      $crypto = $project . '-verrou.rand';
      $dir = '';
      if (is_null($tmpdir) || !is_writable($tmpdir)) {
         $envdir = getenv('ARTNUM_LOCK_TMPDIR');
         if (!$envdir || !is_writable($envdir)) {
            $dir = sys_get_temp_dir();
         } else {
            $dir = $envdir;
         }
      } else {
         $dir = $tmpdir;
      }

      $file = $dir . '/' . $file;
      $crypto = $dir . '/' . $crypto;

      $new_key = true;
      if (file_exists($crypto)) {
         $this->RKey = file_get_contents($crypto);
         if ($this->RKey != FALSE) {
            $new_key = false;
         }
      }

      if ($new_key) {
         $this->RKey = $this->Crypto->random(64);
         file_put_contents($crypto, $this->RKey);
      }

      $this->Timeout = 900; /* Timeout default to 15 minutes */
      $this->DB = new \PDO('sqlite:' . $file);
      $this->DBFile = $file;
      try {
         $stmt = $this->DB->query('CREATE TABLE IF NOT EXISTS "verrou" ( "verrou_path" BLOB(32) UNIQUE NOT NULL, "verrou_key" BLOB(32) NULL, "verrou_state" INTEGER DEFAULT(' . self::LOCK_NONE . '), "verrou_timestamp" INTEGER ); CREATE INDEX IF NOT EXISTS "idxVerrouPath" ON "verrou"("verrou_path"); CREATE INDEX IF NOT EXISTS "idxVerrouKey" ON "verrou"("verrou_key");');
      } catch (\Exception $e) {
         /* ignore */
      }
   }

   function _genkey ($id, $prev_key = null) {
      $data = $id;
      $data .= $prev_key ? $prev_key : '-';
      foreach (array('REMOTE_ADDR', 'REMOTE_HOST', 'REMOTE_PORT', 'HTTP_REFERER', 'HTTP_ACCEPT_ENCODING', 'HTTP_USER_AGENT', 'HTTP_ACCEPT_LANGUAGE') as $i) {
         if (isset($_SERVER[$i])) {
            $data .= $_SERVER[$i];
         }
      }
      $data .= strval(time()) . strval(getmypid());

      $key = $this->Crypto->hmac($data, $this->RKey, true);
      return $key[0];
   }

   function _is_locked ($id) {
      $stmt = $this->DB->prepare('SELECT "verrou_state", "verrou_timestamp", "verrou_key" FROM "verrou" WHERE "verrou_path" = :id');
      $stmt->bindValue(':id', $id, \PDO::PARAM_LOB);
      $res = $stmt->execute();
      if($res) {
         $v = $stmt->fetch();
         if($v && intval($v[0]) > self::LOCK_NONE) {
            if($this->Timeout == 0) {
               return array($v[2], -1);
            }

            if(time() - $v[1]  < $this->Timeout) {
               return array($v[2], $this->Timeout - (time() - $v[1]));
            } else {
               return false;
            }
         }
      }
      return false;
   }

   function set_timeout ($timeout) {
      if (is_integer($timeout) && intval($timeout) >= 0) {
         $this->Timeout = $timeout;
      }
   }

   function lock ($path, $prev_key = null, $type = self::LOCK_EXCLUSIVE) {
      $result = array('lock' => $path, 'state' => 'unlocked', 'key' => '', 'timeout' => 0, 'error' => false);

      $id = $this->Crypto->hash($path, true)[0];
      $prev_key = is_null($prev_key) ? null : $this->Crypto->y64decode($prev_key);

      $can_lock = false;
      $locked = $this->_is_locked($id);
      if ($locked) {
         $key = $locked[0];
         $result['timeout'] = $locked[1];
      } else {
         $key = false;
         $result['timeout'] = -1;
         $can_lock = true;
      }

      if ($key && !is_null($prev_key)) {
         if (strcmp($key, $prev_key) == 0) {
            $can_lock = true;
         }
      } else if (!$key && is_null($prev_key)) {
         $can_lock = true;
      }

      if(! $this->_begin_transaction()) {
         error_log('Cannot start transaction');
         $result['error'] = true;
         return $result;
      }
      if($can_lock) {
         $key = $this->_genkey($id, $prev_key);
         $stmt = $this->DB->prepare('REPLACE INTO "verrou"("verrou_path", "verrou_state", "verrou_timestamp", "verrou_key") VALUES ( :id, :lock, :ts, :key)');
         $stmt->bindValue(':id', $id, \PDO::PARAM_LOB);
         $stmt->bindValue(':ts', time(), \PDO::PARAM_INT);
         $stmt->bindValue(':key', $key, \PDO::PARAM_LOB);
         $stmt->bindValue(':lock', $type, \PDO::PARAM_INT);
         $stmt->execute();
         $this->_commit();
         $l = $this->_is_locked($id);
         if ($l) {
            if (strcmp($l[0], $key) == 0) {
               $result['state'] = 'acquired';
               $result['key'] = $this->Crypto->y64encode($key);
               $result['timeout'] = $l[1];
            } else {
               $result['state'] = 'locked';
            }
         }
      } else {
         $this->_commit();
         $result['state'] = 'locked';
      }

      return $result;
   }

   function _commit () {
      switch ($this->Type) {
      default:
      case 'pdo':
         $this->DB->commit();
         break;
      case 'sqlite':
         $this->DB->query('COMMIT');
         break;
      }
   }

   function _begin_transaction () {
      $i = 0;
      $available = false;
      do {
         $tr = false;
         switch ($this->Type) {
         default:
         case 'pdo':
            $tr = $this->DB->beginTransaction();
            break;
         case 'sqlite':
            $tr = $this->DB->query('BEGIN IMMEDIATE TRANSACTION');
            break;
         }

         if(! $tr) {
            usleep(rand(1000, 3000));
         } else {
            $available = true;
         }
            $i++;
      } while(!$available && $i < 5);
      return $available;
   }

   function unlock ($path, $key) {
      $result = array('lock' => $path, 'state' => 'unlocked', 'key' => '', 'timeout' => 0, 'error' => false);
      $id = $this->Crypto->hash($path, true)[0];
      $key = $this->Crypto->y64decode($key);
      if(! $this->_begin_transaction()) {
         error_log('Cannot start transaction');
         $result['error'] = true;
         return $result;
      }

      $stmt = $this->DB->prepare('UPDATE "verrou" SET "verrou_state" = 0, "verrou_timestamp" = 0, "verrou_key" = NULL WHERE "verrou_key" = :key AND "verrou_path" = :id');
      $stmt->bindValue(':key', $key, \PDO::PARAM_LOB);
      $stmt->bindValue(':id', $id, \PDO::PARAM_LOB);
      $stmt->execute();
      $this->_commit();

      $locked = $this->_is_locked($id);
      if ($locked) {
         $result['state'] = 'locked';
         $result['timeout'] = $locked[1];
      }

      return $result;
   }

   function state ($path) {
      $result = array('lock' => $path, 'state' => 'unlocked', 'key' => '', 'timeout' => 0, 'error' => false);
      $id = $this->Crypto->hash($path, true)[0];
      if (! $this->_begin_transaction()) {
         error_log('Cannot start transcation');
         $result['error'] = true;
      }

      $locked = $this->_is_locked($id);
      if ($locked) {
         $result['state'] = 'locked';
         $result['timeout'] = $locked[1];
      }

      return $result;
   }

   function request ($req) {
      $now = time();
      $result = false;
      if (!isset($req['key']) || empty($req['key'])) { $req['key'] = null; }
      switch (strtolower($req['operation'])) {
         case 'lock':
            $result = $this->lock($req['on'], $req['key']);
            break;
         case 'unlock':
            $result = $this->unlock($req['on'], $req['key']);
            break;
         case 'state':
            $result = $this->state($req['on']);
            break;
      }

      if ($result) {
         $result['timestamp'] = $now;
      }

      return $result;
   }

   function http_locking () {
      $key = isset($_GET['key']) ? $_GET['key'] : null;
      $result = array('lock' => '', 'state' => 'unlocked', 'key'=>'');
      if (!empty($_GET['lock'])) {
         $result = $this->request(array('key' => $key, 'on' => $_GET['lock'], 'operation' => 'lock'));
      }
      
      if(!empty($_GET['unlock'])) {
         $result = $this->request(array('key' => $key, 'on' => $_GET['unlock'], 'operation' => 'unlock'));
      }
  
      if(!empty($_GET['state'])) {
         $result = $this->request(array('on' => $_GET['state'], 'operation' => 'state'));
      }
      echo json_encode($result);
   }
}
?>
