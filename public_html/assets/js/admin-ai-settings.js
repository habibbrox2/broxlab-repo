document.addEventListener('DOMContentLoaded', () => {
  const aiProviderSelect = document.getElementById('ai-provider');
  const aiApiKeyInput = document.getElementById('ai-api-key');
  const saveAISettingsBtn = document.getElementById('save-ai-settings');

  if (aiProviderSelect && aiApiKeyInput && saveAISettingsBtn) {
    saveAISettingsBtn.addEventListener('click', async () => {
      const provider = aiProviderSelect.value;
      const apiKey = aiApiKeyInput.value;

      if (!apiKey) {
        alert('Please enter an API key');
        return;
      }

      try {
        const response = await fetch('/admin/settings/ai', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({ provider, apiKey, }),
        });

        const result = await response.json();

        if (result.success) {
          alert('AI settings saved successfully');
        } else {
          alert(`Failed to save AI settings: ${ result.error}`);
        }
      } catch (error) {
        console.error('Error saving AI settings:', error);
        alert('An error occurred while saving AI settings');
      }
    });
  }

  const mcpServerUrlInput = document.getElementById('mcp-server-url');
  const mcpApiKeyInput = document.getElementById('mcp-api-key');
  const saveMCPSettingsBtn = document.getElementById('save-mcp-settings');

  if (mcpServerUrlInput && mcpApiKeyInput && saveMCPSettingsBtn) {
    saveMCPSettingsBtn.addEventListener('click', async () => {
      const serverUrl = mcpServerUrlInput.value;
      const apiKey = mcpApiKeyInput.value;

      if (!serverUrl || !apiKey) {
        alert('Please enter both server URL and API key');
        return;
      }

      try {
        const response = await fetch('/admin/settings/mcp', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: JSON.stringify({ serverUrl, apiKey, }),
        });

        const result = await response.json();

        if (result.success) {
          alert('MCP settings saved successfully');
        } else {
          alert(`Failed to save MCP settings: ${ result.error}`);
        }
      } catch (error) {
        console.error('Error saving MCP settings:', error);
        alert('An error occurred while saving MCP settings');
      }
    });
  }
});