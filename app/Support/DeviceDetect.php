<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Detecção de dispositivo móvel — PWA apenas em telemóvel/tablet.
 */
class DeviceDetect
{
    public static function isMobileRequest(?Request $request = null): bool
    {
        $request = $request ?? request();
        if (! $request) {
            return false;
        }

        $ua = strtolower($request->userAgent() ?? '');

        if ($ua === '') {
            return false;
        }

        $mobilePatterns = [
            'android', 'iphone', 'ipod', 'ipad', 'windows phone', 'mobile',
            'webos', 'blackberry', 'opera mini', 'opera mobi', 'iemobile',
        ];

        foreach ($mobilePatterns as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function isTabletRequest(?Request $request = null): bool
    {
        $request = $request ?? request();
        $ua = strtolower($request->userAgent() ?? '');

        return str_contains($ua, 'ipad')
            || (str_contains($ua, 'android') && ! str_contains($ua, 'mobile'));
    }

    /** PWA: telemóvel e tablet; desktop fica versão web normal. */
    public static function suportaPwa(?Request $request = null): bool
    {
        return self::isMobileRequest($request);
    }
}
