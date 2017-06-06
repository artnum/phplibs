<?PHP
/*- 
 * Copyright (c) 2017 Artisan du Numérique <etienne@artisan-numerique.ch>
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
