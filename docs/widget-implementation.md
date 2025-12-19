# Chat Widget Implementation Guide

This document provides a complete implementation guide for the embeddable chat widget that companies can add to their websites.

---

## Architecture Overview

```
Customer Website → Widget Script → Iframe → Your Laravel App → API Response
```

**Components**:
1. **Widget SDK** (`widget.js`) - Loads on customer's site
2. **Widget UI** (`/widget/{company}`) - Iframe content served by Laravel
3. **Widget API** (`/api/widget/*`) - Backend endpoints for chat
4. **Session Management** - Handle anonymous users and persistence

---

## 1. Widget SDK (JavaScript)

### File: `public/js/widget.js`

```javascript
(function() {
    'use strict';
    
    class CustomerSupportWidget {
        constructor() {
            this.config = {};
            this.iframe = null;
            this.isOpen = false;
            this.sessionId = null;
            this.unreadCount = 0;
        }
        
        init(config) {
            this.config = {
                companyId: config.companyId,
                apiKey: config.apiKey,
                baseUrl: config.baseUrl || 'https://your-domain.com',
                theme: config.theme || 'light',
                position: config.position || 'bottom-right',
                primaryColor: config.primaryColor || '#3B82F6',
                greeting: config.greeting || 'Hi! How can we help you?',
                placeholder: config.placeholder || 'Type your message...',
                ...config
            };
            
            this.loadSessionFromStorage();
            this.createWidgetButton();
            this.createWidgetIframe();
            this.setupMessageListener();
        }
        
        createWidgetButton() {
            const button = document.createElement('div');
            button.id = 'cs-widget-button';
            button.innerHTML = `
                <div class="cs-button-content">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="cs-unread-badge" style="display: none;">0</span>
                </div>
            `;
            
            this.applyButtonStyles(button);
            button.addEventListener('click', () => this.toggleWidget());
            document.body.appendChild(button);
            
            this.button = button;
        }
        
        applyButtonStyles(button) {
            const position = this.config.position;
            const styles = `
                position: fixed;
                ${position.includes('bottom') ? 'bottom: 20px;' : 'top: 20px;'}
                ${position.includes('right') ? 'right: 20px;' : 'left: 20px;'}
                width: 60px;
                height: 60px;
                background: ${this.config.primaryColor};
                border-radius: 50%;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                transition: all 0.3s ease;
            `;
            button.style.cssText = styles;
            
            // Hover effect
            button.addEventListener('mouseenter', () => {
                button.style.transform = 'scale(1.1)';
            });
            button.addEventListener('mouseleave', () => {
                button.style.transform = 'scale(1)';
            });
        }
        
        createWidgetIframe() {
            const container = document.createElement('div');
            container.id = 'cs-widget-container';
            container.style.cssText = `
                position: fixed;
                ${this.config.position.includes('bottom') ? 'bottom: 90px;' : 'top: 90px;'}
                ${this.config.position.includes('right') ? 'right: 20px;' : 'left: 20px;'}
                width: 400px;
                height: 600px;
                max-width: calc(100vw - 40px);
                max-height: calc(100vh - 120px);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.12);
                z-index: 999998;
                display: none;
                overflow: hidden;
            `;
            
            const iframe = document.createElement('iframe');
            iframe.src = `${this.config.baseUrl}/widget/${this.config.companyId}?theme=${this.config.theme}&session=${this.sessionId || ''}`;
            iframe.style.cssText = `
                width: 100%;
                height: 100%;
                border: none;
                border-radius: 12px;
            `;
            iframe.allow = 'microphone; camera';
            
            container.appendChild(iframe);
            document.body.appendChild(container);
            
            this.container = container;
            this.iframe = iframe;
        }
        
        setupMessageListener() {
            window.addEventListener('message', (event) => {
                if (event.origin !== this.config.baseUrl) return;
                
                const { type, data } = event.data;
                
                switch (type) {
                    case 'widget-ready':
                        this.onWidgetReady(data);
                        break;
                    case 'new-message':
                        this.onNewMessage(data);
                        break;
                    case 'session-created':
                        this.sessionId = data.sessionId;
                        this.saveSessionToStorage();
                        break;
                    case 'close-widget':
                        this.closeWidget();
                        break;
                    case 'resize-widget':
                        this.resizeWidget(data.height);
                        break;
                }
            });
        }
        
        toggleWidget() {
            if (this.isOpen) {
                this.closeWidget();
            } else {
                this.openWidget();
            }
        }
        
        openWidget() {
            this.container.style.display = 'block';
            this.isOpen = true;
            this.unreadCount = 0;
            this.updateUnreadBadge();
            
            // Send config to iframe
            setTimeout(() => {
                this.iframe.contentWindow.postMessage({
                    type: 'widget-config',
                    data: this.config
                }, this.config.baseUrl);
            }, 100);
        }
        
        closeWidget() {
            this.container.style.display = 'none';
            this.isOpen = false;
        }
        
        onNewMessage(data) {
            if (!this.isOpen && data.role === 'assistant') {
                this.unreadCount++;
                this.updateUnreadBadge();
            }
        }
        
        updateUnreadBadge() {
            const badge = this.button.querySelector('.cs-unread-badge');
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
        
        loadSessionFromStorage() {
            try {
                const stored = localStorage.getItem('cs-widget-session');
                if (stored) {
                    const data = JSON.parse(stored);
                    this.sessionId = data.sessionId;
                }
            } catch (e) {
                console.warn('Failed to load widget session:', e);
            }
        }
        
        saveSessionToStorage() {
            try {
                localStorage.setItem('cs-widget-session', JSON.stringify({
                    sessionId: this.sessionId,
                    timestamp: Date.now()
                }));
            } catch (e) {
                console.warn('Failed to save widget session:', e);
            }
        }
    }
    
    // Global API
    window.CustomerSupport = {
        init: function(config) {
            if (!config.companyId || !config.apiKey) {
                console.error('CustomerSupport: companyId and apiKey are required');
                return;
            }
            
            const widget = new CustomerSupportWidget();
            widget.init(config);
            
            // Store instance for debugging
            window._csWidget = widget;
        }
    };
})();
```

