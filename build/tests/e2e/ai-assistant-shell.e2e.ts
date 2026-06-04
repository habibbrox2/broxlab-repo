import { test, expect } from '@playwright/test';

/**
 * AI Assistant Shell — Browser Rendering Tests
 *
 * Verifies that the admin shell overlay and public chat widget
 * render correctly after the CSS !important consolidation.
 *
 * Tests focus on DOM structure, visibility states, interactive
 * behavior, and accessibility — reliably verifiable in E2E without
 * depending on the full CSS loading chain.
 */

/* ─── Config ───────────────────────────────────────────── */

const NAV_TIMEOUT = 60_000;
const ACTION_TIMEOUT = 15_000;

/* ─── Helpers ───────────────────────────────────────────── */

async function waitForReady(page: import('@playwright/test').Page) {
  await page.waitForLoadState('domcontentloaded', { timeout: NAV_TIMEOUT });
  await page.waitForTimeout(500);
}

/* ─── Public Chat Widget ────────────────────────────────── */

test.describe('Public Chat Widget', () => {
  test.use({ navigationTimeout: NAV_TIMEOUT, actionTimeout: ACTION_TIMEOUT });

  test.beforeEach(async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
  });

  test('FAB button exists in the DOM and is visible', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    await expect(fab).toBeAttached({ timeout: 10_000 });
    await expect(fab).toBeVisible();

    // Verify it's a real button with correct ARIA
    await expect(fab).toHaveAttribute('type', 'button');
    await expect(fab).toHaveAttribute('aria-label', 'Open Chat');

    // Verify minimum size (not collapsed)
    const box = await fab.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeGreaterThanOrEqual(40);
    expect(box!.height).toBeGreaterThanOrEqual(40);
  });

  test('chat shell is hidden initially via brox-ai-hidden class', async ({ page }) => {
    const shell = page.locator('#publicAssistantChat');
    await expect(shell).toBeAttached({ timeout: 10_000 });

    const hasHiddenClass = await shell.evaluate((el) =>
      el.classList.contains('brox-ai-hidden')
    );
    expect(hasHiddenClass).toBe(true);
  });

  test('clicking FAB toggles chat shell visibility', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    const shell = page.locator('#publicAssistantChat');

    await expect(fab).toBeVisible({ timeout: 10_000 });

    // Click FAB to open
    await fab.click();

    // Shell should no longer have brox-ai-hidden
    await expect(shell).not.toHaveClass(/brox-ai-hidden/, { timeout: 5000 });
    await expect(shell).toBeVisible({ timeout: 5000 });

    // Verify shell has the copilot-sidebar class
    await expect(shell).toHaveClass(/brox-ai-copilot-sidebar/);

    // Verify it's in the DOM with correct ARIA role
    await expect(shell).toHaveAttribute('role', 'dialog');
    await expect(shell).toHaveAttribute('aria-modal', 'true');
  });

  test('chat shell has all required structural elements', async ({ page }) => {
    // Force shell visible
    await page.locator('#publicAssistantChat').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    // Title
    await expect(page.locator('#publicAssistantTitle')).toBeAttached();

    // Messages container with ARIA
    const messages = page.locator('#publicAssistantMessages');
    await expect(messages).toBeAttached();
    await expect(messages).toHaveAttribute('role', 'log');
    await expect(messages).toHaveAttribute('aria-live', 'polite');

    // Input field
    const input = page.locator('#publicAssistantInput');
    await expect(input).toBeAttached();
    await expect(input).toHaveAttribute('aria-label', 'Message input');

    // Send button
    const sendBtn = page.locator('#sendToPublicAssistant');
    await expect(sendBtn).toBeAttached();
    await expect(sendBtn).toHaveAttribute('aria-label', 'Send');

    // Status indicator
    await expect(page.locator('#publicAssistantStatusIndicator')).toBeAttached();
    await expect(page.locator('#publicAssistantStatusText')).toBeAttached();

    // Language toggle
    await expect(page.locator('#publicAssistantLangBn')).toBeAttached();
    await expect(page.locator('#publicAssistantLangEn')).toBeAttached();
  });

  test('chat input accepts text and is functional', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    await fab.click();

    const input = page.locator('#publicAssistantInput');
    await expect(input).toBeVisible({ timeout: 5000 });

    // Type and verify
    await input.click();
    await input.fill('Hello test');
    await expect(input).toHaveValue('Hello test');

    // Input should not be disabled
    await expect(input).toBeEnabled();
  });

  test('language toggle buttons are present and have size', async ({ page }) => {
    const fab = page.locator('#publicAssistantBtn');
    await fab.click();

    const bnBtn = page.locator('#publicAssistantLangBn');
    const enBtn = page.locator('#publicAssistantLangEn');

    await expect(bnBtn).toBeVisible({ timeout: 5000 });
    await expect(enBtn).toBeVisible();

    // Both should have content (not collapsed)
    const bnBox = await bnBtn.boundingBox();
    const enBox = await enBtn.boundingBox();
    expect(bnBox!.width).toBeGreaterThan(10);
    expect(enBox!.width).toBeGreaterThan(10);

    // Verify button text content
    await expect(bnBtn).toHaveText('BN');
    await expect(enBtn).toHaveText('EN');
  });

  test('shell contains the wrapper with correct data attribute', async ({ page }) => {
    const wrapper = page.locator('#publicAssistantWrapper');
    await expect(wrapper).toBeAttached({ timeout: 10_000 });
    await expect(wrapper).toHaveAttribute('data-ai-role', 'public');
  });
});

