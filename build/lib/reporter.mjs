#!/usr/bin/env node
/**
 * Report Generator Utilities
 * Common report formatting for build scripts
 */

import { Logger, formatSize, formatPercent, getStatusIcon } from './utils.mjs';

/**
 * Report builder class for consistent output
 */
export class Report {
    constructor(title) {
        this.title = title;
        this.sections = [];
        this.stats = {};
        this.errors = [];
        this.warnings = [];
    }

    /**
     * Add section to report
     */
    addSection(name, items = []) {
        this.sections.push({ name, items });
        return this;
    }

    /**
     * Add statistic
     */
    addStat(key, value) {
        this.stats[key] = value;
        return this;
    }

    /**
     * Add error
     */
    addError(message, details = null) {
        this.errors.push({ message, details });
        return this;
    }

    /**
     * Add warning
     */
    addWarning(message, details = null) {
        this.warnings.push({ message, details });
        return this;
    }

    /**
     * Print report header
     */
    printHeader() {
        console.log();
        Logger.section(this.title);
    }

    /**
     * Print all sections
     */
    printSections() {
        this.sections.forEach(({ name, items }) => {
            if (items.length === 0) return;
            console.log(`\n${getStatusIcon('folder')} ${name}:`);
            items.forEach(item => {
                this.printItem(item);
            });
        });
    }

    /**
     * Print single item
     */
    printItem(item) {
        if (typeof item === 'string') {
            console.log(`  ${item}`);
        } else if (typeof item === 'object') {
            const icon = item.icon || getStatusIcon(item.status || 'ok');
            const line = item.line || '';
            console.log(`  ${icon} ${item.name || item.text || ''} ${line}`.trim());
        }
    }

    /**
     * Print statistics section
     */
    printStats() {
        if (Object.keys(this.stats).length === 0) return;
        console.log('\n📊 Statistics:');
        Object.entries(this.stats).forEach(([key, value]) => {
            console.log(`  ${key}: ${value}`);
        });
    }

    /**
     * Print errors section
     */
    printErrors() {
        if (this.errors.length === 0) return;
        console.log('\n❌ Errors:');
        this.errors.forEach(({ message, details }) => {
            console.log(`  ${message}`);
            if (details) {
                console.log(`    ${details}`);
            }
        });
    }

    /**
     * Print warnings section
     */
    printWarnings() {
        if (this.warnings.length === 0) return;
        console.log('\n⚠️  Warnings:');
        this.warnings.forEach(({ message, details }) => {
            console.log(`  ${message}`);
            if (details) {
                console.log(`    ${details}`);
            }
        });
    }

    /**
     * Print summary
     */
    printSummary() {
        const hasErrors = this.errors.length > 0;
        const hasWarnings = this.warnings.length > 0;

        console.log();

        if (hasErrors) {
            Logger.error(`Found ${this.errors.length} errors`);
            if (hasWarnings) {
                Logger.warning(`Found ${this.warnings.length} warnings`);
            }
            return 1;
        }

        if (hasWarnings) {
            Logger.warning(`Found ${this.warnings.length} warnings`);
            return 0;
        }

        Logger.success('All checks passed!');
        return 0;
    }

    /**
     * Print complete report
     */
    print() {
        this.printHeader();
        this.printSections();
        this.printStats();
        this.printErrors();
        this.printWarnings();
        return this.printSummary();
    }

    /**
     * Get exit code
     */
    getExitCode() {
        return this.errors.length > 0 ? 1 : (this.warnings.length > 0 ? 0 : 0);
    }
}

/**
 * File comparison report
 */
export class FileComparisonReport extends Report {
    constructor(title) {
        super(title);
        this.comparisons = [];
    }

    addComparison(file1, file2, size1, size2, difference) {
        this.comparisons.push({ file1, file2, size1, size2, difference });
        return this;
    }

    printComparisons() {
        if (this.comparisons.length === 0) return;
        console.log('\n📊 File Comparisons:');
        this.comparisons.forEach(({ file1, file2, size1, size2, difference }) => {
            const icon = difference > 0 ? '⬆️' : '⬇️';
            console.log(`  ${icon} ${file1} vs ${file2}`);
            console.log(`     Size: ${formatSize(size1)} → ${formatSize(size2)} (${difference > 0 ? '+' : ''}${formatSize(difference)})`);
        });
    }

    print() {
        this.printHeader();
        this.printSections();
        this.printComparisons();
        this.printStats();
        this.printErrors();
        this.printWarnings();
        return this.printSummary();
    }
}

/**
 * Performance report
 */
export class PerformanceReport extends Report {
    constructor(title) {
        super(title);
        this.timings = [];
    }

    addTiming(name, duration) {
        this.timings.push({ name, duration });
        return this;
    }

    printTimings() {
        if (this.timings.length === 0) return;
        console.log('\n⏱️  Performance:');
        this.timings.forEach(({ name, duration }) => {
            console.log(`  ${name}: ${duration.toFixed(2)}ms`);
        });
    }

    print() {
        this.printHeader();
        this.printSections();
        this.printStats();
        this.printTimings();
        this.printErrors();
        this.printWarnings();
        return this.printSummary();
    }
}

/**
 * Budget check report
 */
export class BudgetReport extends Report {
    constructor(title) {
        super(title);
        this.budgetItems = [];
    }

    addBudgetItem(name, size, budget, status = 'ok') {
        const hasBudget = typeof budget === 'number' && budget > 0;
        const percent = hasBudget ? (size / budget) * 100 : null;
        this.budgetItems.push({ name, size, budget, percent, status, hasBudget });
        return this;
    }

    printBudgetItems() {
        if (this.budgetItems.length === 0) return;
        console.log('\n💾 Budget Analysis:');
        this.budgetItems.forEach(({ name, size, budget, percent, status }) => {
            const icon = getStatusIcon(status);
            console.log(`  ${icon} ${name}`);
            if (budget == null) {
                console.log(`     ${formatSize(size)} / No budget set`);
                return;
            }

            const percentStr = formatPercent(percent / 100);
            console.log(`     ${formatSize(size)} / ${formatSize(budget)} (${percentStr})`);
        });
    }

    print() {
        this.printHeader();
        this.printSections();
        this.printBudgetItems();
        this.printStats();
        this.printErrors();
        this.printWarnings();
        return this.printSummary();
    }
}
