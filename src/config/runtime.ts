import { readFileSync } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

type PackageMetadata = {
    name?: string;
    version?: string;
};

function readPackageMetadata(): PackageMetadata {
    const currentDir = path.dirname(fileURLToPath(import.meta.url));
    const packagePath = path.resolve(currentDir, '../../package.json');

    try {
        return JSON.parse(readFileSync(packagePath, 'utf8')) as PackageMetadata;
    } catch {
        return {};
    }
}

const packageMetadata = readPackageMetadata();

export const runtime = {
    name: packageMetadata.name || 'BroxLab',
    version: packageMetadata.version || '0.0.0',
    nodeVersion: process.version,
    startedAt: new Date().toISOString(),
} as const;
