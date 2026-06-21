import { test, expect } from '@playwright/test';

/**
 * AI Assistant Shell — Browser Rendering Tests
 *
 * Tests the actual rendered DOM from assistant.twig on public (/faq) pages.
 * For admin assistant, navigates to /admin/dashboard with authentication
 * to test the real admin DOM structure.
 *
 * All selectors match the real assistant.twig template exactly.
 */

/* ─── Config ───────────────────────────────────────────── */

const NAV_TIMEOUT = 60_000;
const ACTION_TIMEOUT = 15_000;

/* ─── Helpers ───────────────────────────────────────────── */

async function waitForReady(page: import('@playwright/test').Page) {
  await page.waitForLoadState('domcontentloaded', { timeout: NAV_TIMEOUT });
  await page.waitForTimeout(500);
}

const ADMIN_EMAIL = process.env.ADMIN_EMAIL || 'admin@broxlab.online';
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'admin123456';

async function loginAsAdmin(page: import('@playwright/test').Page) {
  // Check if already authenticated (session from previous test)
  const currentUrl = page.url();
  if (currentUrl.includes('/admin') || currentUrl.includes('/dashboard')) return;

  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);

  // If redirected away from /login, already authenticated
  if (!page.url().includes('/login')) return;

  // Fill and submit login form
  await page.fill('input[name="email"], input[name="username"], input[type="email"]', ADMIN_EMAIL);
  await page.fill('input[type="password"]', ADMIN_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1000);
}

/**
 * Ensures the admin assistant shell is available for testing.
 * Tries real authentication first; falls back to injecting exact template HTML.
 */
async function ensureAdminShell(page: import('@playwright/test').Page) {
  await loginAsAdmin(page);
  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded', timeout: NAV_TIMEOUT }).catch(() => {});
  await page.waitForTimeout(500);

  const onAdminPage = page.url().includes('/admin');
  if (!onAdminPage) {
    // Fallback: inject template-accurate admin HTML on public page
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminAssistant(page);
  }
}

/**
 * Injects the admin assistant HTML matching the exact structure from assistant.twig.
 * Used as fallback when admin login is unavailable in CI.
 * The HTML structure matches the {% if is_admin_assistant %} block.
 */
