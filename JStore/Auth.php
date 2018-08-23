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
Namespace artnum\JStore;

class Auth {
   function __construct($session, $user) {
      $this->Session = $session;
      $this->UserInterface = $user;
      $this->Random = new \artnum\Random();
      $this->Crypto = new \artnum\Crypto('sha256');
   }

   function getName() {
      return 'Artnum.GenericAuth';
   }

   function handle($request) {
      switch($request->getVerb()) {
         case 'POST':
         case 'GET':
            return $this->authenticate($request);
         case 'PATCH':
            return $this->update($request);
      }

      return false;
   }

   function verify($request) {
      return $this->Session->set('auth-valid');
   }

   function authorize($request) {
      /* todo autorization part */
      return $this->verify($request);
   }

   /* set password */
   function update($request) {
      return;
   }

   function authenticate($request) {
      if ($request->getVerb() == 'GET') {
         $challenge = $this->Random->str(128);
         if (!$challenge) {
            return -1;
         }
         $this->Session->set('auth-challenge', $challenge);

         $sleep = $this->UserInterface->fail('get', $request->getItem());
         usleep(10000 * $sleep);

         return array('challenge' => $challenge);
      } else if ($request->getVerb() == 'POST') {
         /* second pass */
         $key = $this->Random->str(256);
         $_key = $this->UserInterface->getkey($request->getItem());
         if (!is_null($_key)) {
            $key = $_key;
         }
         
         if ($this->Crypto->compare($this->Crypto->hmac($this->Session->get('auth-challenge'), $key)[0], $request->getParameter('response'))) { 
            $this->UserInterface->fail('reset', $request->getItem());
            $this->Session->set('auth-valid', true);
            return array('auth-success' => true);
         } else {
            $this->UserInterface->fail('inc', $request->getItem());
            $this->Session->set('auth-valid', true);
            return array('auth-success' => false);
         }
      }
   }      
}

?>
