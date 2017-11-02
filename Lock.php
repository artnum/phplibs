<?PHP


Namespace artnum;

/* Use sqlite as backend as it works on OCFS2 clustered filesystem */
class Lock {

   protected $DB;
   protected $Timeout;

   function __construct($project, $tmpdir = NULL) {
      $file = $project .'.sqlite';
      if(is_null($tmpdir)) {
         $file = sys_get_temp_dir() . '/' . $file;
      } else {
         $file = $tmpdir . '/' . $file;
      }

      $this->Timeout = 900; /* Timeout default to 15 minutes */
      $this->DB = new \SQLite3($file);
      $this->DB->exec('CREATE TABLE IF NOT EXISTS `locks` ( `locks_id` VARCHAR(25) UNIQUE NOT NULL, `locks_state` BOOL DEFAULT(0), `locks_timestamp` INTEGER ); CREATE INDEX IF NOT EXISTS `idxLocksID` ON `locks` ( `locks_id`);');
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
      $this->DB->exec('BEGIN IMMEDIATE TRANSACTION');
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

   function unlock($id) {
      if(! $this->_is_locked($id)) {
         return true;
      }
      
      $this->DB->exec('BEGIN IMMEDIATE TRANSACTION');
      $stmt = $this->DB->prepare('INSERT OR REPLACE INTO `locks` (`locks_id`, `locks_state`, locks_timestamp`) VALUES ( :id, 0, 0)');
      $stmt->bindValue(':id', strval($id), \SQLITE3_TEXT);
      $stmt->execute();
      $this->DB->exec('COMMIT');
      $l = $this->_is_locked($id);
   
      return !$l;
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

      echo '0'; 
   }

}

?>
