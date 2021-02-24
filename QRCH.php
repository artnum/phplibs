<?PHP
Namespace artnum;

define('QREOL', "\r\n"); /* standard allow only \n, but it doesn't seem to be default */
define('QRType', 'SPC');
define('QRVersion', '0200');
define('QRCoding', '1'); /* utf-8 */
define('QRStructAddr', 'S');
define('QR2LinesAddr', 'K');
define('QREnd', 'EPD');
define('QRRef', 'QRR');
define('QRCreRef', 'SCOR');
define('QRNoRef', 'NON');
const $ISO31661CC = array('AF','AX','AL','DZ','AS','AD','AO','AI','AQ','AG','AR','AM','AW','AU','AT','AZ','BS','BH','BD','BB','BY','BE','BZ','BJ','BM','BT','BO','BQ','BA','BW','BV','BR','IO','BN','BG','BF','BI','KH','CM','CA','CV','KY','CF','TD','CL','CN','CX','CC','CO','KM','CG','CD','CK','CR','CI','HR','CU','CW','CY','CZ','DK','DJ','DM','DO','EC','EG','SV','GQ','ER','EE','ET','FK','FO','FJ','FI','FR','GF','PF','TF','GA','GM','GE','DE','GH','GI','GR','GL','GD','GP','GU','GT','GG','GN','GW','GY','HT','HM','VA','HN','HK','HU','IS','IN','ID','IR','IQ','IE','IM','IL','IT','JM','JP','JE','JO','KZ','KE','KI','KP','KR','KW','KG','LA','LV','LB','LS','LR','LY','LI','LT','LU','MO','MK','MG','MW','MY','MV','ML','MT','MH','MQ','MR','MU','YT','MX','FM','MD','MC','MN','ME','MS','MA','MZ','MM','NA','NR','NP','NL','NC','NZ','NI','NE','NG','NU','NF','MP','NO','OM','PK','PW','PS','PA','PG','PY','PE','PH','PN','PL','PT','PR','QA','RE','RO','RU','RW','BL','SH','KN','LC','MF','PM','VC','WS','SM','ST','SA','SN','RS','SC','SL','SG','SX','SK','SI','SB','SO','ZA','GS','SS','ES','LK','SD','SR','SJ','SZ','SE','CH','SY','TW','TJ','TZ','TH','TL','TG','TK','TO','TT','TN','TR','TM','TC','TV','UG','UA','AE','GB','US','UM','UY','UZ','VU','VE','VN','VG','VI','WF','EH','YE','ZM','ZW');
class QRCH {
  $qrdata = array(
    QRType,
    QRVersion,
    QRCoding,
    '', /* 3 iban */
    '', /* 4 caddr type */
    '', /* 5 caddr name */
    '', /* 6 caddr str or line1 */
    '', /* 7 caddr number or line 2 */
    '', /* 8 caddr postal code */
    '', /* 9 caddr locality */
    '', /* 10 caddr country */
    '', /* 11 ucaddr type */
    '', /* 12 ucaddr name */
    '', /* 13 ucaddr str or line1 */
    '', /* 14 ucaddr number or line 2 */
    '', /* 15 ucaddr postal code */
    '', /* 16 ucaddr locality */
    '', /* 17 ucaddr country */
    '', /* 18 amount */
    '', /* 19 currency */
    '', /* 20 udaddr type */
    '', /* 21 udaddr name */
    '', /* 22 udaddr str or line1 */
    '', /* 23 udaddr number or line 2 */
    '', /* 24 udaddr postal code */
    '', /* 25 udaddr locality */
    '', /* 26 udaddr country */
    QENoRef, /* 27 reference type */
    '', /* 28 reference */
    '', /* 29 unstrctured text of 140 chars */
    QREnd, /* 30 end which is not at the end */
    '' /* 31 Swico stuff ... not implemented */
    /* still two lines but not implemented and must not be present if not implemented */
    );
  function __construct ($data) {   
    $this->exIBAN($this->qrdata, $data);
    $this->exAddr($this->qrdata, $data, 'C');
    if (isset($data['UDName'])) {
      $this->exAddr($this->qrdata, $data, 'UD');
    }
    if (isset($data['amount'])) {
      $this->exAmount($this->qrdata, $data);
    }
    $this->exCurrency($this->qrdata, $data);
  }

