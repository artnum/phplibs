<?PHP
define('HT_BUCKETS', 10);
define('HT_RECORD_SIZE', 42);

define('HTSLOT_FREE', 0x0000);
define('HTSLOT_SET', 0x8000);
define('HTSLOT_WRAP', 0x4000);
define('HTSLOT_MAX_COUNT', 0x0FFF);


class HTable {
  private const $headerSize = 10;
  private $valueSize = 32;
  private $buckets = 10;
  private $sid;
  
  function __construct ($path = __FILE__, $buckets = 10, $valueSize = 32) {
    $this->sid = null;
    $this->buckets = $buckets;
    $this->valueSize = $valueSize;
    $svkey = ftok($path, 'a');
    @$sid = shm_open($svkey, 'w', 0x100 * $buckets * $valueSize + $this->headerSize);
    if (!empty($sid)) {
    } else {
      @$sid = shm_open($svkey, 'n', 0x100 * $buckets * $valueSize + $this->headerSize);
      if (empty($sid)) {
        if(shmop_delete($this->sid)) {
          @$sid = shm_open($svkey, 'c', 0x100 * $buckets * $valueSize + $this->headerSize);
          if (!empty($sid)) {
            $this->sid = $sid;
          }
        }
      }
    }

    if ($this->sid === NULL) { throw new Exception ('Could not open shared memory segment'); }
  }

  private function getpos($value) {
    $short = unpack('v', $value)[1];
    $a = ($short & 0xFF00) >> 8;
    $b = ($short & 0x00FF);
    return $a * $this->buckets + ($b * $this->buckets) * ($this->valueSize + $this->recordSize);
  }

  function get(
  
  function set($value) {
    
  }
  
}

function htableGetPos ($value) {
  $res = unpack('v', $value)[1];
  echo $res . PHP_EOL;
  return ((($res & 0xFF00) >> 8) * HT_BUCKETS) + (($res & 0x00FF) % HT_BUCKETS) * HT_RECORD_SIZE;
}

function htableSetPos($sid, $pos, $state, $count, $value) {
  return shmop_write($sid, $value . pack('vP', ($state & 0xF000) | ($count & 0x0FFF), time()), $pos);
}

function htableGet

$skey = ftok(__FILE__, 'a');

$sid = shmop_open($skey, 'c', 0664, 0x100 * HT_BUCKETS * HT_RECORD_SIZE);
$value = hash('sha256', 'xtest', true);
$pos = htableGetPos($value);

$record = shmop_read($sid, $pos, HT_RECORD_SIZE);
$state = unpack('v', $record, 32)[1];
$count = $state & 0x0FFF;
$state = $state & 0xF000;
echo $state . PHP_EOL;
if ($state & HTSLOT_SET) {
  echo 'Record used' . PHP_EOL;
  $time = unpack('P', $record, 34)[1];
  if (time() - $time > 30) {
    echo 'Record passed' . PHP_EOL;
    htableSetPos($sid, $pos, HTSLOT_SET, 1, $value);
  } else {
    $rvalue = substr($record, 0, 32);
    if ($rvalue !== $value) {
      echo 'ERROR : Colision' . PHP_EOL;
    } else {
      $count++;
      $new_state = HTSLOT_SET;
      if ($count >= HTSLOT_MAX_COUNT) {
        echo 'Wrap around' . PHP_EOL;
        $new_state |= HTSLOT_WRAP;
      }
      echo 'Seen ' . $count . ' times' . PHP_EOL;
      htableSetPos($sid, $pos, $new_state, $count, $value);
    }
  }
} else {
  echo 'Record free' . PHP_EOL;
  htableSetPos($sid, $pos, HTSLOT_SET, 1, $value);
}
shmop_close($sid);
?>
