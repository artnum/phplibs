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
namespace artnum\JStore;

use PDO;
use Exception;

class Auth {
    protected $pdo;
    protected $table;

    function __construct(PDO $pdo, String $table = 'jstore_auth') {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->timeout = 86400; // 24h
        $this->current_userid = -1;
    }

    function get_current_userid() {
        return $this->current_userid;
    }

    function generate_auth ($userid, $hpw) {
        $sign = bin2hex(random_bytes(32));
        $authvalue=  bin2hex(hash_hmac('sha256', $sign, $hpw, true));
        if ($this->add_auth($userid, $authvalue)) {
            return $sign;
        }
        return '';
    }

    function confirm_auth ($authvalue) {
        $pdo = $this->pdo;
        $done = false;
        try {
            $stmt = $pdo->prepare(sprintf('UPDATE %s SET "time" = :time, "confirmed" = 1 WHERE auth = :auth', $this->table));
            $stmt->bindValue(':auth', $authvalue, PDO::PARAM_STR);
            $stmt->bindValue(':time', time(), PDO::PARAM_INT);

            $done = $stmt->execute();
        } catch(Exception $e) {
            error_log(sprintf('kaal-auth <confirm-auth>, "%s"', $e->getMessage()));
        } finally {
            if ($done) {
                return $this->check_auth($authvalue);
            }
            return $done;
        }
    }

    function add_auth ($userid, $authvalue) {
        $pdo = $this->pdo;
        $done = false;
        $ip = $_SERVER['REMOTE_ADDR'];
        $host = empty($_SERVER['REMOTE_HOST']) ? $ip : $_SERVER['REMOTE_HOST'];
        $ua = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        try {
            $stmt = $pdo->prepare(sprintf('INSERT INTO %s (userid, auth, started, remotehost, remoteip, useragent) VALUES (:uid, :auth, :started, :remotehost, :remoteip, :useragent);', $this->table));
            $stmt->bindValue(':uid', $userid, PDO::PARAM_STR);
            $stmt->bindValue(':auth', $authvalue, PDO::PARAM_STR);
            $stmt->bindValue(':started', time(), PDO::PARAM_INT);
            $stmt->bindValue(':remotehost', $host, PDO::PARAM_STR);
            $stmt->bindValue(':remoteip', $ip, PDO::PARAM_STR);
            $stmt->bindValue(':useragent', $ua, PDO::PARAM_STR);

            $done = $stmt->execute();
        } catch (Exception $e) {
            error_log(sprintf('kaal-auth <add-auth>, "%s"', $e->getMessage()));
        } finally {
            return $done;
        }
    }

    function del_auth ($authvalue) {
        $pdo = $this->pdo;
        try {
            $stmt = $pdo->prepare(sprintf('DELETE FROM %s WHERE auth = :auth', $this->table));
            $stmt->bindValue(':auth', $authvalue, PDO::PARAM_STR);
            $stmt->execute();
        } catch(Exception $e) {
            error_log(sprintf('kaal-auth <del-auth>, "%s"', $e->getMessage()));
        } finally {
            return true;
        }
    }

