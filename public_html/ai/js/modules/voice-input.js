/**
 * Voice Input Handler for AI Assistant
 * Path: /public_html/ai/js/modules/voice-input.js
 * 
 * Features:
 *  - Web Speech API integration
 *  - Microphone permission handling
 *  - Real-time transcription
 *  - Visual feedback (recording indicator)
 *  - Language detection from admin settings
 *  - Fallback for unsupported browsers
 */

export class VoiceInputHandler {
    constructor(config = {}) {
        this.config = {
            micButtonId: 'adminAiMic',
            inputFieldId: 'adminAiInput',
            ...config,
        };

        this.isRecording = false;
        this.recognition = null;
        this.transcript = '';
        this.isSpeechApiSupported = this.checkSpeechApiSupport();

        if (this.isSpeechApiSupported) {
            this.init();
        }
    }

    /**
     * Check if Web Speech API is supported
     */
    checkSpeechApiSupport() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        return !!SpeechRecognition;
    }

    /**
     * Initialize voice input handler
     */
    init() {
        const micButton = document.getElementById(this.config.micButtonId);
        if (!micButton) return;

        // Setup recognition object
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.recognition = new SpeechRecognition();

        this.setupRecognition();
        this.setupMicButton(micButton);
        this.setupKeyboardShortcut();
    }

    /**
     * Setup speech recognition
     */
    setupRecognition() {
        if (!this.recognition) return;

        // Get language from settings or detect
        const language = this.getLanguageCode();

        this.recognition.continuous = true;
        this.recognition.interimResults = true;
        this.recognition.language = language;

        this.recognition.onstart = () => {
            this.isRecording = true;
            this.updateMicButtonState();
            this.showRecordingIndicator();
        };

        this.recognition.onresult = (event) => {
            this.transcript = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                this.transcript += transcript;

                if (event.results[i].isFinal) {
                    this.insertTranscript(this.transcript);
                    this.transcript = '';
                } else {
                    // Show interim results
                    this.showInterimTranscript(this.transcript);
                }
            }
        };

        this.recognition.onerror = (event) => {
            this.handleRecognitionError(event.error);
        };

        this.recognition.onend = () => {
            this.isRecording = false;
            this.updateMicButtonState();
            this.hideRecordingIndicator();
        };
    }

    /**
     * Setup microphone button
     */
    setupMicButton(button) {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleRecording();
        });

        // Show visual feedback on hover
        button.addEventListener('mouseenter', () => {
            if (!this.isSpeechApiSupported) {
                button.title = 'Voice input not supported in this browser';
                button.style.opacity = '0.5';
                button.style.cursor = 'not-allowed';
            }
        });
    }

    /**
     * Setup keyboard shortcut for voice (e.g., Ctrl+Shift+V)
     */
    setupKeyboardShortcut() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+Shift+V to start/stop recording
            if (e.ctrlKey && e.shiftKey && e.code === 'KeyV') {
                e.preventDefault();
                this.toggleRecording();
            }
        });
    }

    /**
     * Toggle recording on/off
     */
    toggleRecording() {
        if (!this.isSpeechApiSupported) {
            console.warn('Speech Recognition API not supported');
            return;
        }

        if (this.isRecording) {
            this.stopRecording();
        } else {
            this.startRecording();
        }
    }

    /**
     * Start recording
     */
    startRecording() {
        if (!this.recognition || this.isRecording) return;

        try {
            this.transcript = '';
            this.recognition.start();
        } catch (err) {
            console.error('Error starting recording:', err);
        }
    }

    /**
     * Stop recording
     */
    stopRecording() {
        if (!this.recognition) return;

        try {
            this.recognition.stop();
        } catch (err) {
            console.error('Error stopping recording:', err);
        }
    }

    /**
     * Insert transcript into input field
     */
    insertTranscript(text) {
        const inputField = document.getElementById(this.config.inputFieldId);
        if (!inputField) return;

        // Append to existing text
        const currentValue = inputField.value.trim();
        const newValue = currentValue ? currentValue + ' ' + text : text;

        inputField.value = newValue;

        // Trigger input event for auto-save or other listeners
        inputField.dispatchEvent(new Event('input', { bubbles: true }));

        // Auto-focus
        inputField.focus();

        // Show confirmation
        this.showTranscriptNotification(text);
    }

    /**
     * Show interim transcript while recording
     */
    showInterimTranscript(text) {
        const inputField = document.getElementById(this.config.inputFieldId);
        if (!inputField) return;

        // Create/update interim indicator
        let interim = inputField.parentElement.querySelector('.interim-transcript');
        if (!interim) {
            interim = document.createElement('div');
            interim.className = 'interim-transcript';
            interim.style.cssText = `
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 8px 12px;
        font-size: 0.9em;
        color: var(--assistant-muted);
        font-style: italic;
        border-top: 1px dashed var(--assistant-border);
      `;
            inputField.parentElement.appendChild(interim);
        }

        interim.textContent = `Transcribing: ${text}...`;
    }

    /**
     * Show transcript notification
     */
    showTranscriptNotification(text) {
        const notification = document.createElement('div');
        notification.style.cssText = `
      position: fixed;
      bottom: 80px;
      right: 20px;
      background: var(--assistant-success);
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 0.8em;
      animation: slideInLeft 0.2s ease-out;
      z-index: 10000;
    `;

        notification.textContent = `✓ Transcribed: "${text.substring(0, 50)}${text.length > 50 ? '...' : ''}"`;
        document.body.appendChild(notification);

        setTimeout(() => notification.remove(), 2000);
    }

    /**
     * Handle recognition errors
     */
    handleRecognitionError(error) {
        let message = 'Voice input error';

        switch (error) {
            case 'no-speech':
                message = 'No speech detected. Please try again.';
                break;
            case 'audio-capture':
                message = 'No microphone found. Please check your device.';
                break;
            case 'network':
                message = 'Network error. Please try again.';
                break;
            case 'permission-denied':
                message = 'Microphone permission denied.';
                break;
            default:
                message = `Error: ${error}`;
        }

        console.error('Speech Recognition Error:', message);
        this.showErrorNotification(message);
    }

    /**
     * Show error notification
     */
    showErrorNotification(message) {
        const notification = document.createElement('div');
        notification.style.cssText = `
      position: fixed;
      bottom: 80px;
      right: 20px;
      background: var(--assistant-error);
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 0.8em;
      animation: slideInLeft 0.2s ease-out;
      z-index: 10000;
    `;

        notification.textContent = `✗ ${message}`;
        document.body.appendChild(notification);

        setTimeout(() => notification.remove(), 3000);
    }

    /**
     * Show recording indicator
     */
    showRecordingIndicator() {
        const micButton = document.getElementById(this.config.micButtonId);
        if (!micButton) return;

        let indicator = micButton.querySelector('.recording-indicator');
        if (!indicator) {
            indicator = document.createElement('span');
            indicator.className = 'recording-indicator';
            indicator.style.cssText = `
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--assistant-error);
        margin-left: 4px;
        animation: pulse 1s infinite;
      `;
            micButton.appendChild(indicator);
        }

        micButton.classList.add('recording');
        micButton.style.background = 'rgba(239, 68, 68, 0.1)';
    }

    /**
     * Hide recording indicator
     */
    hideRecordingIndicator() {
        const micButton = document.getElementById(this.config.micButtonId);
        if (!micButton) return;

        const indicator = micButton.querySelector('.recording-indicator');
        if (indicator) {
            indicator.remove();
        }

        micButton.classList.remove('recording');
        micButton.style.background = '';

        const interim = document.querySelector('.interim-transcript');
        if (interim) {
            interim.remove();
        }
    }

    /**
     * Update microphone button state
     */
    updateMicButtonState() {
        const micButton = document.getElementById(this.config.micButtonId);
        if (!micButton) return;

        if (this.isRecording) {
            micButton.setAttribute('aria-pressed', 'true');
            micButton.classList.add('active');
        } else {
            micButton.setAttribute('aria-pressed', 'false');
            micButton.classList.remove('active');
        }
    }

    /**
     * Get language code from settings or browser
     */
    getLanguageCode() {
        // Try to get from admin settings
        const reasoningSelect = document.getElementById('adminAiResponseFormat');
        const htmlLang = document.documentElement.lang;

        // Map common language codes
        const langMap = {
            'bn': 'bn-BD', // Bengali
            'en': 'en-US',
            'es': 'es-ES',
            'fr': 'fr-FR',
            'de': 'de-DE',
            'ja': 'ja-JP',
            'ko': 'ko-KR',
            'zh': 'zh-CN',
            'pt': 'pt-BR',
            'ru': 'ru-RU',
            'ar': 'ar-SA',
            'hi': 'hi-IN',
        };

        const baseLang = htmlLang ? htmlLang.split('-')[0] : 'en';
        return langMap[baseLang] || langMap['en'];
    }

    /**
     * Check if browser supports speech API
     */
    isSupported() {
        return this.isSpeechApiSupported;
    }

    /**
     * Show unsupported message
     */
    showUnsupportedMessage() {
        if (!this.isSpeechApiSupported) {
            const micButton = document.getElementById(this.config.micButtonId);
            if (micButton) {
                micButton.disabled = true;
                micButton.title = 'Voice input not supported. Please use text instead.';
                micButton.style.opacity = '0.5';
                micButton.style.cursor = 'not-allowed';
            }
        }
    }
}

export default VoiceInputHandler;