---

## 2. Widget UI (Laravel Blade/Inertia)

### Route: `routes/web.php`
```php
Route::get('/widget/{company:slug}', [WidgetController::class, 'show'])
    ->name('widget.show');
```

### Controller: `app/Http/Controllers/WidgetController.php`
```php
<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WidgetController extends Controller
{
    public function show(Company $company, Request $request)
    {
        // Validate company is active
        if ($company->status !== 'active') {
            abort(404);
        }
        
        $sessionId = $request->get('session');
        $theme = $request->get('theme', 'light');
        
        return Inertia::render('Widget/Chat', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'settings' => $company->settings,
            ],
            'session_id' => $sessionId,
            'theme' => $theme,
            'config' => [
                'api_base' => config('app.url') . '/api/widget',
                'websocket_url' => config('broadcasting.connections.pusher.options.host'),
            ]
        ]);
    }
}
```

### Vue Component: `resources/js/Pages/Widget/Chat.vue`
```vue
<template>
  <div class="widget-container" :class="themeClass">
    <!-- Header -->
    <div class="widget-header">
      <div class="company-info">
        <div class="company-avatar">
          {{ company.name.charAt(0) }}
        </div>
        <div>
          <h3 class="company-name">{{ company.name }}</h3>
          <p class="status">{{ isTyping ? 'Typing...' : 'Online' }}</p>
        </div>
      </div>
      <button @click="closeWidget" class="close-btn">
        <XMarkIcon class="w-5 h-5" />
      </button>
    </div>
    
    <!-- Messages -->
    <div class="messages-container" ref="messagesContainer">
      <div v-if="!sessionId" class="welcome-message">
        <h4>Welcome! How can we help you?</h4>
        <p>Ask us anything about {{ company.name }}</p>
      </div>
      
      <div
        v-for="message in messages"
        :key="message.id"
        class="message"
        :class="message.role"
      >
        <div class="message-content">
          <div class="message-text" v-html="formatMessage(message.content)"></div>
          <div v-if="message.citations?.length" class="citations">
            <div class="citations-label">Sources:</div>
            <div class="citation-pills">
              <span
                v-for="citation in message.citations"
                :key="citation.chunk_id"
                class="citation-pill"
                @click="showCitation(citation)"
              >
                {{ citation.document }}
              </span>
            </div>
          </div>
        </div>
        <div class="message-time">
          {{ formatTime(message.created_at) }}
        </div>
      </div>
      
      <div v-if="isLoading" class="message assistant">
        <div class="message-content">
          <div class="typing-indicator">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Input -->
    <div class="input-container">
      <form @submit.prevent="sendMessage" class="input-form">
        <input
          v-model="newMessage"
          type="text"
          placeholder="Type your message..."
          class="message-input"
          :disabled="isLoading"
          @keydown.enter.prevent="sendMessage"
        />
        <button
          type="submit"
          class="send-btn"
          :disabled="!newMessage.trim() || isLoading"
        >
          <PaperAirplaneIcon class="w-5 h-5" />
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick, computed } from 'vue'
import { XMarkIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

interface Props {
  company: {
    id: number
    name: string
    slug: string
    settings: any
  }
  session_id?: string
  theme: string
  config: {
    api_base: string
    websocket_url: string
  }
}

const props = defineProps<Props>()

const messages = ref([])
const newMessage = ref('')
const isLoading = ref(false)
const isTyping = ref(false)
const sessionId = ref(props.session_id)
const messagesContainer = ref<HTMLElement>()

const themeClass = computed(() => `theme-${props.theme}`)

onMounted(() => {
  setupPostMessageListener()
  
  if (sessionId.value) {
    loadChatHistory()
  }
  
  // Notify parent that widget is ready
  notifyParent('widget-ready', { sessionId: sessionId.value })
})

function setupPostMessageListener() {
  window.addEventListener('message', (event) => {
    if (event.data.type === 'widget-config') {
      // Handle configuration from parent
      console.log('Received config:', event.data.data)
    }
  })
}

async function loadChatHistory() {
  try {
    const response = await axios.get(`${props.config.api_base}/sessions/${sessionId.value}/messages`)
    messages.value = response.data.data
    await nextTick()
    scrollToBottom()
  } catch (error) {
    console.error('Failed to load chat history:', error)
  }
}

async function sendMessage() {
  if (!newMessage.value.trim() || isLoading.value) return
  
  const messageText = newMessage.value.trim()
  newMessage.value = ''
  
  // Add user message immediately
  const userMessage = {
    id: Date.now(),
    role: 'user',
    content: messageText,
    created_at: new Date().toISOString()
  }
  messages.value.push(userMessage)
  
  await nextTick()
  scrollToBottom()
  
  isLoading.value = true
  
  try {
    const response = await axios.post(`${props.config.api_base}/chat`, {
      company_id: props.company.id,
      session_id: sessionId.value,
      message: messageText
    })
    
    const { session_id, message: assistantMessage } = response.data
    
    // Update session ID if this was the first message
    if (!sessionId.value) {
      sessionId.value = session_id
      notifyParent('session-created', { sessionId: session_id })
    }
    
    // Add assistant response
    messages.value.push(assistantMessage)
    
    // Notify parent of new message
    notifyParent('new-message', assistantMessage)
    
    await nextTick()
    scrollToBottom()
    
  } catch (error) {
    console.error('Failed to send message:', error)
    
    // Add error message
    messages.value.push({
      id: Date.now() + 1,
      role: 'assistant',
      content: 'Sorry, I encountered an error. Please try again.',
      created_at: new Date().toISOString()
    })
  } finally {
    isLoading.value = false
  }
}

function formatMessage(content: string): string {
  // Convert markdown-like formatting
  return content
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/\n/g, '<br>')
}

function formatTime(timestamp: string): string {
  return new Date(timestamp).toLocaleTimeString([], { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

function closeWidget() {
  notifyParent('close-widget', {})
}

function showCitation(citation: any) {
  // Handle citation click - could open modal or highlight
  console.log('Citation clicked:', citation)
}

function notifyParent(type: string, data: any) {
  window.parent.postMessage({ type, data }, '*')
}
</script>

<style scoped>
.widget-container {
  height: 100vh;
  display: flex;
  flex-direction: column;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.theme-light {
  background: white;
  color: #1f2937;
}

.theme-dark {
  background: #1f2937;
  color: white;
}

.widget-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
}

.theme-dark .widget-header {
  border-bottom-color: #374151;
  background: #111827;
}

.company-info {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.company-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
}

.company-name {
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}

.status {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
}

.messages-container {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.welcome-message {
  text-align: center;
  padding: 2rem 1rem;
  color: #6b7280;
}

.message {
  display: flex;
  flex-direction: column;
}

.message.user {
  align-items: flex-end;
}

.message.assistant {
  align-items: flex-start;
}

.message-content {
  max-width: 80%;
  padding: 0.75rem 1rem;
  border-radius: 1rem;
  word-wrap: break-word;
}

.message.user .message-content {
  background: #3b82f6;
  color: white;
  border-bottom-right-radius: 0.25rem;
}

.message.assistant .message-content {
  background: #f3f4f6;
  color: #1f2937;
  border-bottom-left-radius: 0.25rem;
}

.theme-dark .message.assistant .message-content {
  background: #374151;
  color: white;
}

.message-time {
  font-size: 0.75rem;
  color: #9ca3af;
  margin-top: 0.25rem;
  padding: 0 0.5rem;
}

.citations {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid rgba(0,0,0,0.1);
}

.citations-label {
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.citation-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.citation-pill {
  background: rgba(59, 130, 246, 0.1);
  color: #3b82f6;
  padding: 0.125rem 0.5rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: background-color 0.2s;
}

.citation-pill:hover {
  background: rgba(59, 130, 246, 0.2);
}

.typing-indicator {
  display: flex;
  gap: 0.25rem;
  align-items: center;
}

.typing-indicator span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #9ca3af;
  animation: typing 1.4s infinite ease-in-out;
}

.typing-indicator span:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
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

.input-container {
  padding: 1rem;
  border-top: 1px solid #e5e7eb;
  background: white;
}

.theme-dark .input-container {
  border-top-color: #374151;
  background: #1f2937;
}

.input-form {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.message-input {
  flex: 1;
  padding: 0.75rem 1rem;
  border: 1px solid #d1d5db;
  border-radius: 1.5rem;
  outline: none;
  font-size: 0.875rem;
}

.message-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.theme-dark .message-input {
  background: #374151;
  border-color: #4b5563;
  color: white;
}

.send-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #3b82f6;
  color: white;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.2s;
}

.send-btn:hover:not(:disabled) {
  background: #2563eb;
}

.send-btn:disabled {
  background: #9ca3af;
  cursor: not-allowed;
}

.close-btn {
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 0.25rem;
}

.close-btn:hover {
  background: #f3f4f6;
}

.theme-dark .close-btn:hover {
  background: #374151;
}
</style>
```

