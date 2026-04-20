# src/lib - Shared Application Library

Unified library for consistent patterns across the Node.js/TypeScript application server.

## 📦 Modules

### 1. `logger.ts` - Unified Logging System
Consolidates all logging into a single consistent interface.

**Key Features:**
- Debug, info, warn, error, fatal levels
- Child loggers with context
- Request/response logging helpers
- Service-specific logging

**Usage:**
```typescript
import { Logger } from '../lib/logger';

Logger.info('Server started');
Logger.error('Connection failed', error);
const serviceLogger = Logger.child({ service: 'ChatService' });
serviceLogger.info('Chat initialized');
```

### 2. `response.ts` - Response Formatting
Standardized API response format for all endpoints.

**Response Types:**
- `SuccessResponse<T>` - Successful API response
- `ErrorResponse` - Error response with details
- `PaginatedResponse<T>` - Paginated results

**Usage:**
```typescript
import { ResponseBuilder } from '../lib/response';

// Success response
ResponseBuilder.success(reply, { id: 1, name: 'John' });

// Error response
ResponseBuilder.error(reply, 'Invalid input', { statusCode: 400 });

// Paginated response
ResponseBuilder.paginated(reply, items, {
  page: 1,
  pageSize: 20,
  total: 100
});

// Specific errors
ResponseBuilder.unauthorized(reply);
ResponseBuilder.notFound(reply, 'User');
ResponseBuilder.internalError(reply, error);
```

### 3. `error-handler.ts` - Error Handling
Comprehensive error handling system with custom error classes.

**Error Classes:**
- `AppError` - Base error class
- `ValidationError` - Input validation errors
- `AuthenticationError` - Auth failures
- `AuthorizationError` - Permission denied
- `NotFoundError` - Resource not found
- `ConflictError` - Resource conflict
- `RateLimitError` - Rate limiting
- `ExternalServiceError` - Third-party service failures

**Utilities:**
- `safeExecute()` - Execute with error handling
- `retryWithBackoff()` - Retry with exponential backoff
- `formatError()` - Format error for response
- `assert()` - Assertion with error throwing
- `throwValidation/Auth/NotFound()` - Throw specific errors

**Usage:**
```typescript
import { retryWithBackoff, ValidationError, safeExecute } from '../lib/error-handler';

// Retry with backoff
const result = await retryWithBackoff(
  () => fetchData(),
  { maxAttempts: 3 }
);

// Safe execution
const data = await safeExecute(
  () => processData(),
  { context: 'DataProcessing', fallback: [] }
);

// Custom errors
if (!isValid) {
  throw new ValidationError('Invalid data', {
    email: 'Email is invalid'
  });
}
```

### 4. `validators.ts` - Input Validation
Comprehensive validation system for input data.

**Validators:**
- `StringValidator` - String validation
- `NumberValidator` - Number validation
- `ArrayValidator` - Array validation
- `ObjectValidator` - Object validation
- `ChainValidator` - Chainable validator

**Utilities:**
- `validateBatch()` - Batch validate object
- `ChainValidator` - Fluent validation API

**Usage:**
```typescript
import { StringValidator, NumberValidator, ChainValidator, validateBatch } from '../lib/validators';

// String validation
StringValidator.email(email, 'Email');
StringValidator.minLength(password, 8, 'Password');
StringValidator.enum(role, ['admin', 'user'], 'Role');

// Number validation
NumberValidator.positive(age, 'Age');
NumberValidator.range(score, 0, 100, 'Score');

// Batch validation
const validated = validateBatch(request.body, {
  email: (v) => StringValidator.email(v),
  age: (v) => NumberValidator.required(v),
  role: (v) => StringValidator.enum(v, ['admin', 'user'])
});

// Chain validator
new ChainValidator(email)
  .required()
  .string(5, 100)
  .email()
  .validate();
```

### 5. `middleware.ts` - Middleware Utilities
Common middleware patterns and helpers.

**Middleware:**
- `timingMiddleware()` - Track request timing
- `loggingMiddleware()` - Log all requests
- `asyncHandler()` - Wrap async handlers
- `cacheMiddleware()` - Cache GET requests
- `createRateLimiter()` - Rate limiting

**Helpers:**
- `extractRequestData()` - Extract body/query/params
- `getUser/getUserId/isAuthenticated/isAdmin()` - User info
- `requireAuth/requireAdmin()` - Auth checks
- `getVisitorToken()` - Get visitor token

