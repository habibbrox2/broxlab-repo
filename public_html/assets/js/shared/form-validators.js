/**
 * Shared Form Validation Utilities
 * Consolidated from: auth/register.js, admin/modules/media-upload.js
 */

/**
 * Password strength requirements
 */
export const PASSWORD_REQUIREMENTS = {
    length: {
        pattern: /.{8,}/,
        label: 'At least 8 characters'
    },
    uppercase: {
        pattern: /[A-Z]/,
        label: 'One uppercase letter'
    },
    lowercase: {
        pattern: /[a-z]/,
        label: 'One lowercase letter'
    },
    number: {
        pattern: /[0-9]/,
        label: 'One number'
    },
    special: {
        pattern: /[!@#$%^&*\-_=+\[\]{};:'",.<>?\/\\|`~]/,
        label: 'One special character'
    }
};

/**
 * Allowed file MIME types by category
 */
export const ALLOWED_TYPES = {
    image: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    video: ['video/mp4', 'video/webm', 'video/quicktime'],
    audio: ['audio/mpeg', 'audio/wav', 'audio/ogg'],
    document: [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ]
};

/**
 * Check password against all requirements
 * @param {string} pwd - Password to check
 * @returns {Object} Map of requirement keys to boolean results
 */
export function checkPasswordRequirements(pwd) {
    const results = {};
    for (const [key, req] of Object.entries(PASSWORD_REQUIREMENTS)) {
        results[key] = req.pattern.test(pwd);
    }
    return results;
}

/**
 * Calculate password strength score
 * @param {string} pwd - Password to score
 * @returns {number} Strength score 0-5 (5 = all requirements met)
 */
export function getPasswordStrength(pwd) {
    if (!pwd) return 0;
    const requirements = checkPasswordRequirements(pwd);
    return Object.values(requirements).filter(Boolean).length;
}

/**
 * Validate password confirmation (if both passwords match)
 * Updates DOM elements with validation feedback
 * @param {Object} elements - DOM element references:
 *   - password: HTMLInputElement
 *   - confirmPassword: HTMLInputElement
 *   - confirmFeedback: HTMLElement (for messages)
 * @returns {boolean} True if passwords match or both empty
 */
export function validateConfirmation(elements) {
    if (!elements?.password || !elements?.confirmPassword) return true;

    const pwd = elements.password.value;
    const conf = elements.confirmPassword.value;

    if (!pwd && !conf) {
        if (elements.confirmFeedback) {
            elements.confirmFeedback.innerHTML = '';
        }
        elements.confirmPassword.classList.remove('is-valid', 'is-invalid');
        return true;
    }

    const isValid = pwd === conf;

    if (elements.confirmFeedback) {
        let html = '';
        if (pwd && conf) {
            if (isValid) {
                html = '<small class="text-success"><i class="bi bi-check-circle"></i> Passwords match</small>';
            } else {
                html = '<small class="text-danger"><i class="bi bi-x-circle"></i> Passwords do not match</small>';
            }
        }
        elements.confirmFeedback.innerHTML = html;
    }

    elements.confirmPassword.classList.toggle('is-valid', isValid);
    elements.confirmPassword.classList.toggle('is-invalid', pwd && conf && !isValid);

    return isValid || (!pwd && !conf);
}

/**
 * Validate uploaded file (type, size, extension)
 * @param {File} file - File object to validate
 * @param {Object} config - Configuration:
 *   - maxFileSize: number (bytes)
 *   - allowedMimes: string[] (MIME types)
 *   - blockedExtensions: string[] (file extensions to block)
 * @returns {Object} {valid: boolean, error?: string}
 */
export function validateFile(file, config = {}) {
    const {
        maxFileSize = 100 * 1024 * 1024, // 100MB default
        allowedMimes = [],
        blockedExtensions = ['php', 'phtml', 'exe', 'bat', 'sh', 'js', 'asp', 'jsp']
    } = config;

    if (!file) {
        return { valid: false, error: 'No file selected.' };
    }

    if (file.size === 0) {
        return { valid: false, error: 'File is empty (0 bytes).' };
    }

    if (file.size > maxFileSize) {
        const maxSizeMB = (maxFileSize / (1024 * 1024)).toFixed(2);
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        return {
            valid: false,
            error: `File size exceeds the allowed limit.<br><small>File size: ${fileSizeMB} MB | Max allowed: ${maxSizeMB} MB</small>`
        };
    }

    if (allowedMimes.length > 0 && !allowedMimes.includes(file.type)) {
        return {
            valid: false,
            error: `Invalid file type.<br><small>Selected: ${file.type}<br>Allowed: ${allowedMimes.join(', ')}</small>`
        };
    }

    const ext = file.name.split('.').pop().toLowerCase();
    if (blockedExtensions.includes(ext)) {
        return {
            valid: false,
            error: `Blocked file extension.<br><small>Blocked extension: .${ext}</small>`
        };
    }

    return { valid: true };
}

/**
 * Validate email format
 * @param {string} email - Email address to validate
 * @returns {boolean} True if valid email format
 */
export function validateEmail(email) {
    if (!email) return false;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}