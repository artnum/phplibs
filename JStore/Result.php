<?PHP
/*- 
 * Copyright (c) 2018-2020 Etienne Bagnoud <etienne@artisan-numerique.ch>
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

class Result {
  protected $items;
  protected $count;
  protected $errors;
  
  function __construct ($items = NULL, $count = 0) {
    $this->items = $items;
    $this->count = $count;
    $this->errors = array();
  }
  
  function getCount () {
    return $this->count;
  }

  function getItems () {
    return $this->items;
  }

  function setCount ($count) {
    $this->count = $count;
  }

  function setItems ($items) {
    $this->items = $items;
  }

  function addItem ($item) {
    if (is_null($this->items) || !isset($this->items)) {
      $this->items = array();
      $this->count = 0;
    }
    if (is_array($this->items)) {
      $this->items[] = $item;
      $this->count++;
    }
  }

  function copyError ($src) {
    if ($src instanceof \artnum\JStore\Result) {
      $this->errors = array_merge($this->errors, $src->getError());
    }
  }
  
  function addError ($msg, $data = NULL, $time = null, $file = __FILE__, $line = __LINE__) {
    if (is_null($time)) { $time = time(); }
    $this->errors[] = array(
      'time' => $time,
      'message' => $msg,
      'data' => $data,
      'file' => $file,
      'line' => $line
    );
  }

  function countError () {
    return count($this->errors);
  }

  function getError () {
    return $this->errors;
  }
}
