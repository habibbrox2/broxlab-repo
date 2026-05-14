import { access, readFile, stat } from 'fs/promises';
import { constants as fsConstants } from 'fs';
import { extname, resolve, sep } from 'path';

const projectRoot = resolve(process.cwd());
const blockedSegments = new Set(['.git', 'node_modules']);
const blockedFilenames = new Set([
    '.env',
    '.env.local',
    '.env.production',
    '.env.development',
    '.env.test',
]);

export type ResolvedLocalFile = {
    absolutePath: string;
    relativePath: string;
    sizeBytes: number;
    extension: string;
};

function normalizeInputPath(inputPath: string): string {
    const trimmed = inputPath.trim();
    if (trimmed.startsWith('file://')) {
        try {
            const parsed = new URL(trimmed);
            return decodeURIComponent(parsed.pathname || '');
        } catch {
            return trimmed;
        }
    }

    if (trimmed.startsWith('/uploads/')) {
        return `public_html${trimmed}`;
    }

    if (trimmed.startsWith('/')) {
        return trimmed.slice(1);
    }

    return trimmed;
}

function isWithinRoot(targetPath: string): boolean {
    return targetPath === projectRoot || targetPath.startsWith(`${projectRoot}${sep}`);
}

function validateResolvedPath(absolutePath: string): void {
    if (!isWithinRoot(absolutePath)) {
        throw new Error('File path is outside the project workspace');
    }

    const relative = absolutePath.slice(projectRoot.length).replace(/^[\\/]+/, '');
    const segments = relative.split(/[\\/]+/).filter(Boolean);

    for (const segment of segments) {
        if (blockedSegments.has(segment)) {
            throw new Error(`Access to '${segment}' is not allowed`);
        }
    }

    const filename = segments[segments.length - 1] || '';
    if (blockedFilenames.has(filename.toLowerCase())) {
        throw new Error(`Access to '${filename}' is not allowed`);
    }
}

export async function resolveLocalFile(inputPath: string): Promise<ResolvedLocalFile> {
    const normalized = normalizeInputPath(inputPath);
    if (!normalized) {
        throw new Error('File path is required');
    }

    const absolutePath = resolve(projectRoot, normalized);
    validateResolvedPath(absolutePath);

    await access(absolutePath, fsConstants.R_OK);
    const fileStats = await stat(absolutePath);
    if (!fileStats.isFile()) {
        throw new Error('Resolved path is not a file');
    }

    return {
        absolutePath,
        relativePath: absolutePath.slice(projectRoot.length).replace(/^[\\/]+/, ''),
        sizeBytes: fileStats.size,
        extension: extname(absolutePath).toLowerCase(),
    };
}

export async function readLocalFileBuffer(inputPath: string): Promise<ResolvedLocalFile & { buffer: Buffer }> {
    const resolved = await resolveLocalFile(inputPath);
    const buffer = await readFile(resolved.absolutePath);
    return {
        ...resolved,
        buffer,
    };
}

export function parseInlineBuffer(value: string): { buffer: Buffer; mimeType: string | null } {
    const trimmed = value.trim();
    const dataUrlMatch = trimmed.match(/^data:([^;]+);base64,(.+)$/i);
    if (dataUrlMatch) {
        return {
            mimeType: dataUrlMatch[1] || null,
            buffer: Buffer.from(dataUrlMatch[2], 'base64'),
        };
    }

    const cleaned = trimmed.replace(/\s+/g, '');
    if (!cleaned || !/^[a-zA-Z0-9+/=]+$/.test(cleaned)) {
        throw new Error('Inline image data must be a base64 string or data URL');
    }

    return {
        mimeType: null,
        buffer: Buffer.from(cleaned, 'base64'),
    };
}

export function formatBytes(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 Bytes';
    }

    const units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / Math.pow(1024, exponent);
    return `${value.toFixed(value >= 10 || exponent === 0 ? 0 : 2)} ${units[exponent]}`;
}