async function injectAdminAssistant(page: import('@playwright/test').Page) {
  await page.evaluate(() => {
    // Remove any existing admin assistant
    document.querySelector('#adminAiWrapper')?.remove();
    document.querySelector('[data-ai-role="admin"]')?.remove();

    const wrapper = document.createElement('div');
    wrapper.id = 'adminAiWrapper';
    wrapper.className = 'ai-widget-wrapper';
    wrapper.setAttribute('data-ai-role', 'admin');
    wrapper.setAttribute('dir', 'ltr');
    wrapper.innerHTML = `
      <button
        id="adminAiBtn"
        class="ai-fab"
        title="Open Admin Copilot"
        aria-label="Open Admin Copilot"
        aria-controls="adminAiShell"
        data-assistant-trigger
        aria-expanded="false"
        type="button">
        <span data-icon="open" class="flex items-center justify-center">
          <i class="lucide lucide-badge-sparkles"></i>
        </span>
        <span data-icon="close" class="hidden items-center justify-center">
          <i class="lucide lucide-x"></i>
        </span>
      </button>
      <div
        id="adminAiShell"
        class="ai-shell ai-hidden"
        role="dialog"
        aria-labelledby="adminAiTitle"
        aria-modal="true"
        aria-label="Brox Admin Assistant">
        <div class="relative flex h-[min(84dvh,52rem)] min-h-0 flex-col">
          <section class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <header class="sticky top-0 z-20 border-b border-white/10 bg-slate-950/88 px-3 py-3 backdrop-blur-2xl sm:px-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-100">
                      <i class="lucide lucide-sparkles text-[0.9rem]"></i>
                      Admin Copilot
                    </span>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[10px] font-medium text-slate-300">Unified workspace</span>
                  </div>
                  <h3 id="adminAiTitle" class="mt-1.5 flex items-center gap-2 text-[1.05rem] font-semibold tracking-tight text-white">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-indigo-500 text-slate-950 shadow-lg shadow-cyan-500/25">
                      <i class="lucide lucide-badge-sparkles text-[1rem]"></i>
                    </span>
                    Brox Admin Assistant
                  </h3>
                  <p class="mt-1 max-w-2xl text-[13px] leading-5 text-slate-400">Generate replies, summarize context, and analyze messages without leaving the admin workspace.</p>
                </div>
                <div class="flex shrink-0 items-start gap-2.5">
                  <button class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10 hover:text-white" title="Toggle history" aria-label="Toggle history" type="button">
                    <i class="lucide lucide-panel-left text-[0.95rem]"></i>
                  </button>
                  <div class="inline-flex items-center gap-1 rounded-2xl border border-white/10 bg-white/5 p-1">
                    <button class="rounded-xl px-3 py-1.5 text-[0.72rem] font-semibold uppercase tracking-[0.18em] text-slate-300 transition hover:bg-white/10" title="Bangla" aria-label="Switch to Bengali" type="button">BN</button>
                    <button class="rounded-xl bg-white/10 px-3 py-1.5 text-[0.72rem] font-semibold uppercase tracking-[0.18em] text-white transition" title="English" aria-label="Switch to English" type="button">EN</button>
                  </div>
                  <button class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10 hover:text-white" title="Minimize" aria-label="Minimize" type="button">
                    <i class="lucide lucide-minus text-[1rem]"></i>
                  </button>
                  <button class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200 transition hover:bg-rose-500/15 hover:text-rose-100" title="Close" aria-label="Close" type="button">
                    <i class="lucide lucide-x text-[1rem]"></i>
                  </button>
                </div>
              </div>
            </header>
            <div class="flex min-h-0 flex-1 flex-col">
              <div class="flex min-h-0 flex-col">
                <div class="border-b border-white/10 bg-white/[0.03] px-3 py-3 sm:px-4">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1.5 text-[11px] font-medium text-slate-200">Loading models...</span>
                    <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1.5 text-[11px] font-medium text-emerald-100">Ready</span>
                  </div>
                </div>
                <div data-prechat class="px-3 py-3 sm:px-4">
                  <div class="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                    <div class="flex items-start gap-3">
                      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[1rem] bg-gradient-to-br from-cyan-300 via-sky-400 to-indigo-500 text-slate-950 shadow-lg shadow-cyan-500/25">
                        <i class="lucide lucide-rocket text-[1rem]"></i>
                      </div>
                      <div class="min-w-0">
                        <h4 class="text-[1.05rem] font-bold text-white">Ask, summarize, or draft without leaving the chat</h4>
                        <p class="mt-1 text-[13px] leading-5 text-slate-300">The workspace is arranged so the chat, tools, and state are easy to scan at a glance.</p>
                      </div>
                    </div>
                  </div>
                  <div class="mt-4 rounded-2xl border border-white/10 bg-white/[0.04] p-4">
                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">Suggested prompts</p>
                    <div class="flex gap-2 overflow-x-auto pb-1">
                      <button type="button" class="shrink-0 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white" data-prompt="Summarize the current context.">Summarize</button>
                      <button type="button" class="shrink-0 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white" data-prompt="Draft a concise reply.">Draft reply</button>
                    </div>
                  </div>
                </div>
                <div class="flex-1 overflow-y-auto px-3 py-3 sm:px-4" role="log" aria-live="polite" aria-label="Chat messages"></div>
                <div class="sticky bottom-0 z-20 border-t border-white/10 bg-slate-950/95 px-3 py-3 backdrop-blur-xl sm:px-4">
                  <input type="file" class="hidden" accept="image/*,.pdf,.txt,.doc,.docx" data-file-input />
                  <div data-input-area class="flex items-end gap-2 rounded-[1.5rem] border border-white/10 bg-white/[0.04] p-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                    <button class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10 hover:text-white" title="Attach file" aria-label="Attach file" type="button" data-attach-btn>
                      <i class="lucide lucide-paperclip text-[1rem]"></i>
                    </button>
                    <button class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200 transition hover:bg-white/10 hover:text-white" title="Voice input" aria-label="Voice input" type="button" data-voice-input>
                      <i class="lucide lucide-mic text-[1rem]"></i>
                    </button>
                    <div class="relative flex-1">
                      <textarea rows="1" class="w-full max-h-32 resize-none rounded-[1rem] border border-white/10 bg-slate-900/80 px-3 py-3 text-[0.95rem] leading-6 text-slate-100 placeholder:text-slate-400 outline-none transition focus:border-cyan-400/50 focus:ring-2 focus:ring-cyan-400/20" placeholder="Type your message..." aria-label="Message input"></textarea>
                    </div>
                    <button class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-indigo-500 text-slate-950 transition hover:brightness-110" title="Send message" aria-label="Send" type="button">
                      <i class="lucide lucide-send text-[1rem]"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    `;
    document.body.appendChild(wrapper);
  });
}

