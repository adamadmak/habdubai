<?php

namespace App\Support;

use Composer\CaBundle\CaBundle;

/**
 * The certificate authorities outgoing HTTPS is verified against.
 *
 * Some PHP builds (the Windows one from winget, for example) ship with no CA
 * list configured, so every HTTPS call fails with "self-signed certificate in
 * certificate chain". composer/ca-bundle finds the system's list (php.ini,
 * SSL_CERT_FILE, /etc/ssl/...) and falls back to Mozilla's bundle. An
 * operator can still point HTTP_CA_BUNDLE at a file of their own.
 */
final class TrustedCas
{
    private static ?string $path = null;

    public static function path(): string
    {
        if (self::$path !== null) {
            return self::$path;
        }

        $override = trim((string) (config('services.http.ca_bundle') ?: config('services.telegram.ca_bundle')));

        return self::$path = ($override !== '' && is_readable($override)) ? $override : CaBundle::getSystemCaRootBundlePath();
    }
}
