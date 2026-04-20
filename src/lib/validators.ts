/**
 * Input Validation System
 * Provides consistent validation patterns across application
 */

import { ValidationError } from './error-handler';

/**
 * Validator interface
 */
export interface Validator<T> {
    validate(value: any): { valid: boolean; error?: string };
    transform?(value: any): T;
}

/**
 * String validator
 */
export const StringValidator = {
    required: (value: any, fieldName: string = 'Field'): string => {
        if (!value || (typeof value === 'string' && value.trim() === '')) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} is required`,
            });
        }
        return String(value).trim();
    },

    minLength: (value: string, min: number, fieldName: string = 'Field'): string => {
        if (value.length < min) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must be at least ${min} characters`,
            });
        }
        return value;
    },

    maxLength: (value: string, max: number, fieldName: string = 'Field'): string => {
        if (value.length > max) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must not exceed ${max} characters`,
            });
        }
        return value;
    },

    pattern: (value: string, pattern: RegExp, fieldName: string = 'Field'): string => {
        if (!pattern.test(value)) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} format is invalid`,
            });
        }
        return value;
    },

    email: (value: string, fieldName: string = 'Email'): string => {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return StringValidator.pattern(value, emailPattern, fieldName);
    },

    url: (value: string, fieldName: string = 'URL'): string => {
        try {
            new URL(value);
            return value;
        } catch {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} is not a valid URL`,
            });
        }
    },

    enum: (value: string, allowedValues: string[], fieldName: string = 'Field'): string => {
        if (!allowedValues.includes(value)) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must be one of: ${allowedValues.join(', ')}`,
            });
        }
        return value;
    },
};

/**
 * Number validator
 */
export const NumberValidator = {
    required: (value: any, fieldName: string = 'Field'): number => {
        const num = Number(value);
        if (isNaN(num)) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must be a valid number`,
            });
        }
        return num;
    },

    min: (value: number, min: number, fieldName: string = 'Field'): number => {
        if (value < min) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must be at least ${min}`,
            });
        }
        return value;
    },

    max: (value: number, max: number, fieldName: string = 'Field'): number => {
        if (value > max) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must not exceed ${max}`,
            });
        }
        return value;
    },

    range: (value: number, min: number, max: number, fieldName: string = 'Field'): number => {
        NumberValidator.min(value, min, fieldName);
        NumberValidator.max(value, max, fieldName);
        return value;
    },

    integer: (value: any, fieldName: string = 'Field'): number => {
        const num = NumberValidator.required(value, fieldName);
        if (!Number.isInteger(num)) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must be an integer`,
            });
        }
        return num;
    },

    positive: (value: number, fieldName: string = 'Field'): number => {
        return NumberValidator.min(value, 0, fieldName);
    },
};

/**
 * Array validator
 */
export const ArrayValidator = {
    required: (value: any, fieldName: string = 'Field'): any[] => {
        if (!Array.isArray(value)) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must be an array`,
            });
        }
        return value;
    },

    minLength: (value: any[], min: number, fieldName: string = 'Field'): any[] => {
        if (value.length < min) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must have at least ${min} items`,
            });
        }
        return value;
    },

    maxLength: (value: any[], max: number, fieldName: string = 'Field'): any[] => {
        if (value.length > max) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must not exceed ${max} items`,
            });
        }
        return value;
    },

    unique: (value: any[], fieldName: string = 'Field'): any[] => {
        const set = new Set(value);
        if (set.size !== value.length) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} contains duplicate items`,
            });
        }
        return value;
    },
};

/**
 * Object validator
 */
export const ObjectValidator = {
    required: (value: any, fieldName: string = 'Field'): Record<string, any> => {
        if (typeof value !== 'object' || value === null) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} must be an object`,
            });
        }
        return value;
    },

    keys: (value: Record<string, any>, requiredKeys: string[], fieldName: string = 'Field'): Record<string, any> => {
        const missingKeys = requiredKeys.filter((key) => !(key in value));
        if (missingKeys.length > 0) {
            throw new ValidationError('Validation failed', {
                [fieldName]: `${fieldName} missing required keys: ${missingKeys.join(', ')}`,
            });
        }
        return value;
    },
};

/**
 * Batch validation helper
 */
export function validateBatch<T extends Record<string, any>>(
    data: any,
    schema: Record<keyof T, (value: any) => any>
): T {
    const errors: Record<string, string> = {};
    const validated: any = {};

    for (const [key, validator] of Object.entries(schema)) {
        try {
            validated[key] = validator(data[key]);
        } catch (error) {
            if (error instanceof ValidationError) {
                Object.assign(errors, error.errors);
            } else {
                errors[key] = error instanceof Error ? error.message : String(error);
            }
        }
    }

    if (Object.keys(errors).length > 0) {
        throw new ValidationError('Validation failed', errors);
    }

    return validated as T;
}

/**
 * Create chainable validator
 */
export class ChainValidator {
    private value: any;
    private errors: Record<string, string> = {};
    private fieldName: string;

    constructor(value: any, fieldName: string = 'Field') {
        this.value = value;
        this.fieldName = fieldName;
    }

    required(): this {
        if (!this.value || (typeof this.value === 'string' && this.value.trim() === '')) {
            this.errors[this.fieldName] = `${this.fieldName} is required`;
        }
        return this;
    }

    string(minLen?: number, maxLen?: number): this {
        if (typeof this.value !== 'string') {
            this.errors[this.fieldName] = `${this.fieldName} must be a string`;
        } else {
            if (minLen && this.value.length < minLen) {
                this.errors[this.fieldName] = `${this.fieldName} must be at least ${minLen} characters`;
            }
            if (maxLen && this.value.length > maxLen) {
                this.errors[this.fieldName] = `${this.fieldName} must not exceed ${maxLen} characters`;
            }
        }
        return this;
    }

    number(min?: number, max?: number): this {
        const num = Number(this.value);
        if (isNaN(num)) {
            this.errors[this.fieldName] = `${this.fieldName} must be a valid number`;
        } else {
            if (min !== undefined && num < min) {
                this.errors[this.fieldName] = `${this.fieldName} must be at least ${min}`;
            }
            if (max !== undefined && num > max) {
                this.errors[this.fieldName] = `${this.fieldName} must not exceed ${max}`;
            }
        }
        return this;
    }

    email(): this {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(this.value)) {
            this.errors[this.fieldName] = `${this.fieldName} must be a valid email`;
        }
        return this;
    }

    url(): this {
        try {
            new URL(this.value);
        } catch {
            this.errors[this.fieldName] = `${this.fieldName} must be a valid URL`;
        }
        return this;
    }

    enum(allowedValues: string[]): this {
        if (!allowedValues.includes(this.value)) {
            this.errors[this.fieldName] = `${this.fieldName} must be one of: ${allowedValues.join(', ')}`;
        }
        return this;
    }

    custom(validator: (value: any) => boolean, message: string): this {
        if (!validator(this.value)) {
            this.errors[this.fieldName] = message;
        }
        return this;
    }

    validate(): this {
        if (Object.keys(this.errors).length > 0) {
            throw new ValidationError('Validation failed', this.errors);
        }
        return this;
    }

    getErrors(): Record<string, string> {
        return this.errors;
    }

    getValue(): any {
        return this.value;
    }
}

export default {
    StringValidator,
    NumberValidator,
    ArrayValidator,
    ObjectValidator,
    validateBatch,
    ChainValidator,
};
