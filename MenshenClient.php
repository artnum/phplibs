<?PHP
/*- 
 * Copyright (c) 2020 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

class MenshenClient extends JRestClient {
    protected $rsa = null;
    protected $defaultsOpts = [
        'dgt' => 'sha256', /* digest */
        'sle' => 0, /* saltlen */
        'mgf' => 'sha256'
    ];
    protected $opts = null;

    function setAuth ($cid, $privKey, $opts = []) {
        $rsa = new \phpseclib\Crypt\RSA();

        $rsa->loadKey($privKey);
        foreach ($this->defaultsOpts as $k => $v) {
            if (empty($opts[$k])) {
                $opts[$k] = $v;
            }
        }

        $rsa->setHash($opts['dgt']);
        $rsa->setMGFHash($opts['mgf']);
        $rsa->setSaltLength($opts['sle']);
        $opts['cid'] = $cid;
        $this->opts = $opts;
        $rsa->setSignatureMode(\phpseclib\Crypt\RSA::SIGNATURE_PSS);

        $this->rsa = $rsa;
    }

    function exec() {
        if (empty($this->queryHeaders['X-Request-Id'])) {
            if (!empty($this->queryHeaders['Content-MD5'])) {
                $this->queryHeaders['X-Request-Id'] = hash_hmac('sha1', uniqid(), $this->queryHeaders['Content-MD5']);
            } else {
                $this->queryHeaders['X-Request-Id'] = uniqid('', true);
            }
        }
        $this->genAuth();
        return parent::exec();
    }

    function genAuth() {
        $url = parse_url($this->effectiveURL);
        $reqUri = $url['path'];
        if (!empty($url['query'])) {
            $reqUri .= '?' . $url['query'];
        }

        $sig = $this->rsa->sign(sprintf('%s|%s|%s', strtolower($this->method), $reqUri, $this->queryHeaders['X-Request-Id']));
        $this->queryHeaders['Authorization'] = sprintf(
            'Menshen sig=%s,cid=%d,sle=%d,dgt=%s,mgf=%s',
            bin2hex($sig),
            $this->opts['cid'],
            $this->opts['sle'],
            $this->opts['dgt'],
            $this->opts['mgf']
        );
    }
}