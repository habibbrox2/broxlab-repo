# Node.js Build System Refactoring Guide

## Overview

The Node.js build system has been refactored to improve code quality, maintainability, and performance. The refactoring consolidates common utilities into reusable modules, eliminating code duplication across build scripts.

## New Shared Utilities

### 1. `build/lib/utils.mjs` - Core Utilities
General utilities used across all build scripts.

**Key Functions:**
- `formatSize(bytes)` - Format file sizes in human-readable format
- `Logger` - Consistent logging with emojis and formatting
- `parseArgs(args)` - Parse command-line arguments
- `exit(code, message)` - Exit with proper status and message
- `isDev()` / `isProd()` - Check environment

**Usage:**
```javascript
import { Logger, formatSize, exit } from '../lib/utils.mjs';

Logger.success('Build complete!');
Logger.info(`Size: ${formatSize(1024 * 1024)}`);
exit(0, 'Done');
```

### 2. `build/lib/fs-utils.mjs` - File System Operations
Consolidated file system utilities for consistent file handling.

**Key Functions:**
- `scanDirectory(dirPath, options)` - Recursively scan directories with filters
- `calculateFileHash(filePath, maxSize)` - Hash files efficiently
- `getFileInfo(filePath)` - Get detailed file information
- `groupByExtension(files)` - Group files by extension
- `getTotalSize(files)` - Calculate total size of files

**Usage:**
```javascript
import { scanDirectory, calculateFileHash } from '../lib/fs-utils.mjs';

const files = scanDirectory('public_html/assets/js', {
  extensions: ['.js'],
  ignoreDirs: ['node_modules'],
});

files.forEach(file => {
  const hash = calculateFileHash(file);
  console.log(`${file}: ${hash}`);
});
```

### 3. `build/lib/reporter.mjs` - Report Generation
Standardized report formatting across build scripts.

**Key Classes:**
- `Report` - Basic report builder
- `BudgetReport` - For budget checking
- `FileComparisonReport` - For comparing files
- `PerformanceReport` - For performance metrics

**Usage:**
```javascript
import { BudgetReport } from '../lib/reporter.mjs';

const report = new BudgetReport('Build Report');
report.addBudgetItem('bundle.js', 50000, 100000, 'warning');
report.addError('Size exceeds budget');
report.print();
```

### 4. `build/lib/validators.mjs` - Validation Utilities
Common validation patterns for configuration and files.

**Key Functions:**
- `validateNaming(name, pattern)` - Validate naming conventions
- `validateBudget(size, budget, threshold)` - Validate budget
- `validateConfig(config, schema)` - Validate configuration
- `batchValidate(files, validationFn)` - Batch validation

**Usage:**
```javascript
import { validateBudget, batchValidate } from '../lib/validators.mjs';

const result = validateBudget(55000, 100000, 0.8);
console.log(result.status); // 'warning'
```

### 5. `build/lib/build-config.mjs` - Build Configuration
Centralized build configuration management.

**Key Functions:**
- `getProjectDirs()` - Get project directory structure
- `getAppEntryPoints()` - Get app entry points
- `getCommonBuildOptions()` - Get standard esbuild options
- `createBuildContext()` - Create context for builds
- `createLoggingPlugin()` - esbuild logging plugin

**Usage:**
```javascript
import { createBuildContext, getCommonBuildOptions } from '../lib/build-config.mjs';

const context = createBuildContext();
const options = getCommonBuildOptions({ isDev: true });
```

## Refactored Scripts

### Before (Original)
- Duplicated code across multiple scripts
- Inconsistent formatting and output
- Mixed concerns (validation, file ops, reporting)
- Hard to maintain

### After (Refactored)
- Single source of truth for common operations
- Consistent, professional output
- Separated concerns with dedicated modules
- Easy to test and maintain

## Migration Guide

If you're adding a new build script, follow this pattern:

```javascript
#!/usr/bin/env node

import { Logger, exit, formatSize } from '../lib/utils.mjs';
import { scanDirectory } from '../lib/fs-utils.mjs';
import { Report } from '../lib/reporter.mjs';

class MyChecker {
  async run() {
    const report = new Report('My Report Title');
    
    try {
      // Do work
      Logger.info('Checking files...');
      
      // Add results
      report.addSection('Results', [...]);
      report.addStat('Total', '100');
      
      // Print and exit
      const exitCode = report.print();
      exit(exitCode);
    } catch (error) {
      Logger.error(`Fatal error: ${error.message}`);
      exit(1);
    }
  }
}

const checker = new MyChecker();
checker.run();
```

## Performance Improvements

1. **Shared Utilities** - Eliminates duplicated code
2. **Better Hashing** - Efficient file hashing with size optimization
3. **Streaming Operations** - Supports large file handling
4. **Caching Ready** - Infrastructure for hash caching
5. **Parallel Ready** - Design supports future parallelization

## Best Practices

### 1. Use Appropriate Modules
- Use `fs-utils.mjs` for file operations
- Use `utils.mjs` for logging and formatting
- Use `reporter.mjs` for output
- Use `validators.mjs` for validation
- Use `build-config.mjs` for build configuration

### 2. Consistent Logging
```javascript
Logger.heading('Starting task');
Logger.success('Task completed');
Logger.warning('Warning message');
Logger.error('Error message');
Logger.info('Info message');
```

### 3. Error Handling
```javascript
try {
  // Do work
} catch (error) {
  Logger.error(`Failed: ${error.message}`);
  exit(1);
}
```

### 4. Report Generation
```javascript
const report = new Report('Title');
report.addStat('key', 'value');
report.addSection('section', items);
report.addError('error message');
report.addWarning('warning message');
report.print();
```

## Future Improvements

1. **Add Caching** - Cache file hashes for faster subsequent runs
2. **Parallel Processing** - Use worker threads for large operations
3. **Database Integration** - Store metrics over time
4. **Advanced Analytics** - Generate trend reports
5. **Plugin System** - Allow custom validators and reporters

## File Structure

```
build/
├── lib/
│   ├── utils.mjs              # Core utilities
│   ├── fs-utils.mjs           # File system operations
│   ├── reporter.mjs           # Report generation
│   ├── validators.mjs         # Validation utilities
│   └── build-config.mjs       # Build configuration
├── Scripts/
│   ├── check-asset-duplicates.mjs
│   ├── check-dist-file-budget.mjs
│   ├── check-naming-conventions.mjs
│   ├── check-script-loading.mjs
│   └── check-firebase-dist-chunks.mjs
└── esbuild.config.js          # esbuild configuration
```

## Contributing

When adding new build scripts or utilities:

1. Use the appropriate existing utility module
2. If adding new shared functionality, add it to the appropriate module
3. Follow the existing code style and patterns
4. Add JSDoc comments to all functions
5. Test with both dev and production builds

## Support

For questions or issues with the refactored build system, refer to:
- `build/lib/utils.mjs` - Core functionality
- `build/lib/fs-utils.mjs` - File system operations
- Individual script comments - Specific implementations
