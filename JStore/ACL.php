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
    const LEVEL_AUTH = 1;
    const LEVEL_SEARCH = 2;
    const LEVEL_READ = 4;
    const LEVEL_UPDATE = 8;
    const LEVEL_CREATE = 16;
    const LEVEL_DELETE = 32;
    const LEVEL_IMPERSONATE = 2048;
    const LEVEL_ANY = 32768;

    function __construct($groups) {
        $this->rules = [];
        $this->groups = $groups;
        $this->current_rule = ['collection' => '*', 'who' => -1, 'what' => self::LEVEL_NONE, 'isGroup' => true, 'access' => self::ANY, 'attributes' => ['*']];
    }

    function getCurrentAttributesFilter () {
        $filter = $this->current_rule['attributes'];
        if (in_array('*', $filter)) { return []; }
        return $filter;
    }

    function addRule($collection, $who, $what = self::LEVEL_NONE, $isGroup = false, $access = self::ANY, $attributes = ['*']) {
        $this->rules[] = ['collection' => $collection, 'who' => $who, 'what' => $what, 'isGroup' => $isGroup, 'access' => $access, 'attributes' => $attributes];
    }

    function matchRule ($rule, $collection, $who, $what) {
        if ($rule['isGroup']) {
            if (
                $this->isMember($who, $rule['who']) && 
                ($what <= $rule['what']) &&
                ($collection === $rule['collection'] || $rule['collection'] === '*')
            ) 
                { return true; }
        }
        if (
            $rule['who'] === $who && 
            ($what <= $rule['what']) &&
            ($collection === $rule['collection'] || $rule['collection'] === '*')
        ) { return true; }
        return false;
    }

    function check ($collection, $who, $what, $owner = null) {
        foreach ($this->rules as $rule) {
            if ($this->matchRule($rule, $collection, $who, $what)) {
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