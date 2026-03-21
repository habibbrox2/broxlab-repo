/**
 * EnvLoader
 * Lightweight .env loader (no dependencies).
 * Loads repo-root .env into process.env (only fills missing keys).
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

function parseEnvValue(raw) {
    let value = raw.trim();
    if (value === '') return '';

    const isQuoted =
        (value.startsWith('"') && value.endsWith('"')) ||
        (value.startsWith("'") && value.endsWith("'"));

    if (isQuoted) {
        value = value.slice(1, -1);
    }

    // Minimal escape handling for common sequences.
    value = value.replace(/\\n/g, '\n').replace(/\\r/g, '\r').replace(/\\t/g, '\t');

    return value;
}

function parseDotEnv(content) {
    const out = {};
    const lines = content.split(/\r?\n/);
    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;

        const eq = trimmed.indexOf('=');
        if (eq === -1) continue;

        const key = trimmed.slice(0, eq).trim();
        if (!key) continue;

        const rawVal = trimmed.slice(eq + 1);
        out[key] = parseEnvValue(rawVal);
    }
    return out;
}

class EnvLoader {
    /**
     * Load .env from repo root.
     * @param {{ envPath?: string, override?: boolean }} options
     */
    static load(options = {}) {
        const override = !!options.override;

        let envPath = options.envPath;
        if (!envPath) {
            const __filename = fileURLToPath(import.meta.url);
            const __dirname = path.dirname(__filename);
            const repoRoot = path.resolve(__dirname, '..', '..', '..');
            envPath = path.join(repoRoot, '.env');
        }

        if (!fs.existsSync(envPath)) {
            return { loaded: false, path: envPath, count: 0 };
        }

        try {
            const content = fs.readFileSync(envPath, 'utf8');
            const parsed = parseDotEnv(content);
            let count = 0;

            for (const [k, v] of Object.entries(parsed)) {
                if (!override && process.env[k] !== undefined && process.env[k] !== '') {
                    continue;
                }
                process.env[k] = v;
                count++;
            }

            return { loaded: true, path: envPath, count };
        } catch (e) {
            return { loaded: false, path: envPath, count: 0, error: e?.message || String(e) };
        }
    }
}

export default EnvLoader;
