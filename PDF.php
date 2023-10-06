<?PHP
/*- 
 * Copyright (c) 2017-2020 Etienne Bagnoud <etienne@artnum.ch>
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
 *cd
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

class PDF extends \tFPDF {
  protected $layers = array();
  protected $current_layer = '_origin';
  protected $previous_layer = '_origin';
  protected $doc = array();
  protected $tabs = array();
  protected $vtabs = array();
  protected $nvtabs = array();
  protected $current_align = 'left';
  protected $tabbed_align = false;
  protected $current_line_max = 0;
  protected $page_count = 0;
  protected $unbreaked_line = false;
  protected $last_font_size = 0;
  protected $tagged_fonts = array();
  protected $blocks = array();
  protected $current_block = null;
  protected $margin = array('left' => null, 'right' => null, 'top' => null);
  protected $blank = 0;
  private $page_is_added = false;
  public $coverPage = 0;

  function __construct($orientation='P', $unit='mm', $size='A4') {
    if (is_array($orientation)) {
      $params = $orientation;
      $orientation = 'P';
      $unit = 'mm';
      $size = 'A4';
      foreach($params as $k => $v) {
        if ($k === 'orientation') { $orientation = $v; }
        if ($k === 'unit') { $unit = $v; }
        if ($k === 'size') { $size = $v; }

      }
    }
    
    parent::__construct($orientation, $unit, $size);
    $this->add_layer('_origin');
    $this->current_layer = '_origin';
    $this->last_font_size = $this->FontSize;
  }

  function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '') {
    if (in_array($this->FontFamily, $this->CoreFonts)) {
      $txt = iconv('UTF-8', 'windows-1252', $txt);
    }
    $ret = parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    $this->_block();
    return $ret;
  }

  function Image($file, $x = NULL, $y = NULL, $w = 0, $h = 0, $type = '', $link = '') {
    $ret = parent::Image($file, $x, $y, $w, $h, $type);
    $this->_block();
    return $ret;
  }

  function Line($x1, $y1, $x2, $y2) {
    $ret = parent::Line($x1, $y1, $x2, $y2);
    $this->_block();
    return $ret;
  }

  function Link($x, $y, $w, $h, $link) {
    $ret = parent::Link($x, $y, $w, $h, $link);
    $this->_block();
    return $ret;
  }

  function Ln($h = null) {
    $ret = parent::Ln($h);
    $this->_block();
    return $ret;
  }

  function MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false) {
    if (in_array($this->FontFamily, $this->CoreFonts)) {
      $txt = iconv('UTF-8', 'windows-1252', $txt);
    }
    $ret = parent::MultiCell($w, $h, $txt, $border, $align, $fill);
    $this->_block();
    return $ret;
  }

  function Text($x, $y, $txt) {
    $ret = parent::Text($x, $y, $txt);
    $this->_block();
    return $ret;
  }

  function SetY($y, $resetX = true) {
    $ret = parent::SetY($y, $resetX);
    $this->_block();
    return $ret;
  }

  function SetXY($x, $y) {
    $ret = parent::SetXY($x, $y);
    $this->_block();
    return $ret;
  }

  function Close()
  {
    if($this->state==3) {
      return;
    }
    
    if($this->page==0) {
      $this->AddPage();
    }
    if ($this->blank <= 1) {
      $this->InFooter = true;
      $this->Footer();
      $this->InFooter = false;
    } else {
      $this->blank = 0;
    }
    
    $this->_endpage();
    $this->_enddoc();
  }
  
  function AddPage($orientation = '', $size = '', $rotation = 0) {
    /* extension */
    $this->page_is_added = true;
    $this->_flushblock();

    /* direct copy of original AddPage */
    // Start a new page 
    if($this->state==3)
      $this->Error('The document is closed');

    $family = $this->FontFamily;
    $style = $this->FontStyle.($this->underline ? 'U' : '');
    $fontsize = $this->FontSizePt;
    $lw = $this->LineWidth;
    $dc = $this->DrawColor;
    $fc = $this->FillColor;
    $tc = $this->TextColor;
    $cf = $this->ColorFlag;
    if($this->page>0)
    {
      // Page footer
      if ($this->blank <= 1) {
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
      } else {
        $this->blank = 0;
      }
      // Close page
      $this->_endpage();
    }
    // Start new page
    $this->_beginpage($orientation,$size,$rotation);
    // Set line cap style to square
    $this->_out('2 J');
    // Set line width
    $this->LineWidth = $lw;
    $this->_out(sprintf('%.2F w',$lw*$this->k));
    // Set font
    if($family)
    $this->SetFont($family,$style,$fontsize);
    // Set colors
    $this->DrawColor = $dc;
    if($dc!='0 G')
    $this->_out($dc);
    $this->FillColor = $fc;
    if($fc!='0 g')
    $this->_out($fc);
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
    if ($this->blank < 1) {
      // Page header
      $this->InHeader = true;
      $this->Header();
      $this->InHeader = false;
    } else {
      $this->blank = 2;
    }
    // Restore line width
    if($this->LineWidth!=$lw)
    {
      $this->LineWidth = $lw;
      $this->_out(sprintf('%.2F w',$lw*$this->k));
    }
    // Restore font
    if($family)
      $this->SetFont($family,$style,$fontsize);
    // Restore colors
    if($this->DrawColor!=$dc)
    {
      $this->DrawColor = $dc;
      $this->_out($dc);
    }
    if($this->FillColor!=$fc)
    {
      $this->FillColor = $fc;
      $this->_out($fc);
    }
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
    
    /* extension */
    $this->SetXY($this->lMargin, $this->tMargin);
    $this->_reinitblock();
    $this->page_is_added = false;

  }

  function AddBlankPage ($orientation = '', $size = '') {
    $this->blank = 1;
    $this->AddPage($orientation, $size);
  }

  function SetFillColor($color, $g = NULL, $b = NULL) {
    if (is_string($color)) {
      list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
    } else {
      $r = $color;
    }
    return parent::SetFillColor($r, $g, $b);
  }

  function _pdf_color($color, $g = NULL, $b = NULL) {
    if (is_string($color)) {
      list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
    }

    if (is_null($g)) {
      return sprintf('%.3F g', $r / 255);
    } else {
      return sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
    }
  }

  function block($name, $options = null) {
    $after = null;
    if (is_string($options)) {
      $after = $options;
    } else if (is_array($options)) {
      $after = $options[0];
      foreach($options as $b) {
        if ($this->blocks[$after]['max-y'] < $this->blocks[$b]['max-y']) {
          $after = $b;
        }
      }
    }
    $this->close_block();
    if (isset($this->blocks[$name])) {
      $this->SetY($this->blocks[$name]['origin']);
    } else {
      if ($after) {
        if (isset($this->blocks[$after])) {
          $this->SetY($this->blocks[$after]['max-y']);
        }
      }

      $dheight = $this->getLineHeight() - $this->getFontSize();
      $this->blocks[$name] = array(
        'origin' => $this->GetY(),
        'closed' => false,
        'max-y' => $this->GetY(),
        'left' => $this->left,
        'right' => $this->right,
        'delta-height' => $dheight,
        'buffer' => ''
      );
    }
    $this->current_block = $name;
    $this->SetX($this->left);
  }

  function get_block_origin () {
    if ($this->current_block && isset($this->blocks[$this->current_block])) {
      $block = $this->blocks[$this->current_block];
      return $block['origin'] - $block['delta-height'];
    }
    return 0;
  }

  function Output($dest='', $name='', $isUTF8=false) {
    $this->close_block();
    return parent::Output($dest, $name, $isUTF8);
  }

  protected function _flushblock() {
    if ($this->current_block && isset($this->blocks[$this->current_block])) {
      $this->blocks[$this->current_block]['max-y'] = $this->GetY();
      $this->_draw_block_bg($this->blocks[$this->current_block]);
    }
  }

  protected function _reinitblock() {
    if ($this->current_block && isset($this->blocks[$this->current_block])) {
      $this->blocks[$this->current_block]['origin'] = $this->GetY();
      $this->blocks[$this->current_block]['max-y'] = $this->GetY();
    }
  }

  protected function _block($y = null) {
    if ($this->current_block && isset($this->blocks[$this->current_block])) {
      if (!$y) { $y = $this->GetY(); }
      if ($this->blocks[$this->current_block]['max-y'] < $y) {
        $this->blocks[$this->current_block]['max-y'] = $y;
      }
    }
  }

  function reset_blocks() {
    $this->close_block();
    $this->blocks = array();
  }
  
  function to_block_end() {
    if ($this->current_block) {
      $this->SetY($this->blocks[$this->current_block]['max-y']);
    }
    $this->SetX($this->left);
  }

  function to_block_begin() {
    if ($this->current_block) {
      $this->SetY($this->blocks[$this->current_block]['origin']);
    }
    $this->SetX($this->left);
  }

  function _draw_block_bg($block) {
    if (isset($block['color'])) {
      $this->switch_layer('background');
      $this->SetFillColor($block['color']);
      $this->Rect($block['left'], $block['origin'] - $block['delta-height'], $this->w - ($block['left'] + $block['right']), $block['delta-height'] + ($block['max-y'] - $block['origin']), 'F');
      $this->SetFillColor('#000000');
      $this->reset_layer();
    }
  }

  function close_block() {
    $y = null;
    if ($this->current_block) {
      if (isset($this->blocks[$this->current_block])) {
        $block = &$this->blocks[$this->current_block];
        $block['closed'] = true;
        $y = $block['max-y'];
        $this->_draw_block_bg($block);
        $this->buffer .= $block['buffer'];
        $block['buffer'] = '';

      }
      $this->current_block = null;
    }
    if ($y) {
      $this->SetY($y);
    }
    $this->SetX($this->left);
  }

  function background_block($color) {
    $this->add_layer('background', array('index' => -99999));
    if ($this->current_block) {
      if (isset($this->blocks[$this->current_block])) {
        $this->blocks[$this->current_block]['color'] = $color;
        $this->blocks[$this->current_block]['painted-y'] = $this->blocks[$this->current_block]['origin'];
      }
    }
  }


  function add_layer($name, $options = array()) {
    $index = isset($options['index']) ? $options['index'] : 0;
    $drawcolor = isset($options['draw-color']) ? $this->_pdf_color($options['draw-color']) : '0 G';
    $fillcolor = isset($options['fill-color']) ? $this->_pdf_color($options['fill-color']) : '0 g';
    $textcolor = isset($options['text-color']) ? $this->_pdf_color($options['text-color']) : '0 g';

    if (! isset($this->layers[$name])) {
      $this->layers[$name] = array('data' => array(), 'index' => $index, 'fill-color' => $fillcolor, 'draw-color' => $drawcolor, 'text-color' => $textcolor);
    }
  }

  function switch_layer($name) {
    if (isset($this->layers[$name])) {
      $this->previous_layer = $this->current_layer;
      $this->current_layer = $name;
      $this->FillColor = $this->layers[$this->current_layer]['fill-color'];
      $this->DrawColor = $this->layers[$this->current_layer]['draw-color'];
      $this->TextColor = $this->layers[$this->current_layer]['text-color'];
    }
  }

  function reset_layer() {
    if ($this->previous_layer) {
      $this->current_layer = $this->previous_layer;
    } else {
      $this->current_layer = '_origin';
    }
    $this->FillColor = $this->layers[$this->current_layer]['fill-color'];
    $this->DrawColor = $this->layers[$this->current_layer]['draw-color'];
    $this->TextColor = $this->layers[$this->current_layer]['text-color'];
  }

  function _out($s) {
    if ($this->state == 2) {
      if (!isset($this->current_layer)) {
        if (!isset($this->layers['_origin']['data'][$this->page])) {
          $this->layers['_origin']['data'][$this->page] = $s . "\n";
        } else {
          $this->layers['_origin']['data'][$this->page] .= $s . "\n";
        }
      } else if (isset($this->layers[$this->current_layer]['data'][$this->page])) {
        $this->layers[$this->current_layer]['data'][$this->page] .= $s ."\n";
      } else {
        $this->layers[$this->current_layer]['data'][$this->page] = $s ."\n";
      }
    } else {
      if (!$this->page_is_added && $this->current_block && isset($this->blocks[$this->current_block])) {
        $this->blocks[$this->current_block]['buffer'] .= $s ."\n";
      } else {
        $this->buffer .= $s . "\n";
      }
    }
  }

  function _put ($s) {
    if (!$this->page_is_added && $this->current_block && isset($this->blocks[$this->current_block])) {
      $this->blocks[$this->current_block]['buffer'] .= $s ."\n";
    } else {
      $this->buffer .= $s . "\n";
    }
  }

  function _putpages() {
    usort($this->layers, function ($a, $b) {
      if (!isset($a['index'])) { $a['index'] = 0; }
      if (!isset($b['index'])) { $b['index'] = 0; }
      if ($a['index'] == $b['index']) { return 0; }
      return ($a['index'] < $b['index']) ? -1 : 1;
    });
    $pages = array();
    foreach ($this->layers as $layer) {
      foreach ($layer['data'] as $k => $line) {
        if (!isset($pages[$k])) {
          $pages[$k] = $layer['fill-color'] . "\n" . $layer['draw-color'] . "\n";
        }
        $pages[$k] .= $line . "\n";
      }
    }
    $this->pages =$pages;
    parent::_putpages();
  }

  function __get($name) {
    switch ($name) {
      case 'innerWidth':
        return $this->w - ($this->right + $this->left);
      case 'innerCenter':
        return ($this->w - ($this->left + $this->right)) / 2;
      case 'right': case 'left': case 'top':
        if (isset($this->margin[$name]) && $this->margin[$name]) {
          return $this->margin[$name];
        }
        if ($name == 'right') { return $this->rMargin; }
        if ($name == 'left') { return $this->lMargin; }
        if ($name == 'top') { return $this->tMargin; }
    }
  }

  function __set($name, $value) {
    switch ($name) {
      case 'right': case 'left': case 'top':
        if (is_numeric($value)) {
          if ($name == 'right') { $value += $this->rMargin; }
          if ($name == 'left') { $value += $this->lMargin; $this->SetX($value); }
          if ($name == 'top') { $value += $this->tMargin; }
          $this->margin[$name] = $value;
        }
    }
  }

  function __unset($name) {
    switch ($name) {
      case 'left': case 'right': case 'top':
        $this->margin[$name] = null;
        if ($name == 'left') { $this->SetX($this->left); }
    }
  }

  function addCover() {
    $this->coverPage++;
  }

  function pageCount() {
    return count($this->pages);
  }

  function resetFontSize() {
    $this->setFontSize($this->last_font_size);
  }

  function setPtFontSize($pt) {
    $this->FontSizePt = $pt;
    $this->FontSize = $pt / $this->k;
    $this->last_font_size = $this->FontSize;
    if($this->CurrentFont && $this->page>0) {
      $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
    }
  }

  function setFontSize($mm, $pt = false) {
    if ($pt) { return $this->setPtFontSize($mm); }
    $this->last_font_size = $this->FontSize;
    $this->FontSize = $mm;
    $this->FontSizePt = $mm * $this->k;
    if($this->page>0) {
      $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
    }
  }

  function reset() {
    $this->SetXY($this->left, $this->top);
  }

  function getFontSize() {
    return $this->FontSize;
  }

  function getFontWidth() {
    return $this->GetStringWidth('o');
  }

  function addTaggedFont($tag, $family, $style = '', $file = '', $uni = false) {
    $this->AddFont($family, $style, $file, $uni);
    $this->tagged_fonts[$tag] = [$family, $style];
  }

  function setTaggedFont($tag) {
    if(isset($this->tagged_fonts[$tag])) {
      $this->SetFont($this->tagged_fonts[$tag][0],$this->tagged_fonts[$tag][1]);
    }
  }

  /* Set position on the page relative to margins. Top - left corner is 0, 0. Only positive value */
  function setPosition ($x = -1, $y = -1) {
    if ($x < 0) {
      $x = $this->GetX();
    } else {
      $x += $this->left;
    }

    if ($y < 0) {
      $y = $this->GetY();
    } else {
      $y += $this->top;
    }

    $this->SetXY($x, $y);
  }

  function set($name, $content) {
    $this->doc[$name] = $content;
  }

  function get($name) {
    if ($this->has($name)) {
      return $this->doc[$name];
    }
  }

  function has($name) {
    return isset($this->doc[$name]);
  }

  function addTab($mm, $align = 'left') {
    if(is_string($mm)) {
      switch($mm) {
        default:
        case 'right': $mm = $this->w  - ($this->right + $this->left + $this->getFontWidth()) ; break;
        case 'left': $mm =0; break;
        case 'middle': $mm = ($this->w / 2) - ($this->left + $this->getFontWidth()); break;
      }
    }
    $this->tabs[] = array($mm, $align);
  }

  function tab($i) {
    $this->SetX($this->tabs[$i-1][0] + $this->left);
    $this->current_align = $this->tabs[$i-1][1];
    $this->tabbed_align = true;
  }

  function getTab($i) {
    return $this->tabs[$i-1][0] + $this->left;
  }
  
  function addVTab($mm, $name = null) {
    if($name == NULL) {
      $this->vtabs[] = $mm;
    } else {
      $this->vtabs[] = $mm;
      $this->nvtabs[$name] = $mm;
    }
  }

  function getVTab($i) {
    if (is_numeric($i)) {
      if (count($this->vtabs) < $i && $i >= 0) {
        return $this->vtabs[$i - 1] + $this->top;
      }
    } else {
      if (isset($this->nvtabs[$i])) {
        return $this->nvtabs[$i] + $this->top;
      }
    }
    return 0;
  }

  function vtab($i) {
    if(is_numeric($i)) {
      $this->SetY($this->vtabs[$i-1] + $this->top);
    } else {
      $this->SetY($this->nvtabs[$i] + $this->top);
    }
  }

  function vspace($mm) {
    $this->setY($this->getY() + $mm);
  }

  function printTaggedLn($txt, $options = array()) {
    if (is_string($txt)) {
      $txt = array($txt);
    }
    $break = isset($options['break']) ? $options['break'] : true;
    if (isset($options['align']) && strcasecmp($options['align'], 'right') == 0) {
      $txt = array_reverse($txt);
      if (!isset($options['max-width'])) {
        $this->SetX($this->w - ($this->right + $this->GetStringWidth('0')));
      } else {
        $this->SetX($this->GetX() + $options['max-width']);
      }
      foreach($txt as $t) {
        $t = strval($t);
        if (empty($t)) { continue; }
        if ($t[0] == '%') {
          if (isset($this->tagged_fonts[substr($t, 1)])) {
            $this->setTaggedFont(substr($t, 1));
            continue;
          }
        }
        $txtWidth = $this->GetStringWidth($t);
        $this->SetX($this->GetX() - $txtWidth);
        $o = $options; $o['break'] = false; $o['align'] = 'left';
        $this->printLn($t, $o);
        $this->SetX($this->GetX() - $txtWidth);
      }
    } else {
      foreach($txt as $t) {
        $t = strval($t);
        if (empty($t)) { continue; }
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
    }

    if($break) {
      $this->br();
    }
  }

  function printLn($txt, $options = array()) {
    $origin = $this->GetX();
    $this->unbreaked_line = false;

    $break = isset($options['break']) ? $options['break'] : true;
    $linespacing = isset($options['linespacing']) ? $options['linespacing'] : 'single';
    $align = isset($options['align']) ? $options['align'] : $this->current_align;
    $underline = isset($options['underline']) ? $options['underline'] : false;
    $max_width = isset($options['max-width']) ? $options['max-width'] : $this->w -($this->GetX() + $this->right);
    $multiline = isset($options['multiline']) ? $options['multiline'] : false;

    $height = $this->getFontSize();
    $width = $this->GetStringWidth($txt);

    $paragraph = array();
    if($width > $max_width) {
      $fromX = $this->GetX();
      switch($align) {
        case 'left':
        default:
          $ttxt = explode(' ', $txt);
          if(count($ttxt) > 1) {
            $sub = '';
            for($i = 0; $i < count($ttxt); $i++) {
              if($this->GetStringWidth($sub . ' ' . $ttxt[$i]) > $max_width) {
                if(isset($options['break']) && !$options['break']) {
                  $options['break'] = true;
                }
                $paragraph[] = $sub;
                $sub = '';
              }

              if($sub == '') {
                $sub = $ttxt[$i];
              } else {
                $sub .= ' ' . $ttxt[$i];
              }
            }
            if ($sub != '') {
              $paragraph[] = $sub;
            }
          } else {
            $sub = '';
            for($i = 0; $i < strlen($txt); $i++) {
              if($this->GetStringWidth($sub . $txt[$i]) > $max_width) {
                $paragraph[] = $sub;
                $sub = '';
              }
              $sub .= $txt[$i];
            }
          }
          break;
        case 'right':
          $paragraph[] = $txt;
          break;
      }
    } else {
      $paragraph[] = $txt;
    }

    switch($align) {
      default:
      case 'left':
        $underline_start = $this->GetX();
        $this->Cell($this->GetStringWidth($paragraph[0]), $height, $paragraph[0]);
        if ($multiline) {
          for ($i = 1; $i < count($paragraph); $i++) {
            $this->br();
            $this->SetX($origin);
            $this->Cell($this->GetStringWidth($paragraph[$i]), $height, $paragraph[$i]);
          }
        }
        break;
      case 'right':
        $this->SetX($this->w - ($this->right + $width));
        $underline_start = $this->GetX();
        $this->Cell($width, $height, $paragraph[0]);
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

  function getRGB($color)
  {
    switch ($color) {
        /* CSS Level 1 */
      case 'black':
        $color = '#000';
        break;
      case 'silver':
        $color = '#c0c0c0';
        break;
      case 'gray':
        $color = '#808080';
        break;
      case 'white':
        $color = '#fff';
        break;
      case 'maroon':
        $color = '#800000';
        break;
      case 'red':
        $color = '#ff0000';
        break;
      case 'purple':
        $color = '#800080';
        break;
      case 'fuchsia':
        $color = '#ff00ff';
        break;
      case 'green':
        $color = '#008000';
        break;
      case 'lime':
        $color = '#00ff00';
        break;
      case 'olive':
        $color = '#808000';
        break;
      case 'yellow':
        $color = '#ffff00';
        break;
      case 'navy':
        $color = '#000080';
        break;
      case 'blue':
        $color = '#0000ff';
        break;
      case 'teal':
        $color = '#008080';
        break;
      case 'aqua':
        $color = '#00ffff';
        break;
      /* css level 2, 3 and 4 */
      case 'orange': $color = '#ffa500'; break;
      case 'aliceblue': $color = '#f0f8ff'; break;
      case 'antiquewhite': $color = '#faebd7'; break;
      case 'aquamarine': $color = '#7fffd4'; break;
      case 'azure': $color = '#f0ffff'; break;
      case 'beige': $color = '#f5f5dc'; break;
      case 'bisque': $color = '#ffe4c4'; break;
      case 'blanchedalmond': $color = '#ffebcd'; break;
      case 'blueviolet': $color = '#8a2be2'; break;
      case 'brown': $color = '#a52a2a'; break;
      case 'burlywood': $color = '#deb887'; break;
      case 'cadetblue': $color = '#5f9ea0'; break;
      case 'chartreuse': $color = '#7fff00'; break;
      case 'chocolate': $color = '#d2691e'; break;
      case 'coral': $color = '#ff7f50'; break;
      case 'cornflowerblue': $color = '#6495ed'; break;
      case 'cornsilk': $color = '#fff8dc'; break;
      case 'crimson': $color = '#dc143c'; break;
      case 'cyan': $color = '#00ffff'; break;
      case 'darkblue': $color = '#00008b'; break;
      case 'darkcyan': $color = '#008b8b'; break;
      case 'darkgoldenrod': $color = '#b8860b'; break;
      case 'darkgray': $color = '#a9a9a9'; break;
      case 'darkgreen': $color = '#006400'; break;
      case 'darkgrey': $color = '#a9a9a9'; break;
      case 'darkkhaki': $color = '#bdb76b'; break;
      case 'darkmagenta': $color = '#8b008b'; break;
      case 'darkolivegreen': $color = '#556b2f'; break;
      case 'darkorange': $color = '#ff8c00'; break;
      case 'darkorchid': $color = '#9932cc'; break;
      case 'darkred': $color = '#8b0000'; break;
      case 'darksalmon': $color = '#e9967a'; break;
      case 'darkseagreen': $color = '#8fbc8f'; break;
      case 'darkslateblue': $color = '#483d8b'; break;
      case 'darkslategray': $color = '#2f4f4f'; break;
      case 'darkslategrey': $color = '#2f4f4f'; break;
      case 'darkturquoise': $color = '#00ced1'; break;
      case 'darkviolet': $color = '#9400d3'; break;
      case 'deeppink': $color = '#ff1493'; break;
      case 'deepskyblue': $color = '#00bfff'; break;
      case 'dimgray': $color = '#696969'; break;
      case 'dimgrey': $color = '#696969'; break;
      case 'dodgerblue': $color = '#1e90ff'; break;
      case 'firebrick': $color = '#b22222'; break;
      case 'floralwhite': $color = '#fffaf0'; break;
      case 'forestgreen': $color = '#228b22'; break;
      case 'gainsboro': $color = '#dcdcdc'; break;
      case 'ghostwhite': $color = '#f8f8ff'; break;
      case 'gold': $color = '#ffd700'; break;
      case 'goldenrod': $color = '#daa520'; break;
      case 'greenyellow': $color = '#adff2f'; break;
      case 'grey': $color = '#808080'; break;
      case 'honeydew': $color = '#f0fff0'; break;
      case 'hotpink': $color = '#ff69b4'; break;
      case 'indianred': $color = '#cd5c5c'; break;
      case 'indigo': $color = '#4b0082'; break;
      case 'ivory': $color = '#fffff0'; break;
      case 'khaki': $color = '#f0e68c'; break;
      case 'lavender': $color = '#e6e6fa'; break;
      case 'lavenderblush': $color = '#fff0f5'; break;
      case 'lawngreen': $color = '#7cfc00'; break;
      case 'lemonchiffon': $color = '#fffacd'; break;
      case 'lightblue': $color = '#add8e6'; break;
      case 'lightcoral': $color = '#f08080'; break;
      case 'lightcyan': $color = '#e0ffff'; break;
      case 'lightgoldenrodyellow': $color = '#fafad2'; break;
      case 'lightgray': $color = '#d3d3d3'; break;
      case 'lightgreen': $color = '#90ee90'; break;
      case 'lightgrey': $color = '#d3d3d3'; break;
      case 'lightpink': $color = '#ffb6c1'; break;
      case 'lightsalmon': $color = '#ffa07a'; break;
      case 'lightseagreen': $color = '#20b2aa'; break;
      case 'lightskyblue': $color = '#87cefa'; break;
      case 'lightslategray': $color = '#778899'; break;
      case 'lightslategrey': $color = '#778899'; break;
      case 'lightsteelblue': $color = '#b0c4de'; break;
      case 'lightyellow': $color = '#ffffe0'; break;
      case 'limegreen': $color = '#32cd32'; break;
      case 'linen': $color = '#faf0e6'; break;
      case 'magenta': $color = '#ff00ff'; break;
      case 'mediumaquamarine': $color = '#66cdaa'; break;
      case 'mediumblue': $color = '#0000cd'; break;
      case 'mediumorchid': $color = '#ba55d3'; break;
      case 'mediumpurple': $color = '#9370db'; break;
      case 'mediumseagreen': $color = '#3cb371'; break;
      case 'mediumslateblue': $color = '#7b68ee'; break;
      case 'mediumspringgreen': $color = '#00fa9a'; break;
      case 'mediumturquoise': $color = '#48d1cc'; break;
      case 'mediumvioletred': $color = '#c71585'; break;
      case 'midnightblue': $color = '#191970'; break;
      case 'mintcream': $color = '#f5fffa'; break;
      case 'mistyrose': $color = '#ffe4e1'; break;
      case 'moccasin': $color = '#ffe4b5'; break;
      case 'navajowhite': $color = '#ffdead'; break;
      case 'oldlace': $color = '#fdf5e6'; break;
      case 'olivedrab': $color = '#6b8e23'; break;
      case 'orangered': $color = '#ff4500'; break;
      case 'orchid': $color = '#da70d6'; break;
      case 'palegoldenrod': $color = '#eee8aa'; break;
      case 'palegreen': $color = '#98fb98'; break;
      case 'paleturquoise': $color = '#afeeee'; break;
      case 'palevioletred': $color = '#db7093'; break;
      case 'papayawhip': $color = '#ffefd5'; break;
      case 'peachpuff': $color = '#ffdab9'; break;
      case 'peru': $color = '#cd853f'; break;
      case 'pink': $color = '#ffc0cb'; break;
      case 'plum': $color = '#dda0dd'; break;
      case 'powderblue': $color = '#b0e0e6'; break;
      case 'rosybrown': $color = '#bc8f8f'; break;
      case 'royalblue': $color = '#4169e1'; break;
      case 'saddlebrown': $color = '#8b4513'; break;
      case 'salmon': $color = '#fa8072'; break;
      case 'sandybrown': $color = '#f4a460'; break;
      case 'seagreen': $color = '#2e8b57'; break;
      case 'seashell': $color = '#fff5ee'; break;
      case 'sienna': $color = '#a0522d'; break;
      case 'skyblue': $color = '#87ceeb'; break;
      case 'slateblue': $color = '#6a5acd'; break;
      case 'slategray': $color = '#708090'; break;
      case 'slategrey': $color = '#708090'; break;
      case 'snow': $color = '#fffafa'; break;
      case 'springgreen': $color = '#00ff7f'; break;
      case 'steelblue': $color = '#4682b4'; break;
      case 'tan': $color = '#d2b48c'; break;
      case 'thistle': $color = '#d8bfd8'; break;
      case 'tomato': $color = '#ff6347'; break;
      case 'turquoise': $color = '#40e0d0'; break;
      case 'violet': $color = '#ee82ee'; break;
      case 'wheat': $color = '#f5deb3'; break;
      case 'whitesmoke': $color = '#f5f5f5'; break;
      case 'yellowgreen': $color = '#9acd32'; break;
      case 'rebeccapurple': $color = '#663399'; break;       
    }

    $r = 0;
    $g = 0;
    $b = 0;
    if ($color[0] === '#') {
      $color = substr($color, 1);
      $h1 = '';
      $h2 = '';
      $h3 = '';
      if (strlen($color) == 3) {
        $h1 = $color[0] . $color[0];
        $h2 = $color[1] . $color[1];
        $h3 = $color[2] . $color[2];
      } else {
        $h1 = substr($color, 0, 2) ? substr($color, 0, 2) : 'ff';
        $h2 = substr($color, 2, 2) ? substr($color, 2, 2) : 'ff';
        $h3 = substr($color, 4, 2) ? substr($color, 4, 2) : 'ff';
      }

      $r = hexdec($h1);
      $g = hexdec($h2);
      $b = hexdec($h3);
    }

    return [$r, $g, $b];
  }

  function getReverseColor ($color) { 
    list ($r, $g, $b) = $this->getRGB($color);
    $r = 255 - $r;
    $g = 255 - $g;
    $b = 255 - $b;
    
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
  }


  function getBWFromColor ($color) { 
    list ($r, $g, $b) = $this->getRGB($color);
    $r = pow($r / 255, 2.2);
    $g = pow($g / 255, 2.2);
    $b = pow($b / 255, 2.2);
    
    if ((0.2126 * $r + 0.7151 * $g + 0.0721 * $b) < 0.5) {
      return 'white';
    }
    return 'black';
  }

  function setColor($color, $what = 'text') {
    list ($r, $g, $b) = $this->getRGB($color);
    switch(strtolower($what)) {
      case 'text' : default: $this->SetTextColor($r, $g, $b); break;
      case 'draw': $this->SetDrawColor($r, $g, $b); break;
      case 'fill': $this->SetFillColor($r, $g, $b); break;

    }
  }

  function getXYFromAL($x1, $y1, $x2, $y2) {
    /* Inverse Y coordinate to match PDF / circle coordinate */
    $angle = round(rad2deg(atan2(((-$y2)-(-$y1)), ($x2 - $x1))));
    $length = round(sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2)));
    return array($angle, $length);
  }

  function drawLineXY($x1, $y1, $x2, $y2, $type = 'line', $options = array()) {
    list($angle, $length) = $this->getXYFromAL($x1, $y1, $x2, $y2);
    $this->drawLine($x1, $y1, $length, $angle, $type, $options);
  }

  function drawLine($x1, $y1, $length, $angle = 0, $type = 'line', $options = array()) {
    $prevDrawColor = $this->DrawColor;
    $prevLineWidth = $this->LineWidth;

    if(isset($options['color'])) {
      $this->setColor($options['color'], 'draw');
    }

    $dashSize = 1;
    if(isset($options['dash-size'])) {
      $dashSize = $options['dash-size'];
    }

    $dashSpace = pow($dashSize, 1/3);
    if(isset($options['dash-space'])) {
      $dashSpace = $options['dash-space'];
    }

    if (isset($options['width'])) {
      $this->SetLineWidth($options['width']);
    }

    /* We use angle of circle which start at 3 o'clock (0°) and goes
       counter-clockwise (12 o'clock => 90 ...) but x,y are reversed in PDF
       so we just reverse the angle */
    $angle = - $angle;
    $x2 = $x1 + $length * cos(deg2rad($angle));
    $y2 = $y1 + $length * sin(deg2rad($angle));
    $this->_block($y2);
    $this->_block($y1);

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
          $this->drawLine($x1, $y1, $length, -$angle, 'dashed', array_merge(array( 
            'dash-size'=> 0.1, 'dash-space'=> 0.1
          ), $options));
          break;
      }
    }
    $this->DrawColor = $prevDrawColor;
    $this->SetLineWidth($prevLineWidth);
    $this->_out($this->DrawColor);
  }

  function squaredFrame($height, $options = array()) {
    $prevLineWidth = $this->LineWidth;
    $prevDrawColor = $this->DrawColor;

    $maxLength = isset($options['length']) ? $options['length'] : ceil($this->w - ($this->right + $this->left));
    $lineWidth = isset($options['line']) ? $options['line'] : 0.2;
    $squareSize = isset($options['square']) ? $options['square'] : 4;
    $lineType = isset($options['line-type']) ? $options['line-type'] : 'line';
    $upTo = isset($options['up-to']) ? $options['up-to'] : null;

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

    $lineX = $startX = isset($options['x-origin']) ? $options['x-origin'] : $this->left;
    $lineY = $startY = isset($options['y-origin']) ? $options['y-origin'] : $this->GetY();
    $lenX = $stopX =  $maxLength;
    if($lineX != $this->left && !isset($options['length'])) {
      $lenX = $this->w - ($lineX + $this->right);
    }

    if (is_null($upTo)) {
      $stopY = $startY + $height;
    } else {
      $stopY = $upTo;
    }

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
      $this->drawLine($startX, $i, $lenX, 0, $lineType);
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


  function frame($height, $options = array()) {
    $prevLineWidth = $this->LineWidth;
    $prevDrawColor = $this->DrawColor;

    $maxLength = isset($options['length']) ? $options['length'] : ceil($this->w - ($this->right + $this->left));

    if(isset($options['color'])) {
      $this->setColor($options['color'], 'draw');
    } else {
      $this->setColor('black', 'draw');
    }

    $lineX = $startX = isset($options['x-origin']) ? $options['x-origin'] : $this->left;
    $lineY = $startY = isset($options['y-origin']) ? $options['y-origin'] : $this->GetY();
    $lenX = $maxLength;
    if($lineX != $this->left && !isset($options['length'])) {
      $lenX = $this->w - ($lineX + $this->right);
    }

    $width = 0.2;
    if(isset($options['width'])) {
      $width = floatval($options['width']);
      $this->SetLineWidth($options['width']);
    }

    $width =  $width / 2;
    $this->drawLine($startX, $startY, $lenX);
    $this->drawLine($startX, $startY, $height - $width, -90);
    $this->drawLine($startX, $startY + $height,  $lenX);
    $this->drawLine($lenX + $startX, $startY, $height - $width, -90);
  

    /* Reset to previous state */
    $this->SetLineWidth($prevLineWidth);
    $this->DrawColor = $prevDrawColor;
    $this->_out($this->DrawColor);
  }

  function getRemainingWidth() {
    return $this->w - ($this->right + $this->GetX());
  }

  function getLineHeight($linespacing = 'single') {
    $height = $this->getFontSize();

    /* http://practicaltypography.com/line-spacing.html */
    if(is_string($linespacing)) {
      switch($linespacing) {
        default: case 'single': $linespacing = 1.20;
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
    $this->SetX($this->left);
    $this->_block();
  }

  function hr($fontsize = 0) {
    $y = $this->GetY();
    if($this->unbreaked_line) {
      $this->br();
    }
    if($fontsize != 0) {
      $this->setFontSize($fontsize);
    }

    $this->Line($this->lMargin, $y, ceil($this->w - $this->rMargin), $y);
    $this->br(50);

    if($fontsize != 0) {
      $this->resetFontSize();
    }
    $this->_block();
  }

  function getMargin ($margin = '') {
    switch (strtoupper($margin)) {
      // css order (top - right - bottom - left)
      default: return [$this->tMargin, $this->rMargin, $this->bMargin, $this->lMargin]; 
      case 'T': return $this->tMargin;
      case 'R': return $this->rMargin;
      case 'B': return $this->bMargin;
      case 'L': return $this->lMargin;
    }
  }

  function getDimension ($dimension = '') {
    switch (strtoupper($dimension)) {
      default: return [$this->w, $this->h, $this->wPt, $this->hPt];
      case 'W': return $this->w;
      case 'WPT': return $this->wPt;
      case 'H': return $this->h;
      case 'HPT': return $this->hPt;
    }
  }

  function getPageBottom () {
    return $this->h - $this->bMargin;
  }

  function setBottomMargin ($margin) {
    $this->bMargin = $margin;
  }

   /* fix httpencode for some case where user agent is not set */
   protected function _httpencode($param, $value, $isUTF8)
   {
      // Encode HTTP header field parameter
      if($this->_isascii($value)) {
         return $param.'="'.$value.'"';
      }
      if(!$isUTF8) {
         $value = utf8_encode($value);
      }
      if(!empty($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')!==false) {
         return $param.'="'.rawurlencode($value).'"';
      } else {
         return $param."*=UTF-8''".rawurlencode($value);
      }
   }

   function getTextHeight ($text, $width = 0) {
    if ($width === 0) { $width = $this->w; }
    $lines = explode("\n", $text);
    $strHeight = 0;
    $lineWidth = 0;
    foreach ($lines as $line) {
      $words = explode(' ', $line);
      foreach($words as $word) {
        $wWidth = $this->GetStringWidth($word . ' ');
        if ($lineWidth + $wWidth > $width) {
          $strHeight++;
          $lineWidth = 0;
        }
        $lineWidth += $wWidth;
      }
    }

    return $this->getLineHeight() * $strHeight;
   }
}


?>
