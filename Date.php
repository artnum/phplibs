<?PHP
namespace artnum;

class Date {
    const eumonths = [
        'gennaio' => 1,
        'janvier' => 1,
        'january' => 1,
        'januar' => 1,
        'febbraio' => 2,
        'fevrier' => 2,
        'février' => 2,
        'february' => 2,
        'feburar' => 2,
        'marzo' => 3,
        'mars' => 3,
        'march' => 3,
        'marz' => 3,
        'märz' => 3,
        'aprile' => 4,
        'avril' => 4,
        'april' => 4,
        'maggio' => 5,
        'mai' => 5,
        'may' => 5,
        'guigno' => 6,
        'juin' => 6,
        'june' => 6,
        'juni' => 6,
        'luglio' => 7,
        'juillet' => 7,
        'july' => 7,
        'juli' => 7,
        'agosto' => 8,
        'aout' => 8,
        'août' => 8,
        'august' => 8,
        'settembre' => 9,
        'septembre' => 9,
        'september' => 9,
        'ottobre' => 10,
        'octobre' => 10,
        'october' => 10,
        'oktober' => 10,
        'novembre' => 11,
        'november' => 11,
        'dicembre' => 12,
        'decembre' => 12,
        'décembre' => 12,
        'dezember' => 12,
        'december' => 12
    ];
    // never fails. if it can't parse, give now date
    public static function parse ($txt) {
        $regexp = '/^\s*([0-9]+)\s*(?:$|[\.\/\-]?\s*([0-9]+|[a-zäéû]+)\s*(?:$|[\.\/\-]?\s*([0-9]+)?\s*))$/i';
        $date = new \DateTime();

        if (preg_match($regexp, $txt, $matches)) {
            $year = -1;
            $month = -1;
            $day = -1;

            for($i = 1; $i < count($matches); $i++) {
                if (is_numeric($matches[$i])) {
                    /* month can be only two chars length */
                    if (strlen($matches[$i]) > 2) {
                        $year = intval($matches[$i]);
                    } else {
                        /* two char length, first found is day */
                        if ($day === -1) {
                            $day = intval($matches[$i]);
                        } else if($month === -1) {
                            $month = intval($matches[$i]);
                        } else {
                            $y = intval($date->format('y'));
                            $cent = intval($date->format('Y')) - $y;
                            /* two chars year bigger than current year is last century year */
                            if (intval($matches[$i]) > $y) {
                                $year = ($cent - 100) + intval($matches[$i]);
                            } else {
                                $year = $cent + intval($matches[$i]);
                            }
                        }
                    }
                } else {
                    $m = strtolower($matches[$i]);
                    foreach (self::eumonths as $k => $v) {
                        if ($m === $k) {
                            $month = self::eumonths[$k];
                            break;
                        } else {
                            if (strpos($k, $m, 0) === 0) {
                                $month = self::eumonths[$k];
                                break;
                            }
                        }
                    }
                    /* still no month found */
                    if ($month === -1) {
                        $max = [1, 0];
                        foreach (self::eumonths as $k => $v) {
                            similar_text($m, $k, $perc);
                            if ($perc > $max[1]) {
                                $max[1] = $perc;
                                $max[0] = self::eumonths[$k];
                            }
                        }
                        $month = $max[0];
                    }
                }
            }

            if ($year === -1) {
                $year = intval($date->format('Y'));
            }
            if ($month === -1) {
                $month = intval($date->format('n'));
            }
            if ($day === -1) {
                $day = intval($date->format('j'));
            }

            if (!checkdate($month, $day, $year) || !$date->setDate($year, $month, $day)) {
                $date = new \DateTime();
            }
        }
        return $date;
    }
}
?>