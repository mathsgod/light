<?php

namespace Light;

class Util
{
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
