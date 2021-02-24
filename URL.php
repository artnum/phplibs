<?PHP
Namespace artnum;

class URL {
   private $protocol = 'http://';
   private $host = '';
   private $path = '';

   function __construct () {
      $url = $_SERVER['REQUEST_URI'];

      $parts = explode('/', $url);
      for($url = array_shift($parts); count($parts) > 0; $url = array_shift($parts)) {
         if (!empty($url)) { break; }
      }

      $https = false;
      if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
         $https = true;
      } else if ((!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ||
         (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')) {
         $https = true;
      } else if (strtolower($_SERVER['REQUEST_SCHEME']) == 'https') {
         $https = true;
      }

      $host = '';
      if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
         $host = trim(end(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])));
      } else if (!empty($_SERVER['HTTP_FORWARDED'])) {
         $forwarded = explode(',', $_SERVER['HTTP_FORWARDED']);
         foreach ($forwarede as $item) {
            list ($f, $v) = explode('=', $item);
            $f = strtolower(trim($f)); $v = trim($v);

            if ($f == 'proto' && $v == 'https') {
               $https = true;
            } else if ($f == 'host') {
               $host = $v;
            }
         }
      }

      if (empty($host)) {
         $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (!empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['SERVER_ADDR']);
      }

      $this->host = $host;
      $this->path = $url;
      if ($https) { $this->protocol = 'https://'; }
      else { $this->protocol = 'http://'; }
   }

   function getUrl ($append = '') {
      $url = str_replace('//', '/', $this->host . '/' . $this->path . '/' . $append);
      return $this->protocol . $url;
   }
}
?>
