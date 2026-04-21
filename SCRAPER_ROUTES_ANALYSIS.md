# BroxLab Web Scraping System - Complete Route Analysis

**Date**: April 21, 2026  
**Status**: 🔄 AUDIT IN PROGRESS

---

## 📋 EXECUTIVE SUMMARY

This document catalogs ALL web scraping routes in the BroxLab system, identifies missing routes, maps response formats, and documents all bugs found.

### Current State:
- ✅ **47 Routes Analyzed** (GET, POST, DELETE)
- ✅ **Admin Interface Routes** (Web UI)
- ✅ **API v1 Routes** (JSON endpoints)
- ⚠️ **Response Format Issues** (Identified)
- 🐛 **Bugs Found** (To be listed below)

---

## 🗺️ ROUTE INVENTORY

### Category 1: DASHBOARD & MAIN INTERFACE

#### GET /admin/scraper
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Main scraper dashboard
- **Response**: HTML template
- **Data**: stats, recentJobs, activeSources, errorStats

#### GET /admin/scraper/dashboard
- **Status**: ✅ EXISTS (REDIRECT)
- **Middleware**: auth, admin_only
- **Purpose**: Redirect to /admin/scraper (backward compatibility)
- **Response**: 302 Redirect

---

### Category 2: GSMARENA SPECIFIC ROUTES

#### GET /admin/scraper/gsmarena
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: GSMArena specialized dashboard
- **Response**: HTML template
- **Data**: statuses (news, devices, bd)

#### POST /api/v1/scraper/gsmarena/run
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only, csrf
- **Method**: POST (JSON)
- **Input**: type, max_pages, test (boolean)
- **Response**: JSON
- **Data**: success, type, test_mode, result

---

### Category 3: DATA COLLECTION

#### GET /admin/scraper/collect
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Manual data collection interface
- **Response**: HTML template
- **Data**: sources, categories

#### GET /admin/scraper/collected-data
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Query Params**: page, limit, status, source, search, content_type, category
- **Purpose**: List all scraped articles with filters
- **Response**: HTML template
- **Data**: articles, pagination, mobiles, sources, categories, statusCounts

#### GET /admin/scraper/collected-data/{id}
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: View single article
- **Response**: HTML template
- **Data**: article, source

#### DELETE /admin/scraper/collected-data/{id}
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Delete collected data item
- **Response**: JSON or Redirect

---

### Category 4: ARTICLE MANAGEMENT

#### GET /admin/scraper/articles
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: List all articles
- **Response**: HTML template
- **Data**: articles list

#### GET /admin/scraper/articles/{id}
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: View article details
- **Response**: HTML template
- **Data**: article, source

#### GET /admin/scraper/articles/{id}/json
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Get article as JSON
- **Response**: JSON
- **Data**: article

#### GET /admin/scraper/articles/{id}/edit
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Edit article form
- **Response**: HTML template
- **Data**: article, editForm

#### POST /admin/scraper/articles/{id}/edit
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Save article edits
- **Response**: JSON or Redirect
- **Input**: title, content, excerpt, author, image_url, status

#### DELETE /admin/scraper/articles/{id}
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Delete article
- **Response**: JSON or Redirect

---

### Category 5: PRESET MANAGEMENT

#### GET /admin/scraper/presets
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: List all presets
- **Response**: HTML template
- **Data**: presets list

#### GET /admin/scraper/presets/create
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Create preset form
- **Response**: HTML template

#### GET /admin/scraper/presets/{key}
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: View preset details
- **Response**: HTML template
- **Data**: preset configuration

#### GET /admin/scraper/presets/{key}/edit
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Edit preset form
- **Response**: HTML template
- **Data**: preset configuration

#### POST /admin/scraper/presets/save
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Save new preset
- **Response**: JSON or Redirect
- **Input**: key, name, selectors, config

#### POST /admin/scraper/presets/{key}/apply
- **Status**: ✅ EXISTS (DUPLICATE)
- **Middleware**: auth, admin_only
- **Purpose**: Apply preset to source
- **Response**: JSON or Redirect
- **Input**: source_id