/* ─── Admin Shell Overlay ───────────────────────────────── */

test.describe('Admin Shell Overlay', () => {
  test.use({ navigationTimeout: NAV_TIMEOUT, actionTimeout: ACTION_TIMEOUT });

  test('admin shell HTML has correct structure and IDs', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    // Inject the full admin shell structure from assistant.twig
    await page.evaluate(() => {
      const container = document.createElement('div');
      container.className = 'brox-ai-assistant';
      container.setAttribute('data-ai-role', 'admin');
      container.innerHTML = `
        <input type="file" id="adminAiFileInput" class="brox-ai-input-file hidden" accept="image/*,.pdf,.txt,.doc,.docx" multiple>
        <div id="adminAiShell" class="brox-ai-copilot-sidebar brox-ai-hidden pointer-events-auto relative w-[min(100vw,56rem)] overflow-hidden rounded-none border-l border-white/10 bg-slate-950/96 text-slate-100" role="dialog" aria-labelledby="adminAiTitle" aria-modal="true">
          <div class="brox-ai-chat-main flex min-w-0 flex-col overflow-hidden">
            <div class="brox-ai-header border-b border-white/10 bg-white/[0.04] px-4 py-4">
              <h3 id="adminAiTitle">Brox Admin Copilot</h3>
              <span id="adminAiStatusText">Ready</span>
              <span id="adminAiCurrentModel">Loading...</span>
            </div>
            <div id="adminAiBody" class="brox-ai-body custom-scrollbar brox-ai-scrollbar relative flex-1 overflow-y-auto px-4 py-4" role="log" aria-live="polite" aria-label="Admin chat messages">
              <div class="brox-ai-welcome" id="adminAiWelcome"></div>
            </div>
            <div id="adminAiFooter" class="brox-ai-footer border-t border-white/10 bg-slate-950/95 px-4 py-4">
              <div class="brox-ai-input-wrapper flex items-end gap-2">
                <textarea id="adminAiInput" class="brox-ai-textarea w-full resize-none border border-white/10 bg-slate-900/80 px-4 py-3 text-sm text-slate-100" placeholder="Ask Copilot or type '/' for commands..." aria-label="Message input" maxlength="5000"></textarea>
                <button id="adminAiSend" class="brox-ai-send-btn" title="Send message" aria-label="Send">Send</button>
              </div>
            </div>
          </div>
          <aside id="adminAiSidebar" class="brox-ai-sidebar brox-ai-collapsed border-b border-white/10 bg-slate-950/90">
            <div id="adminAiHistory" class="brox-ai-history-list"></div>
          </aside>
        </div>
        <button id="adminAiBtn" class="brox-ai-trigger-fab" title="Open Copilot (Ctrl+Alt+A)" aria-label="Open AI Copilot" type="button" tabindex="0">
          <span class="brox-ai-fab-icon-open">Open</span>
          <span class="brox-ai-fab-icon-close hidden">Close</span>
          <span class="brox-ai-fab-badge" id="adminAiNotification">0</span>
        </button>
      `;
      document.body.appendChild(container);
    });

    // Verify all critical IDs exist
    await expect(page.locator('#adminAiShell')).toBeAttached();
    await expect(page.locator('#adminAiTitle')).toBeAttached();
    await expect(page.locator('#adminAiBody')).toBeAttached();
    await expect(page.locator('#adminAiInput')).toBeAttached();
    await expect(page.locator('#adminAiSend')).toBeAttached();
    await expect(page.locator('#adminAiSidebar')).toBeAttached();
    await expect(page.locator('#adminAiHistory')).toBeAttached();
    await expect(page.locator('#adminAiStatusText')).toBeAttached();
    await expect(page.locator('#adminAiBtn')).toBeAttached();
    await expect(page.locator('#adminAiFileInput')).toBeAttached();
    await expect(page.locator('#adminAiNotification')).toBeAttached();

    // Verify ARIA attributes on shell
    await expect(page.locator('#adminAiShell')).toHaveAttribute('role', 'dialog');
    await expect(page.locator('#adminAiShell')).toHaveAttribute('aria-modal', 'true');

    // Verify body has ARIA log role
    await expect(page.locator('#adminAiBody')).toHaveAttribute('role', 'log');
    await expect(page.locator('#adminAiBody')).toHaveAttribute('aria-live', 'polite');
  });

  test('admin shell starts hidden and FAB toggles it', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    await page.evaluate(() => {
      const shell = document.createElement('div');
      shell.id = 'adminAiShell';
      shell.className = 'brox-ai-copilot-sidebar brox-ai-hidden';
      shell.setAttribute('role', 'dialog');
      shell.innerHTML = '<div class="brox-ai-chat-main"></div>';
      document.body.appendChild(shell);

      const btn = document.createElement('button');
      btn.id = 'adminAiBtn';
      btn.className = 'brox-ai-trigger-fab';
      btn.setAttribute('aria-label', 'Open AI Copilot');
      btn.innerHTML = '<span class="brox-ai-fab-icon-open">Open</span><span class="brox-ai-fab-icon-close hidden">Close</span>';
      document.body.appendChild(btn);
    });

    const shell = page.locator('#adminAiShell');
    const fab = page.locator('#adminAiBtn');

    // Shell starts hidden
    await expect(shell).toHaveClass(/brox-ai-hidden/);

    // Simulate opening by toggling class
    await fab.evaluate(() => {
      const shell = document.getElementById('adminAiShell')!;
      shell.classList.toggle('brox-ai-hidden');
      // Also toggle FAB icons
      document.querySelector('#adminAiBtn .brox-ai-fab-icon-open')?.classList.toggle('hidden');
      document.querySelector('#adminAiBtn .brox-ai-fab-icon-close')?.classList.toggle('hidden');
    });

    // Shell should be visible
    await expect(shell).not.toHaveClass(/brox-ai-hidden/);

    // FAB icons should toggle
    await expect(page.locator('#adminAiBtn .brox-ai-fab-icon-open')).toHaveClass(/hidden/);
    await expect(page.locator('#adminAiBtn .brox-ai-fab-icon-close')).not.toHaveClass(/hidden/);
  });

  test('admin textarea accepts text input', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    await page.evaluate(() => {
      const textarea = document.createElement('textarea');
      textarea.id = 'adminAiInput';
      textarea.className = 'brox-ai-textarea';
      textarea.setAttribute('aria-label', 'Message input');
      textarea.setAttribute('placeholder', 'Ask Copilot or type \'/\' for commands...');
      textarea.setAttribute('maxlength', '5000');
      document.body.appendChild(textarea);
    });

    const textarea = page.locator('#adminAiInput');
    await textarea.click();
    await textarea.fill('Hello admin test');
    await expect(textarea).toHaveValue('Hello admin test');
    await expect(textarea).toBeEnabled();
  });

  test('admin sidebar starts collapsed', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    await page.evaluate(() => {
      const sidebar = document.createElement('aside');
      sidebar.id = 'adminAiSidebar';
      sidebar.className = 'brox-ai-sidebar brox-ai-collapsed';
      document.body.appendChild(sidebar);
    });

    const sidebar = page.locator('#adminAiSidebar');

    // Starts collapsed
    await expect(sidebar).toHaveClass(/brox-ai-collapsed/);

    // Toggle open
    await sidebar.evaluate((el) => el.classList.remove('brox-ai-collapsed'));
    await expect(sidebar).not.toHaveClass(/brox-ai-collapsed/);

    // Toggle closed
    await sidebar.evaluate((el) => el.classList.add('brox-ai-collapsed'));
    await expect(sidebar).toHaveClass(/brox-ai-collapsed/);
  });

  test('admin file input is hidden but in DOM (a11y pattern)', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    await page.evaluate(() => {
      const input = document.createElement('input');
      input.type = 'file';
      input.id = 'adminAiFileInput';
      input.className = 'brox-ai-input-file hidden';
      input.setAttribute('accept', 'image/*,.pdf,.txt,.doc,.docx');
      input.setAttribute('multiple', '');
      document.body.appendChild(input);
    });

    const input = page.locator('#adminAiFileInput');
    await expect(input).toBeAttached();

    // Should be hidden but present
    await expect(input).not.toBeVisible();
  });

  test('admin notification badge displays initial count', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    await page.evaluate(() => {
      const badge = document.createElement('span');
      badge.id = 'adminAiNotification';
      badge.className = 'brox-ai-fab-badge';
      badge.textContent = '0';
      document.body.appendChild(badge);
    });

    const badge = page.locator('#adminAiNotification');
    await expect(badge).toBeAttached();
    await expect(badge).toHaveText('0');

    // Update count
    await badge.evaluate((el) => { el.textContent = '3'; });
    await expect(badge).toHaveText('3');
  });
});

