<?PHP
/* Chiffre clé BVR */
global $bvr_table;
$bvr_table = array(
  array(0, 9, 4, 6, 8, 2, 7, 1, 3, 5),
  array(9, 4, 6, 8, 2, 7, 1, 3, 5, 0),
  array(4, 6, 8, 2, 7, 1, 3, 5, 0, 9),
  array(6, 8, 2, 7, 1, 3, 5, 0, 9, 4),
  array(8, 2, 7, 1, 3, 5, 0, 9, 4, 6),
  array(2, 7, 1, 3, 5, 0, 9, 4, 6, 8),
  array(7, 1, 3, 5, 0, 9, 4, 6, 8, 2),
  array(1, 3, 5, 0, 9, 4, 6, 8, 2, 7),
  array(3, 5, 0, 9, 4, 6, 8, 2, 7, 1),
  array(5, 0, 9, 4, 6, 8, 2, 7, 1, 3));

function bvrkey($ref) {
  global $bvr_table;
  $r = 0;
  $ref = strrev($ref);
  for ($i = strlen($ref) - 1; $i >= 0; $i--) {
    $r = $bvr_table[$r][intval($ref[$i])];
  }
  return array(0, 9, 8, 7, 6, 5, 4, 3, 2, 1)[$r];
}
?>
