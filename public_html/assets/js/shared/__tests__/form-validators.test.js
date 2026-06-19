/**
 * Tests for shared/form-validators.js
 */

import { describe, it, expect } from 'vitest';
import {
  PASSWORD_REQUIREMENTS,
  checkPasswordRequirements,
  getPasswordStrength,
  validateConfirmation,
  validateFile,
  validateFiles,
  formatFileSize
} from '../form-validators.js';

function mockFile(name, size, type) {
  return { name, size, type, };
}

// ======================== PASSWORD_REQUIREMENTS ========================

describe('PASSWORD_REQUIREMENTS', () => {
  it('should have default configuration', () => {
    expect(PASSWORD_REQUIREMENTS.minLength).toBe(8);
    expect(PASSWORD_REQUIREMENTS.requireUppercase).toBe(true);
    expect(PASSWORD_REQUIREMENTS.requireLowercase).toBe(true);
    expect(PASSWORD_REQUIREMENTS.requireNumber).toBe(true);
    expect(PASSWORD_REQUIREMENTS.requireSpecial).toBe(false);
  });
});

// ======================== checkPasswordRequirements ========================

describe('checkPasswordRequirements', () => {
  it('should return valid for a strong password', () => {
    const r = checkPasswordRequirements('MyPass123');
    expect(r.valid).toBe(true);
    expect(r.requirements.minLength).toBe(true);
    expect(r.requirements.uppercase).toBe(true);
    expect(r.requirements.lowercase).toBe(true);
    expect(r.requirements.number).toBe(true);
  });

  it('should fail when password is too short', () => {
    const r = checkPasswordRequirements('Ab1');
    expect(r.valid).toBe(false);
    expect(r.requirements.minLength).toBe(false);
  });

  it('should fail when missing uppercase', () => {
    const r = checkPasswordRequirements('mypass123');
    expect(r.valid).toBe(false);
    expect(r.requirements.uppercase).toBe(false);
  });

  it('should fail when missing lowercase', () => {
    const r = checkPasswordRequirements('MYPASS123');
    expect(r.valid).toBe(false);
    expect(r.requirements.lowercase).toBe(false);
  });

  it('should fail when missing number', () => {
    const r = checkPasswordRequirements('MyPassword');
    expect(r.valid).toBe(false);
    expect(r.requirements.number).toBe(false);
  });

  it('should handle empty string', () => {
    expect(checkPasswordRequirements('').valid).toBe(false);
  });

  it('should handle null input', () => {
    expect(checkPasswordRequirements(null).valid).toBe(false);
  });
});

// ======================== getPasswordStrength ========================

describe('getPasswordStrength', () => {
  it('should return weak for short simple password', () => {
    const r = getPasswordStrength('abc');
    expect(r.level).toBe('weak');
    expect(r.score).toBeLessThanOrEqual(2);
  });

  it('should return medium for moderate password', () => {
    const r = getPasswordStrength('MyPass1');
    expect(r.level).toBe('medium');
    expect(r.score).toBeGreaterThanOrEqual(3);
    expect(r.score).toBeLessThanOrEqual(4);
  });

  it('should return strong for long complex password', () => {
    const r = getPasswordStrength('MyStr0ng!P@ssw0rd');
    expect(r.level).toBe('strong');
    expect(r.score).toBeGreaterThanOrEqual(5);
  });

  it('should give higher score for longer passwords', () => {
    const short = getPasswordStrength('Ab1');
    const long = getPasswordStrength('Ab123456789012345');
    expect(long.score).toBeGreaterThan(short.score);
  });

  it('should give score for special characters', () => {
    expect(getPasswordStrength('Ab1!').score).toBeGreaterThan(getPasswordStrength('Ab1').score);
  });

  it('should handle empty string', () => {
    const r = getPasswordStrength('');
    expect(r.level).toBe('weak');
    expect(r.score).toBe(0);
  });
});

// ======================== validateConfirmation ========================

describe('validateConfirmation', () => {
  it('should return true when passwords match', () => {
    expect(validateConfirmation('secret123', 'secret123')).toBe(true);
  });

  it('should return false when passwords do not match', () => {
    expect(validateConfirmation('secret123', 'different')).toBe(false);
  });

  it('should return true for empty strings matching', () => {
    expect(validateConfirmation('', '')).toBe(true);
  });

  it('should handle null inputs', () => {
    expect(validateConfirmation(null, null)).toBe(true);
  });

  it('should be case-sensitive', () => {
    expect(validateConfirmation('Password', 'password')).toBe(false);
  });
});

// ======================== validateFile ========================