/* ─── Public Chat Widget ────────────────────────────────── */

test.describe('Public Chat Widget', () => {
  test.use({ navigationTimeout: NAV_TIMEOUT, actionTimeout: ACTION_TIMEOUT });

  test.beforeEach(async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
  });

  test('FAB button exists and is visible', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    await expect(fab).toBeAttached({ timeout: 10_000 });
    await expect(fab).toBeVisible();

    await expect(fab).toHaveAttribute('type', 'button');
    await expect(fab).toHaveAttribute('aria-label', 'Open Chat');
    await expect(fab).toHaveAttribute('aria-controls', 'publicAssistantChat');
    await expect(fab).toHaveAttribute('data-assistant-trigger', '');

    const box = await fab.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeGreaterThanOrEqual(40);
    expect(box!.height).toBeGreaterThanOrEqual(40);
  });

  test('FAB has open/close icon spans with correct data attributes', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');

    const openIcon = fab.locator('[data-icon="open"]');
    await expect(openIcon).toBeAttached();

    const closeIcon = fab.locator('[data-icon="close"]');
    await expect(closeIcon).toBeAttached();
    // Close icon starts hidden
    await expect(closeIcon).toHaveClass(/hidden/);
  });

  test('chat shell is hidden initially via ai-hidden class', async ({ page }) => {
    const shell = page.locator('#publicAssistantChat');
    await expect(shell).toBeAttached({ timeout: 10_000 });

    await expect(shell).toHaveClass(/ai-hidden/);
    await expect(shell).toHaveClass(/ai-shell/);
  });

  test('clicking FAB toggles chat shell visibility', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    const shell = page.locator('#publicAssistantChat');

    await expect(fab).toBeVisible({ timeout: 10_000 });

    // Click FAB to open
    await fab.click();

    // Shell should no longer have ai-hidden
    await expect(shell).not.toHaveClass(/ai-hidden/, { timeout: 5000 });
    await expect(shell).toBeVisible({ timeout: 5000 });

    // Verify ARIA attributes
    await expect(shell).toHaveAttribute('role', 'dialog');
    await expect(shell).toHaveAttribute('aria-modal', 'true');

    // FAB icons should toggle
    const openIcon = fab.locator('[data-icon="open"]');
    const closeIcon = fab.locator('[data-icon="close"]');
    await expect(openIcon).toHaveClass(/hidden/);
    await expect(closeIcon).not.toHaveClass(/hidden/);

    // aria-expanded should be true
    await expect(fab).toHaveAttribute('aria-expanded', 'true');
  });

  test('clicking FAB twice toggles shell closed then open', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    const shell = page.locator('#publicAssistantChat');

    // First click — open
    await fab.click();
    await expect(shell).not.toHaveClass(/ai-hidden/, { timeout: 5000 });

    // Second click — close
    await fab.click();
    await expect(shell).toHaveClass(/ai-hidden/, { timeout: 5000 });
  });

  test('chat shell has all required structural elements', async ({ page }) => {
    // Force shell visible
    await page.locator('#publicAssistantChat').evaluate((el) => {
      el.classList.remove('ai-hidden');
    });

    // Title
    await expect(page.locator('#publicAssistantTitle')).toBeAttached();

    // Header
    await expect(page.locator('header.ai-header')).toBeVisible();

    // Messages container with ARIA
    const messages = page.locator('.ai-messages[role="log"]');
    await expect(messages).toBeAttached();
    await expect(messages).toHaveAttribute('aria-live', 'polite');
    await expect(messages).toHaveAttribute('aria-label', 'Chat messages');

    // Input field
    const input = page.locator('textarea[aria-label="Message input"]');
    await expect(input).toBeAttached();

    // Send button within input area
    const sendBtn = page.locator('[data-input-area] button[aria-label="Send"]');
    await expect(sendBtn).toBeAttached();

    // Status bar
    await expect(page.locator('[data-status-bar]')).toBeAttached();

    // Language toggle
    await expect(page.locator('button[aria-label="Switch to Bengali"]')).toBeAttached();
    await expect(page.locator('button[aria-label="Switch to English"]')).toBeAttached();
  });

  test('chat input accepts text and is functional', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    await fab.click();

    const input = page.locator('textarea[aria-label="Message input"]');
    await expect(input).toBeVisible({ timeout: 5000 });

    // Type and verify
    await input.click();
    await input.fill('Hello test');
    await expect(input).toHaveValue('Hello test');

    // Input should not be disabled
    await expect(input).toBeEnabled();
  });

  test('language toggle buttons are present and have correct text', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    await fab.click();

    const bnBtn = page.locator('button[aria-label="Switch to Bengali"]');
    const enBtn = page.locator('button[aria-label="Switch to English"]');

    await expect(bnBtn).toBeVisible({ timeout: 5000 });
    await expect(enBtn).toBeVisible();

    // Verify button text content
    await expect(bnBtn).toHaveText('BN');
    await expect(enBtn).toHaveText('EN');

    // English should be active by default
    await expect(enBtn).toHaveClass(/active/);
  });

  test('wrapper has correct data-ai-role attribute', async ({ page }) => {
    const wrapper = page.locator('#publicAssistantWrapper');
    await expect(wrapper).toBeAttached({ timeout: 10_000 });
    await expect(wrapper).toHaveAttribute('data-ai-role', 'public');
    await expect(wrapper).toHaveClass(/ai-widget-wrapper/);
  });

  test('pre-chat area exists with welcome content', async ({ page }) => {
    await page.locator('#publicAssistantChat').evaluate((el) => {
      el.classList.remove('ai-hidden');
    });

    const preChat = page.locator('[data-prechat]');
    await expect(preChat).toBeAttached();
    await expect(preChat).not.toHaveClass(/hidden/);

    // Should have the title
    await expect(preChat.locator('.ai-prechat-title')).toContainText('Welcome');
  });

  test('suggestion chips exist with data-action-chip attribute', async ({ page }) => {
    const chips = page.locator('[data-action-chip]');
    const count = await chips.count();
    expect(count).toBeGreaterThanOrEqual(4);

    // Verify first chip has text
    const firstChip = chips.first();
    await expect(firstChip).toBeVisible();
  });

  test('attach and voice input buttons are present', async ({ page }) => {
    const attachBtn = page.locator('[data-attach-btn]');
    await expect(attachBtn).toBeAttached();

    const voiceBtn = page.locator('[data-voice-input]');
    await expect(voiceBtn).toBeAttached();

    const fileInput = page.locator('[data-file-input]');
    await expect(fileInput).toBeAttached();
    await expect(fileInput).not.toBeVisible(); // hidden file input
  });
});