**Usage:**
```typescript
import { asyncHandler, requireAuth, ResponseBuilder } from '../lib/middleware';

// Async handler with error handling
fastify.get('/api/data', asyncHandler(async (request, reply) => {
  const data = await fetchData();
  return ResponseBuilder.success(reply, data);
}));

// Auth required
fastify.post('/api/protected', requireAuth(async (request, reply) => {
  const userId = getUserId(request);
  return ResponseBuilder.success(reply, { userId });
}));

// Admin only
fastify.delete('/api/admin/data', requireAdmin(async (request, reply) => {
  // Admin only logic
  return ResponseBuilder.success(reply, { deleted: true });
}));
```

### 6. `database.ts` - Database Utilities
Database operations, connection pooling, and repositories.

**Classes:**
- `DatabasePoolManager` - Connection pool management
- `Repository` - Base repository class
- `TransactionManager` - Transaction handling
- `QueryBuilder` - SQL query builder

**Usage:**
```typescript
import { DatabasePoolManager, Repository, QueryBuilder } from '../lib/database';

// Setup
const poolManager = new DatabasePoolManager();
poolManager.registerPool('default', mysql2Pool);

// Repository
class UserRepository extends Repository {
  constructor(poolManager) {
    super(poolManager);
    this.tableName = 'users';
  }
}

const userRepo = new UserRepository(poolManager);
const user = await userRepo.findById(1);
const users = await userRepo.findAll();

// Query builder
const { query, params } = new QueryBuilder('users')
  .addSelect('id', 'name', 'email')
  .where('age > ?', [18])
  .where('status = ?', ['active'])
  .orderBy('created_at', 'DESC')
  .take(10)
  .build();

// Transaction
await transactionManager.transaction(async (connection) => {
  await connection.query('INSERT INTO users ...');
  await connection.query('UPDATE profiles ...');
});
```

## 🚀 Best Practices

### 1. Always Use Response Builder
```typescript
// ✅ Good
ResponseBuilder.success(reply, data);
ResponseBuilder.error(reply, 'Not found', { statusCode: 404 });

// ❌ Bad
reply.send({ success: true, data });
reply.status(500).send({ error: 'Server error' });
```

### 2. Handle Errors Consistently
```typescript
// ✅ Good
try {
  const data = await fetchData();
  return ResponseBuilder.success(reply, data);
} catch (error) {
  Logger.error('Fetch failed', error);
  return ResponseBuilder.internalError(reply, error);
}

// ❌ Bad
const data = await fetchData();
reply.send(data);
```

### 3. Validate Input Data
```typescript
// ✅ Good
const validated = validateBatch(request.body, {
  email: (v) => StringValidator.email(v),
  age: (v) => NumberValidator.positive(v)
});

// ❌ Bad
const { email, age } = request.body;
// No validation
```

### 4. Use Async Handler Wrapper
```typescript
// ✅ Good
fastify.get('/api/data', asyncHandler(async (request, reply) => {
  const data = await getData();
  return ResponseBuilder.success(reply, data);
}));

// ❌ Bad
fastify.get('/api/data', async (request, reply) => {
  try {
    const data = await getData();
    reply.send(data);
  } catch (error) {
    console.log(error);
    reply.status(500).send({ error: 'Server error' });
  }
});
```

### 5. Log Important Events
```typescript
// ✅ Good
Logger.info('User login', { userId: user.id, ip: request.ip });
Logger.warn('Rate limit near', { userId, requests: 95 });
Logger.error('Database error', error);

// ❌ Bad
console.log('User logged in');
// No structured logging
```

## 📊 Benefits

- **Consistency** - Unified patterns across all endpoints
- **Maintainability** - Single source of truth for common operations
- **Reliability** - Built-in error handling and retries
- **Security** - Validation and authentication helpers
- **Performance** - Caching and optimization utilities
- **Developer Experience** - Clear, type-safe APIs

## 🔄 Migration Guide

To migrate existing code to use shared utilities:

1. Replace custom logger with `Logger` from `logger.ts`
2. Replace response formatting with `ResponseBuilder`
3. Replace error handling with error classes from `error-handler.ts`
4. Add input validation using `validators.ts`
5. Wrap handlers with `asyncHandler` from `middleware.ts`

## 📚 File Structure

```
src/lib/
├── logger.ts            # Unified logging
├── response.ts          # Response formatting
├── error-handler.ts     # Error handling
├── validators.ts        # Input validation
├── middleware.ts        # Middleware utilities
├── database.ts          # Database operations
└── README.md           # This file
```

## 🎯 Next Steps

1. Review each module's documentation above
2. Check JSDoc comments in source files
3. Look at existing usage in updated services
4. Gradually migrate all handlers to use shared utilities

## ❓ Questions?

- Check the JSDoc comments in each file
- Look for examples in updated services
- Review the best practices section above
