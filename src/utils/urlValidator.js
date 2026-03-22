import dns from 'dns/promises';

const PRIVATE_IPV4_RANGES = [
    /^10\./,
    /^127\./,
    /^169\.254\./,
    /^192\.168\./,
    /^172\.(1[6-9]|2\d|3[0-1])\./,
    /^0\./
];

const PRIVATE_IPV6_PREFIXES = [
    /^::1$/,
    /^fc/i,
    /^fd/i,
    /^fe80/i
];

function isPrivateIpv4(ip) {
    return PRIVATE_IPV4_RANGES.some(rx => rx.test(ip));
}

function isPrivateIpv6(ip) {
    return PRIVATE_IPV6_PREFIXES.some(rx => rx.test(ip));
}

export async function validateUrl(inputUrl) {
    let url;
    try {
        url = new URL(inputUrl);
    } catch {
        return { ok: false, error: 'invalid_url' };
    }

    if (!['http:', 'https:'].includes(url.protocol)) {
        return { ok: false, error: 'invalid_protocol' };
    }

    if (!url.hostname || url.hostname === 'localhost') {
        return { ok: false, error: 'blocked_host' };
    }

    try {
        const results = await dns.lookup(url.hostname, { all: true });
        for (const res of results) {
            const address = res.address || '';
            if (address.includes(':')) {
                if (isPrivateIpv6(address)) {
                    return { ok: false, error: 'private_ip' };
                }
            } else if (isPrivateIpv4(address)) {
                return { ok: false, error: 'private_ip' };
            }
        }
    } catch {
        return { ok: false, error: 'dns_failed' };
    }

    return { ok: true };
}
