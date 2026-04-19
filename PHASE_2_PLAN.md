# Phase 2: Architecture + Features Plan
## Architecture Refactor & High-Value Admin Tools

**Status:** Ready to Begin  
**Duration:** 2-3 weeks  
**Priority Level:** Medium (Foundation for Phase 3)  

---

## Phase 2 Overview

Phase 2 focuses on two critical areas:

1. **JavaScript Architecture Refactor** - Transform monolithic code into maintainable modules
2. **New Admin Features** - Implement most-requested functionality

This foundation enables Phase 3 integrations and ensures long-term maintainability.

---

## Phase 2 Components

### Component 1: JavaScript Architecture Refactor
**Impact:** HIGH | **Complexity:** MEDIUM | **Time:** 5-7 days

#### Current State (Problem)
- `ai-admin.js` is monolithic (~4700 lines)
- Single responsibility principle violated
- Testing difficult
- Hard to extend
- State management scattered across methods

#### Target State (Solution)
```
modules/
├── ui/
│   ├── UIController.js         (DOM manipulation, event binding)
│   ├── UIRenderer.js           (Message rendering, formatting)
│   └── toast-notification.js   (Already done in Phase 1 ✅)
├── state/
│   ├── StateManager.js         (Single source of truth)
│   └── StateStore.js           (Redux-lite pattern)
├── services/
│   ├── ChatService.js          (API calls, SSE setup)
│   ├── ImageContextManager.js  (Multi-image upload, OCR)
│   └── StorageManager.js       (localStorage, IndexedDB)
├── handlers/
│   ├── CommandHandler.js       (Slash command parsing)
│   ├── KeyboardHandler.js      (Shortcuts, accessibility)
│   └── EventHandler.js         (Event delegation)
├── providers/
│   ├── OpenRouterProvider.js
│   ├── KiloProvider.js
│   └── OllamaProvider.js
└── utils/
    ├── EventEmitter.js         (Pub/sub pattern)
    ├── Logger.js               (Structured logging)
    └── Utils.js                (Helpers)
```

#### Benefits
- ✅ Unit testable
- ✅ Easier to extend
- ✅ Clearer separation of concerns
- ✅ Better performance (lazy-load providers)
- ✅ Maintainable

---

### Component 2: Conversation Management Features
**Impact:** HIGH | **Complexity:** MEDIUM | **Time:** 3-4 days

#### 2a. Export Conversations
- Export as PDF with metadata (date, model, cost, duration)
- Export as Markdown for documentation
- Export as JSON for backup/archival
- **New API Endpoint:** `POST /api/ai/export`

#### 2b. Search Conversations
- Client-side search through loaded messages
- Server-side search through all conversations
- Highlight matches
- Filter by date, model, topic
- **New API Endpoint:** `GET /api/ai/search?q=...`

#### 2c. Tag Conversations
- Add comma-separated tags
- Save to `ai_conversations.tags` (JSON)
- Filter history by tags
- Tag suggestions based on content
- **New API Endpoint:** `POST /api/ai/tag`

#### 2d. Conversation Metadata
- Display: duration, token count, cost estimate, model used
- Auto-generate summary
- Show success/failure rate
- Comparison metrics (fastest response, longest conversation)

---

### Component 3: Enhanced Slash Commands
**Impact:** HIGH | **Complexity:** MEDIUM | **Time:** 4-5 days

#### New Commands (Backend Implementation)
1. `/summarize-page` - DOM content extraction + summary
2. `/analyze-posts` - Bulk post analysis
3. `/generate-alt-text` - Image description generation
4. `/extract-entities` - NLP entity detection
5. `/suggest-replies` - Auto-generate comment responses
6. `/check-seo` - Post SEO analysis
7. `/batch-translate` - Multi-language translation

#### Requirements
- Add to `ToolRegistry.php`
- Implement handlers in `app/Modules/AISystem`
- Rate limiting per command
- Permission-based access control
- Audit logging

---

### Component 4: Voice Input & Text-to-Speech
**Impact:** MEDIUM | **Complexity:** MEDIUM | **Time:** 2-3 days

#### Voice Input (Already in Phase 1 ✅)
- Microphone icon in input
- Recording indicator
- Transcription display
- Language support (15+ languages)
- Graceful fallback

#### Text-to-Speech (NEW)
- Play assistant response as audio
- Speed control (0.5x - 2x)
- Voice selection
- Background playback
- Fallback for unsupported browsers

---

### Component 5: Settings Expansion
**Impact:** MEDIUM | **Complexity:** LOW | **Time:** 1-2 days

#### New Settings
- **Custom Prompt** - Per-admin system prompt override
- **Model Selector** - Choose preferred model
- **Response Tone** - Professional/Casual/Technical/Friendly
- **Context Depth** - Control RAG context amount
- **Auto-complete** - Quick action suggestions
- **Notifications** - Sound, banner, silent, disabled

