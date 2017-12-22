<?PHP


Namespace artnum;

/* Use sqlite as backend as it works on OCFS2 clustered filesystem */
class Lock {

   protected $DB;
   protected $Timeout;

   function __construct($project, $tmpdir = NULL) {
      $file = $project .'-lock.sqlite';
      if(is_null($tmpdir)) {
         $envdir = getenv('ARTNUM_LOCK_TMPDIR');
         if(!$envdir) {
            $file = sys_get_temp_dir() . '/' . $file;
         } else {
            $file = $envdir . '/' . $file;
         }
      } else {
         $file = $tmpdir . '/' . $file;
      }

      $this->Timeout = 900; /* Timeout default to 15 minutes */
      $this->DB = new \SQLite3($file);
      $this->DBFile = $file;
      try {
         $this->DB->exec('CREATE TABLE IF NOT EXISTS `locks` ( `locks_id` VARCHAR(25) UNIQUE NOT NULL, `locks_state` BOOL DEFAULT(0), `locks_timestamp` INTEGER ); CREATE INDEX IF NOT EXISTS `idxLocksID` ON `locks` ( `locks_id`);');
      } catch (\Exception $e) {
         /* ignore */
      }
   }

   function _is_locked($id) {
      $stmt = $this->DB->prepare('SELECT `locks_state`, `locks_timestamp` FROM `locks` WHERE `locks_id` = :id');
      $stmt->bindValue(':id', strval($id), \SQLITE3_TEXT);
      $res = $stmt->execute(); 
      if($res) {
         $v = $res->fetchArray();
         if($v && $v[0]) {
            if($this->Timeout == 0) {
               return true;
            }

            if(time() - $v[1]  < $this->Timeout) {
               return true;
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

   function lock ($id) {
      $l = false;
      if(! $this->_begin_transaction()) {
         return true;
      }
      if(! $this->_is_locked($id)) {
         $stmt = $this->DB->prepare('INSERT OR REPLACE INTO `locks` (`locks_id`, `locks_state`, `locks_timestamp`) VALUES ( :id, 1, :ts)');
         $stmt->bindValue(':id', strval($id), \SQLITE3_TEXT);
         $stmt->bindValue(':ts', time(), \SQLITE3_INTEGER);
         $stmt->execute();
         $this->DB->exec('COMMIT');
         $l = $this->_is_locked($id);
      } else {
         $this->DB->exec('COMMIT');
         $l = false;
      }

      return $l;
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

   function unlock($id) {
      if(! $this->_begin_transaction()) {
         return true;
      }

      if(! $this->_is_locked($id)) {
         return true;
      }
      
      $stmt = $this->DB->prepare('INSERT OR REPLACE INTO `locks` (`locks_id`, `locks_state`, `locks_timestamp`) VALUES ( :id, 0, 0)');
      $stmt->bindValue(':id', strval($id), \SQLITE3_TEXT);
      $stmt->execute();
      $this->DB->exec('COMMIT');
      $l = $this->_is_locked($id);
   
      return !$l;
   }

   function follow() {
      $pingCount = 0;
      header("Content-Type: text/event-stream\n\n");
      while(ob_get_level() > 0) {
         ob_end_flush();
      }
      flush();

      $locks = array();
      while(1) {  
         $DB = new \SQLite3($this->DBFile, SQLITE3_OPEN_READONLY);
         $stmt = $DB->prepare('SELECT `locks_id`,`locks_state`, `locks_timestamp` FROM `locks`');
         $res = $stmt->execute();
         if($res) {
            while( ($data = $res->fetchArray()) != FALSE) {
               if(! isset($locks[$data['locks_id']])) {
                 $locks[$data['locks_id']] = array( 'state' => $data['locks_state'] ? true : false, 'ts' => intval($data['locks_timestamp']));
                } else {
                  $current = $locks[$data['locks_id']];
                  $new = $data['locks_state'];
                  if($current['state'] != $new) {
                     $locks[$data['locks_id']]['state'] = $new;
                     echo 'event: lock' ."\n".  'data: { "target": "' . $data['locks_id'] . '", "status": "'. $new .'" }' . "\n\n";
                     while(ob_get_level() > 0) {
                        ob_end_flush();
                     }
                     flush();
                  }         
               }    
            }
         }
         $DB->close();
            
         if($pingCount > 5) {
            echo "event: ping\ndata: " . time() . "\n\n"; 
            while(ob_get_level() > 0) {
               ob_end_flush();
            }
            flush();
            if(connection_status() != 0) { die(); }
            $pingCount = 0;
         }

         $pingCount++;
         usleep(250000);
      }
   }

   function http_locking() {
      if(!empty($_GET['lock'])) {
         if($this->lock($_GET['lock'])) {
            echo '1'; 
            return ;
         }
      }
      
      if(!empty($_GET['unlock'])) {
         if($this->unlock($_GET['unlock'])) {
            echo '1';
            return ;
         }
      }
  
      if(!empty($_GET['status'])) {
         if($this->_is_locked($_GET['status'])) {
            echo '1';
            return;
         }
      }

      if(!empty($_GET['follow'])) {
         $this->follow();
      }

      echo '0'; 
   }

}

?>
