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

   function __construct() {
      parent::tFPDF();
   }

   function setFontSize($mm) {
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

   function set($name, $content) {
      $this->doc[$name] = $content;
   }

   function has($name) {
      return isset($this->doc[$name]);
   }

   function addTab($mm, $align = 'left') {
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

   function printLn($txt, $options = array()) {
      $break = isset($options['break']) ? $options['break'] : true;
      $linespacing = isset($options['linespacing']) ? $options['linespacing'] : 'single';
      $align = isset($options['align']) ? $options['align'] : $this->current_align;
      $underline = isset($options['underline']) ? $options['underline'] : false;

      $height = $this->getFontSize();
      $width = $this->GetStringWidth($txt);
      $underline_start = $this->GetX();

      if($width > $this->w -( $this->GetX() + $this->rMargin)) {
         switch($align) {
            case 'left':
            default:
               $txt = 'trop long : ' .( $this->w - ($this->GetX() + $this->rMargin));
               $width = $this->GetStringWidth($txt);
               break;
            case 'right':
               break;
         }

      }

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
         $lineheight = ($linespacing * $this->current_line_max);
         if($this->current_line_max == 0) {
            $lineheight = ($linespacing * $height);
         }
         $this->SetY($this->GetY() +  $lineheight, true);
         $this->current_line_max = 0;
         if($this->tabbed_align) {
            $this->current_align = 'left';
         }
      } else {
         if($this->FontSize > $this->current_line_max) {
            $this->current_line_max = $this->FontSize;
         }
      }
   }

   function AddPage($orientation = '', $size = '') {
      parent::AddPage();
      $this->page_count++;
      $this->pageLines();
      if($this->page_count > 1) {
         $this->addBillCode(false); 
      } else {
         $this->addBillCode(true);
      }

      $this->SetX($this->lMargin);
      $this->SetY($this->tMargin);
   }
}
?>
