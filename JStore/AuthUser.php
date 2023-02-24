<?php
namespace artnum\JStore;

interface AuthUser {
   public function get($userid);
   public function getByUsername($username);
   public function setPassword($id, $key, $keyopts = []);
}