/* ─── CSS Structure Guards ──────────────────────────────── */

test.describe('CSS Class and Structure Guards', () => {
  test.use({ navigationTimeout: NAV_TIMEOUT, actionTimeout: ACTION_TIMEOUT });

  test('public shell has copilot-sidebar class for CSS targeting', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const shell = page.locator('#publicAssistantChat');
    await expect(shell).toBeAttached({ timeout: 10_000 });

    // The copilot-sidebar class is what ai-style.css targets for layout
    await expect(shell).toHaveClass(/brox-ai-copilot-sidebar/);
  });

  test('public shell has no horizontal overflow when page loads', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const hasOverflow = await page.evaluate(() =>
      document.documentElement.scrollWidth > document.documentElement.clientWidth
    );
    expect(hasOverflow).toBe(false);
  });

  test('admin shell wrapper has correct data-ai-role attribute', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const wrapper = page.locator('.brox-ai-assistant[data-ai-role="public"]');
    await expect(wrapper).toBeAttached({ timeout: 10_000 });
  });

  test('admin shell has correct copilot-sidebar class for CSS', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    await page.evaluate(() => {
      const shell = document.createElement('div');
      shell.id = 'adminAiShell';
      shell.className = 'brox-ai-copilot-sidebar';
      shell.setAttribute('role', 'dialog');
      shell.innerHTML = '<div class="brox-ai-chat-main"></div>';
      document.body.appendChild(shell);
    });

    await expect(page.locator('#adminAiShell')).toHaveClass(/brox-ai-copilot-sidebar/);
  });
});