describe('validateFile', () => {
  it('should reject null file', () => {
    const r = validateFile(null);
    expect(r.valid).toBe(false);
    expect(r.code).toBe('NO_FILE');
  });

  it('should accept a valid JPEG image', () => {
    expect(validateFile(mockFile('photo.jpg', 102400, 'image/jpeg')).valid).toBe(true);
  });

  it('should accept a valid PNG image', () => {
    expect(validateFile(mockFile('image.png', 102400, 'image/png')).valid).toBe(true);
  });

  it('should reject files exceeding maxSize', () => {
    const r = validateFile(mockFile('big.jpg', 20971520, 'image/jpeg'));
    expect(r.valid).toBe(false);
    expect(r.code).toBe('TOO_LARGE');
  });

  it('should reject files below minSize', () => {
    const r = validateFile(mockFile('tiny.jpg', 100, 'image/jpeg'), { minSize: 1024, });
    expect(r.valid).toBe(false);
    expect(r.code).toBe('TOO_SMALL');
  });

  it('should reject disallowed MIME types', () => {
    const r = validateFile(mockFile('script.exe', 1024, 'application/x-executable'));
    expect(r.valid).toBe(false);
    expect(r.code).toBe('INVALID_TYPE');
  });

  it('should accept PDF with default allowedGroups', () => {
    expect(validateFile(mockFile('doc.pdf', 512000, 'application/pdf')).valid).toBe(true);
  });

  it('should reject disallowed file extensions', () => {
    const r = validateFile(mockFile('script.exe', 1024, 'application/x-executable'), {
      allowedTypes: ['application/x-executable',],
      allowedExtensions: ['.jpg', '.png',],
    });
    expect(r.valid).toBe(false);
    expect(r.code).toBe('INVALID_EXTENSION');
  });

  it('should reject when extension does not match MIME type', () => {
    const r = validateFile(mockFile('malware.jpg', 1024, 'application/x-executable'), {
      allowedTypes: ['application/x-executable',],
    });
    expect(r.valid).toBe(false);
    expect(r.code).toBe('EXTENSION_MIME_MISMATCH');
  });

  it('should skip extension-MIME check when disabled', () => {
    const r = validateFile(mockFile('malware.jpg', 1024, 'application/x-executable'), {
      allowedTypes: ['application/x-executable',],
      checkExtensionMatch: false,
    });
    expect(r.valid).toBe(true);
  });

  it('should accept video when video group allowed', () => {
    expect(validateFile(mockFile('clip.mp4', 1048576, 'video/mp4'), { allowedGroups: ['video',], }).valid).toBe(true);
  });

  it('should reject video when only image group allowed', () => {
    const r = validateFile(mockFile('clip.mp4', 1048576, 'video/mp4'), { allowedGroups: ['image',], });
    expect(r.valid).toBe(false);
    expect(r.code).toBe('INVALID_TYPE');
  });

  it('should use custom label in error messages', () => {
    const r = validateFile(mockFile('big.jpg', 20971520, 'image/jpeg'), { label: 'Avatar', });
    expect(r.error).toContain('Avatar');
  });

  it('should accept WebP images', () => {
    expect(validateFile(mockFile('photo.webp', 51200, 'image/webp')).valid).toBe(true);
  });

  it('should reject oversized documents', () => {
    const r = validateFile(mockFile('huge.pdf', 52428800, 'application/pdf'));
    expect(r.valid).toBe(false);
    expect(r.code).toBe('TOO_LARGE');
  });
});

// ======================== validateFiles ========================

describe('validateFiles', () => {
  it('should return valid for empty list', () => {
    const r = validateFiles([]);
    expect(r.valid).toBe(true);
    expect(r.errors).toHaveLength(0);
  });

  it('should return valid for all valid files', () => {
    const r = validateFiles([mockFile('a.jpg', 1024, 'image/jpeg'), mockFile('b.png', 2048, 'image/png'),]);
    expect(r.valid).toBe(true);
    expect(r.errors).toHaveLength(0);
  });

  it('should return errors for invalid files', () => {
    const r = validateFiles([mockFile('a.jpg', 1024, 'image/jpeg'), mockFile('b.exe', 1024, 'application/x-executable'),]);
    expect(r.valid).toBe(false);
    expect(r.errors).toHaveLength(1);
    expect(r.errors[0].index).toBe(1);
    expect(r.errors[0].code).toBe('INVALID_TYPE');
  });

  it('should report errors for multiple invalid files', () => {
    const r = validateFiles([mockFile('a.exe', 1024, 'application/x-executable'), mockFile('b.exe', 1024, 'application/x-executable'),]);
    expect(r.valid).toBe(false);
    expect(r.errors).toHaveLength(2);
  });

  it('should pass options through', () => {
    const r = validateFiles([mockFile('a.jpg', 1024, 'image/jpeg'), mockFile('b.mp4', 1024, 'video/mp4'),], { allowedGroups: ['image',], });
    expect(r.valid).toBe(false);
    expect(r.errors).toHaveLength(1);
    expect(r.errors[0].file).toBe('b.mp4');
  });

  it('should handle null input', () => {
    const r = validateFiles(null);
    expect(r.valid).toBe(true);
    expect(r.errors).toHaveLength(0);
  });
});

// ======================== formatFileSize ========================

describe('formatFileSize', () => {
  it('should format 0 bytes', () => {
    expect(formatFileSize(0)).toBe('0 B');
  });

  it('should format bytes', () => {
    expect(formatFileSize(512)).toBe('512 B');
  });

  it('should format kilobytes', () => {
    expect(formatFileSize(1024)).toBe('1.0 KB');
    expect(formatFileSize(1536)).toBe('1.5 KB');
  });

  it('should format megabytes', () => {
    expect(formatFileSize(1048576)).toBe('1.0 MB');
  });

  it('should format gigabytes', () => {
    expect(formatFileSize(1073741824)).toBe('1.0 GB');
  });

  it('should format terabytes', () => {
    expect(formatFileSize(1099511627776)).toBe('1.0 TB');
  });

  it('should handle typical file sizes', () => {
    expect(formatFileSize(10485760)).toBe('10.0 MB');
    expect(formatFileSize(102400)).toBe('100.0 KB');
    expect(formatFileSize(1)).toBe('1 B');
  });
});
