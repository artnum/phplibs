<?PHP
Namespace artnum;

/* Use sqlite as backend as it works on OCFS2 clustered filesystem */
class Lock {

   protected $DB;
   protected $Timeout;

   const LOCK_NONE = 0;
   const LOCK_SHARED = 25; // NOT USED
   const LOCK_EXCLUSIVE = 50;

   function __construct($project, $tmpdir = NULL) {
      $file = $project .'-lock.sqlite';
      $crypto = $project . '-lock.rand';
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

      $this->Crypto = new \artnum\Crypto();
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
      $this->DB = new \SQLite3($file);
      $this->DBFile = $file;
      try {
         $stmt = $this->DB->exec('CREATE TABLE IF NOT EXISTS "lock" ( "lock_path" BLOB(32) UNIQUE NOT NULL, "lock_key" BLOB(32) NULL, "lock_state" INTEGER DEFAULT(' . self::LOCK_NONE . '), "lock_timestamp" INTEGER ); CREATE INDEX IF NOT EXISTS "idxLockPath" ON "lock"("lock_Path"); CREATE INDEX IF NOT EXISTS "idxLockKey" ON "lock"("lock_key");');
      } catch (\Exception $e) {
         /* ignore */
      }
   }

   function _genkey($id, $prev_key = null) {
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

   function _is_locked($id) {
      $stmt = $this->DB->prepare('SELECT "lock_state", "lock_timestamp", "lock_key" FROM "lock" WHERE "lock_path" = :id');
      $stmt->bindValue(':id', $id, \SQLITE3_BLOB);
      $res = $stmt->execute(); 
      if($res) {
         $v = $res->fetchArray();
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

   function set_timeout($timeout) {
      $this->Timeout = $timeout; 
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
         $stmt = $this->DB->prepare('INSERT OR REPLACE INTO "lock"("lock_path", "lock_state", "lock_timestamp", "lock_key") VALUES ( :id, :lock, :ts, :key)');
         $stmt->bindValue(':id', $id, \SQLITE3_BLOB);
         $stmt->bindValue(':ts', time(), \SQLITE3_INTEGER);
         $stmt->bindValue(':key', $key, \SQLITE3_BLOB);
         $stmt->bindValue(':lock', $type, \SQLITE3_INTEGER);
         $stmt->execute();
         $this->DB->exec('COMMIT');
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
         $this->DB->exec('COMMIT');
         $result['state'] = 'locked';
      }

      return $result;
   }

   function _begin_transaction() {
      $i = 0;
      $available = false;
      do {
         if(! $this->DB->exec('BEGIN IMMEDIATE TRANSACTION')) {
            usleep(rand(1000, 3000));
         } else {
            $available = true;
         }
            $i++;
      } while(!$available && $i < 5);
      return $available;
   }

   function unlock($path, $key) {
      $result = array('lock' => $path, 'state' => 'unlocked', 'key' => '', 'timeout' => 0, 'error' => false);
      $id = $this->Crypto->hash($path, true)[0];
      $key = $this->Crypto->y64decode($key);
      if(! $this->_begin_transaction()) {
         error_log('Cannot start transaction');
         $result['error'] = true;
         return $result;
      }

      $stmt = $this->DB->prepare('UPDATE "lock" SET "lock_state" = 0, "lock_timestamp" = 0, "lock_key" = NULL WHERE "lock_key" = :key AND "lock_path" = :id');
      $stmt->bindValue(':key', $key, \SQLITE3_BLOB);
      $stmt->bindValue(':id', $id, \SQLITE3_BLOB);
      $stmt->execute();
      $this->DB->exec('COMMIT');

      $locked = $this->_is_locked($id);
      if ($locked) {
         $result['state'] = 'locked';
         $result['timeout'] = $locked[1];
      }

      return $result;
   }

   function state($path) {
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

   function request($req) {
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

   function http_locking() {
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
