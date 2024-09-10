<?php

namespace Light;

class Util
{
    /*
    * Sanitize an array
    */
    static  function Sanitize(array $data)
    {

        $out = [];
        foreach ($data as $k => $v) {
            if($v === null){
                $out[$k] = null;
                continue;
            }
            if (is_array($v)) {
                $out[$k] = self::Sanitize($v);
            } else {
                $out[$k] = iconv('UTF-8', 'UTF-8//IGNORE', $v);
            }
        }
        return $out;
    }

    static function ParseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }

    static function Size($size, array $options = null)
    {

        $o = [
            'binary' => false,
            'decimalPlaces' => 2,
            'decimalSeparator' => '.',
            'thausandsSeparator' => '',
            'maxThreshold' => false, // or thresholds key
            'sufix' => [
                'thresholds' => ['', 'K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'],
                'decimal' => ' {threshold}B',
                'binary' => ' {threshold}iB'
            ]
        ];

        if ($options !== null)
            $o = array_replace_recursive($o, $options);

        $count = count($o['sufix']['thresholds']);
        $pow = $o['binary'] ? 1024 : 1000;

        for ($i = 0; $i < $count; $i++)

            if (($size < pow($pow, $i + 1)) ||
                ($i === $o['maxThreshold']) ||
                ($i === ($count - 1))
            )
                return

                    number_format(
                        $size / pow($pow, $i),
                        $o['decimalPlaces'],
                        $o['decimalSeparator'],
                        $o['thausandsSeparator']
                    ) .

                    str_replace(
                        '{threshold}',
                        $o['sufix']['thresholds'][$i],
                        $o['sufix'][$o['binary'] ? 'binary' : 'decimal']
                    );
    }
}