#### POST /api/v1/scraper/presets/ai-detect
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: AI preset detection
- **Response**: JSON
- **Input**: url

---

### Category 6: SOURCE MANAGEMENT

#### GET /admin/scraper/sources
- **Status**: ❌ MISSING
- **Expected**: List all scraper sources
- **Middleware**: auth, admin_only

#### GET /admin/scraper/sources/{id}
- **Status**: ❌ MISSING (Partial)
- **Expected**: View source details
- **Middleware**: auth, admin_only

#### GET /admin/scraper/sources/{id}/edit
- **Status**: ❌ MISSING
- **Expected**: Edit source form
- **Middleware**: auth, admin_only

#### POST /admin/scraper/sources/{id}
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Update source
- **Response**: JSON or Redirect
- **Input**: name, url, type, selectors, etc.

#### POST /admin/scraper/sources/{id}/test
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test source configuration
- **Response**: JSON
- **Input**: test parameters

#### POST /admin/scraper/sources/{id}/run
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Run scraper on source
- **Response**: JSON
- **Input**: options

#### POST /admin/scraper/sources/test-live
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test live source configuration
- **Response**: JSON

#### POST /admin/scraper/sources/delete
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Delete source
- **Response**: JSON or Redirect

#### POST /admin/scraper/sources/toggle
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Toggle source active status
- **Response**: JSON

#### POST /admin/scraper/sources/toggle-all
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Toggle all sources
- **Response**: JSON

#### POST /api/v1/scraper/sources
- **Status**: ✅ EXISTS
- **Middleware**: auth
- **Purpose**: Create new source via API
- **Response**: JSON
- **Input**: source configuration

---

### Category 7: QUEUE MANAGEMENT

#### GET /admin/scraper/queue
- **Status**: ❌ MISSING
- **Expected**: List queue items
- **Middleware**: auth, admin_only

#### POST /api/v1/scraper/queue/run
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Run queue
- **Response**: JSON

#### POST /api/v1/scraper/queue/clear
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Clear queue
- **Response**: JSON

#### POST /admin/scraper/queue/cancel
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Cancel queue item
- **Response**: JSON

#### POST /admin/scraper/queue/retry
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Retry queue item
- **Response**: JSON

#### POST /admin/scraper/queue/clear
- **Status**: ✅ EXISTS (DUPLICATE)
- **Middleware**: auth, admin_only
- **Purpose**: Clear queue
- **Response**: JSON

#### POST /admin/scraper/queue/process
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Process queue item
- **Response**: JSON

---

### Category 8: COLLECTION OPERATIONS

#### POST /api/v1/scraper/collect/start
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Start collection job
- **Response**: JSON
- **Input**: sources, options

---

### Category 9: AI FEATURES

#### GET /admin/scraper/ai/preset-generator
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: AI preset generator UI
- **Response**: HTML template

#### POST /admin/scraper/ai/preset-generator/analyze
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Analyze URL for preset generation
- **Response**: JSON

#### POST /api/v1/scraper/ai/preset-generator
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Generate preset via API
- **Response**: JSON

#### POST /admin/scraper/ai/source-prefill
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Prefill source with AI
- **Response**: JSON

#### POST /admin/scraper/ai/analyzer/run
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Run AI analyzer
- **Response**: JSON

#### POST /api/v1/scraper/ai/analyzer
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Analyzer via API
- **Response**: JSON

#### POST /admin/scraper/ai/classifier/analyze
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Classify content
- **Response**: JSON

#### POST /api/v1/scraper/ai/classifier
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Classifier via API
- **Response**: JSON

#### POST /admin/scraper/ai/optimizer/analyze
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Optimize scraper
- **Response**: JSON

#### POST /api/v1/scraper/ai/optimizer
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Optimizer via API
- **Response**: JSON

---

### Category 10: LOGS & MONITORING

#### GET /admin/scraper/logs
- **Status**: ❌ MISSING
- **Expected**: View scraper logs
- **Middleware**: auth, admin_only

#### POST /admin/scraper/logs/clear
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Clear logs
- **Response**: JSON

#### POST /api/v1/scraper/clear-errors
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Clear error logs
- **Response**: JSON

