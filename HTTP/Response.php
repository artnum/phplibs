<?PHP
/*- 
 * Copyright (c) 2017 Etienne Bagnoud <etienne@artisan-numerique.ch>
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
Namespace artnum\HTTP;

class Response {
   static $http_code = array(
         100 => 'Continue',
         101 => 'Switching Protocols',
         102 => 'Processing',

         200 => 'OK',
         201 => 'Created',
         202 => 'Accepted',
         203 => 'Non-Authoritative Information',
         204 => 'No Content',
         205 => 'Reset Content', 
         206 => 'Partial Content', 
         207 => 'Multi-Status', 
         208 => 'Already Reported',
         226 => 'IMF Used',

         300 => 'Multiple Choices', 
         301 => 'Moved Permanently', 
         302 => 'Found', 
         303 => 'See Other', 
         304 => 'Not Modified', 
         305 => 'Use Proxy', 
         306 => 'Switch Proxy', 
         307 => 'Temporary Redirect', 
         308 => 'Permanent Redirect', 
         
         400 => 'Bad Request', 
         401 => 'Unauthorized', 
         402 => 'Payment Required', 
         403 => 'Forbidden', 
         404 => 'Not Found', 
         405 => 'Method Not Allowed', 
         406 => 'Not Acceptable', 
         407 => 'Proxy Authentication Required', 
         408 => 'Request Timeout', 
         409 => 'Conflict', 
         410 => 'Gone', 
         411 => 'Length Required', 
         412 => 'Precondition Failed', 
         413 => 'Request Entity Too Large', 
         414 => 'Request-URI Too Long', 
         415 => 'Unsupported Media Type', 
         416 => 'Requested Range Not Satisfiable', 
         417 => 'Expectation Failed', 
         418 => 'I\'m a teapot', 
         422 => 'Unprocessable Entity', 
         423 => 'Locked', 
         424 => 'Failed Dependency', 
         426 => 'Upgrade Required',
         428 => 'Precondition Required',
         429 => 'Too Many Requests', 
         431 => 'Request Header Fields Too Large', 
         451 => 'Unavailable For Legal Reasons', 
         
         500 => 'Internal Server Error',
         501 => 'Not Implemented',
         502 => 'Bad Gateway', 
         503 => 'Service Unavailable', 
         504 => 'Gateway Timeout', 
         505 => 'HTTP Version Not Supported', 
         506 => 'Variant Also Negotiates', 
         507 => 'Insufficient Storage', 
         508 => 'Loop Detected', 
         510 => 'Not Extended', 
         511 => 'Network Authentication Required'
            ); 

   function code($code) {
      $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';

      if( ! isset(self::$http_code[intval($code)])) {
         $code = 501;
      } 
      header($protocol . ' ' . $code . ' ' . self::$http_code[intval($code)], true);
   }
}
?>