  protected function outStr ($qrdata) {
    return implode(QREOL, $qrdata);
  }

  protected function exCurrency (&$qrdata, $data) {
    if (!isset($data['currency']) || empty($data['currency'])) {
      $qrdata[19] = 'CHF';
    } else {
      switch (strtolower($data['currency'])) {
        case 'eu': case 'eur': case 'euro': case '€': $qrdata[19] = 'EUR'; break;
        default: case 'chf': case 'francs': case 'franc': case 'ch': $qrdata[19] = 'CHF';
      }
    }
  }

  protected function exAmount(&$qrdata, $data) {
    if (!isset($data['amount'])) {
      $value = floatval($data['amount']);
      $value = strval(intval($value * 100));
      if (strlen($value) === 0) {
        /* nothing */
      } else if (strlen($value) <= 2) {
        $value = '0.' . $value;
      } else {
        $value = substr($value, 0, strlen($value) - 2) . '.' . substr($value, strlen($value) - 2);
      }
      if (strlen($value) > 12) {
        throw new Exception('Amount too big');
      }
      $qrdata[18] = $value;
    }
  }
  
  protected function exIBAN (&$qrdata, $data) {
    if (!isset($data['iban']) || empty($data['iban'])) {
      throw new \Exception('No IBAN set');
    }
    $iban = trim(strtoupper($data['iban']));
    if (substr($iban, 0, 2) !== 'CH' || substr($iban, 0, 2) !== 'LI') {
      throw new \Exception('Only for Swiss or Lichentestein');
    }
    $iban = str_replace(' ', '', $iban);
    if (strlen($iban) !== 21) {
      throw new \Exception('IBAN doesn\'t have required length of 21 characters');
    }
    $qrdata[3] = $iban;
  }

  /* type can be C for "Créancier", UC for "Créancier ultime" (reserved for future use in version 2) or UD "Débiteur ultime" */
  protected function exAddr (&$qrdata, $data, $type = 'C') {
    $offset = 4;
    switch ($type) {
      case 'C': default: break;
      case 'UC':
        $offset = 11;
        $qrdata[$offset + 0] = '';
        $qrdata[$offset + 1] = '';
        $qrdata[$offset + 2] = '';
        $qrdata[$offset + 3] = '';
        $qrdata[$offset + 4] = '';
        $qrdata[$offset + 5] = '';
        $qrdata[$offset + 6] = '';       
        break;
      case 'UD': $offset = 20; break;
    }
    if (!isset($data[$type . 'Name']) || empty($data[$type . 'Name'])) {
      throw new \Exception('No name for ' . $type . ' address');
    }
    if (!isset($data[$type . 'Line1']) && isset($data[$type . 'Street'])) {
      /* structured address */
      $qrdata[$offset] = QRStructAddr;
      $content = array(
        'Name' => array(1, 70),
        'Street' => array(2, 70),
        'Number' => array(3, 16),
        'PCode' => array(4, 16),
        'Locality' => array(5, 35),
        'Country' => array(6, 2)
      );
    } else {
      $qrdata[$offset] = QR2LinesAddr;
      $content = array(
        'Name' => array(1, 70),
        'Line1' => array(2, 70),
        'Line2' => array(3, 70),
        'PCode' => array(4, 0),
        'Locality' => array(5, 0),
        'Country' => array(6, 0)
      );
    }
    foreach ($content as $name => $details) {
      if (!isset($data[$type . $name]) && $details[1] > 0) {
        throw new \Exception('Missing ' $name . ' in ' . $type . ' address');
      }
      $entry = '';
      if ($details[1] !== 0) {
        $entry = $data[$type . $name];
        if (strlen($entry) > $details[1]) {
          $entry = substr($entry, 0, $details[1]);
        }
        switch ($name) {
          case 'Country':
            $entry = strtoupper($entry);
            if (!in_array($entry, $ISO31661CC)) {
              throw new Exception('Not a country code');
            }
            break;
        }
      }
      $qrdata[$offset + $details[0]] = $entry;
    }
  }
  
}


?>
