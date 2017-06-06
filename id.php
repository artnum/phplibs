<?PHP

define('NEW_EPOCH', 946684800000); /* 2000-01-01T00:00:00+00:00 */

/* expecting 10 bits intval for $shard
   expecting a string for $seq
 */
function genId($seq, $shard = null) 
{
   $id = 0;
   $shardId = 0;

   if(!is_null($shard)) {
      $shardId = intval($shard);
   } else {
      if(defined('SHARD_ID')) {
         $shardId = intval(SHARD_ID);
      } else {
         $env = getenv('SHARD_ID');
         if($env) {
            $shardId = intval($env);
         } else {
            $shardId = intval(hash('crc32b', php_uname('n')));
         }
      }  
   }


   $mtime = intval((microtime(TRUE) * 1000) - NEW_EPOCH);
   $id = $id | ($shardId & 0x3ff) << 53 ;
   $id = $id | (intval(hash('crc32b', $seq)) & 0xfff) << 41;
   $id = $id | (($mtime & 0x1ffffffffff)  );

   return base_convert($id, 10, 36);
}
?>
