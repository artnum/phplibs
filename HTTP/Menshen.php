<?PHP

set_include_path(get_include_path() . PATH_SEPARATOR . '/home/etienne/Projets/Web/phplibs/phpseclib');
include('Crypt/RSA.php');

function fromHex ($data) {
  $v = 0;
  $out = array();
  for ($i = 0; $i < strlen($data); $i++) {
    switch($data[$i]) {
      case '0': $v |= 0; break;
      case '1': $v |= 1; break;
      case '2': $v |= 2; break;
      case '3': $v |= 3; break;
      case '4': $v |= 4; break;
      case '5': $v |= 5; break;
      case '6': $v |= 6; break;
      case '7': $v |= 7; break;
      case '8': $v |= 8; break;
      case '9': $v |= 9; break;
      case 'a': case 'A': $v |= 0xA; break;
      case 'b': case 'B': $v |= 0xB; break;
      case 'c': case 'C': $v |= 0xC; break;
      case 'd': case 'D': $v |= 0xD; break;
      case 'e': case 'E': $v |= 0xE; break;
      case 'f': case 'F': $v |= 0xF; break;
    }
    if ($i % 2 === 0) {
      $v <<= 4;
    } else {
      $out[] = $v;
      $v = 0;
    }
  }
  return pack('C*', $out);
}

$data = 'get|http://localhost/|e4daa6054fbb556500000001';
$sig = '0c8a385e75937997896abc60ea093a00928a4c207a7f5aeaa0d4fd9272f6b3b9e4ca0605daee0a1c493ccd3972007f3c7a0609f4e1c768dedd33a088b2d3f3e568eb796cd4ba6c3d36d0e8100d83ff18cdfb491a70ad89aaa461b16a962094a796\
343eaa57630986d1bb916d1e7a34cf4215aa13f6511108a4cb9ee1c89140bc28e943a6dace64b5b927db52679375e652acd3e00d3543871d70f8d7f21f0f314f1e0b866e29fbd83a29d6b35b06e0a07e7a8efba00763f90bc35344a3af4e92913ff503c980ab4f6ff8\
564c4017ce60c6841a4949732d11f030e437ef5ace9c27a300154b168d72af0cb79ed2e3d212fdac41cfc422ef394b8583622b728e310026874b29ed4f70216507b9341ca5c744e07115fdf4cc7afb7aa8f9603396ef9f207d5708da612bb52b200505f325c8f95c1f\
c77dadc43ac7378f89f00f9d6f6350ba524332783be7d9e3528d8fc2387d9a843ee0e04d0ea104d84b3f1c359587a6af1d4492a32213e372b294487a40f6a540b3563056b9e9befdf847ce55b41d1186b7e90c6f05fefaf92f1e158cfd0e1c2f608fdb8a0b34d57ae9\
9f78fc6878cc02338edb63a73c998d9397b21d45d8e6567370ad3d7383506aeb8cbb41eb2d59931192eb218adc5d754ccf0cad07c824c9b8506706991580163da654224e6479f6bf9d3eb2ddb98906bbba20c559fcb310055e4888ab37734ee288ab1403';


$pkey = openssl_pkey_get_public('file://../../../test/WebCrypto/test-pubkey.spki');
$bsig = fromHex($sig);

print_r(openssl_verify($data, $bsig, $pkey, OPENSSL_ALGO_SHA256));


?>
