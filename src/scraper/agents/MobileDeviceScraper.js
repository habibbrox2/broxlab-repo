/**
 * MobileDeviceScraper Agent (GSMArena-like spec pages)
 * Fetches device spec pages and extracts structured mobile data for DB insertion.
 */

import HttpClient from '../utils/HttpClient.js';
import HtmlParser from '../utils/HtmlParser.js';
import Logger from '../utils/Logger.js';

class MobileDeviceScraper {
    constructor() { }

    async scrapeDevice(url) {
        const targetUrl = String(url || '').trim();
        if (!targetUrl) {
            return { success: false, error: 'missing_url', data: null };
        }

        Logger.info(`Scraping device specs: ${targetUrl}`);

        const result = await HttpClient.fetchHtml(targetUrl);
        if (!result.success) {
            Logger.error('Failed to fetch device page', { url: targetUrl, error: result.error });
            return { success: false, error: result.error, data: null };
        }

        const $ = HtmlParser.parse(result.html);
        if (!$) {
            return { success: false, error: 'parse_error', data: null };
        }

        const isBdLayout = this.isGsmaBdLayout($);
        const data = isBdLayout
            ? this.parseGsmaBdDevice($, targetUrl)
            : this.parseGsmaComDevice($, targetUrl);

        const missing = [];
        if (!data?.brand_name) missing.push('brand_name');
        if (!data?.model_name) missing.push('model_name');
        if (!data?.release_date) missing.push('release_date');
        if (missing.length) {
            return { success: false, error: `missing_${missing.join('_')}`, data };
        }

        return { success: true, data, html: result.html };
    }

    isGsmaBdLayout($dom) {
        // gsmarena.com.bd pages use `h1.ptitle` + `.table_specs` blocks.
        if ($dom('h1.ptitle').length > 0) return true;
        if ($dom('table.table_specs td.specs_name, table.table_specs td.specs_name2').length > 4) return true;
        return false;
    }

    parseGsmaComDevice($dom, sourceUrl) {
        const fullName = this.extractFullName($dom);
        const { brand_name, model_name } = this.splitBrandModel(fullName);
        const image_url = this.extractImageUrl($dom);

        const sections = this.extractSpecsSections($dom);
        const release_date = this.extractReleaseDate($dom, sections);
        const status = this.extractStatus($dom, sections);

        const specs = this.buildSimplifiedSpecs(sections, sourceUrl);

        return {
            url: sourceUrl,
            fullName,
            brand_name,
            model_name,
            image_url,
            release_date,
            status,
            official_price: 0,
            unofficial_price: 0,
            is_official: status === 'official' ? 1 : 0,
            specifications: specs
        };
    }

    parseGsmaBdDevice($dom, sourceUrl) {
        const fullName = this.extractBdFullName($dom);
        const image_url = this.extractBdImageUrl($dom);

        const general = this.extractBdGeneralTable($dom);
        const sections = this.extractBdSpecsSections($dom);

        const brandFromTable = (general.Brand || '').trim();
        const modelFromTable = (general.Model || '').trim();
        const priceText = (general.Price || '').trim();

        const brand_name = brandFromTable || this.splitBrandModel(fullName).brand_name;
        const model_name = modelFromTable || this.stripBrandFromFullName(fullName, brand_name);

        const release_date = this.extractBdReleaseDate(sections);
        const status = this.extractBdStatus(sections, general);

        const parsedPrice = this.parseBdPriceToNumber(priceText) || 0;
        const is_official = status === 'official' ? 1 : 0;
        const official_price = is_official ? parsedPrice : 0;
        const unofficial_price = !is_official ? parsedPrice : 0;

        const specs = this.buildSimplifiedSpecsBd({ general, sections }, sourceUrl, status);

        return {
            url: sourceUrl,
            fullName,
            brand_name: String(brand_name || '').trim(),
            model_name: String(model_name || '').trim(),
            image_url,
            release_date,
            status,
            official_price,
            unofficial_price,
            is_official,
            specifications: specs
        };
    }