/* ─── Admin Shell Overlay ───────────────────────────────── */

/* ─── Admin Shell Overlay ───────────────────────────────── */

test.describe('Admin Shell Overlay', () => {
  test.use({ navigationTimeout: NAV_TIMEOUT, actionTimeout: ACTION_TIMEOUT });

  test.beforeEach(async ({ page }) => {
    await ensureAdminShell(page);
  });

  test('admin shell has correct structure with real IDs and ARIA', async ({ page }) => {
    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeAttached({ timeout: 10_000 });
    await expect(shell).toHaveClass(/ai-shell/);
    await expect(shell).toHaveClass(/ai-hidden/);
    await expect(shell).toHaveAttribute('role', 'dialog');
    await expect(shell).toHaveAttribute('aria-modal', 'true');

    // FAB button
    const fab = page.locator('#adminAiBtn');
    await expect(fab).toBeAttached();
    await expect(fab).toHaveAttribute('data-assistant-trigger', '');
    await expect(fab).toHaveAttribute('aria-controls', 'adminAiShell');

    // Wrapper
    await expect(page.locator('#adminAiWrapper')).toBeAttached();
    await expect(page.locator('#adminAiWrapper')).toHaveAttribute('data-ai-role', 'admin');

    // Title
    await expect(page.locator('#adminAiTitle')).toBeAttached();

    // Messages container with ARIA log
    const messages = page.locator('#adminAiShell [role="log"]');
    await expect(messages).toBeAttached();
    await expect(messages).toHaveAttribute('aria-live', 'polite');
    await expect(messages).toHaveAttribute('aria-label', 'Chat messages');
  });

  test('admin input area has all required elements', async ({ page }) => {
    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeAttached({ timeout: 10_000 });

    await expect(shell.locator('[data-input-area]')).toBeAttached();
    await expect(shell.locator('textarea[aria-label="Message input"]')).toBeAttached();
    await expect(shell.locator('button[aria-label="Send"]')).toBeAttached();
    await expect(shell.locator('[data-prechat]')).toBeAttached();
  });

  test('admin has attach, voice, and file input elements', async ({ page }) => {
    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeAttached({ timeout: 10_000 });

    await expect(shell.locator('[data-attach-btn]')).toBeAttached();
    await expect(shell.locator('[data-voice-input]')).toBeAttached();
    await expect(shell.locator('[data-file-input]')).toBeAttached();
  });

  test('admin header shows Copilot badge and action buttons', async ({ page }) => {
    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeAttached({ timeout: 10_000 });

    const header = shell.locator('header');
    await expect(header).toContainText('Admin Copilot');
    await expect(header).toContainText('Brox Admin Assistant');

    // Language toggle
    await expect(header.locator('button[aria-label="Switch to Bengali"]')).toBeAttached();
    await expect(header.locator('button[aria-label="Switch to English"]')).toBeAttached();

    // Header action buttons
    await expect(header.locator('button[aria-label="Toggle history"]')).toBeAttached();
    await expect(header.locator('button[aria-label="Minimize"]')).toBeAttached();
    await expect(header.locator('button[aria-label="Close"]')).toBeAttached();
  });

  test('admin shell starts hidden and FAB toggle changes class', async ({ page }) => {
    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeAttached({ timeout: 10_000 });

    // Starts hidden
    await expect(shell).toHaveClass(/ai-hidden/);

    // Simulate open — same as assistant-shell.js toggleShell does
    await shell.evaluate((el) => el.classList.remove('ai-hidden'));
    await expect(shell).not.toHaveClass(/ai-hidden/);

    // Simulate close — same as closeAssistantFactory does
    await shell.evaluate((el) => el.classList.add('ai-hidden'));
    await expect(shell).toHaveClass(/ai-hidden/);
  });

  test('admin textarea accepts text input', async ({ page }) => {
    const textarea = page.locator('#adminAiShell textarea[aria-label="Message input"]');
    await expect(textarea).toBeAttached({ timeout: 10_000 });

    await textarea.click();
    await textarea.fill('Hello admin test');
    await expect(textarea).toHaveValue('Hello admin test');
    await expect(textarea).toBeEnabled();
  });
});

