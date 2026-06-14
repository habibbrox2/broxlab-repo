#!/usr/bin/env node
/**
 * Validation Utilities
 * Common validation patterns for build scripts
 */

/**
 * Validate naming pattern
 * @param {string} name - Name to validate
 * @param {RegExp} pattern - Pattern to match
 * @returns {object} Validation result
 */
export function validateNaming(name, pattern) {
    const isValid = pattern.test(name);
    return {
        isValid,
        name,
        pattern: pattern.toString(),
    };
}

/**
 * Validate file size against budget
 * @param {number} size - Actual size
 * @param {number} budget - Budget limit
 * @param {number} warningThreshold - Warning threshold (0-1)
 * @returns {object} Validation result
 */
export function validateBudget(size, budget, warningThreshold = 0.8) {
    const percent = size / budget;
    let status = 'ok';

    if (percent > 0.95) {
        status = 'error';
    } else if (percent > warningThreshold) {
        status = 'warning';
    }

    return {
        size,
        budget,
        percent,
        status,
        message: `${(percent * 100).toFixed(1)}% of budget`,
    };
}

/**
 * Validate path against ignore lists
 * @param {string} path - Path to validate
 * @param {array} ignorePatterns - Patterns to ignore
 * @returns {object} Validation result
 */
export function validatePath(path, ignorePatterns = []) {
    const shouldIgnore = ignorePatterns.some(pattern => {
        if (typeof pattern === 'string') {
            return path.includes(pattern);
        }
        return pattern.test(path);
    });

    return {
        path,
        shouldIgnore,
        isValid: !shouldIgnore,
    };
}

/**
 * Validate configuration object
 * @param {object} config - Configuration to validate
 * @param {object} schema - Validation schema
 * @returns {object} Validation result
 */
export function validateConfig(config, schema) {
    const errors = [];
    const warnings = [];

    Object.entries(schema).forEach(([key, validation]) => {
        const value = config[key];

        // Check required
        if (validation.required && value === undefined) {
            errors.push(`Missing required config: ${key}`);
            return;
        }

        // Check type
        if (value !== undefined && validation.type) {
            const valueType = Array.isArray(value) ? 'array' : typeof value;
            if (valueType !== validation.type) {
                errors.push(`Invalid type for ${key}: expected ${validation.type}, got ${valueType}`);
            }
        }

        // Custom validation
        if (validation.validate && value !== undefined) {
            const result = validation.validate(value);
            if (result !== true) {
                errors.push(`Validation failed for ${key}: ${result}`);
            }
        }
    });

    return {
        isValid: errors.length === 0,
        errors,
        warnings,
    };
}

/**
 * Validate file exists
 * @param {string} path - Path to check
 * @param {object} fs - File system module
 * @returns {object} Validation result
 */
export function validateFileExists(path, fs) {
    const exists = fs.existsSync(path);
    return {
        path,
        exists,
        isValid: exists,
        message: exists ? 'File exists' : 'File not found',
    };
}

/**
 * Validate directory exists
 * @param {string} path - Path to check
 * @param {object} fs - File system module
 * @returns {object} Validation result
 */
export function validateDirExists(path, fs) {
    try {
        const stat = fs.statSync(path);
        const isDir = stat.isDirectory();
        return {
            path,
            exists: true,
            isDirectory: isDir,
            isValid: isDir,
            message: isDir ? 'Directory exists' : 'Path is not a directory',
        };
    } catch (error) {
        return {
            path,
            exists: false,
            isDirectory: false,
            isValid: false,
            message: 'Directory not found',
        };
    }
}

/**
 * Batch validate files
 * @param {array} files - Files to validate
 * @param {Function} validationFn - Validation function
 * @returns {object} Batch result
 */
export function batchValidate(files, validationFn) {
    const results = {
        total: files.length,
        valid: 0,
        invalid: 0,
        errors: [],
        items: [],
    };

    files.forEach(file => {
        const result = validationFn(file);
        results.items.push(result);

        if (result.isValid) {
            results.valid++;
        } else {
            results.invalid++;
            if (result.error) {
                results.errors.push(result.error);
            }
        }
    });

    return results;
}

/**
 * Create validation schema builder
 */
export class SchemaBuilder {
    constructor() {
        this.schema = {};
    }

    required(key, type) {
        this.schema[key] = { required: true, type };
        return this;
    }

    optional(key, type) {
        this.schema[key] = { required: false, type };
        return this;
    }

    withValidator(key, validatorFn) {
        if (!this.schema[key]) {
            this.schema[key] = {};
        }
        this.schema[key].validate = validatorFn;
        return this;
    }

    build() {
        return this.schema;
    }
}