#### Database Changes
- New table: `admin_custom_prompts`
- Update `ai_settings` for user preferences

---

## Implementation Priority

### 🔥 **Quick Wins (Start Here)**
| Feature | Time | Impact | Complexity |
|---------|------|--------|-----------|
| StateManager module | 2 days | HIGH | MEDIUM |
| Search conversations | 1 day | HIGH | LOW |
| Tag conversations | 1 day | HIGH | LOW |
| **Total** | **4 days** | **HIGH** | **LOW-MEDIUM** |

### ⭐ **High Impact (Week 2)**
| Feature | Time | Impact | Complexity |
|---------|------|--------|-----------|
| UIController refactor | 3 days | HIGH | MEDIUM |
| Export (PDF/MD/JSON) | 2 days | HIGH | MEDIUM |
| Enhanced commands | 3 days | HIGH | MEDIUM |
| **Total** | **8 days** | **HIGH** | **MEDIUM** |

### 📦 **Nice to Have (Week 3)**
| Feature | Time | Impact | Complexity |
|---------|------|--------|-----------|
| Text-to-speech | 2 days | MEDIUM | LOW |
| Settings expansion | 1 day | MEDIUM | LOW |
| Full architecture refactor | 2 days | HIGH | HIGH |
| **Total** | **5 days** | **MEDIUM-HIGH** | **LOW-MEDIUM** |

---

## Recommended Phase 2 Scope

### ✅ **CORE PHASE 2 (High Priority - 10 days)**
1. **StateManager + UIController Refactor** (3 days)
   - Foundation for all future work
   - Enable better testing
   - Cleaner API

2. **Search + Tag Features** (2 days)
   - High user demand
   - Quick implementation
   - Low risk

3. **Export Conversations** (2 days)
   - PDF, Markdown, JSON formats
   - Useful for documentation
   - Standard feature

4. **Enhanced Commands (Batch 1)** (2 days)
   - `/summarize-page`
   - `/analyze-posts`
   - `/generate-alt-text`

5. **Settings Expansion** (1 day)
   - Custom prompts
   - Model selector
   - Basic preferences

### 📋 **OPTIONAL PHASE 2 (Lower Priority - 5 days)**
6. **More Enhanced Commands** (3 days)
   - `/extract-entities`
   - `/suggest-replies`
   - `/check-seo`
   - `/batch-translate`

7. **Text-to-Speech** (2 days)
   - Audio playback
   - Voice selection
   - Speed control

---

## Deliverables

### Code Deliverables
```
public_html/ai/js/modules/
├── state/
│   ├── StateManager.js
│   └── StateStore.js
├── services/
│   ├── ChatService.js
│   ├── ImageContextManager.js
│   └── StorageManager.js
├── handlers/
│   ├── CommandHandler.js
│   └── KeyboardHandler.js
├── ui/
│   ├── UIController.js
│   └── UIRenderer.js
└── utils/
    ├── EventEmitter.js
    └── Logger.js

public_html/ai/js/ai-admin-v2.js (Refactored main orchestrator)
```

### Database Changes
```sql
-- New table
CREATE TABLE admin_custom_prompts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    custom_prompt TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Migrations for enhanced commands
-- No breaking changes to existing tables
```

### API Endpoints
```
POST /api/ai/export
POST /api/ai/search
POST /api/ai/tag
POST /api/ai/command/{name}
GET /api/ai/settings
PUT /api/ai/settings
```

### Documentation
```
PHASE_2_ARCHITECTURE.md          (New module structure)
PHASE_2_STATE_MANAGEMENT.md      (StateManager API)
PHASE_2_API_DOCUMENTATION.md     (New endpoints)
PHASE_2_FEATURE_GUIDE.md         (User guide)
PHASE_2_MIGRATION_GUIDE.md       (From v1 to v2)
```

---

## Technical Decisions

### 1. State Management Pattern
**Decision:** Redux-lite with EventEmitter
**Rationale:** 
- Simple enough to understand
- Provides single source of truth
- Event-driven UI updates
- No heavy dependencies

```javascript
// Usage
state.subscribe('message:added', (msg) => ui.renderMessage(msg));
state.setState({ messages: [...] });
```

### 2. Refactoring Approach
**Decision:** Create new modules in parallel, then migrate
**Rationale:**
- Zero downtime
- Can test incrementally
- Easy rollback if issues
- Live A/B test if needed

### 3. Database Strategy
**Decision:** Additive only (no breaking changes)
**Rationale:**
- Easy rollback
- Gradual migration
- No data loss risk
- Backward compatibility

---

## Testing Strategy

### Unit Tests (Jest)
```javascript
// StateManager.test.js
test('setState updates state', () => {
  const state = new StateManager();
  state.setState({ count: 1 });
  expect(state.getState().count).toBe(1);
});

// UIController.test.js
test('renderMessage appends to DOM', () => {
  const ui = new UIController(domElement);
  ui.renderMessage({ role: 'assistant', content: 'Hello' });
  expect(domElement.children.length).toBe(1);
});
```