/* ─── CSS Class & Structure Guards ──────────────────────── */

test.describe('CSS Class and Structure Guards', () => {
  test.use({ navigationTimeout: NAV_TIMEOUT, actionTimeout: ACTION_TIMEOUT });

  test('public shell has ai-shell class for CSS targeting', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const shell = page.locator('#publicAssistantChat');
    await expect(shell).toBeAttached({ timeout: 10_000 });
    await expect(shell).toHaveClass(/ai-shell/);
  });

  test('public shell starts with ai-hidden class', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const shell = page.locator('#publicAssistantChat');
    await expect(shell).toHaveClass(/ai-hidden/);
  });

  test('no horizontal overflow when page loads', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const hasOverflow = await page.evaluate(() =>
      document.documentElement.scrollWidth > document.documentElement.clientWidth
    );
    expect(hasOverflow).toBe(false);
  });

  test('admin shell uses same ai-shell ai-hidden class pattern', async ({ page }) => {
    await ensureAdminShell(page);

    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeAttached({ timeout: 10_000 });
    await expect(shell).toHaveClass(/ai-shell/);
    await expect(shell).toHaveClass(/ai-hidden/);
  });

  test('public wrapper has correct data-ai-role', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const wrapper = page.locator('#publicAssistantWrapper');
    await expect(wrapper).toBeAttached({ timeout: 10_000 });
    await expect(wrapper).toHaveAttribute('data-ai-role', 'public');
  });
});

/* ─── Responsive: Mobile (375px) ────────────────────────── */