    extractFullName($dom) {
        const name =
            ($dom('h1.specs-phone-name-title[data-spec="modelname"]').first().text() || '').trim()
            || ($dom('h1[data-spec="modelname"]').first().text() || '').trim()
            || ($dom('h1').first().text() || '').trim();
        return name.replace(/\s+/g, ' ').trim();
    }

    extractBdFullName($dom) {
        const name = ($dom('h1.ptitle').first().text() || '').trim()
            || ($dom('h1').first().text() || '').trim();
        return String(name || '').replace(/\s+/g, ' ').trim();
    }

    splitBrandModel(fullName) {
        const cleaned = String(fullName || '').replace(/\s+/g, ' ').trim();
        if (!cleaned) return { brand_name: '', model_name: '' };
        const parts = cleaned.split(' ');
        if (parts.length === 1) return { brand_name: parts[0], model_name: parts[0] };
        return { brand_name: parts[0], model_name: parts.slice(1).join(' ') };
    }

    stripBrandFromFullName(fullName, brandName) {
        const full = String(fullName || '').replace(/\s+/g, ' ').trim();
        const brand = String(brandName || '').replace(/\s+/g, ' ').trim();
        if (!full) return '';
        if (!brand) return full;
        const re = new RegExp('^' + brand.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s+', 'i');
        const out = full.replace(re, '').trim();
        return out || full;
    }

    extractImageUrl($dom) {
        let src = ($dom('.specs-photo-main img').first().attr('src') || '').trim();
        if (!src) src = ($dom('meta[property="og:image"]').first().attr('content') || '').trim();
        if (!src) return '';
        if (src.startsWith('//')) return 'https:' + src;
        return src;
    }

    extractBdImageUrl($dom) {
        let src = ($dom('a[href*="/pictures/"] img.img-responsive').first().attr('src') || '').trim();
        if (!src) src = ($dom('.col-md-9 .row img.img-responsive').first().attr('src') || '').trim();
        if (!src) src = ($dom('meta[property="og:image"]').first().attr('content') || '').trim();
        if (!src) return '';
        if (src.startsWith('//')) return 'https:' + src;
        return src;
    }

    extractSpecsSections($dom) {
        const sections = {};
        let current = '';

        const clean = (s) => String(s || '').replace(/\s+/g, ' ').trim();

        $dom('#specs-list table').each((_, table) => {
            const $table = $dom(table);
            $table.find('tr').each((__, tr) => {
                const $tr = $dom(tr);
                const $th = $tr.find('th').first();
                if ($th.length) {
                    current = clean($th.text());
                }

                const ttl = clean($tr.find('td.ttl').first().text());
                const nfo = clean($tr.find('td.nfo').first().text());
                if (!ttl || !nfo) return;

                if (!sections[current]) sections[current] = {};
                sections[current][ttl] = nfo;
            });
        });

        return sections;
    }

    extractBdGeneralTable($dom) {
        const out = {};
        const clean = (s) => String(s || '').replace(/\s+/g, ' ').trim();

        // First `table.table_specs` usually contains Name/Model/Price/Brand/Category.
        const $table = $dom('table.table_specs').first();
        if (!$table || $table.length === 0) return out;

        $table.find('tr').each((_, tr) => {
            const $tr = $dom(tr);
            const key = clean($tr.find('td.specs_name').first().text());
            const val = clean($tr.find('td.specs_name2').first().text());
            if (!key || !val) return;
            out[key] = val;
        });

        return out;
    }

    extractBdSpecsSections($dom) {
        const sections = {};
        const clean = (s) => String(s || '').replace(/\s+/g, ' ').trim();

        $dom('div.specs_heading').each((_, headingEl) => {
            const sectionName = clean($dom(headingEl).text());
            if (!sectionName) return;

            const $table = $dom(headingEl).nextAll('table.table_specs').first();
            if (!$table || $table.length === 0) return;

            if (!sections[sectionName]) sections[sectionName] = {};

            $table.find('tr').each((__, tr) => {
                const $tr = $dom(tr);
                const key = clean($tr.find('td').first().text());
                const val = clean($tr.find('td').last().text());
                if (!key || !val) return;
                sections[sectionName][key] = val;
            });
        });

        return sections;
    }

    extractReleaseDate($dom, sections) {
        const textCandidates = [];
        const releasedHl = ($dom('span[data-spec="released-hl"]').first().text() || '').trim();
        if (releasedHl) textCandidates.push(releasedHl);

        const launchStatus = sections?.Launch?.Status;
        const announced = sections?.Launch?.Announced;
        if (launchStatus) textCandidates.push(launchStatus);
        if (announced) textCandidates.push(announced);

        for (const raw of textCandidates) {
            const parsed = this.parseGsmaDateToYmd(raw);
            if (parsed) return parsed;
        }

        return '';
    }

    extractStatus($dom, sections) {
        const candidates = [];
        const releasedHl = ($dom('span[data-spec="released-hl"]').first().text() || '').trim();
        if (releasedHl) candidates.push(releasedHl);
        if (sections?.Launch?.Status) candidates.push(sections.Launch.Status);
        if (sections?.Launch?.Announced) candidates.push(sections.Launch.Announced);

        const blob = candidates.join(' ').toLowerCase();
        // Treat only "available/released/official" as official. "announced"/"coming soon" should remain unofficial.
        if (/(available|released|official)/i.test(blob)) {
            return 'official';
        }

        return 'unofficial';
    }

    extractBdReleaseDate(sections) {
        const candidates = [];
        const launchDate = sections?.Launch?.['Launch Date'] || '';
        const launchAnnouncement = sections?.Launch?.['Launch Announcement'] || '';
        if (launchDate) candidates.push(launchDate);
        if (launchAnnouncement) candidates.push(launchAnnouncement);

        for (const raw of candidates) {
            const parsed = this.parseGsmaDateToYmd(raw);
            if (parsed) return parsed;
        }

        return '';
    }

    extractBdStatus(sections, general) {
        const candidates = [];
        const statusText = sections?.Launch?.['Launch Date'] || '';
        if (statusText) candidates.push(statusText);
        if (sections?.Launch?.['Launch Announcement']) candidates.push(sections.Launch['Launch Announcement']);
        if (general?.Price) candidates.push(general.Price);

        const blob = candidates.join(' ').toLowerCase();

        if (/(available|released|official)/i.test(blob)) return 'official';
        if (/(upcoming|coming soon|rumor|rumour|rumored|rumoured|unofficial)/i.test(blob)) return 'unofficial';

        return 'unofficial';
    }

    parseBdPriceToNumber(raw) {
        const s = String(raw || '').trim();
        if (!s) return 0;
        const m = s.match(/(\d[\d,]*)(\.\d+)?/);
        if (!m) return 0;
        const num = Number(String(m[0]).replace(/,/g, ''));
        if (!Number.isFinite(num) || num <= 0) return 0;
        // Store as integer BDT.
        return Math.round(num);
    }

    parseGsmaDateToYmd(raw) {
        const s0 = String(raw || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
        if (!s0) return '';

        // Normalize common prefixes
        let s = s0;
        for (let i = 0; i < 4; i++) {
            const next = s
                .replace(/^(coming soon|exp\.\s*release|released|available|announced)\.?\s*/i, '')
                .trim();
            if (next === s) break;
            s = next;
        }

        const monthMap = {
            january: 1,
            february: 2,
            march: 3,
            april: 4,
            may: 5,
            june: 6,
            july: 7,
            august: 8,
            september: 9,
            october: 10,
            november: 11,
            december: 12
        };

        // Pattern: 2026, March 21 (or March 2026)
        let m = s.match(/(\d{4})\s*,\s*([A-Za-z]+)\s*(\d{1,2})?/);
        if (m) {
            const year = Number(m[1]);
            const month = monthMap[String(m[2] || '').toLowerCase()] || 0;
            const day = m[3] ? Number(m[3]) : 1;
            if (year && month && day) return this.toYmd(year, month, day);
        }

        // Pattern: 20 March 2026
        m = s.match(/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/);
        if (m) {
            const day = Number(m[1]);
            const month = monthMap[String(m[2] || '').toLowerCase()] || 0;
            const year = Number(m[3]);
            if (year && month && day) return this.toYmd(year, month, day);
        }

        // Fallback: try Date parsing
        try {
            const d = new Date(s);
            if (!Number.isNaN(d.getTime())) {
                return this.toYmd(d.getUTCFullYear(), d.getUTCMonth() + 1, d.getUTCDate());
            }
        } catch { }

        return '';
    }

    toYmd(year, month, day) {
        const y = String(year).padStart(4, '0');
        const m = String(month).padStart(2, '0');
        const d = String(day).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    buildSimplifiedSpecs(sections, sourceUrl) {
        const get = (section, label) => {
            const v = sections?.[section]?.[label];
            return v ? String(v).trim() : '';
        };

        const join = (parts) => parts.filter(Boolean).join(' ');

        const body = join([
            get('Body', 'Dimensions') ? `Dimensions: ${get('Body', 'Dimensions')}.` : '',
            get('Body', 'Weight') ? `Weight: ${get('Body', 'Weight')}.` : '',
            get('Body', 'Build') ? `Build: ${get('Body', 'Build')}.` : '',
            get('Body', 'SIM') ? `SIM: ${get('Body', 'SIM')}.` : ''
        ]);

        const display = join([
            get('Display', 'Type') ? `Type: ${get('Display', 'Type')}.` : '',
            get('Display', 'Size') ? `Size: ${get('Display', 'Size')}.` : '',
            get('Display', 'Resolution') ? `Resolution: ${get('Display', 'Resolution')}.` : ''
        ]);

        const chipset = join([
            get('Platform', 'Chipset') ? `Chipset: ${get('Platform', 'Chipset')}.` : '',
            get('Platform', 'CPU') ? `CPU: ${get('Platform', 'CPU')}.` : '',
            get('Platform', 'GPU') ? `GPU: ${get('Platform', 'GPU')}.` : ''
        ]);

        const memory = join([
            get('Memory', 'Internal') ? `Internal: ${get('Memory', 'Internal')}.` : '',
            get('Memory', 'Card slot') ? `Card slot: ${get('Memory', 'Card slot')}.` : ''
        ]);

        const os = get('Platform', 'OS') ? `OS: ${get('Platform', 'OS')}.` : '';

        const rearCamera = join([
            get('Main Camera', 'Single') ? `Single: ${get('Main Camera', 'Single')}.` : '',
            get('Main Camera', 'Dual') ? `Dual: ${get('Main Camera', 'Dual')}.` : '',
            get('Main Camera', 'Triple') ? `Triple: ${get('Main Camera', 'Triple')}.` : '',
            get('Main Camera', 'Quad') ? `Quad: ${get('Main Camera', 'Quad')}.` : '',
            get('Main Camera', 'Features') ? `Features: ${get('Main Camera', 'Features')}.` : ''
        ]);

        const frontCamera = join([
            get('Selfie camera', 'Single') ? `Single: ${get('Selfie camera', 'Single')}.` : '',
            get('Selfie camera', 'Dual') ? `Dual: ${get('Selfie camera', 'Dual')}.` : '',
            get('Selfie camera', 'Features') ? `Features: ${get('Selfie camera', 'Features')}.` : ''
        ]);

        const videoCapture = join([
            get('Main Camera', 'Video') ? `Rear: ${get('Main Camera', 'Video')}.` : '',
            get('Selfie camera', 'Video') ? `Front: ${get('Selfie camera', 'Video')}.` : ''
        ]);

        const battery = join([
            get('Battery', 'Type') ? `Type: ${get('Battery', 'Type')}.` : '',
            get('Battery', 'Charging') ? `Charging: ${get('Battery', 'Charging')}.` : ''
        ]);

        const connectivity = join([
            get('Comms', 'WLAN') ? `WLAN: ${get('Comms', 'WLAN')}.` : '',
            get('Comms', 'Bluetooth') ? `Bluetooth: ${get('Comms', 'Bluetooth')}.` : '',
            get('Comms', 'Positioning') ? `Positioning: ${get('Comms', 'Positioning')}.` : '',
            get('Comms', 'NFC') ? `NFC: ${get('Comms', 'NFC')}.` : '',
            get('Comms', 'USB') ? `USB: ${get('Comms', 'USB')}.` : '',
            get('Comms', 'Radio') ? `Radio: ${get('Comms', 'Radio')}.` : ''
        ]);

        const misc = join([
            get('Features', 'Sensors') ? `Sensors: ${get('Features', 'Sensors')}.` : '',
            get('Misc', 'Colors') ? `Colors: ${get('Misc', 'Colors')}.` : '',
            get('Misc', 'Models') ? `Models: ${get('Misc', 'Models')}.` : '',
            get('Misc', 'Price') ? `Price: ${get('Misc', 'Price')}.` : '',
            get('Launch', 'Announced') ? `Announced: ${get('Launch', 'Announced')}.` : '',
            get('Launch', 'Status') ? `Status: ${get('Launch', 'Status')}.` : '',
            sourceUrl ? `Source URL: ${sourceUrl}.` : ''
        ]);

        const out = {};
        if (body) out['Body'] = body;
        if (display) out['Display'] = display;
        if (chipset) out['Chipset'] = chipset;
        if (memory) out['Memory'] = memory;
        if (os) out['OS/Software'] = os;
        if (rearCamera) out['Rear camera'] = rearCamera;
        if (frontCamera) out['Front camera'] = frontCamera;
        if (videoCapture) out['Video capture'] = videoCapture;
        if (battery) out['Battery'] = battery;
        if (connectivity) out['Connectivity'] = connectivity;
        if (misc) out['Misc'] = misc;

        return out;
    }

    buildSimplifiedSpecsBd(payload, sourceUrl, status) {
        const general = payload?.general || {};
        const sections = payload?.sections || {};

        const clean = (s) => String(s || '').replace(/\s+/g, ' ').trim();
        const get = (section, label) => clean(sections?.[section]?.[label]);
        const join = (parts) => parts.filter(Boolean).join(' ');

        const body = join([
            general?.Category ? `Category: ${clean(general.Category)}.` : '',
            get('Body', 'Body Dimensions') ? `Dimensions: ${get('Body', 'Body Dimensions')}.` : '',
            get('Body', 'Body Weight') ? `Weight: ${get('Body', 'Body Weight')}.` : '',
            get('Body', 'Build') ? `Build: ${get('Body', 'Build')}.` : '',
            get('Body', 'Network Sim') ? `SIM: ${get('Body', 'Network Sim')}.` : '',
            get('Body', 'Water Resistant') ? `Water Resistant: ${get('Body', 'Water Resistant')}.` : ''
        ]);

        const display = join([
            get('Display', 'Display Type') ? `Type: ${get('Display', 'Display Type')}.` : '',
            get('Display', 'Display Size') ? `Size: ${get('Display', 'Display Size')}.` : '',
            get('Display', 'Display Resolution') ? `Resolution: ${get('Display', 'Display Resolution')}.` : '',
            get('Display', 'Display Screen Protection') ? `Protection: ${get('Display', 'Display Screen Protection')}.` : '',
            get('Display', 'Display Density') ? `Density: ${get('Display', 'Display Density')}.` : ''
        ]);

        const chipset = join([
            get('Platform', 'Chipset') ? `Chipset: ${get('Platform', 'Chipset')}.` : '',
            get('Platform', 'CPU') ? `CPU: ${get('Platform', 'CPU')}.` : '',
            get('Platform', 'GPU') ? `GPU: ${get('Platform', 'GPU')}.` : ''
        ]);

        const memory = join([
            get('Memory', 'Memory Internal') ? `Internal: ${get('Memory', 'Memory Internal')}.` : '',
            get('Memory', 'Memory External') ? `Card slot: ${get('Memory', 'Memory External')}.` : '',
            get('Memory', 'Ram') ? `RAM: ${get('Memory', 'Ram')}.` : '',
            get('Memory', 'Memory Type') ? `Type: ${get('Memory', 'Memory Type')}.` : ''
        ]);

        const os = join([
            get('Platform', 'Operating System') ? `OS: ${get('Platform', 'Operating System')}.` : '',
            get('Platform', 'OS Version') ? `OS Version: ${get('Platform', 'OS Version')}.` : '',
            get('Platform', 'User Interface (ui)') ? `UI: ${get('Platform', 'User Interface (ui)')}.` : ''
        ]);

        const rearCamera = join([
            get('Camera', 'Primary Camera') ? `Primary: ${get('Camera', 'Primary Camera')}.` : '',
            get('Camera', 'Camera Features') ? `Features: ${get('Camera', 'Camera Features')}.` : ''
        ]);

        const frontCamera = join([
            get('Camera', 'Secondary Camera') ? `Secondary: ${get('Camera', 'Secondary Camera')}.` : ''
        ]);

        const videoCapture = join([
            get('Camera', 'Video') ? `Video: ${get('Camera', 'Video')}.` : ''
        ]);

        const battery = join([
            get('Battery', 'Battery Type') ? `Type: ${get('Battery', 'Battery Type')}.` : '',
            get('Battery', 'Battery Capacity') ? `Capacity: ${get('Battery', 'Battery Capacity')}.` : '',
            get('Battery', 'Charging') ? `Charging: ${get('Battery', 'Charging')}.` : ''
        ]);

        const connectivity = join([
            get('Network', 'Network Type') ? `Network: ${get('Network', 'Network Type')}.` : '',
            get('Network', 'Speed') ? `Speed: ${get('Network', 'Speed')}.` : '',
            get('Connectivity', 'WiFi') ? `WLAN: ${get('Connectivity', 'WiFi')}.` : '',
            get('Connectivity', 'Bluetooth') ? `Bluetooth: ${get('Connectivity', 'Bluetooth')}.` : '',
            get('Connectivity', 'GPS') ? `GPS: ${get('Connectivity', 'GPS')}.` : '',
            get('Connectivity', 'NFC') ? `NFC: ${get('Connectivity', 'NFC')}.` : '',
            get('Connectivity', 'USB') ? `USB: ${get('Connectivity', 'USB')}.` : '',
            get('Connectivity', 'Fm Radio') ? `Radio: ${get('Connectivity', 'Fm Radio')}.` : ''
        ]);

        const misc = join([
            general?.Brand ? `Brand: ${clean(general.Brand)}.` : '',
            general?.Model ? `Model: ${clean(general.Model)}.` : '',
            general?.Price ? `Price: ${clean(general.Price)}.` : '',
            get('Launch', 'Launch Announcement') ? `Announced: ${get('Launch', 'Launch Announcement')}.` : '',
            get('Launch', 'Launch Date') ? `Status: ${get('Launch', 'Launch Date')}.` : '',
            get('Features', 'Sensors') ? `Sensors: ${get('Features', 'Sensors')}.` : '',
            get('More', 'Body Color') ? `Colors: ${get('More', 'Body Color')}.` : '',
            get('More', 'Other Features') ? `Other: ${get('More', 'Other Features')}.` : '',
            status ? `Import status: ${status}.` : '',
            sourceUrl ? `Source URL: ${sourceUrl}.` : ''
        ]);

        const out = {};
        if (body) out['Body'] = body;
        if (display) out['Display'] = display;
        if (chipset) out['Chipset'] = chipset;
        if (memory) out['Memory'] = memory;
        if (os) out['OS/Software'] = os;
        if (rearCamera) out['Rear camera'] = rearCamera;
        if (frontCamera) out['Front camera'] = frontCamera;
        if (videoCapture) out['Video capture'] = videoCapture;
        if (battery) out['Battery'] = battery;
        if (connectivity) out['Connectivity'] = connectivity;
        if (misc) out['Misc'] = misc;
        return out;
    }
}

export default MobileDeviceScraper;
