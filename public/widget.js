/**
 * DocWise Chat Widget
 * A standalone embeddable chat widget for customer support with RAG-powered responses
 * 
 * @version 1.0.1
 * @license MIT
 */

(function() {
    'use strict';

    // ============================================================================
    // CONFIGURATION & CONSTANTS
    // ============================================================================

    const DEFAULT_CONFIG = {
        apiUrl: window.location.origin,
        apiKey: null,
        position: 'bottom-right',
        primaryColor: '#3B82F6',
        theme: 'light',
        greeting: 'Hi! How can we help you?',
        placeholder: 'Type your message...',
        buttonSize: 60,
        windowWidth: 400,
        windowHeight: 600,
        showCitations: true,
        showFeedback: true,
        zIndex: 999999,
        maxMessageLength: 2000
    };

    const STORAGE_KEY = 'docwise-widget-session';
    const RETRY_ATTEMPTS = 3;
    const RETRY_DELAY = 1000; // Base delay in ms
    const REQUEST_TIMEOUT = 30000; // 30 seconds

    // ============================================================================
    // UTILITY FUNCTIONS
    // ============================================================================

    const Utils = {
        /**
         * Generate a simple UUID v4
         */
        generateUUID: function() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Format timestamp to readable time
         */
        formatTime: function(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        /**
         * Simple markdown-like formatting
         */
        formatMessage: function(text) {
            text = this.escapeHtml(text);
            // Bold: **text**
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            // Italic: *text*
            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
            // Line breaks
            text = text.replace(/\n/g, '<br>');
            // Auto-link URLs
            text = text.replace(
                /(https?:\/\/[^\s]+)/g,
                '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
            );
            return text;
        },

        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Sleep utility for retry logic
         */
        sleep: function(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
    };

    // ============================================================================
    // API CLIENT
    // ============================================================================

    class APIClient {
        constructor(apiUrl, apiKey) {
            this.apiUrl = apiUrl;
            this.apiKey = apiKey;
        }

        /**
         * Make HTTP request with retry logic
         */
        async request(endpoint, options = {}, attempt = 1) {
            const url = `${this.apiUrl}/api/widget${endpoint}`;
            const config = {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.apiKey}`,
                    ...options.headers
                }
            };

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), REQUEST_TIMEOUT);

            try {
                const response = await fetch(url, {
                    ...config,
                    signal: controller.signal
                });

                clearTimeout(timeoutId);

                // Handle rate limiting
                if (response.status === 429) {
                    throw {
                        type: 'rate_limit',
                        message: 'Too many requests. Please wait a moment.',
                        status: 429
                    };
                }

                // Handle authentication errors
                if (response.status === 401) {
                    throw {
                        type: 'auth_error',
                        message: 'Invalid API key. Please check your configuration.',
                        status: 401
                    };
                }

                // Parse response
                const data = await response.json();

                if (!response.ok) {
                    throw {
                        type: 'api_error',
                        message: data.message || 'An error occurred',
                        status: response.status,
                        data: data
                    };
                }

                return data;

            } catch (error) {
                clearTimeout(timeoutId);

                // Don't retry rate limits or auth errors
                if (error.type === 'rate_limit' || error.type === 'auth_error') {
                    throw error;
                }

                // Retry on network errors or 5xx errors
                if (attempt < RETRY_ATTEMPTS) {
                    const delay = RETRY_DELAY * Math.pow(2, attempt - 1);
                    await Utils.sleep(delay);
                    return this.request(endpoint, options, attempt + 1);
                }

                // Final failure
                throw {
                    type: 'network_error',
                    message: error.message || 'Unable to connect. Please check your internet connection.',
                    originalError: error
                };
            }
        }

        /**
         * Send a chat message
         */
        async sendMessage(message, sessionId = null, userMetadata = null) {
            const body = {
                message: message
            };

            if (sessionId) {
                body.session_id = sessionId;
            }

            if (userMetadata) {
                body.user_metadata = userMetadata;
            }

            return await this.request('/chat', {
                method: 'POST',
                body: JSON.stringify(body)
            });
        }

        /**
         * Load chat history for a session
         */
        async loadMessages(sessionId) {
            return await this.request(`/sessions/${sessionId}/messages`, {
                method: 'GET'
            });
        }

        /**
         * Submit feedback for a message
         */
        async submitFeedback(sessionId, messageId, type, rating = null, comment = null) {
            const body = {
                message_id: messageId,
                type: type
            };

            if (rating !== null) {
                body.rating = rating;
            }

            if (comment !== null) {
                body.comment = comment;
            }

            return await this.request(`/sessions/${sessionId}/feedback`, {
                method: 'POST',
                body: JSON.stringify(body)
            });
        }
    }

    // ============================================================================
    // MAIN WIDGET CLASS
    // ============================================================================

    class DocWiseWidget {
        constructor(config) {
            this.config = { ...DEFAULT_CONFIG, ...config };
            this.state = {
                isOpen: false,
                sessionId: null,
                messages: [],
                isLoading: false,
                unreadCount: 0,
                feedbackGiven: new Set() // Track message IDs that have feedback
            };

            // Validate required config
            if (!this.config.apiKey) {
                throw new Error('DocWiseChat: apiKey is required');
            }

            // Initialize API client
            this.apiClient = new APIClient(this.config.apiUrl, this.config.apiKey);

            // DOM elements (will be created lazily)
            this.elements = {
                button: null,
                container: null,
                messagesContainer: null,
                input: null
            };

            // Initialize
            this.init();
        }

        /**
         * Initialize the widget
         */
        init() {
            // Load session from storage
            this.loadSession();

            // Inject styles
            this.injectStyles();

            // Create UI
            this.createButton();

            // Load chat history if session exists
            if (this.state.sessionId) {
                this.loadChatHistory();
            }
        }

        /**
         * Inject CSS styles into document
         */
        injectStyles() {
            if (document.getElementById('docwise-widget-styles')) {
                return; // Already injected
            }

            const isDark = this.config.theme === 'dark';
            const primaryColor = this.config.primaryColor;

            const styles = `
                /* Widget Button */
                #docwise-chat-button {
                    position: fixed;
                    ${this.config.position.includes('bottom') ? 'bottom: 20px;' : 'top: 20px;'}
                    ${this.config.position.includes('right') ? 'right: 20px;' : 'left: 20px;'}
                    width: ${this.config.buttonSize}px;
                    height: ${this.config.buttonSize}px;
                    background: ${primaryColor};
                    border-radius: 50%;
                    cursor: pointer;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: ${this.config.zIndex};
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    transition: all 0.3s ease;
                    border: none;
                }

                #docwise-chat-button:hover {
                    transform: scale(1.1);
                    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
                }

                #docwise-chat-button .unread-badge {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #EF4444;
                    color: white;
                    border-radius: 10px;
                    padding: 2px 6px;
                    font-size: 12px;
                    font-weight: 600;
                    min-width: 20px;
                    text-align: center;
                }

                /* Widget Container */
                #docwise-chat-container {
                    position: fixed;
                    ${this.config.position.includes('bottom') ? 'bottom: 90px;' : 'top: 90px;'}
                    ${this.config.position.includes('right') ? 'right: 20px;' : 'left: 20px;'}
                    width: ${this.config.windowWidth}px;
                    height: ${this.config.windowHeight}px;
                    max-width: calc(100vw - 40px);
                    max-height: calc(100vh - 120px);
                    background: ${isDark ? '#1F2937' : '#FFFFFF'};
                    border-radius: 12px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
                    z-index: ${this.config.zIndex - 1};
                    display: flex;
                    flex-direction: column;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    overflow: hidden;
                    transform: scale(0.9);
                    opacity: 0;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    pointer-events: none;
                }

                #docwise-chat-container.open {
                    transform: scale(1);
                    opacity: 1;
                    pointer-events: auto;
                }

                /* Header */
                .docwise-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 16px;
                    border-bottom: 1px solid ${isDark ? '#374151' : '#E5E7EB'};
                    background: ${isDark ? '#111827' : '#F9FAFB'};
                }

                .docwise-header-title {
                    font-size: 16px;
                    font-weight: 600;
                    color: ${isDark ? '#F9FAFB' : '#1F2937'};
                    margin: 0;
                }

                .docwise-close-btn {
                    background: none;
                    border: none;
                    font-size: 24px;
                    color: ${isDark ? '#9CA3AF' : '#6B7280'};
                    cursor: pointer;
                    padding: 4px 8px;
                    line-height: 1;
                    border-radius: 4px;
                    transition: background 0.2s;
                }

                .docwise-close-btn:hover {
                    background: ${isDark ? '#374151' : '#F3F4F6'};
                }

                /* Messages Container */
                .docwise-messages {
                    flex: 1;
                    overflow-y: auto;
                    padding: 16px;
                    display: flex;
                    flex-direction: column;
                    gap: 16px;
                }

                .docwise-messages::-webkit-scrollbar {
                    width: 6px;
                }

                .docwise-messages::-webkit-scrollbar-track {
                    background: transparent;
                }

                .docwise-messages::-webkit-scrollbar-thumb {
                    background: ${isDark ? '#4B5563' : '#D1D5DB'};
                    border-radius: 3px;
                }

                /* Welcome Message */
                .docwise-welcome {
                    text-align: center;
                    padding: 32px 16px;
                    color: ${isDark ? '#9CA3AF' : '#6B7280'};
                }

                .docwise-welcome h4 {
                    margin: 0 0 8px 0;
                    font-size: 18px;
                    color: ${isDark ? '#F9FAFB' : '#1F2937'};
                }

                .docwise-welcome p {
                    margin: 0;
                    font-size: 14px;
                }

                /* Message */
                .docwise-message {
                    display: flex;
                    flex-direction: column;
                    animation: messageSlide 0.3s ease;
                }

                @keyframes messageSlide {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .docwise-message.user {
                    align-items: flex-end;
                }

                .docwise-message.assistant {
                    align-items: flex-start;
                }

                .docwise-message-bubble {
                    max-width: 80%;
                    padding: 12px 16px;
                    border-radius: 16px;
                    word-wrap: break-word;
                }

                .docwise-message.user .docwise-message-bubble {
                    background: ${primaryColor};
                    color: white;
                    border-bottom-right-radius: 4px;
                }

                .docwise-message.assistant .docwise-message-bubble {
                    background: ${isDark ? '#374151' : '#F3F4F6'};
                    color: ${isDark ? '#F9FAFB' : '#1F2937'};
                    border-bottom-left-radius: 4px;
                }

                .docwise-message-text {
                    font-size: 14px;
                    line-height: 1.5;
                }

                .docwise-message-text a {
                    color: inherit;
                    text-decoration: underline;
                }

                .docwise-message-time {
                    font-size: 11px;
                    color: ${isDark ? '#9CA3AF' : '#9CA3AF'};
                    margin-top: 4px;
                    padding: 0 8px;
                }

                /* Citations */
                .docwise-citations {
                    margin-top: 12px;
                    padding-top: 12px;
                    border-top: 1px solid ${isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)'};
                }

                .docwise-citations-label {
                    font-size: 11px;
                    color: ${isDark ? '#9CA3AF' : '#6B7280'};
                    margin-bottom: 6px;
                    font-weight: 500;
                }

                .docwise-citation-pills {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                }

                .docwise-citation-pill {
                    background: ${isDark ? 'rgba(59, 130, 246, 0.2)' : 'rgba(59, 130, 246, 0.1)'};
                    color: ${primaryColor};
                    padding: 4px 10px;
                    border-radius: 12px;
                    font-size: 11px;
                    cursor: pointer;
                    transition: background 0.2s;
                    max-width: 150px;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .docwise-citation-pill:hover {
                    background: ${isDark ? 'rgba(59, 130, 246, 0.3)' : 'rgba(59, 130, 246, 0.2)'};
                }

                /* Feedback Buttons */
                .docwise-feedback {
                    display: flex;
                    gap: 8px;
                    margin-top: 8px;
                }

                .docwise-feedback-btn {
                    background: none;
                    border: 1px solid ${isDark ? '#4B5563' : '#E5E7EB'};
                    border-radius: 6px;
                    padding: 4px 8px;
                    font-size: 16px;
                    cursor: pointer;
                    transition: all 0.2s;
                    opacity: 0.6;
                }

                .docwise-feedback-btn:hover:not(:disabled) {
                    opacity: 1;
                    transform: scale(1.1);
                }

                .docwise-feedback-btn.active {
                    opacity: 1;
                    border-color: ${primaryColor};
                    background: ${isDark ? 'rgba(59, 130, 246, 0.2)' : 'rgba(59, 130, 246, 0.1)'};
                }

                .docwise-feedback-btn:disabled {
                    cursor: not-allowed;
                }

                /* Loading Indicator */
                .docwise-typing {
                    display: flex;
                    gap: 4px;
                    padding: 8px 0;
                }

                .docwise-typing-dot {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    background: ${isDark ? '#9CA3AF' : '#9CA3AF'};
                    animation: typing 1.4s infinite ease-in-out;
                }

                .docwise-typing-dot:nth-child(2) {
                    animation-delay: 0.2s;
                }

                .docwise-typing-dot:nth-child(3) {
                    animation-delay: 0.4s;
                }

                @keyframes typing {
                    0%, 60%, 100% {
                        transform: translateY(0);
                    }
                    30% {
                        transform: translateY(-10px);
                    }
                }

                /* Error Message */
                .docwise-error {
                    background: #FEE2E2;
                    color: #991B1B;
                    padding: 12px;
                    border-radius: 8px;
                    font-size: 13px;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .docwise-retry-btn {
                    background: #DC2626;
                    color: white;
                    border: none;
                    padding: 4px 12px;
                    border-radius: 4px;
                    font-size: 12px;
                    cursor: pointer;
                    margin-left: auto;
                }

                .docwise-retry-btn:hover {
                    background: #B91C1C;
                }

                /* Input Container */
                .docwise-input-container {
                    padding: 16px;
                    border-top: 1px solid ${isDark ? '#374151' : '#E5E7EB'};
                    background: ${isDark ? '#1F2937' : '#FFFFFF'};
                }

                .docwise-input-form {
                    display: flex;
                    gap: 8px;
                    align-items: center;
                }

                .docwise-input {
                    flex: 1;
                    padding: 10px 16px;
                    border: 1px solid ${isDark ? '#4B5563' : '#D1D5DB'};
                    border-radius: 24px;
                    outline: none;
                    font-size: 14px;
                    background: ${isDark ? '#374151' : '#FFFFFF'};
                    color: ${isDark ? '#F9FAFB' : '#1F2937'};
                    transition: border-color 0.2s;
                }

                .docwise-input:focus {
                    border-color: ${primaryColor};
                    box-shadow: 0 0 0 3px ${isDark ? 'rgba(59, 130, 246, 0.2)' : 'rgba(59, 130, 246, 0.1)'};
                }

                .docwise-input::placeholder {
                    color: ${isDark ? '#9CA3AF' : '#9CA3AF'};
                }

                .docwise-send-btn {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: ${primaryColor};
                    color: white;
                    border: none;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    transition: all 0.2s;
                    flex-shrink: 0;
                }

                .docwise-send-btn:hover:not(:disabled) {
                    background: ${this.adjustColor(primaryColor, -20)};
                    transform: scale(1.05);
                }

                .docwise-send-btn:disabled {
                    background: ${isDark ? '#4B5563' : '#9CA3AF'};
                    cursor: not-allowed;
                    opacity: 0.5;
                }

                .docwise-char-counter {
                    font-size: 11px;
                    color: ${isDark ? '#9CA3AF' : '#6B7280'};
                    text-align: right;
                    margin-top: 4px;
                }

                .docwise-char-counter.warning {
                    color: #DC2626;
                }

                /* Mobile Responsive */
                @media (max-width: 480px) {
                    #docwise-chat-container {
                        width: calc(100vw - 20px) !important;
                        height: calc(100vh - 100px) !important;
                        ${this.config.position.includes('right') ? 'right: 10px;' : 'left: 10px;'}
                        ${this.config.position.includes('bottom') ? 'bottom: 80px;' : 'top: 80px;'}
                    }

                    #docwise-chat-button {
                        ${this.config.position.includes('right') ? 'right: 10px;' : 'left: 10px;'}
                        ${this.config.position.includes('bottom') ? 'bottom: 10px;' : 'top: 10px;'}
                    }
                }
            `;

            const styleElement = document.createElement('style');
            styleElement.id = 'docwise-widget-styles';
            styleElement.textContent = styles;
            document.head.appendChild(styleElement);
        }

        /**
         * Adjust color brightness
         */
        adjustColor(color, amount) {
            const num = parseInt(color.replace('#', ''), 16);
            const r = Math.max(0, Math.min(255, (num >> 16) + amount));
            const g = Math.max(0, Math.min(255, ((num >> 8) & 0x00FF) + amount));
            const b = Math.max(0, Math.min(255, (num & 0x0000FF) + amount));
            return '#' + ((r << 16) | (g << 8) | b).toString(16).padStart(6, '0');
        }

        /**
         * Create chat button
         */
        createButton() {
            const button = document.createElement('button');
            button.id = 'docwise-chat-button';
            button.innerHTML = `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" 
                          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="unread-badge" style="display: none;">0</span>
            `;

            button.addEventListener('click', () => this.toggleWidget());

            document.body.appendChild(button);
            this.elements.button = button;
        }

        /**
         * Create chat container
         */
        createContainer() {
            if (this.elements.container) {
                return; // Already created
            }

            const container = document.createElement('div');
            container.id = 'docwise-chat-container';
            container.innerHTML = `
                <div class="docwise-header">
                    <h3 class="docwise-header-title">Chat Support</h3>
                    <button class="docwise-close-btn" aria-label="Close chat">&times;</button>
                </div>
                <div class="docwise-messages">
                    ${!this.state.sessionId ? `
                        <div class="docwise-welcome">
                            <h4>${this.config.greeting}</h4>
                            <p>Ask us anything</p>
                        </div>
                    ` : ''}
                </div>
                <div class="docwise-input-container">
                    <form class="docwise-input-form">
                        <input 
                            type="text" 
                            class="docwise-input" 
                            placeholder="${this.config.placeholder}"
                            maxlength="${this.config.maxMessageLength}"
                            autocomplete="off"
                        />
                        <button type="submit" class="docwise-send-btn" disabled aria-label="Send message">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </form>
                    <div class="docwise-char-counter" style="display: none;">
                        <span class="current">0</span> / ${this.config.maxMessageLength}
                    </div>
                </div>
            `;

            document.body.appendChild(container);
            this.elements.container = container;
            this.elements.messagesContainer = container.querySelector('.docwise-messages');
            this.elements.input = container.querySelector('.docwise-input');
            this.elements.sendBtn = container.querySelector('.docwise-send-btn');
            this.elements.charCounter = container.querySelector('.docwise-char-counter');

            // Event listeners
            container.querySelector('.docwise-close-btn').addEventListener('click', () => this.closeWidget());
            container.querySelector('.docwise-input-form').addEventListener('submit', (e) => this.handleSendMessage(e));
            this.elements.input.addEventListener('input', () => this.handleInputChange());
        }

        /**
         * Toggle widget open/close
         */
        toggleWidget() {
            if (this.state.isOpen) {
                this.closeWidget();
            } else {
                this.openWidget();
            }
        }

        /**
         * Open widget
         */
        openWidget() {
            // Create container lazily
            if (!this.elements.container) {
                this.createContainer();
                
                // Render any messages that were loaded before container was created
                if (this.state.messages.length > 0) {
                    // Clear welcome message
                    const welcome = this.elements.messagesContainer.querySelector('.docwise-welcome');
                    if (welcome) {
                        welcome.remove();
                    }
                    
                    // Render all existing messages
                    this.state.messages.forEach(message => {
                        this.renderMessage(message);
                    });
                }
            }

            this.elements.container.classList.add('open');
            this.state.isOpen = true;
            this.state.unreadCount = 0;
            this.updateUnreadBadge();

            // Focus input
            setTimeout(() => {
                this.elements.input.focus();
            }, 300);
        }

        /**
         * Close widget
         */
        closeWidget() {
            if (this.elements.container) {
                this.elements.container.classList.remove('open');
            }
            this.state.isOpen = false;
        }

        /**
         * Handle input change
         */
        handleInputChange() {
            const value = this.elements.input.value;
            const length = value.length;
            const maxLength = this.config.maxMessageLength;

            // Update character counter
            const counter = this.elements.charCounter;
            if (length > maxLength * 0.8) {
                counter.style.display = 'block';
                counter.querySelector('.current').textContent = length;
                counter.classList.toggle('warning', length > maxLength * 0.95);
            } else {
                counter.style.display = 'none';
            }

            // Enable/disable send button
            this.elements.sendBtn.disabled = !value.trim() || this.state.isLoading;
        }

        /**
         * Handle send message
         */
        async handleSendMessage(event) {
            event.preventDefault();

            const message = this.elements.input.value.trim();
            if (!message || this.state.isLoading) {
                return;
            }

            // Clear input
            this.elements.input.value = '';
            this.handleInputChange();

            // Add user message to UI
            this.addMessage({
                role: 'user',
                content: message,
                created_at: new Date().toISOString()
            });

            // Set loading state
            this.state.isLoading = true;
            this.showTypingIndicator();

            try {
                // Send message to API
                const response = await this.apiClient.sendMessage(
                    message,
                    this.state.sessionId
                );

                // Update session ID if first message
                if (!this.state.sessionId && response.data?.session_id) {
                    this.state.sessionId = response.data.session_id;
                    this.saveSession();
                }

                // Hide typing indicator
                this.hideTypingIndicator();

                // Add assistant response
                if (response.data?.message) {
                    this.addMessage(response.data.message);
                }

            } catch (error) {
                this.hideTypingIndicator();
                this.showError(error.message || 'Failed to send message', message);
            } finally {
                this.state.isLoading = false;
                this.handleInputChange();
            }
        }

        /**
         * Render message to DOM (without adding to state)
         */
        renderMessage(message) {
            // Remove welcome message if exists
            const welcome = this.elements.messagesContainer.querySelector('.docwise-welcome');
            if (welcome) {
                welcome.remove();
            }

            const messageEl = document.createElement('div');
            messageEl.className = `docwise-message ${message.role}`;
            messageEl.dataset.messageId = message.id;

            const bubble = document.createElement('div');
            bubble.className = 'docwise-message-bubble';

            const text = document.createElement('div');
            text.className = 'docwise-message-text';
            text.innerHTML = Utils.formatMessage(message.content);
            bubble.appendChild(text);

            // Add citations if present
            if (this.config.showCitations && message.citations && message.citations.length > 0) {
                const citations = this.createCitations(message.citations);
                bubble.appendChild(citations);
            }

            messageEl.appendChild(bubble);

            // Add feedback buttons for assistant messages
            if (this.config.showFeedback && message.role === 'assistant' && message.id) {
                const feedback = this.createFeedbackButtons(message.id);
                messageEl.appendChild(feedback);
            }

            // Add timestamp
            const time = document.createElement('div');
            time.className = 'docwise-message-time';
            time.textContent = Utils.formatTime(message.created_at);
            messageEl.appendChild(time);

            this.elements.messagesContainer.appendChild(messageEl);
            this.scrollToBottom();

            // Update unread count if widget is closed and message is from assistant
            if (!this.state.isOpen && message.role === 'assistant') {
                this.state.unreadCount++;
                this.updateUnreadBadge();
            }
        }

        /**
         * Add message to state and UI
         */
        addMessage(message) {
            // Store message in state
            this.state.messages.push(message);
            
            // Render to DOM
            this.renderMessage(message);
        }

        /**
         * Create citations display
         */
        createCitations(citations) {
            const container = document.createElement('div');
            container.className = 'docwise-citations';

            const label = document.createElement('div');
            label.className = 'docwise-citations-label';
            label.textContent = 'Sources:';
            container.appendChild(label);

            const pills = document.createElement('div');
            pills.className = 'docwise-citation-pills';

            citations.forEach((citation, index) => {
                const pill = document.createElement('div');
                pill.className = 'docwise-citation-pill';
                pill.textContent = citation.document_name || `Source ${index + 1}`;
                pill.title = `Similarity: ${(citation.similarity_score * 100).toFixed(0)}%`;
                pill.dataset.citationId = citation.chunk_id;
                pills.appendChild(pill);
            });

            container.appendChild(pills);
            return container;
        }

        /**
         * Create feedback buttons
         */
        createFeedbackButtons(messageId) {
            const container = document.createElement('div');
            container.className = 'docwise-feedback';

            const thumbsUp = document.createElement('button');
            thumbsUp.className = 'docwise-feedback-btn';
            thumbsUp.innerHTML = '👍';
            thumbsUp.dataset.type = 'thumbs_up';
            thumbsUp.dataset.messageId = messageId;
            thumbsUp.addEventListener('click', (e) => this.handleFeedback(e));

            const thumbsDown = document.createElement('button');
            thumbsDown.className = 'docwise-feedback-btn';
            thumbsDown.innerHTML = '👎';
            thumbsDown.dataset.type = 'thumbs_down';
            thumbsDown.dataset.messageId = messageId;
            thumbsDown.addEventListener('click', (e) => this.handleFeedback(e));

            container.appendChild(thumbsUp);
            container.appendChild(thumbsDown);

            return container;
        }

        /**
         * Handle feedback button click
         */
        async handleFeedback(event) {
            const button = event.currentTarget;
            const messageId = button.dataset.messageId;
            const type = button.dataset.type;

            // Prevent duplicate feedback
            if (this.state.feedbackGiven.has(messageId)) {
                return;
            }

            try {
                // Submit feedback
                await this.apiClient.submitFeedback(
                    this.state.sessionId,
                    messageId,
                    type
                );

                // Mark as given
                this.state.feedbackGiven.add(messageId);

                // Update UI
                const container = button.parentElement;
                const buttons = container.querySelectorAll('.docwise-feedback-btn');
                buttons.forEach(btn => {
                    btn.disabled = true;
                    if (btn === button) {
                        btn.classList.add('active');
                    }
                });

            } catch (error) {
                console.error('Failed to submit feedback:', error);
            }
        }

        /**
         * Show typing indicator
         */
        showTypingIndicator() {
            const indicator = document.createElement('div');
            indicator.className = 'docwise-message assistant';
            indicator.id = 'docwise-typing-indicator';
            indicator.innerHTML = `
                <div class="docwise-message-bubble">
                    <div class="docwise-typing">
                        <div class="docwise-typing-dot"></div>
                        <div class="docwise-typing-dot"></div>
                        <div class="docwise-typing-dot"></div>
                    </div>
                </div>
            `;
            this.elements.messagesContainer.appendChild(indicator);
            this.scrollToBottom();
        }

        /**
         * Hide typing indicator
         */
        hideTypingIndicator() {
            const indicator = document.getElementById('docwise-typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }

        /**
         * Show error message
         */
        showError(message, originalMessage = null) {
            const errorEl = document.createElement('div');
            errorEl.className = 'docwise-error';
            errorEl.innerHTML = `
                <span>⚠️</span>
                <span>${Utils.escapeHtml(message)}</span>
                ${originalMessage ? `<button class="docwise-retry-btn">Retry</button>` : ''}
            `;

            if (originalMessage) {
                const retryBtn = errorEl.querySelector('.docwise-retry-btn');
                retryBtn.addEventListener('click', () => {
                    errorEl.remove();
                    this.elements.input.value = originalMessage;
                    this.handleInputChange();
                    this.elements.input.focus();
                });
            }

            this.elements.messagesContainer.appendChild(errorEl);
            this.scrollToBottom();

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (errorEl.parentElement) {
                    errorEl.remove();
                }
            }, 5000);
        }

        /**
         * Scroll messages to bottom
         */
        scrollToBottom() {
            if (this.elements.messagesContainer) {
                this.elements.messagesContainer.scrollTop = this.elements.messagesContainer.scrollHeight;
            }
        }

        /**
         * Update unread badge
         */
        updateUnreadBadge() {
            const badge = this.elements.button.querySelector('.unread-badge');
            if (this.state.unreadCount > 0) {
                badge.textContent = this.state.unreadCount;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }

        /**
         * Load session from localStorage
         */
        loadSession() {
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                if (stored) {
                    const data = JSON.parse(stored);
                    this.state.sessionId = data.sessionId;
                }
            } catch (error) {
                console.warn('Failed to load session:', error);
            }
        }

        /**
         * Save session to localStorage
         */
        saveSession() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    sessionId: this.state.sessionId,
                    timestamp: Date.now()
                }));
            } catch (error) {
                console.warn('Failed to save session:', error);
            }
        }

        /**
         * Load chat history
         */
        async loadChatHistory() {
            if (!this.state.sessionId) {
                return;
            }

            try {
                const response = await this.apiClient.loadMessages(this.state.sessionId);
                
                if (response.data && Array.isArray(response.data)) {
                    // Clear welcome message if container exists
                    if (this.elements.messagesContainer) {
                        const welcome = this.elements.messagesContainer.querySelector('.docwise-welcome');
                        if (welcome) {
                            welcome.remove();
                        }
                    }

                    // Add messages to state and render if container exists
                    response.data.forEach(message => {
                        this.state.messages.push(message);
                        
                        // Only render if container is created
                        if (this.elements.messagesContainer) {
                            this.renderMessage(message);
                        }
                    });
                }
            } catch (error) {
                console.warn('Failed to load chat history:', error);
                // Reset session if invalid
                this.state.sessionId = null;
                this.saveSession();
            }
        }
    }

    // ============================================================================
    // GLOBAL API
    // ============================================================================

    window.DocWiseChat = {
        /**
         * Initialize the widget
         */
        init: function(config) {
            if (!config || !config.apiKey) {
                console.error('DocWiseChat: Configuration with apiKey is required');
                return null;
            }

            try {
                const widget = new DocWiseWidget(config);
                
                // Store instance for debugging
                if (window.DocWiseChat._instances === undefined) {
                    window.DocWiseChat._instances = [];
                }
                window.DocWiseChat._instances.push(widget);
                
                return widget;
            } catch (error) {
                console.error('DocWiseChat initialization failed:', error);
                return null;
            }
        },

        /**
         * Get version
         */
        version: '1.0.1'
    };

    // Log ready state
    console.log('DocWise Chat Widget loaded (v1.0.1)');

})();
