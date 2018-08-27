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

/* Mainly to initialize and abstract algo while using algo available in Stanford Javascript Crypto Library https://bitwiseshiftleft.github.io/sjcl/ */
class Crypto {
   private $HAlgo;
   private $CAlgo;

   function __construct($halgo = NULL, $calgo = NULL, $sjcl = false) {
      if ($sjcl) {
         $this->HAlgo = 'sha256';
      } else {
         if (is_null($halgo)) {
            $this->HAlgo = 'sha1';
            $algos = hash_algos();
            /* select strongest hash function */
            foreach (array('sha512', 'sha256') as $hash) {
               if (in_array($hash, $algos)) {
                  $this->HAlgo = $hash;
                  break;
               }
            }
         } else {
            $this->HAlgo = $halgo;
         }
      }

      if (is_null($calgo)) {
         $this->CAlgo = 'aes-128-cbc';
         $algos = openssl_get_cipher_methods();
         foreach (array('aes-256-cbc', 'aes-192-cbc') as $cipher) {
            if (in_array($cipher, $algos)) {
               $this->CAlgo = $cipher;
               break;
            } 
         }
      } else {
         $this->CAlgo = $calgo; 
      }
   }

   function gethalgo() {
      return $this->HAlgo;
   }

   function getcalgo() {
      return $this->CAlgo;
   }

   /* hmac */
   function hmac($value, $key) {
      $signed = hash_hmac($this->HAlgo, $value, $key, true);
      return array(base64_encode($signed), $this->HAlgo);
   }

   function hash($value) {
      $hashed = hash($this->HAlgo, $value, true);
      return array(base64_encode($hashed), $this->HAlgo);
   }

   function compare($value1, $value2) {
      if (function_exists('hash_equals')) {
         return hash_equals($value1, $value2);
      } else {
         return strcmp($value1, $value2) == 0;
      }
   }

   /* Symmetric encryption/decryption */
   function sencrypt($value, $key) {
      $iv = '';
      if (openssl_cipher_iv_length($this->CAlgo)) {
         $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->CAlgo));
      }
      $crypted = openssl_encrypt($value, $this->CAlgo, $key, OPENSSL_RAW_DATA, $iv);
      return array($crypted, $iv, $this->CAlgo);
   }

   function sdecrypt($crypted, $key, $iv = '') {
      $value = openssl_decrypt($crypted, $this->CAlgo, $key, OPENSSL_RAW_DATA, $iv);
      return $value;
   }
}
?>
