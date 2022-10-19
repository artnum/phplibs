<?php
/*- 
 * Copyright (c) 2022 Etienne Bagnoud <etienne@artisan-numerique.ch>
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
use \Exception;

class Response {
  function __construct() {
    ob_start();
    $this->output_started = false;
    $this->headers = [];
    $this->first = true;
    $this->code = 200;
  }

  function stop_output() {
    ob_end_flush();
    flush();
  }

  function echo ($txt) {
    try {
      echo $txt;
    } catch (Exception $e) {
      error_log($e->getMessage());
    }
  }

  function print($json) {
    try {
      if (!$this->first) { echo ',';}
      $this->first = false;
      echo json_encode($json);
      ob_flush();
    } catch (Exception $e) {
      error_log($e->getMessage());
    }
  }

  function clear_output() {
    ob_clean();
  }

  function start_output () {
    if ($this->output_started) { return; }
    http_response_code($this->code);
    foreach ($this->headers as $header) {
      header($header, true);
    }
    ob_flush();
  }

  function code($num) {
    $this->code = $num;
  }

  function error($message, $code = 500) {
    ob_end_clean();
    http_response_code($code);
    foreach ($this->headers as $header) {
      header($header, true);
    }
    header('Content-Type: application/json', true);
    $this->print(
      [
        'success' => false,
        'type' => 'error',
        'message' => $message,
        'data' => [],
        'length' => 0
      ]
    );
  }

  function header($name, $value, $replace = true) {
    $key = strtolower($name);
    if ($replace) {
      $this->headers[$key] = sprintf('%s: %s', $name, $value);
    } else {
      if (empty($this->headers[$key])) { $this->headers[$key]  = sprintf('%s: %s', $name, $value); }
    }
  }
}