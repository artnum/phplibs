<?PHP
function getAttr($path, $attr)
{
   $ePath = escapeshellarg($path);
   $eAttr = escapeshellarg($attr);

   $lLine = exec(sprintf('attr -q -g %s %s 2>/dev/null', $eAttr, $ePath), $rTxt, $rCode);
   if($rCode == 0) {
      return $lLine;
   }

   return FALSE;
}

function setAttr($path, $attr, $value)
{
   $ePath = escapeshellarg($path);
   $eAttr = escapeshellarg($attr);
   $eValue = escapeshellarg($value);

   $lLine = exec(sprintf('attr -q -s %s -V %s %s 2>&1 > /dev/null', $eAttr, $eValue, $ePath), $rTxt, $rCode);
   if($rCode == 0) {
      return TRUE;
   }

   return FALSE;
}
?>
