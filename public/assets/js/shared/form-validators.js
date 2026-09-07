/**
 * Shared Form Validators
 * Validates files, passwords, and form inputs.
 */

// ── Password Validation ──

export const PASSWORD_REQUIREMENTS = {
  minLength: 8,
  requireUppercase: true,
  requireLowercase: true,
  requireNumber: true,
  requireSpecial: false,
};

/**
 * Check if a password meets all configured requirements.
 * @param {string} password
 * @returns {{ valid: boolean, requirements: Record<string, boolean> }}
 */
export function checkPasswordRequirements(password) {
  const p = String(password || '');
  const results = {};

  if (PASSWORD_REQUIREMENTS.minLength) {
    results.minLength = p.length >= PASSWORD_REQUIREMENTS.minLength;
  }
  if (PASSWORD_REQUIREMENTS.requireUppercase) {
    results.uppercase = /[A-Z]/.test(p);
  }
  if (PASSWORD_REQUIREMENTS.requireLowercase) {
    results.lowercase = /[a-z]/.test(p);
  }
  if (PASSWORD_REQUIREMENTS.requireNumber) {
    results.number = /[0-9]/.test(p);
  }
  if (PASSWORD_REQUIREMENTS.requireSpecial) {
    results.special = /[^A-Za-z0-9]/.test(p);
  }

  const valid = Object.values(results).every(Boolean);
  return { valid, requirements: results, };
}

/**
 * Estimate password strength on a 0-6 scale.
 * @param {string} password
 * @returns {{ level: 'weak'|'medium'|'strong', score: number }}
 */
export function getPasswordStrength(password) {
  const p = String(password || '');
  let score = 0;
  if (p.length >= 8) score++;
  if (p.length >= 12) score++;
  if (p.length >= 16) score++;
  if (/[A-Z]/.test(p)) score++;
  if (/[a-z]/.test(p)) score++;
  if (/[0-9]/.test(p)) score++;
  if (/[^A-Za-z0-9]/.test(p)) score++;

  if (score <= 2) return { level: 'weak', score, };
  if (score <= 4) return { level: 'medium', score, };
  return { level: 'strong', score, };
}

/**
 * Check if password and confirmation match.
 * @param {string} password
 * @param {string} confirmation
 * @returns {boolean}
 */
export function validateConfirmation(password, confirmation) {
  return String(password || '') === String(confirmation || '');
}

// ── File Validation ──

/**
 * Default allowed MIME types grouped by category.
 */
const FILE_TYPE_GROUPS = {
  image: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml', 'image/avif',],
  document: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',],
  spreadsheet: ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',],
  text: ['text/plain', 'text/csv', 'text/html', 'text/css', 'text/javascript',],
  video: ['video/mp4', 'video/webm', 'video/ogg',],
  audio: ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm',],
  archive: ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/gzip',],
};

/**
 * Common file extension → MIME type mapping for extension fallback validation.
 */
const EXTENSION_MIME_MAP = {
  '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png',
  '.webp': 'image/webp', '.gif': 'image/gif', '.svg': 'image/svg+xml', '.avif': 'image/avif',
  '.pdf': 'application/pdf', '.doc': 'application/msword',
  '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  '.xls': 'application/vnd.ms-excel',
  '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  '.txt': 'text/plain', '.csv': 'text/csv',
  '.mp4': 'video/mp4', '.webm': 'video/webm',
  '.mp3': 'audio/mpeg', '.wav': 'audio/wav',
  '.zip': 'application/zip', '.rar': 'application/x-rar-compressed', '.7z': 'application/x-7z-compressed',
};

/**
 * @typedef {Object} FileValidationOptions
 * @property {number}   [maxSize=10485760]       - Max file size in bytes (default 10MB)
 * @property {string[]} [allowedTypes]            - Explicit allowed MIME types (overrides groups)
 * @property {string[]} [allowedGroups]           - Type group names: 'image', 'document', etc.
 * @property {string[]} [allowedExtensions]       - Allowed file extensions (e.g. ['.jpg', '.png'])
 * @property {boolean}  [checkExtensionMatch=true]- Verify extension matches MIME type
 * @property {number}   [minSize=0]               - Minimum file size in bytes
 * @property {string}   [label='File']            - Human-readable name for error messages
 */