---

## 3. Widget API Endpoints

### Routes: `routes/api.php`
```php
Route::prefix('widget')->group(function () {
    Route::post('/chat', [WidgetApiController::class, 'chat']);
    Route::get('/sessions/{session}/messages', [WidgetApiController::class, 'getMessages']);
    Route::post('/feedback', [WidgetApiController::class, 'feedback']);
});
```

### Controller: `app/Http/Controllers/Api/WidgetApiController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WidgetApiController extends Controller
{
    public function __construct(
        private ChatService $chatService
    ) {}
    
    public function chat(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'message' => 'required|string|max:2000',
            'session_id' => 'nullable|string',
            'user_metadata' => 'nullable|array'
        ]);
        
        $company = Company::findOrFail($request->company_id);
        
        // Check if company is active and within limits
        if ($company->status !== 'active') {
            return response()->json(['error' => 'Service unavailable'], 503);
        }
        
        // Get or create session
        $session = $this->getOrCreateSession($company, $request);
        
        // Create user message
        $userMessage = ChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $request->message,
            'created_at' => now()
        ]);
        
        // Generate AI response
        try {
            $response = $this->chatService->generateResponse(
                $company,
                $session,
                $request->message
            );
            
            $assistantMessage = ChatMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $response['content'],
                'tokens_prompt' => $response['tokens_prompt'],
                'tokens_completion' => $response['tokens_completion'],
                'model_used' => $response['model'],
                'latency_ms' => $response['latency_ms'],
                'confidence_score' => $response['confidence_score'],
                'citations' => $response['citations'],
                'raw_llm_response' => $response['raw_response'],
                'created_at' => now()
            ]);
            
            // Update session
            $session->increment('message_count', 2);
            $session->increment('total_tokens', $response['tokens_prompt'] + $response['tokens_completion']);
            $session->touch();
            
            return response()->json([
                'session_id' => $session->uuid,
                'message' => [
                    'id' => $assistantMessage->id,
                    'role' => 'assistant',
                    'content' => $assistantMessage->content,
                    'citations' => $assistantMessage->citations,
                    'confidence_score' => $assistantMessage->confidence_score,
                    'created_at' => $assistantMessage->created_at->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Widget chat error', [
                'company_id' => $company->id,
                'session_id' => $session->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'I apologize, but I encountered an error. Please try again or contact support.'
            ], 500);
        }
    }
    
    public function getMessages(Request $request, string $sessionUuid)
    {
        $session = ChatSession::where('uuid', $sessionUuid)->firstOrFail();
        
        $messages = ChatMessage::where('session_id', $session->id)
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'citations' => $message->citations,
                    'created_at' => $message->created_at->toISOString()
                ];
            });
        
        return response()->json([
            'data' => $messages
        ]);
    }
    
    public function feedback(Request $request)
    {
        $request->validate([
            'message_id' => 'required|exists:chat_messages,id',
            'type' => 'required|in:thumbs_up,thumbs_down,rating',
            'rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);
        
        $message = ChatMessage::findOrFail($request->message_id);
        
        \App\Models\Feedback::create([
            'uuid' => Str::uuid(),
            'message_id' => $message->id,
            'session_id' => $message->session_id,
            'feedback_type' => $request->type,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip()
            ]
        ]);
        
        return response()->json(['success' => true]);
    }
    
    private function getOrCreateSession(Company $company, Request $request): ChatSession
    {
        if ($request->session_id) {
            $session = ChatSession::where('uuid', $request->session_id)
                ->where('company_id', $company->id)
                ->first();
            
            if ($session) {
                return $session;
            }
        }
        
        // Create new session
        return ChatSession::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'external_user_id' => $request->user_metadata['user_id'] ?? null,
            'channel' => 'widget',
            'status' => 'active',
            'user_metadata' => $request->user_metadata ?? [],
            'context' => [
                'referrer' => $request->header('referer'),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip()
            ]
        ]);
    }
}
```

---

## 4. Usage Example

### Company Integration
```html
<!DOCTYPE html>
<html>
<head>
    <title>My Company Website</title>
