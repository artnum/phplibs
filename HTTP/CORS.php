<?PHP
Namespace artnum\HTTP;

class CORS
{
   function optionsAction ($req) {
      header('Allow: GET, POST, PUT, DELETE, OPTIONS');
      header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
      header('Access-Control-Allow-Origin: *');
      header('Access-Control-Max-Age: 3600');
      header('Access-Control-Allow-Headers: x-requested-with, x-artnum-reqid, content-type, x-request-id');
   }
}
