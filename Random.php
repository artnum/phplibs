<?PHP
/*- 
 * Copyright (c) 2018 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

class Random {
   private $File;

   function __construct() {
      $this->File = new \artnum\Files();
   }

   /* return a random string of any length. Use cryptographic secure function if available */
   function str ($len = 256, $toFile = NULL) {
      $rstr = '';
      if (function_exists('random_bytes')) {
         $rstr = random_bytes($len);
      } else if(function_exists('openssl_random_pseudo_bytes')) {
         $strongCrypto = true;
         $rstr = openssl_random_pseudo_bytes($len, $strongCrypto);
      } else {
         /* not so random seed */
         $seed = getmyinode() + getlastmod() + time() + getmypid() + getmyuid();
         mt_srand($seed);
         for ($i = 0; $i < $len; $i++) {
            $rstr .= pack('c', mt_rand(0, 255));
         }
      }

      $rstr = base64_encode($rstr);
      if (!is_null($toFile)) {
         $this->File->toFile($rstr, $toFile);
      }

      return $rstr;
   }
}
?>