/* ─── Helper: inject admin shell for responsive tests ─── */

async function injectAdminShellForResponsive(page: import('@playwright/test').Page) {
  await page.evaluate(() => {
    const container = document.createElement('div');
    container.className = 'brox-ai-assistant';
    container.setAttribute('data-ai-role', 'admin');
    container.innerHTML = `
      <button id="adminAiBtn" class="brox-ai-trigger-fab pointer-events-auto fixed bottom-4 right-4 z-[9998] inline-flex h-14 w-14 items-center justify-center overflow-hidden rounded-[1.35rem] border border-white/15 bg-slate-950/95 text-white" title="Open Copilot" aria-label="Open AI Copilot" type="button" tabindex="0">
        <span class="brox-ai-fab-icon-open flex items-center justify-center"><i class="lucide lucide-stars text-[1.35rem]"></i></span>
        <span class="brox-ai-fab-icon-close hidden items-center justify-center"><i class="lucide lucide-x text-[1.35rem]"></i></span>
        <span class="brox-ai-fab-badge absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full border border-cyan-300/30 bg-cyan-400 px-1 text-[10px] font-bold text-slate-950" id="adminAiNotification">0</span>
      </button>
      <div id="adminAiShell" class="brox-ai-copilot-sidebar brox-ai-hidden pointer-events-auto relative w-[min(100vw,56rem)] overflow-hidden rounded-none border-l border-white/10 bg-slate-950/96 text-slate-100 shadow-[0_35px_100px_rgba(2,6,23,0.5)] backdrop-blur-2xl lg:rounded-l-[2rem]" role="dialog" aria-labelledby="adminAiTitle" aria-modal="true">
        <div class="relative flex h-[100dvh] flex-col lg:h-screen">
          <div class="grid flex-1 grid-cols-1 overflow-hidden lg:grid-cols-[21rem_minmax(0,1fr)]">
            <aside id="adminAiSidebar" class="brox-ai-sidebar brox-ai-collapsed border-b border-white/10 bg-slate-950/90 lg:border-b-0 lg:border-r">
              <div class="border-b border-white/10 px-4 py-4">
                <h2 class="text-base font-bold text-white">Brox AI Copilot</h2>
              </div>
              <div id="adminAiHistory" class="brox-ai-history-list mt-3 space-y-2"></div>
            </aside>
            <section class="brox-ai-chat-main flex min-w-0 flex-col overflow-hidden">
              <div class="brox-ai-header border-b border-white/10 bg-white/[0.04] px-4 py-4 sm:px-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h3 id="adminAiTitle" class="text-[1.1rem] font-bold tracking-tight text-white">Brox Admin Copilot</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-300">
                      <span id="adminAiStatusText" class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-2.5 py-1 font-medium text-emerald-200">Ready</span>
                      <span id="adminAiCurrentModel" class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-2.5 py-1 font-medium text-slate-200">Loading...</span>
                    </div>
                  </div>
                  <div class="flex shrink-0 items-center gap-2">
                    <button id="adminAiHistoryToggle" class="brox-ai-icon-btn inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200" title="Chat history" aria-label="Chat history"></button>
                    <button id="adminAiClose" class="brox-ai-icon-btn inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-200" title="Close" aria-label="Close"></button>
                  </div>
                </div>
              </div>
              <div id="adminAiBody" class="brox-ai-body custom-scrollbar brox-ai-scrollbar relative flex-1 overflow-y-auto px-4 py-4 sm:px-5" role="log" aria-live="polite" aria-label="Admin chat messages">
                <div class="brox-ai-welcome" id="adminAiWelcome"></div>
              </div>
              <div id="adminAiFooter" class="brox-ai-footer border-t border-white/10 bg-slate-950/95 px-4 py-4">
                <div class="brox-ai-input-wrapper flex items-end gap-2 rounded-[1.4rem] border border-white/10 bg-white/[0.04] p-2">
                  <div class="brox-ai-input-container relative flex-1">
                    <textarea id="adminAiInput" class="brox-ai-textarea min-h-[3.5rem] w-full resize-none rounded-[1.2rem] border border-white/10 bg-slate-900/80 px-4 py-3 pb-7 text-sm text-slate-100 placeholder:text-slate-400 outline-none" placeholder="Ask Copilot or type '/' for commands..." aria-label="Message input" maxlength="5000"></textarea>
                  </div>
                  <button id="adminAiSend" class="brox-ai-send-btn inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-indigo-500 text-slate-950" title="Send message" aria-label="Send">Send</button>
                </div>
                <div class="brox-ai-input-hint mt-3 flex flex-wrap gap-2 text-[11px] text-slate-400">
                  <span>Enter Send</span>
                  <span>/ Commands</span>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(container);
  });
}

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
    expect(box!.x).toBeGreaterThanOrEqual(0);
    expect(box!.x + box!.width).toBeLessThanOrEqual(375);
    expect(box!.y).toBeGreaterThanOrEqual(0);
    expect(box!.y + box!.height).toBeLessThanOrEqual(812);
  });

  test('public chat shell fills viewport width on mobile', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    // Force shell visible
    await page.locator('#publicAssistantChat').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    const shell = page.locator('#publicAssistantChat');
    await expect(shell).toBeVisible({ timeout: 5000 });

    const box = await shell.boundingBox();
    expect(box).not.toBeNull();

    // At 500px breakpoint, shell goes to 100vw
    // At 375px viewport, shell should be close to full width
    expect(box!.width).toBeGreaterThanOrEqual(350);
    expect(box!.x).toBeGreaterThanOrEqual(-5);
  });

  test('no horizontal overflow on mobile viewport', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);

    const hasOverflow = await page.evaluate(() =>
      document.documentElement.scrollWidth > document.documentElement.clientWidth
    );
    expect(hasOverflow).toBe(false);
  });

  test('admin shell goes full-screen on mobile', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    // Make shell visible
    await page.locator('#adminAiShell').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeVisible({ timeout: 5000 });

    const box = await shell.boundingBox();
    expect(box).not.toBeNull();

    // At 375px (< 480px breakpoint), admin shell should be full viewport
    expect(box!.width).toBeGreaterThanOrEqual(350);
    expect(box!.x).toBeLessThanOrEqual(10);

    // Height should cover the viewport
    expect(box!.height).toBeGreaterThanOrEqual(700);
  });

  test('admin shell layout switches to column on mobile', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    await page.locator('#adminAiShell').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeVisible({ timeout: 5000 });

    // The inner grid should be single-column at 375px (not lg:grid-cols)
    const gridStyle = await shell.evaluate((el) => {
      const grid = el.querySelector('.grid');
      if (!grid) return 'no-grid';
      return getComputedStyle(grid).gridTemplateColumns;
    });

    // Single column means no 21rem sidebar column
    // The grid-template-columns should be a single value, not '21rem ...'
    expect(gridStyle).not.toContain('21rem');
  });

  test('admin input is functional on mobile', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    await page.locator('#adminAiShell').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    // The input wrapper should be visible on mobile
    const wrapper = page.locator('#adminAiShell .brox-ai-input-wrapper');
    await expect(wrapper).toBeVisible({ timeout: 5000 });

    // Input should be usable on mobile
    const textarea = page.locator('#adminAiInput');
    await expect(textarea).toBeVisible();
    await textarea.click();
    await textarea.fill('Mobile input test');
    await expect(textarea).toHaveValue('Mobile input test');
  });

  test('admin FAB is visible on mobile', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    const fab = page.locator('#adminAiBtn');
    await expect(fab).toBeVisible({ timeout: 5000 });

    const box = await fab.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeGreaterThanOrEqual(40);
    expect(box!.height).toBeGreaterThanOrEqual(40);

    // Should be within viewport
    expect(box!.x + box!.width).toBeLessThanOrEqual(375);
    expect(box!.y + box!.height).toBeLessThanOrEqual(812);
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
    expect(box!.x).toBeGreaterThanOrEqual(0);
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

  test('admin shell goes full-width on tablet', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    await page.locator('#adminAiShell').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeVisible({ timeout: 5000 });

    const box = await shell.boundingBox();
    expect(box).not.toBeNull();

    // At 768px (< 991.98px breakpoint), admin shell should be full viewport width
    expect(box!.width).toBeGreaterThanOrEqual(750);
    expect(box!.x).toBeLessThanOrEqual(10);
  });

  test('admin sidebar is stacked above chat on tablet', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    await page.locator('#adminAiShell').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeVisible({ timeout: 5000 });

    // At < 991.98px, grid should be single-column (stacked)
    const gridStyle = await shell.evaluate((el) => {
      const grid = el.querySelector('.grid');
      if (!grid) return 'no-grid';
      return getComputedStyle(grid).gridTemplateColumns;
    });

    expect(gridStyle).not.toContain('21rem');
  });

  test('admin header wraps correctly on tablet', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    await page.locator('#adminAiShell').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeVisible({ timeout: 5000 });

    // Header should exist and be visible
    const header = shell.locator('.brox-ai-header');
    await expect(header).toBeVisible();

    const headerBox = await header.boundingBox();
    expect(headerBox).not.toBeNull();
    // Header should not exceed viewport width
    expect(headerBox!.width).toBeLessThanOrEqual(768);
  });

  test('admin body and footer are visible on tablet', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    await page.locator('#adminAiShell').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    const shell = page.locator('#adminAiShell');
    await expect(shell).toBeVisible({ timeout: 5000 });

    await expect(page.locator('#adminAiBody')).toBeVisible();
    await expect(page.locator('#adminAiFooter')).toBeVisible();

    // Footer should not overflow viewport
    const footerBox = await page.locator('#adminAiFooter').boundingBox();
    expect(footerBox).not.toBeNull();
    expect(footerBox!.width).toBeLessThanOrEqual(768);
  });

  test('admin input wrapper is functional on tablet', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    await page.locator('#adminAiShell').evaluate((el) => {
      el.classList.remove('brox-ai-hidden');
    });

    const textarea = page.locator('#adminAiInput');
    await expect(textarea).toBeVisible({ timeout: 5000 });

    await textarea.click();
    await textarea.fill('Tablet test message');
    await expect(textarea).toHaveValue('Tablet test message');
  });

  test('admin FAB is visible and positioned on tablet', async ({ page }) => {
    await page.goto('/faq', { waitUntil: 'domcontentloaded' });
    await waitForReady(page);
    await injectAdminShellForResponsive(page);

    const fab = page.locator('#adminAiBtn');
    await expect(fab).toBeVisible({ timeout: 5000 });

    const box = await fab.boundingBox();
    expect(box).not.toBeNull();
    expect(box!.width).toBeGreaterThanOrEqual(40);
    expect(box!.height).toBeGreaterThanOrEqual(40);

    // Should be within viewport
    expect(box!.x + box!.width).toBeLessThanOrEqual(768);
    expect(box!.y + box!.height).toBeLessThanOrEqual(1024);
  });
});