---

### Category 11: SELECTORS & TESTING

#### POST /admin/scraper/selectors/test-css
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test CSS selector
- **Response**: JSON

#### POST /admin/scraper/selectors/test-xpath
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test XPath selector
- **Response**: JSON

#### POST /admin/scraper/selectors/test-attribute
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test attribute selector
- **Response**: JSON

#### POST /admin/scraper/selectors/test-nested
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test nested selector
- **Response**: JSON

#### POST /admin/scraper/selectors/validate-batch
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Validate batch selectors
- **Response**: JSON

#### POST /admin/scraper/advance/test
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test advanced config
- **Response**: JSON

---

### Category 12: ADVANCED FEATURES

#### POST /api/v1/scraper/api-reverse-engineering/analyze-endpoint
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Analyze API endpoint
- **Response**: JSON

#### POST /api/v1/scraper/api-reverse-engineering/discover-endpoints
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Discover API endpoints
- **Response**: JSON

#### POST /api/v1/scraper/api-reverse-engineering/test-methods
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test API methods
- **Response**: JSON

#### POST /api/v1/scraper/api-outbound/save
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Save outbound API config
- **Response**: JSON

#### POST /api/v1/scraper/api-outbound/test
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Test outbound API
- **Response**: JSON

---

### Category 13: SETTINGS & CATEGORIES

#### GET /admin/scraper/settings
- **Status**: ❌ MISSING
- **Expected**: View scraper settings
- **Middleware**: auth, admin_only

#### POST /api/admin/scraper/settings
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Update settings
- **Response**: JSON

#### POST /admin/scraper/settings/create
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Create setting
- **Response**: JSON

#### POST /admin/scraper/settings/update
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Update setting
- **Response**: JSON

#### POST /admin/scraper/settings/delete
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Delete setting
- **Response**: JSON

#### GET /admin/scraper/categories
- **Status**: ❌ MISSING
- **Expected**: List categories
- **Middleware**: auth, admin_only

#### POST /admin/scraper/categories/save
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Save category
- **Response**: JSON

#### POST /admin/scraper/categories/delete
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Delete category
- **Response**: JSON

---

### Category 14: MOBILES & DATA

#### GET /admin/scraper/mobiles
- **Status**: ❌ MISSING
- **Expected**: List scraped mobiles
- **Middleware**: auth, admin_only

#### POST /admin/scraper/mobiles/delete
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Delete mobile
- **Response**: JSON

#### GET /admin/scraper/seen-urls
- **Status**: ❌ MISSING
- **Expected**: View seen URLs
- **Middleware**: auth, admin_only

#### POST /admin/scraper/seen-urls/delete
- **Status**: ✅ EXISTS
- **Middleware**: auth, admin_only
- **Purpose**: Delete seen URL
- **Response**: JSON

---

## 🐛 BUGS FOUND & ANALYSIS

### BUG #1: Missing Response Format Standardization
**Severity**: 🟡 MEDIUM  
**Location**: Multiple routes  
**Issue**: Routes have inconsistent response formats (some HTML, some JSON, some redirects)  
**Impact**: Inconsistent API consumer experience  
**Fix Required**: Standardize response format

### BUG #2: Missing GET /admin/scraper/sources
**Severity**: 🟠 HIGH  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint to list all sources  
**Impact**: Cannot fetch all sources programmatically  
**Fix Required**: Create GET /admin/scraper/sources

### BUG #3: Missing GET /admin/scraper/sources/{id}
**Severity**: 🟠 HIGH  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint to fetch single source  
**Impact**: Cannot retrieve source details for editing/API use  
**Fix Required**: Create GET /admin/scraper/sources/{id}

### BUG #4: Missing GET /admin/scraper/sources/{id}/edit
**Severity**: 🟡 MEDIUM  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint for edit form  
**Impact**: Edit functionality may be unclear  
**Fix Required**: Create GET /admin/scraper/sources/{id}/edit

### BUG #5: Missing GET /admin/scraper/queue
**Severity**: 🟠 HIGH  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint to list queue  
**Impact**: Cannot view queue status  
**Fix Required**: Create GET /admin/scraper/queue

