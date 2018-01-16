<?PHP
/*- 
 * Copyright (c) 2017 Etienne Bagnoud <etienne@artisan-numerique.ch>
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
namespace artnum;

include('tfpdf/tfpdf.php');

class PDF extends \tFPDF {
   protected $doc = array();
   protected $tabs = array();
   protected $vtabs = array();
   protected $current_align = 'left';
   protected $tabbed_align = false;
   protected $current_line_max = 0;
   protected $page_count = 0;
   protected $unbreaked_line = false;
   protected $last_font_size = 0;
   protected $tagged_fonts = array();

   function __construct() {
      parent::tFPDF();
      $this->last_font_size = $this->FontSize;
   }

   function resetFontSize() {
      $this->setFontSize($this->last_font_size);
   }

   function setFontSize($mm) {
      $this->last_font_size = $this->FontSize;
      $this->FontSize = $mm;
      $this->FontSizePt = $mm * $this->k;
      if($this->page>0) {
         $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
      }
   }

   function reset() {
      $this->SetXY($this->lMargin, $this->tMargin);
   }

   function getFontSize() {
      return $this->FontSize;
   }

   function getFontWidth() {
      return $this->GetStringWidth('o');
   }

   function addTaggedFont($tag, $family, $style = '', $file = '', $uni = false) {
      $this->AddFont($family, $style, $file, $uni);
      $this->tagged_fonts[$tag] = $family; 
   }

   function setTaggedFont($tag) {
      if(isset($this->tagged_fonts[$tag])) {
         $this->SetFont($this->tagged_fonts[$tag]);
      }
   }

   function set($name, $content) {
      $this->doc[$name] = $content;
   }

   function has($name) {
      return isset($this->doc[$name]);
   }

   function addTab($mm, $align = 'left') {
      if(is_string($mm)) {
         switch($mm) {
            default:
            case 'right': $mm = $this->w  - ($this->rMargin + $this->lMargin + $this->getFontWidth()) ; break;
            case 'left': $mm =0; break;
            case 'middle': $mm = ($this->w / 2) - ($this->lMargin + $this->getFontWidth()); break;
         }
      }
      $this->tabs[] = array($mm, $align);
   }

   function tab($i) {
      $this->SetX($this->tabs[$i-1][0] + $this->lMargin);
      $this->current_align = $this->tabs[$i-1][1];
      $this->tabbed_align = true;
   }

   function addVTab($mm) {
      $this->vtabs[] = $mm;
   }

   function vtab($i) {
      $this->SetY($this->vtabs[$i-1] + $this->tMargin);
   }

   function vspace($mm) {
      $this->setY($this->getY() + $mm);
   }

   function printTaggedLn($txt, $options = array()) {
      $break = isset($options['break']) ? $options['break'] : true;
      foreach($txt as $t) {
         if($t[0] == '%') {
            if(isset($this->tagged_fonts[substr($t, 1)])) {
               $this->setTaggedFont(substr($t, 1));
            } else {
               $o = $options; $o['break'] = false;
               $this->printLn($t, $o);
            }
         } else {
            $o = $options; $o['break'] = false;
            $this->printLn($t, $o);
         }
      }
       
      if($break) {
         $this->br();
      }
   }

   function printLn($txt, $options = array()) {
      $this->unbreaked_line = false;

      $break = isset($options['break']) ? $options['break'] : true;
      $linespacing = isset($options['linespacing']) ? $options['linespacing'] : 'single';
      $align = isset($options['align']) ? $options['align'] : $this->current_align;
      $underline = isset($options['underline']) ? $options['underline'] : false;
      $max_width = isset($options['max-width']) ? $options['max-width'] : $this->w -( $this->GetX() + $this->rMargin);

      $height = $this->getFontSize();
      $width = $this->GetStringWidth($txt);
      $underline_start = $this->GetX();

      if($width > $max_width) {
         switch($align) {
            case 'left':
            default:
               $ttxt = explode(' ', $txt);
               $sub = '';
               for($i = 0; $i < count($ttxt); $i++) {
                  if($this->GetStringWidth($sub . ' ' . $ttxt[$i]) > $max_width) {
                     if(isset($options['break']) && !$options['break']) {
                        $options['break'] = true;
                     }

                     $this->printLn($sub, $options);
                     $sub = '';
                  }

                  if($sub == '') {
                     $sub = $ttxt[$i];
                  } else {
                     $sub .= ' ' . $ttxt[$i];
                  }
               }
               if($sub != '' && !empty($sub)) {
                  $txt = $sub;
                  $width = $this->GetStringWidth($txt);
                  break;
               } else {
                  return;
               }
            case 'right':
               break;
         }

      }

      switch($align) {
         default:
         case 'left':
            $this->Cell($width, $height, $txt);
            break;
         case 'right':
            $this->SetX($this->getX() - $width);
            $this->Cell($width, $height, $txt);
            break;
         case 'center':
            break;
      }
      if($underline) {
         $underline_height = $this->GetY() + $height + 0.5;
         $this->Line($underline_start + $this->cMargin, $underline_height, $underline_start + $width + $this->cMargin, $underline_height);
      }

      if($break) {
         $this->br($linespacing);
      } else {
         if($this->FontSize > $this->current_line_max) {
            $this->current_line_max = $this->FontSize;
            $this->unbreaked_line = true;
         }
      }
   }

   function setColor($hex, $what = 'text') {

      switch($hex) {
         /* CSS Level 1 */
         case 'black':     $hex = '000'; break;
         case 'silver':    $hex = 'c0c0c0'; break;
         case 'gray':      $hex = '808080'; break;
         case 'white':     $hex = 'fff'; break;
         case 'maroon':    $hex = '800000'; break;
         case 'red':       $hex = 'ff0000'; break;
         case 'purple':    $hex = '800080'; break;
         case 'fuchsia':   $hex = 'ff00ff'; break;
         case 'green':     $hex = '008000'; break;
         case 'lime':      $hex = '00ff00'; break;
         case 'olive':     $hex = '808000'; break;
         case 'yellow':    $hex = 'ffff00'; break;
         case 'navy':      $hex = '000080'; break;
         case 'blue':      $hex = '0000ff'; break;
         case 'teal':      $hex = '008080'; break;
         case 'aqua':      $hex = '00ffff'; break;
      }

      $r = 0; $g = 0; $b = 0;
      if($hex[0] == '#') {
         $hex = substr($hex, 1);
      }
      
      $h1 = ''; $h2 = ''; $h3 = '';
      if(strlen($hex) == 3) {
         $h1 = $hex[0] . $hex[0];
         $h2 = $hex[1] . $hex[1];
         $h3 = $hex[2] . $hex[2];
      } else {
         $h1 = substr($hex, 0, 2) ? substr($hex, 0, 2) : 'ff';
         $h2 = substr($hex, 2, 2) ? substr($hex, 2, 2) : 'ff';
         $h3 = substr($hex, 4, 2) ? substr($hex, 4, 2) : 'ff';
      }

      $r = hexdec($h1);
      $g = hexdec($h2);
      $b = hexdec($h3);

      switch(strtolower($what)) {
         case 'text' : default: $this->SetTextColor($r, $g, $b); break;
         case 'draw': $this->SetDrawColor($r, $g, $b); break;
         case 'fill': $this->SetFillColor($r, $g, $b); break;

      }
   }

   function getXYFromAL($x1, $y1, $x2, $y2) {
      /* Inverse Y coordinate to match PDF / circle coordinate */
      $angle = rad2deg(atan2(((-$y2)-(-$y1)), ($x2 - $x1)));
      $length = sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
      return array($angle, $length); 
   }

   function drawLineXY($x1, $y1, $x2, $y2, $type = 'line', $options = array()) {
      list($angle, $length) = $this->getXYFromAL($x1, $y1, $x2, $y2);
      $this->drawLine($x1, $y1, $length, $angle, $type, $options);
   }

   function drawLine($x1, $y1, $length, $angle = 0, $type = 'line', $options = array()) {
      $prevDrawColor = $this->DrawColor;
      
      if(isset($options['color'])) {
         $this->setColor($options['color'], 'draw');
      }

      $dashSize = 1;
      if(isset($options['dash-size'])) {
         $dashSize = $options['dash-size'];
      }

      $dashSpace = pow($dashSize, 1/3);
      if(isset($options['dash-space'])) {
         $dashSapce = $options['dash-space'];
      }

      /* We use angle of circle which start at 3 o'clock (0°) and goes
         counter-clockwise (12 o'clock => 90 ...) but x,y are reversed in PDF
         so we just reverse the angle */
      $angle = - $angle;
      $x2 = $x1 + $length * cos(deg2rad($angle));
      $y2 = $y1 + $length * sin(deg2rad($angle));

      if($type == 'line') {
         $this->Line($x1, $y1, $x2, $y2);
      } else {
         switch($type) {
            case 'dashed':
               $totalLength = 0;
               while($totalLength <= $length) {
                  if($totalLength + $dashSize > $length) {
                     $this->drawLine($x1, $y1, $length - $totalLength, - $angle, 'line', $options); 
                  } else {
                     $this->drawLine($x1, $y1, $dashSize, - $angle, 'line', $options); 
                  }
                  $x1 = $x1 + ($dashSize + $dashSpace) * cos(deg2rad($angle));
                  $y1 = $y1 + ($dashSize + $dashSpace) * sin(deg2rad($angle));
                  $totalLength += ($dashSize + $dashSpace);
               }
               break;
            case 'dotted':
               $this->drawLine($x1, $y1, $length, $angle, 'dashed', array_merge(array( 
                        'dash-size'=> 0.1, 'dash-space'=> 0.1
                        ), $options));
               break;
         }
      }

      $this->DrawColor = $prevDrawColor;
      $this->_out($this->DrawColor);
   }

   function squaredFrame($height, $options = array()) {
      $prevLineWidth = $this->LineWidth;
      $prevDrawColor = $this->DrawColor;
      
      $maxLength = isset($options['length']) ? $options['length'] : ($this->w - ($this->rMargin + $this->lMargin));
      $lineWidth = isset($options['line']) ? $options['line'] : 0.2;
      $squareSize = isset($options['square']) ? $options['square'] : 4;
      $lineType = isset($options['line-type']) ? $options['line-type'] : 'line';
     
      $vertical = true;
      if(isset($options['lined']) && $options['lined']) {
         $vertical = false;
      }
      
      if(isset($options['color'])) {
         $this->setColor($options['color'], 'draw');
      } else {
         $this->setColor('black', 'draw');
      }
   
      $this->SetLineWidth($lineWidth);

      $lineX = $startX = isset($options['x-origin']) ? $options['x-origin'] : $this->lMargin;
      $lineY = $startY = isset($options['y-origin']) ? $options['y-origin'] : $this->GetY();
      $lenX = $stopX =  $maxLength;
      if($lineX != $this->lMargin && !isset($options['length'])) {
         $lenX = $this->w - ($lineX + $this->rMargin);
      }
      $lenY = $stopY = $startY + $height;

      $border = false;
      if((isset($options['border']) && $options['border']) || (isset($options['skip']) && $options['skip'])) {
         $lineX += $squareSize;
         $lineY += $squareSize;
         $stopX -= $squareSize;
         $stopY -= $squareSize;
         if(isset($options['border']) && $options['border']) {
            $border = true;
         }
      }

      if($vertical) {
         for($i = $lineX; $i <= $stopX + $startX; $i += $squareSize) {
            $this->drawLine($i, $startY, $height, 270, $lineType);
         }
      }
      for($i = $lineY; $i <= $stopY; $i += $squareSize) {
         $this->drawLine($startX, $i, $lenX, 0, $lineType) ;
      }

      if($border) {
         if(isset($options['border-line']) && $options['border-line']) {
            $this->SetLineWidth($options['border-line']);
         }      
         if(isset($options['border-color']) && $options['border-line']) {
            $this->setColor($options['border-color'], 'draw');
         }
         
         $this->drawLine($startX, $startY, $lenX);
         $this->drawLine($startX, $startY, $height, 270);
         $this->drawLine($lenX + $startX , $stopY + $squareSize, $height, 90);
         $this->drawLine($lenX + $startX, $stopY + $squareSize, $lenX, 180);
      }

      /* Reset to previous state */
      $this->SetLineWidth($prevLineWidth);
      $this->DrawColor = $prevDrawColor;
      $this->_out($this->DrawColor);
   }

   function getRemainingWidth() {
      return $this->w - ($this->rMargin + $this->GetX());
   }

   function getLineHeight($linespacing = 'single') {
      $height = $this->getFontSize();

      /* http://practicaltypography.com/line-spacing.html */
      if(is_string($linespacing)) {
         switch($linespacing) {
            default: case 'single': $linespacing = 1.20 ;
            case '1.5': $linespacing = 1.32;
            case 'double': $linespacing = 1.45 ;
         }
      } else if(is_numeric($linespacing)) {
         $linespacing = $linespacing / 100;
      } 

      $lineheight = ($linespacing * $this->current_line_max);
      if($this->current_line_max == 0) {
         $lineheight = ($linespacing * $height);
      }
      
      return $lineheight; 
   }

   function br($linespacing = 'single') {
      $this->SetY($this->GetY() +  $this->getLineHeight($linespacing), true);
      $this->current_line_max = 0;
      if($this->tabbed_align) {
         $this->current_align = 'left';
      }
   }

   function hr($fontsize = 0) {
      $y = $this->GetY();
      if($this->unbreaked_line) {
         $this->br();
      }
      if($fontsize != 0) {
         $this->setFontSize($fontsize);
      }

      $this->Line($this->lMargin, $y, $this->w - ($this->rMargin), $y);
      $this->br(50);

      if($fontsize != 0) {
         $this->resetFontSize();
      }
   }
}
?>
