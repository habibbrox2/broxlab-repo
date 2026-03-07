/**
 * AI Content Enhancer Module
 * Handles content enhancement for RTE via admin assistant
 */

export function createContentEnhancer({ rteEditorId = 'content' } = {}) {
  const state = {
    originalContent: null,
    isEnhancing: false,
    rteEditorId
  };

  const elements = {
    enhanceBtn: document.getElementById('aiEnhanceBtn'),
    statusDiv: document.getElementById('aiEnhanceStatus'),
    undoBtn: document.getElementById('aiUndoBtn'),
    sidebar: document.getElementById('aiContentSidebar'),
    floatBtn: document.getElementById('aiEnhanceFloatBtn'),
    floatUndoBtn: document.getElementById('aiUndoBtn')
  };

  function showFloatStatus(type, message) {
    const floatStatus = document.querySelector('.ai-float-status');
    if (!floatStatus) return;
    
    floatStatus.textContent = message || '';
    floatStatus.className = 'ai-float-status show ' + (type || '');
    
    if (type !== 'loading') {
      setTimeout(() => {
        floatStatus.classList.remove('show');
      }, 3000);
    }
  }

  function hideFloatStatus() {
    const floatStatus = document.querySelector('.ai-float-status');
    if (floatStatus) {
      floatStatus.classList.remove('show');
    }
  }

  function showFloatUndoButton() {
    const floatUndoBtn = document.querySelector('.ai-undo-float-btn');
    if (floatUndoBtn) {
      floatUndoBtn.classList.add('show');
    }
  }

  function hideFloatUndoButton() {
    const floatUndoBtn = document.querySelector('.ai-undo-float-btn');
    if (floatUndoBtn) {
      floatUndoBtn.classList.remove('show');
    }
  }

  function getRTEEditor() {
    const editorWindow = window[`editor_${state.rteEditorId}`];
    if (!editorWindow) {
      console.warn(`RTE editor ${state.rteEditorId} not found`);
      return null;
    }
    return editorWindow;
  }

  function setStatus(type, message) {
    // Original sidebar status
    if (elements.statusDiv) {
      elements.statusDiv.innerHTML = '';
      
      if (!message) return;

      const statusEl = document.createElement('div');
      statusEl.className = `ai-status-${type}`;
      statusEl.textContent = message;
      statusEl.setAttribute('role', 'status');
      statusEl.setAttribute('aria-live', 'polite');
      
      elements.statusDiv.appendChild(statusEl);
    }
    
    // Also show float status if sidebar doesn't exist
    if (!elements.sidebar && (type && message)) {
      showFloatStatus(type, message);
    }
  }

  function clearStatus() {
    setStatus(null);
    hideFloatStatus();
  }

  function setEnhancingState(enhancing) {
    state.isEnhancing = enhancing;
    
    if (elements.enhanceBtn) {
      elements.enhanceBtn.disabled = enhancing;
      if (enhancing) {
        elements.enhanceBtn.innerHTML = '<i class="bi bi-hourglass"></i> Enhancing...';
        elements.enhanceBtn.classList.add('loading');
      } else {
        elements.enhanceBtn.innerHTML = '<i class="bi bi-stars"></i>\n        ✨ Enhance Content';
        elements.enhanceBtn.classList.remove('loading');
      }
    }
    
    // Also update floating button state
    if (elements.floatBtn) {
      elements.floatBtn.disabled = enhancing;
      if (enhancing) {
        elements.floatBtn.classList.add('loading');
        elements.floatBtn.innerHTML = '<i class="bi bi-hourglass"></i>';
      } else {
        elements.floatBtn.classList.remove('loading');
        elements.floatBtn.innerHTML = '<i class="bi bi-stars"></i>';
      }
    }
  }

  function showUndoButton() {
    if (elements.undoBtn) {
      elements.undoBtn.classList.remove('d-none');
    }
    showFloatUndoButton();
  }

  function hideUndoButton() {
    if (elements.undoBtn) {
      elements.undoBtn.classList.add('d-none');
    }
    hideFloatUndoButton();
  }

  async function enhanceContent() {
    const editor = getRTEEditor();
    if (!editor) {
      setStatus('error', 'Editor not found');
      return;
    }

    const currentContent = editor.getContent?.();
    if (!currentContent || currentContent.trim().length === 0) {
      setStatus('error', 'No content to enhance');
      return;
    }

    // Save original content for undo
    state.originalContent = currentContent;

    setStatus('loading', 'Analyzing and enhancing content...');
    setEnhancingState(true);

    try {
      // Create enhancement request for admin assistant
      const enhancementPrompt = buildEnhancementPrompt(currentContent);
      
      // Send to admin assistant window if available
      if (window.parent !== window && window.parent.postMessage) {
        // We're in an iframe - send to parent
        window.parent.postMessage(
          {
            type: 'enhance_content_request',
            content: currentContent,
            prompt: enhancementPrompt
          },
          '*'
        );
      } else {
        // Direct call to assistant if available
        await processEnhancementDirectly(enhancementPrompt);
      }
    } catch (error) {
      console.error('Enhancement error:', error);
      setStatus('error', 'Enhancement failed: ' + (error.message || 'Unknown error'));
      setEnhancingState(false);
    }
  }

  async function processEnhancementDirectly(prompt) {
    try {
      // Wait for enhanceContentWithAI to be available (it's exposed by modules/admin/app.js)
      let attempts = 0;
      while (!window.enhanceContentWithAI && attempts < 50) {
        await new Promise(resolve => setTimeout(resolve, 100));
        attempts++;
      }

      if (!window.enhanceContentWithAI) {
        setStatus('error', 'AI enhancement not available on this page');
        setEnhancingState(false);
        return;
      }

      const response = await window.enhanceContentWithAI({
        content: state.originalContent,
        prompt: prompt
      });

      if (response && response.enhanced) {
        const editor = getRTEEditor();
        if (editor && editor.setContent) {
          // Save current state in RTE history for undo
          if (editor.saveToHistory) {
            editor.saveToHistory();
          }

          // Set the enhanced content
          editor.setContent(response.enhanced);

          setStatus('success', 'Content enhanced successfully!');
          showUndoButton();

          // Clear status after 3 seconds
          setTimeout(() => {
            clearStatus();
          }, 3000);
        }
      } else {
        setStatus('error', 'Enhancement returned no results');
      }
    } catch (error) {
      console.error('Direct enhancement error:', error);
      setStatus('error', 'Enhancement request failed');
    } finally {
      setEnhancingState(false);
    }
  }

  function undoEnhancement() {
    const editor = getRTEEditor();
    if (!editor || !state.originalContent) {
      setStatus('error', 'Cannot undo - original content not found');
      return;
    }

    editor.setContent(state.originalContent);
    state.originalContent = null;
    setStatus('success', 'Content restored to original version');
    hideUndoButton();

    // Clear status after 3 seconds
    setTimeout(() => {
      clearStatus();
    }, 3000);
  }

  function buildEnhancementPrompt(content) {
    return `You are a Bengali-Bangla content enhancement expert. Your task is to improve the following HTML content.

IMPORTANT INSTRUCTIONS:
1. Return ONLY valid, clean HTML - NO markdown, NO fenced code blocks, just HTML
2. Improve grammar and spelling in Bengali and English
3. Enhance Bengali language quality and naturalness
4. Improve content structure with proper heading hierarchy (h1, h2, h3, etc.)
5. Optimize for SEO by improving clarity and adding logical structure
6. Apply professional formatting with proper paragraph breaks
7. Preserve all embedded images, links, and custom formatting
8. Keep the original meaning and intent
9. Do NOT add explanations, do NOT add code fences, do NOT add markdown

CONTENT TO ENHANCE:
${content}

Return ONLY the enhanced HTML content, nothing else.`;
  }

  function init() {
    if (!elements.sidebar && !elements.floatBtn) {
      console.warn('AI Content Sidebar or Float Button not found in DOM');
      return;
    }

    if (elements.enhanceBtn) {
      elements.enhanceBtn.addEventListener('click', enhanceContent);
    }

    if (elements.undoBtn) {
      elements.undoBtn.addEventListener('click', undoEnhancement);
    }
    
    // Add floating button event listener
    if (elements.floatBtn) {
      elements.floatBtn.addEventListener('click', enhanceContent);
    }
    
    // Add float undo button event listener
    const floatUndoBtn = document.querySelector('.ai-undo-float-btn');
    if (floatUndoBtn) {
      floatUndoBtn.addEventListener('click', undoEnhancement);
    }

    // Listen for enhancement responses from parent window
    window.addEventListener('message', (event) => {
      if (event.data?.type === 'enhance_content_response') {
        handleEnhancementResponse(event.data);
      }
    });
  }

  function handleEnhancementResponse(data) {
    if (data.error) {
      setStatus('error', data.error);
      setEnhancingState(false);
      return;
    }

    if (data.enhanced) {
      const editor = getRTEEditor();
      if (editor && editor.setContent) {
        if (editor.saveToHistory) {
          editor.saveToHistory();
        }
        editor.setContent(data.enhanced);
        setStatus('success', 'Content enhanced successfully!');
        showUndoButton();

        setTimeout(() => {
          clearStatus();
        }, 3000);
      }
    }

    setEnhancingState(false);
  }

  // Public API
  return {
    init,
    enhance: enhanceContent,
    undo: undoEnhancement,
    setStatus,
    clearStatus
  };
}

// Auto-initialize if sidebar or float button exists
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('aiContentSidebar');
  const floatBtn = document.getElementById('aiEnhanceFloatBtn');
  
  if (sidebar || floatBtn) {
    const enhancer = createContentEnhancer({ rteEditorId: 'content' });
    enhancer.init();
    // Make enhancer globally accessible for admin assistant
    window.contentEnhancer = enhancer;
  }
});
