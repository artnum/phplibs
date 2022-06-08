<?PHP
Namespace artnum\HTTP;

class CORS
{
   function setCorsHeaders () {
      $server = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
      $method = isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) ? $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] : '';
      $request_header = isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) ? $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] : '';

      if (empty($method)) { $method = 'GET, POST, PUT, DELETE, HEAD, OPTIONS'; }
      if (empty($server)) { $server = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['SERVER_NAME']; }
      if (empty($request_header)) { $request_header = 'x-requested-with,x-artnum-reqid,content-type,x-request-id,authorization'; }

      header('Allow: GET, POST, PUT, DELETE, OPTIONS', true);
      header('Access-Control-Allow-Methods: ' . $method, true);
      header('Access-Control-Allow-Origin: ' . $server, true);
      header('Access-Control-Max-Age: 3600', true);
      header('Access-Control-Allow-Credentials: true', true);
      header('Access-Control-Allow-Headers: ' . $request_header, true);
   }

   function optionsAction ($req) {
      $this->setCorsHeaders();
   }
}