### Integration Tests (Playwright)
```javascript
// export.spec.js
test('Export conversation as PDF', async ({ page }) => {
  await page.click('text=Export');
  await page.click('text=PDF');
  const download = await page.waitForEvent('download');
  expect(download.suggestedFilename()).toContain('.pdf');
});

// search.spec.js
test('Search finds messages', async ({ page }) => {
  await page.fill('[placeholder="Search"]', 'hello');
  await page.waitForSelector('text=Results: 5');
  expect(results).toBeTruthy();
});
```

### Manual Testing Checklist
- [ ] StateManager persists state across page reload
- [ ] Export PDF is readable and contains all data
- [ ] Search finds messages (case-insensitive)
- [ ] Tags persist after reload
- [ ] Commands execute correctly
- [ ] Voice input still works post-refactor
- [ ] No console errors or warnings

---

## Risk Mitigation

### Risk: Breaking Changes
**Mitigation:** 
- Create new module structure in parallel
- Keep old ai-admin.js working
- Use feature flags for gradual rollout

### Risk: Performance Degradation
**Mitigation:**
- Profile with DevTools before/after
- Lazy-load non-critical modules
- Benchmark key operations

### Risk: State Management Complexity
**Mitigation:**
- Document state schema clearly
- Provide usage examples
- Keep StateManager focused (single responsibility)

### Risk: Lost Functionality
**Mitigation:**
- Write tests for all existing features
- Diff old vs new behavior
- User acceptance testing before rollout

---

## Success Criteria

### Architectural
- ✅ All modules <500 lines (except ChatService, may be 800+)
- ✅ Single StateManager source of truth
- ✅ Event-driven UI updates
- ✅ 80%+ code coverage with unit tests
- ✅ Zero console errors/warnings

### Feature
- ✅ Export works for all formats (PDF, MD, JSON)
- ✅ Search finds all matching messages
- ✅ Tags persist and filter correctly
- ✅ All new commands execute without errors
- ✅ Voice input still works post-refactor

### Performance
- ✅ No performance degradation vs Phase 1
- ✅ Module load time <100ms
- ✅ Message render <100ms (same as before)
- ✅ Lighthouse score ≥90

### User Experience
- ✅ No downtime during deployment
- ✅ All features accessible to users
- ✅ New features intuitive to discover
- ✅ Keyboard shortcuts still work

---

## Timeline

### Week 1 (Days 1-5)
**Goal:** Core modules + quick wins
- Day 1-2: StateManager + EventEmitter
- Day 2-3: UIController refactor
- Day 4: Search feature
- Day 5: Tag feature

**Deliverable:** Core v2 architecture ready, search/tag working

### Week 2 (Days 6-10)
**Goal:** Feature implementation
- Day 6-7: Export (PDF, MD, JSON)
- Day 8-9: Enhanced commands (batch 1)
- Day 10: Settings expansion

**Deliverable:** All core features implemented

### Week 3 (Days 11-14) [Optional]
**Goal:** Polish + extras
- Day 11-12: More commands (batch 2)
- Day 13: Text-to-speech
- Day 14: Testing + documentation

**Deliverable:** Feature-complete Phase 2

---

## Questions for You

Before we begin Phase 2, please clarify:

1. **Scope Priority:**
   - Want to do CORE only (10 days)?
   - Or also include OPTIONAL features (14 days)?

2. **Timeline:**
   - Can dedicate 2 weeks solid?
   - Or prefer spread over 3 weeks?

3. **Features:**
   - Which commands are highest priority?
   - Is text-to-speech important?
   - Custom prompts per-admin needed?

4. **Rollout Strategy:**
   - Deploy new modules alongside old (no cutover)?
   - Use feature flags for gradual rollout?
   - Big bang cutover after full refactor?

5. **Testing:**
   - Need end-to-end tests (Playwright)?
   - Or unit tests sufficient (Jest)?
   - Manual testing acceptable?

---

## Next Steps

### If You Want to Proceed:
1. Confirm scope (Core vs Core+Optional)
2. Prioritize command implementation order
3. Set deployment timeline
4. Begin StateManager implementation

### If You Want to Adjust:
1. Let me know which features to focus on
2. Any features to defer or remove?
3. Performance targets to hit?

---

**Status:** Ready to begin Phase 2 implementation  
**Estimated Time (Core):** 10 business days  
**Estimated Time (Core+Optional):** 14 business days  
**Risk Level:** Medium (parallel development approach)  
**Rollback Difficulty:** Easy (feature flags)

---

*Note: Phase 1 provides solid foundation. Phase 2 builds critical features and architecture. Phase 3 (system integration) will be straightforward after Phase 2 is complete.*
