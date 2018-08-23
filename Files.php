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

class Files {
   /* Write data to file if it's writable */
   function toFile ($content, $file) {
      if ($this->writable($file)) {
         return file_put_contents($file, $content);
      }
      return false;
   }

   /* check if file is writable, if file doesn't exist, check if the directory is writable */
   function writable($file) {
      if (file_exists($file) && is_file($file) && is_writable($file)) {
         return true;
      } else {
         if (is_dir(dirname($file)) && is_writable(dirname($file))) {
            return true;
         }
      }
      return false;
   }

   /* alias */
   function writeable($file) {
      return $this->writable($file);
   }

   /* verify if file exists and is readable but if file doesn't exist, check if it could be written */
   function mayExist($file) {
      if (file_exists($file)) {
         if ($this->readable($file)) {
            return true;
         }
      }
      if ($this->writeable($file)) {
         return true;
      }
      return false;
   }

   function readable($file) {
      if (file_exists($file) && is_file($file) && is_readable($file)) {
         return true;
      }
      return false;
   }

   function fromFile($file) {
      if ($this->readable($file)) {
         return file_get_contents($file);
      }
      return false;
   }
}

?>