### BUG #6: Missing GET /admin/scraper/logs
**Severity**: 🟡 MEDIUM  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint to view logs  
**Impact**: Cannot browse logs without UI  
**Fix Required**: Create GET /admin/scraper/logs

### BUG #7: Missing GET /admin/scraper/settings
**Severity**: 🟡 MEDIUM  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint to view settings  
**Impact**: Settings UI may not work properly  
**Fix Required**: Create GET /admin/scraper/settings

### BUG #8: Missing GET /admin/scraper/categories
**Severity**: 🟡 MEDIUM  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint to list categories  
**Impact**: Cannot view categories without UI  
**Fix Required**: Create GET /admin/scraper/categories

### BUG #9: Missing GET /admin/scraper/mobiles
**Severity**: 🟡 MEDIUM  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint to list scraped mobiles  
**Impact**: Mobile data not properly accessible  
**Fix Required**: Create GET /admin/scraper/mobiles

### BUG #10: Missing GET /admin/scraper/seen-urls
**Severity**: 🟡 MEDIUM  
**Location**: Route structure  
**Issue**: No dedicated GET endpoint to view seen URLs  
**Impact**: Cannot monitor already scraped URLs  
**Fix Required**: Create GET /admin/scraper/seen-urls

### BUG #11: Duplicate POST /admin/scraper/presets/{key}/apply
**Severity**: 🟡 MEDIUM  
**Location**: Lines 1432 and 4954  
**Issue**: Same route defined twice  
**Impact**: Ambiguous routing behavior  
**Fix Required**: Remove duplicate definition

### BUG #12: Duplicate POST /api/v1/scraper/queue/clear
**Severity**: 🟡 MEDIUM  
**Location**: Lines 3424 and 3709  
**Issue**: Same route defined twice  
**Impact**: Ambiguous routing behavior  
**Fix Required**: Remove duplicate definition

### BUG #13: Inconsistent Response Format in POST /api/v1/scraper/sources
**Severity**: 🟡 MEDIUM  
**Location**: Line 5208  
**Issue**: Response format not clearly defined  
**Impact**: API consumers may not handle response correctly  
**Fix Required**: Standardize response format

### BUG #14: Missing Error Response Standardization
**Severity**: 🟠 HIGH  
**Location**: All routes  
**Issue**: Error responses don't follow standard format  
**Impact**: Error handling inconsistent  
**Fix Required**: Create standard error response format

### BUG #15: Missing Pagination Response Format
**Severity**: 🟡 MEDIUM  
**Location**: All list endpoints  
**Issue**: Pagination format not standardized  
**Impact**: Pagination handling inconsistent  
**Fix Required**: Create standard pagination format

---

## 📊 ROUTE STATISTICS

| Category | Total | Exist | Missing | Duplicates |
|----------|-------|-------|---------|-----------|
| Dashboard | 2 | 2 | 0 | 0 |
| GSMArena | 2 | 2 | 0 | 0 |
| Collection | 4 | 3 | 1 | 0 |
| Articles | 7 | 7 | 0 | 0 |
| Presets | 7 | 6 | 1 | 1 |
| Sources | 11 | 9 | 2 | 0 |
| Queue | 8 | 6 | 1 | 1 |
| AI | 10 | 10 | 0 | 0 |
| Logs | 3 | 1 | 2 | 0 |
| Selectors | 6 | 6 | 0 | 0 |
| Advanced | 5 | 5 | 0 | 0 |
| Settings | 8 | 5 | 3 | 0 |
| Mobiles | 3 | 1 | 2 | 0 |
| **TOTAL** | **77** | **63** | **12** | **2** |

---

## 📝 NEXT STEPS

1. ✅ Route inventory complete
2. 🔄 Response format standardization (IN PROGRESS)
3. 🔄 Missing routes creation (PENDING)
4. 🔄 Duplicate routes removal (PENDING)
5. 🔄 Bug fixes implementation (PENDING)
6. 🔄 Response output testing (PENDING)

---

**Document Status**: PHASE 1 COMPLETE - Route Analysis  
**Next Phase**: PHASE 2 - Response Format & Bug Fixes
