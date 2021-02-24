<?PHP
require('../ref.php');

$bid = '123456';
$cid = 9873;
$iid = 3982;
$key = 'this is a key';

for ($i = 0; $i < 9999; $i+=7) {
  for ($j = 0; $j < 9999; $j++) {
    $ref = encodeRef($bid, $i, $j, $key);
    echo $ref . PHP_EOL;
    $res = decodeRef($bid, $ref, $key);
    if ($res['client'] === $i && $res['invoice'] === $j) {
      echo 'success' . PHP_EOL;
    }
  }
}
?>
