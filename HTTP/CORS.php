<?PHP
Namespace artnum\HTTP;

class CORS
{
   protected $options;

   function __construct($options = [
      'max-age' => 3600,
      'allow' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
      'creds' => true,
      'allow-headers' => ['*'],
      'origin' => '*'
   ], $response = null) {
      $this->options = $options;
      $this->response = null;
      if ($response) { $this->response = $response; }
   }

   function setCorsOptions ($options) {
      foreach ($options as $k => $v) {
         $this->options[$k] = $v;
      }
   }

   function header($name, $value) {
      if ($this->response) {
         $this->response->header($name, $value, true);
      } else {
         header($name . ' ' . $value, true);
      }
   }

   function setCorsHeaders () {
      $server = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
      $request_header = isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) ? $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] : '';

      if (empty($server)) { $server = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['SERVER_NAME']; }

      $this->header('Access-Control-Allow-Methods', join(',', $this->options['allow']));
      if ($this->options['origin'] === '*') {
         $this->header('Access-Control-Allow-Origin', $server);
         $this->header('Vary', 'Access-Control-Allow-Origin');
      } else {
         if (is_array($this->options['origin'])) {
            if (in_array($server, $this->options['origin'])) {
               $this->header('Access-Control-Allow-Origin', $server);
            } else {
               $this->header('Access-Control-Allow-Origin', $this->options['origin'][0]);
            }
            $this->header('Vary', 'Access-Control-Allow-Origin');
         } else {
            $this->header('Access-Control-Allow-Origin', $this->options['origin']);

         }
      }
      $this->header('Access-Control-Max-Age', $this->options['max-age']);
      $this->header('Access-Control-Allow-Credentials', ($this->options['creds'] ? 'true' : 'false'));
      if ($this->options['allow-headers'][0] === '*') {
         if ($request_header !== '') {
            $this->header('Access-Control-Allow-Headers', $request_header);
         }
      } else {
         $this->header('Access-Control-Allow-Headers', join(',', $this->options['allow-headers'])); 
      }
   }

   function optionsAction () {
      $this->setCorsHeaders();
   }
}
