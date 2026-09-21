<?php

namespace App\Support;

use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\QrCode;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Kharij record lookup + QR generation (port of the legacy KharijController
 * QR helpers and KharijModel::findByHash).
 *
 * The QR encodes a public verification URL that mimics the Bangladesh land
 * government site structure, preserving the subdomain the visitor used so
 * generated links stay on that subdomain (legacy parity).
 */
class KharijService
{
    /** Government-site path prefixes per domain variant (legacy parity). */
    protected const PATH_PREFIX = [
        'mutation' => '/mutation-land-gov-bd/',
        'dakhila' => '/ldtax-gov-bd/',
    ];

    protected const FALLBACK_HOST = [
        'mutation' => 'https://mutation-land.broxlab.online',
        'dakhila' => 'https://dakhila.broxlab.online',
    ];

    protected const APEX = 'broxlab.online';

    /**
     * Base URL for verification links: keep the visitor's subdomain when on
     * a *.broxlab.online host, else fall back to the domain's fixed host.
     */
    public function verificationBaseUrl(string $domain = 'mutation'): string
    {
        $host = (string) (request()->host());

        $isSubdomain = $host !== ''
            && preg_match('/\.'.preg_quote(self::APEX, '/').'$/i', $host)
            && ! preg_match('/^(www\.)?'.preg_quote(self::APEX, '/').'$/i', $host);

        if ($isSubdomain) {
            $scheme = request()->isSecure() ? 'https' : 'http';

            return $scheme.'://'.$host;
        }

        return self::FALLBACK_HOST[$domain] ?? self::FALLBACK_HOST['mutation'];
    }

    /** Full public verification URL for a hash. */
    public function verificationUrl(string $hash, string $path = 'qr-vk', string $domain = 'mutation'): string
    {
        $prefix = self::PATH_PREFIX[$domain] ?? self::PATH_PREFIX['mutation'];

        return $this->verificationBaseUrl($domain).$prefix.$path.'/'.$hash;
    }

    /**
     * QR code PNG as a data URI (legacy parity), or null on failure.
     */
    public function qrDataUri(string $hash, string $path = 'qr-vk', string $domain = 'mutation'): ?string
    {
        try {
            $url = $this->verificationUrl($hash, $path, $domain);

            $result = (new PngWriter())->write(
                new QrCode($url)
            );

            return 'data:image/png;base64,'.base64_encode($result->getString());
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Find a non-deleted record by hash (KharijModel::findByHash port).
     * Hash is sanitized to alphanumerics exactly like the legacy lookup.
     *
     * @return object|null{ id, hash, data: array, generated_by, created_at, updated_at }
     */
    public function findByHash(string $hash): ?object
    {
        // Legacy parity: strip everything to alphanumerics, then take the
        // longest alnum run — so "prefix<hash>' OR 1=1--" still resolves to
        // <hash> but pure junk ("'; DROP TABLE...") resolves to empty.
        preg_match_all('/[a-zA-Z0-9]+/', $hash, $runs);
        $runs = $runs[0] ?? [];

        if ($runs === []) {
            return null;
        }

        $hash = array_reduce($runs, fn ($a, $b) => strlen($b) > strlen($a) ? $b : $a, '');

        if ($hash === '') {
            return null;
        }

        $record = DB::table('kharij_records')
            ->where('hash', $hash)
            ->whereNull('deleted_at')
            ->select('id', 'hash', 'data_json', 'generated_by', 'created_at', 'updated_at')
            ->first();

        if ($record === null) {
            return null;
        }

        $record->data = json_decode((string) $record->data_json, true) ?? [];

        return $record;
    }
}