/** @type {FileValidationOptions} */
const DEFAULT_FILE_OPTIONS = {
  maxSize: 10 * 1024 * 1024,
  allowedTypes: null,
  allowedGroups: ['image', 'document',],
  allowedExtensions: null,
  checkExtensionMatch: true,
  minSize: 0,
  label: 'File',
};

/**
 * Resolve the effective list of allowed MIME types from options.
 * @param {FileValidationOptions} opts
 * @returns {string[]}
 */
function resolveAllowedTypes(opts) {
  if (opts.allowedTypes && opts.allowedTypes.length) return opts.allowedTypes;

  const types = [];
  const groups = opts.allowedGroups || [];
  for (const g of groups) {
    if (FILE_TYPE_GROUPS[g]) types.push(...FILE_TYPE_GROUPS[g]);
  }
  return [...new Set(types),];
}

/**
 * Get file extension (lowercased, with leading dot).
 * @param {string} filename
 * @returns {string}
 */
function getExtension(filename) {
  const idx = String(filename || '').lastIndexOf('.');
  return idx >= 0 ? String(filename).slice(idx).toLowerCase() : '';
}

/**
 * Validate a file against the given options.
 *
 * @param {File|null} file - The File object to validate
 * @param {FileValidationOptions} [options]
 * @returns {{ valid: boolean, error?: string, code?: string }}
 */
export function validateFile(file, options = {}) {
  const opts = { ...DEFAULT_FILE_OPTIONS, ...options, };

  if (!file) {
    return { valid: false, error: `${opts.label} is required`, code: 'NO_FILE', };
  }

  // Size checks
  if (opts.minSize > 0 && file.size < opts.minSize) {
    const minMB = (opts.minSize / (1024 * 1024)).toFixed(1);
    return { valid: false, error: `${opts.label} must be at least ${minMB}MB`, code: 'TOO_SMALL', };
  }
  if (opts.maxSize > 0 && file.size > opts.maxSize) {
    const maxMB = (opts.maxSize / (1024 * 1024)).toFixed(1);
    return { valid: false, error: `${opts.label} must be under ${maxMB}MB`, code: 'TOO_LARGE', };
  }

  // MIME type check
  const allowed = resolveAllowedTypes(opts);
  if (allowed.length > 0 && !allowed.includes(file.type)) {
    return { valid: false, error: `${opts.label} type "${file.type || 'unknown'}" is not allowed`, code: 'INVALID_TYPE', };
  }

  // Extension check
  const ext = getExtension(file.name);
  if (opts.allowedExtensions && opts.allowedExtensions.length) {
    if (!opts.allowedExtensions.includes(ext)) {
      return { valid: false, error: `${opts.label} extension "${ext}" is not allowed`, code: 'INVALID_EXTENSION', };
    }
  }

  // Cross-check extension against MIME type (detect spoofed uploads)
  if (opts.checkExtensionMatch && ext && file.type) {
    const expectedMime = EXTENSION_MIME_MAP[ext];
    if (expectedMime && expectedMime !== file.type) {
      return {
        valid: false,
        error: `${opts.label} extension "${ext}" does not match type "${file.type}"`,
        code: 'EXTENSION_MIME_MISMATCH',
      };
    }
  }

  return { valid: true, };
}

/**
 * Validate multiple files at once.
 * @param {FileList|File[]} files
 * @param {FileValidationOptions} [options]
 * @returns {{ valid: boolean, errors: Array<{ index: number, error: string, code: string }> }}
 */
export function validateFiles(files, options = {}) {
  const list = Array.from(files || []);
  const errors = [];

  for (let i = 0; i < list.length; i++) {
    const result = validateFile(list[i], options);
    if (!result.valid) {
      errors.push({ index: i, file: list[i].name, error: result.error, code: result.code, });
    }
  }

  return { valid: errors.length === 0, errors, };
}

/**
 * Format a file size in human-readable form.
 * @param {number} bytes
 * @returns {string}
 */
export function formatFileSize(bytes) {
  if (bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB',];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return `${(bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0)} ${units[i]}`;
}
