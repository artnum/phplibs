<?PHP
/*- 
 * Copyright (c) 2018-2022 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

class ACL {
    const SELF = 1;
    const ANY = 2;
    const NONE = 0;

    const LEVEL_NONE = 0;
    const LEVEL_AUTH = 8;
    const LEVEL_SEARCH = 16;
    const LEVEL_READ = 32;
    const LEVEL_UPDATE = 64;
    const LEVEL_CREATE = 128;
    const LEVEL_DELETE = 256;
    const LEVEL_ANY = 512;

    function __construct($groups) {
        $this->rules = [];
        $this->groups = $groups;
        $this->current_rule =['who' => -1, 'what' => '*', 'isGroup' => true, 'access' => self::LEVEL_NONE, 'attributes' => ['*']];
    }

    function addRule($collection, $who, $what = self::LEVEL_NONE, $isGroup = false, $access = self::ANY, $attributes = ['*']) {
        if (empty($this->rules[$collection])) {
            $this->rules[$collection] = [];
        }
        $this->rules[$collection][] = ['who' => $who, 'what' => $what, 'isGroup' => $isGroup, 'access' => $access, 'attributes' => $attributes];
    }

    function matchRule ($rule, $who, $what) {
        if ($rule['isGroup']) {
            if ($this->isMember($who, $rule['who']) && ($what <= $rule['what'])) { return true; }
        }
        if ($rule['who'] === $who && ($what <= $rule['what'])) { return true; }
        return false;
    }

    function filterData ($data) {
      //  error_log(var_export($this->current_rule, true));
        //if ($this->current_rule['access'] < self::LEVEL_AUTH) { return []; }
        error_log(var_export($this->current_rule, true));

        if (in_array('*', $this->current_rule['attributes'])) { return $data; }

        error_log(var_export($data, true));
        foreach (array_keys($data) as $key) {
            if (!in_array($key, $this->current_rule['attributes'])) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    function check ($collection, $who, $what, $owner = null) {
        // any collection
        if (!empty($this->rules['*'])) {
            foreach ($this->rules['*'] as $rule) {
                if($this->matchRule($rule, $who, $what)) {
                    if (
                        $owner !== null && 
                        $rule['access'] === self::SELF &&
                        $owner !== -1 && // owner -1 is everyone owner 
                        $who !== $owner
                    ) {
                        $this->current_rule = $rule;
                        return false;
                    }
                    $this->current_rule = $rule;
                    return $rule['access'] > 0;
                }
            }
        }
        if (empty($this->rules[$collection])) { return false; }
        foreach ($this->rules[$collection] as $rule) {
            if ($this->matchRule($rule, $who, $what)) {
                if (
                    $owner !== null && 
                    $rule['access'] === self::SELF &&
                    $who !== $owner
                ) {
                    $this->current_rule = $rule;
                    return false;
                }
                $this->current_rule = $rule;
                return $rule['access'] > 0;
            }
        }
        return false;
    }

    function isMember($who, $group) {
        if (!is_array($group) && $group === -1) { return true; } // group -1 is everybody
        if (empty($this->groups[$group])) { return false; }
        if (!in_array($who, $this->groups[$group])) { return false; }
        return true;
    }
}