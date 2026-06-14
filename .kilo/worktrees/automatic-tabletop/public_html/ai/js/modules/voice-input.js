/**
 * Voice Input Handler Module
 * Handles Web Speech API for voice input (lazy-loaded, only initialized on demand)
 * Microphone permission is only requested if user explicitly clicks voice input button
 */
export default class VoiceInputHandler {
    constructor() {
        this.recognition = null;
        this.isListening = false;
        this.initialized = false;
    }

    isSupported() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        return SpeechRecognition !== undefined;
    }

    /**
     * Initialize SpeechRecognition on-demand (only when user wants to use voice)
     * This prevents microphone permission requests until actually needed
     */
    initializeRecognition() {
        if (this.initialized) return true;

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            console.warn('[VoiceInputHandler] Web Speech API not supported');
            return false;
        }

        try {
            this.recognition = new SpeechRecognition();
            this.recognition.continuous = false;
            this.recognition.interimResults = true;
            this.recognition.lang = 'en-US';
            this.initialized = true;
            return true;
        } catch (error) {
            console.error('[VoiceInputHandler] Failed to initialize:', error);
            return false;
        }
    }

    startListening(onResult, onError) {
        // Only initialize when user actually tries to use voice input
        if (!this.initializeRecognition()) {
            if (onError) onError('not-supported');
            return;
        }

        if (!this.recognition) return;

        this.isListening = true;

        this.recognition.onresult = (event) => {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            if (onResult) onResult(transcript);
        };

        this.recognition.onerror = (event) => {
            this.isListening = false;
            if (onError) onError(event.error);
        };

        this.recognition.onend = () => {
            this.isListening = false;
        };

        try {
            this.recognition.start();
        } catch (error) {
            console.error('[VoiceInputHandler] Error starting recognition:', error);
            if (onError) onError('error');
        }
    }

    stopListening() {
        if (this.recognition && this.isListening) {
            this.recognition.stop();
            this.isListening = false;
        }
    }
}