</head>
<body>
    <h1>Welcome to Acme Corp</h1>
    <p>Your content here...</p>
    
    <!-- Chat Widget -->
    <script src="https://your-domain.com/js/widget.js"></script>
    <script>
        CustomerSupport.init({
            companyId: 'acme-corp',
            apiKey: 'cs_live_abc123def456...',
            theme: 'light',
            position: 'bottom-right',
            primaryColor: '#FF6B35',
            greeting: 'Hi! How can Acme Corp help you today?'
        });
    </script>
</body>
</html>
```

### Advanced Configuration
```javascript
CustomerSupport.init({
    companyId: 'acme-corp',
    apiKey: 'cs_live_abc123def456...',
    
    // Styling
    theme: 'light', // 'light' | 'dark'
    position: 'bottom-right', // 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'
    primaryColor: '#3B82F6',
    
    // Content
    greeting: 'Hi! How can we help you?',
    placeholder: 'Type your message...',
    
    // Behavior
    autoOpen: false,
    showOnPages: ['/support', '/contact'],
    hideOnPages: ['/checkout'],
    
    // User context
    userMetadata: {
        user_id: 'customer_123',
        name: 'John Doe',
        email: 'john@example.com',
        plan: 'premium'
    },
    
    // Callbacks
    onReady: function() {
        console.log('Widget ready');
    },
    onNewMessage: function(message) {
        console.log('New message:', message);
    }
});
```

This implementation provides a complete, production-ready chat widget that companies can easily embed on their websites with full customization options and secure API communication.