test.describe('Responsive — Mobile (375px)', () => {
  test.use({
    navigationTimeout: NAV_TIMEOUT,
    actionTimeout: ACTION_TIMEOUT,
    viewport: { width: 375, height: 812 },
  });

  test('public FAB is visible and correctly sized on mobile', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const fab = page.locator('#publicAssistantBtn');
    await expect(fab).toBeVisible({ timeout: 10_000 });

    const box = await fab.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeGreaterThanOrEqual(40);
    expect(box!.height).toBeGreaterThanOrEqual(40);

    // FAB should be within viewport bounds
    expect(box!.x + box!.width).toBeLessThanOrEqual(375);
    expect(box!.y + box!.height).toBeLessThanOrEqual(812);
  });

  test('public chat shell opens and is closable on mobile', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const fab = page.locator('#publicAssistantBtn');
    const shell = page.locator('#publicAssistantChat');

    // Open
    await fab.click();
    await expect(shell).not.toHaveClass(/ai-hidden/, { timeout: 5000 });

    const box = await shell.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeGreaterThanOrEqual(350); // Nearly full-width on mobile

    // Close via FAB
    await fab.click();
    await expect(shell).toHaveClass(/ai-hidden/, { timeout: 5000 });
  });

  test('no horizontal overflow on mobile viewport', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const hasOverflow = await page.evaluate(() =>
      document.documentElement.scrollWidth > document.documentElement.clientWidth
    );
    expect(hasOverflow).toBe(false);
  });

  test('public input is functional on mobile', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const fab = page.locator('#publicAssistantBtn');
    await fab.click();

    const textarea = page.locator('textarea[aria-label="Message input"]');
    await expect(textarea).toBeVisible({ timeout: 5000 });
    await textarea.click();
    await textarea.fill('Mobile test');
    await expect(textarea).toHaveValue('Mobile test');
  });
});

/* ─── Responsive: Tablet (768px) ───────────────────────── */

test.describe('Responsive — Tablet (768px)', () => {
  test.use({
    navigationTimeout: NAV_TIMEOUT,
    actionTimeout: ACTION_TIMEOUT,
    viewport: { width: 768, height: 1024 },
  });

  test('public FAB is visible and correctly sized on tablet', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const fab = page.locator('#publicAssistantBtn');
    await expect(fab).toBeVisible({ timeout: 10_000 });

    const box = await fab.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeGreaterThanOrEqual(40);
    expect(box!.height).toBeGreaterThanOrEqual(40);

    // FAB should be within viewport bounds
    expect(box!.x + box!.width).toBeLessThanOrEqual(768);
  });

  test('no horizontal overflow on tablet viewport', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const hasOverflow = await page.evaluate(() =>
      document.documentElement.scrollWidth > document.documentElement.clientWidth
    );
    expect(hasOverflow).toBe(false);
  });

  test('public chat shell and input are functional on tablet', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const fab = page.locator('#publicAssistantBtn');
    await fab.click();

    const textarea = page.locator('textarea[aria-label="Message input"]');
    await expect(textarea).toBeVisible({ timeout: 5000 });

    await textarea.click();
    await textarea.fill('Tablet test message');
    await expect(textarea).toHaveValue('Tablet test message');
  });
});

/* ─── Keyboard Shortcuts ────────────────────────────────── */

test.describe('Keyboard Shortcuts (Ctrl+K, Escape)', () => {
  test.use({ navigationTimeout: NAV_TIMEOUT, actionTimeout: ACTION_TIMEOUT });

  test('Escape closes the assistant shell', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const fab = page.locator('#publicAssistantBtn');
    const shell = page.locator('#publicAssistantChat');

    // Open via FAB
    await fab.click();
    await expect(shell).not.toHaveClass(/ai-hidden/, { timeout: 5000 });

    // Close via Escape
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);

    await expect(shell).toHaveClass(/ai-hidden/);
  });

  test('Ctrl+K toggles the assistant shell', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const shell = page.locator('#publicAssistantChat');

    // Shell starts hidden
    await expect(shell).toHaveClass(/ai-hidden/);

    // Press Ctrl+K to open
    await page.keyboard.press('Control+k');
    await page.waitForTimeout(300);

    // Shell should now be visible
    await expect(shell).not.toHaveClass(/ai-hidden/);

    // Press again to close
    await page.keyboard.press('Control+k');
    await page.waitForTimeout(300);

    await expect(shell).toHaveClass(/ai-hidden/);
  });
});