    function check_auth ($authvalue) {
        $pdo = $this->pdo;
        $matching = false;
        try {
            $stmt = $pdo->prepare(sprintf('SELECT * FROM %s WHERE auth = :auth', $this->table));
            $stmt->bindValue(':auth', $authvalue, PDO::PARAM_STR);
            $stmt->execute();
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                if (time() - intVal($row['time'], 10) > $this->timeout) {
                    $del = $pdo->prepare(sprintf('DELETE FROM %s WHERE auth = :auth', $this->table));
                    $del->bindValue(':auth', $row['auth'], PDO::PARAM_STR);
                    $del->execute();
                } else {
                    $matching = true;
                    $this->current_userid = $row['userid'];
                    break;
                }
            }
        } catch(Exception $e) {
            error_log(sprintf('kaal-auth <check-auth>, "%s"', $e->getMessage()));
        } finally {
            return $matching;
        }
    }

    function refresh_auth($authvalue) {
        $pdo = $this->pdo;
        $done = false;
        $ip = $_SERVER['REMOTE_ADDR'];
        $host = empty($_SERVER['REMOTE_HOST']) ? $ip : $_SERVER['REMOTE_HOST'];
        try {
            $stmt = $pdo->prepare(sprintf('UPDATE %s SET time = :time, remotehost = :remotehost, remoteip = :remoteip WHERE auth = :auth', $this->table));
            $stmt->bindValue(':time', time(), PDO::PARAM_INT);
            $stmt->bindValue(':auth', $authvalue, PDO::PARAM_STR);
            $stmt->bindValue(':remotehost', $host, PDO::PARAM_STR);
            $stmt->bindValue(':remoteip', $ip, PDO::PARAM_STR);

            $done = $stmt->execute();
        } catch (Exception $e) {
            error_log(sprintf('kaal-auth <add-auth>, "%s"', $e->getMessage()));
        } finally {
            return $done;
        }
    }

    function get_id ($authvalue) {
        $pdo = $this->pdo;
        $matching = false;
        try {
            $stmt = $pdo->prepare(sprintf('SELECT * FROM %s WHERE auth = :auth', $this->table));
            $stmt->bindValue(':auth', $authvalue, PDO::PARAM_STR);
            $stmt->execute();
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                if (time() - intVal($row['time'], 10) > $this->timeout) {
                    $del = $pdo->prepare(sprintf('DELETE FROM %s WHERE auth = :auth', $this->table));
                    $del->bindValue(':auth', $row['auth'], PDO::PARAM_STR);
                    $del->execute();
                } else {
                    $matching = $row['userid'];
                    break;
                }
            }
        } catch(Exception $e) {
            error_log(sprintf('kaal-auth <get-id>, "%s"', $e->getMessage()));
        } finally {
            return $matching;
        }
    }

    function get_auth_token () {
        try {
            $authContent = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
            if (count($authContent) !== 2) { throw new Exception('Wrong auth header'); }
            if ($authContent[0] !== 'Bearer') { throw new Exception('Wrong auth header'); }
            return $authContent[1];
        } catch (Exception $e) {
            error_log(sprintf('kaal-auth <get-id>, "%s"', $e->getMessage()));
        }
    }

    function get_active_connection ($userid) {
        $pdo = $this->pdo;
        $connections = [];
        try {
            $stmt = $pdo->prepare(sprintf('SELECT * FROM %s WHERE userid = :userid', $this->table));
            $stmt->bindValue(':userid', $userid, PDO::PARAM_INT);
            $stmt->execute();
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
                if (time() - intVal($row['time'], 10) > $this->timeout) {
                    $del = $pdo->prepare(sprintf('DELETE FROM %s WHERE auth = :auth', $this->table));
                    $del->bindValue(':auth', $row['auth'], PDO::PARAM_STR);
                    $del->execute();
                } else {
                   $connections[] = [
                    'uid' => $row['uid'],
                    'time' => $row['time'],
                    'useragent' => $row['useragent'],
                    'remoteip' => $row['remoteip'],
                    'remotehost' => $row['remotehost']
                   ];
                }
            }
        } catch(Exception $e) {
            error_log(sprintf('kaal-auth <get-active-connection>, "%s"', $e->getMessage()));
        } finally {
            return $connections;
        }
    }

    function del_specific_connection ($connectionid) {
      // todo
   }

    function del_all_connections ($userid) {
        $pdo = $this->pdo;
        try {
            $stmt = $pdo->prepare(sprintf('DELETE FROM %s WHERE userid = :userid', $this->table));
            $stmt->bindValue(':userid', $userid, PDO::PARAM_INT);
            return $stmt->execute();
        } catch(Exception $e) {
            error_log(sprintf('kaal-auth <del-all-connections>, "%s"', $e->getMessage()));
        } 
    }

    function verify () {
        try {
            $token = $this->get_auth_token();
            return $this->check_auth($token);
        } catch(Exception $e) {
            error_log(sprintf('kaal-auth <verify>, "%s"', $e->getMessage()));
            return false;
        }
    }

    function run (User $user, $step, $content) {
      try {
         header('Content-Type: application/json', true);
         switch ($step) {
            default: throw new Exception('Unknown step');
            case 'init':
                  if(empty($content['userid'])) { throw new Exception(); }
                  $u = $user->get($content['userid']);
             
                  $auth = $this->generate_auth($u['id'], $u['key']);
                  if (empty($auth)) { throw new Exception(); }
                  echo json_encode([
                     'auth' => $auth,
                     'count' => intval($u['key_iteration']),
                     'salt' => $u['key_salt'],
                     'userid' => intval($u['id'])
                  ]);
                  break;
            case 'check':
                  if (empty($content['auth'])) { throw new Exception(); }
                  if (!$this->confirm_auth($content['auth'])) { throw new Exception(); }
                  $this->refresh_auth($content['auth']);
                  echo json_encode(['done' => true]);
                  break;
            case 'quit':
                  if (empty($content['auth'])) { throw new Exception(); }
                  if (!$this->del_auth($content['auth'])) { throw new Exception(); }
                  echo json_encode(['done' => true]);
                  break;
            case 'userid':
                  if (empty($content['username'])) { throw new Exception(); }
                  $u = $user->getByUsername($content['username']);
                  if (empty($u['id'])) { throw new Exception(); }
                  echo json_encode(['userid' => intval($u['id'])]);
                  break;
            case 'disconnect':
                  $token = $this->get_auth_token();
                  if (!$this->check_auth($token)) { throw new Exception(); }
                  $userid = $this->get_id($token);
                  if (empty($content['userid'])) { throw new Exception(); }
                  if (intval($content['userid']) !== $userid)  { throw new Exception(); }
                  if (!$this->del_all_connections($content['userid'])) { throw new Exception(); }
                  echo json_encode(['userid' => intval($content['userid'])]);
                  break;
         }
      } catch (Exception $e) {
         $msg = $e->getMessage();
         error_log(var_export($e, true));
         if (empty($msg)) { $msg = 'Wrong parameter'; }
         echo json_encode(['error' => $msg]);
         exit(0);

      }
    }
}