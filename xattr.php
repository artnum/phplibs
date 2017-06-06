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
Namespace artnum;

function getAttr($path, $attr)
{
   $ePath = \escapeshellarg($path);
   $eAttr = \escapeshellarg($attr);

   $lLine = \exec(\sprintf('attr -q -g %s %s 2>/dev/null', $eAttr, $ePath), $rTxt, $rCode);
   if($rCode == 0) {
      return $lLine;
   }

   return FALSE;
}

function setAttr($path, $attr, $value)
{
   $ePath = \escapeshellarg($path);
   $eAttr = \escapeshellarg($attr);
   $eValue = \escapeshellarg($value);

   $lLine = \exec(\sprintf('attr -q -s %s -V %s %s 2>&1 > /dev/null', $eAttr, $eValue, $ePath), $rTxt, $rCode);
   if($rCode == 0) {
      return TRUE;
   }

   return FALSE;
}
?>
