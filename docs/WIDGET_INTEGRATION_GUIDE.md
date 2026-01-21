# DocWise Chat Widget - Integration Guide

## Overview

The DocWise Chat Widget is a standalone, embeddable JavaScript chat interface that enables companies to add AI-powered customer support to their websites. The widget connects to your DocWise backend API and provides RAG (Retrieval Augmented Generation) powered responses with citations.

## Quick Start

### 1. Get Your API Key

First, obtain an API key from your DocWise backend:

```bash
# Via API or admin panel
POST /api/api-keys
{
  "name": "Website Widget",
  "permissions": ["*"],
  "allowed_domain": "yourdomain.com"
}
```

### 2. Add Widget to Your Website

Add these two script tags before the closing `</body>` tag:

```html
<!-- Load Widget Script -->
<script src="https://your-docwise-domain.com/widget.js"></script>

<!-- Initialize Widget -->
<script>
  DocWiseChat.init({
    apiKey: 'dwc_live_abc123def456...',
    apiUrl: 'https://your-docwise-domain.com',
    primaryColor: '#3B82F6',
    position: 'bottom-right'
  });
</script>
```

That's it! The chat widget will appear in the bottom-right corner of your website.

## Configuration Options

### Required Options

| Option | Type | Description |
|--------|------|-------------|
| `apiKey` | string | Your DocWise API key (required) |

### Optional Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `apiUrl` | string | `window.location.origin` | Base URL of your DocWise API |
| `position` | string | `'bottom-right'` | Widget position: `'bottom-right'`, `'bottom-left'`, `'top-right'`, `'top-left'` |
| `primaryColor` | string | `'#3B82F6'` | Primary color (hex format) |
| `theme` | string | `'light'` | Theme: `'light'` or `'dark'` |
| `greeting` | string | `'Hi! How can we help you?'` | Welcome greeting message |
| `placeholder` | string | `'Type your message...'` | Input placeholder text |
| `buttonSize` | number | `60` | Chat button size in pixels |
| `windowWidth` | number | `400` | Chat window width in pixels |
| `windowHeight` | number | `600` | Chat window height in pixels |
| `showCitations` | boolean | `true` | Show source citations from RAG |
| `showFeedback` | boolean | `true` | Show feedback buttons (thumbs up/down) |
| `zIndex` | number | `999999` | CSS z-index for widget |
| `maxMessageLength` | number | `2000` | Maximum message length |

### Example with All Options

```javascript
DocWiseChat.init({
  // Required
  apiKey: 'dwc_live_abc123def456...',
  
  // API Configuration
  apiUrl: 'https://api.docwise.com',
  
  // Appearance
  position: 'bottom-right',
  primaryColor: '#10B981',
  theme: 'light',
  buttonSize: 60,
  windowWidth: 400,
  windowHeight: 600,
  
  // Content
  greeting: 'Welcome! How can we assist you today?',
  placeholder: 'Ask me anything...',
  
  // Features
  showCitations: true,
  showFeedback: true,
  
  // Advanced
  zIndex: 999999,
  maxMessageLength: 2000
});
```

## Features

### 💬 Real-time Chat
- Send and receive messages instantly
- AI-powered responses using RAG
- Smooth animations and transitions
- Typing indicators

### 📚 Citations
- View source documents for AI responses
- Similarity scores for each citation
- Click to see document names
- Hover for additional details

### 👍 Feedback System
- Thumbs up/down for each AI response
- Prevents duplicate feedback
- Helps improve response quality
- Tracks user satisfaction

### 💾 Session Persistence
- Chat history saved in localStorage
- Automatic session recovery on page reload
- UUID-based session management
- No server-side sessions required

### 🎨 Customization
- Custom brand colors
- Light and dark themes
- Flexible positioning
- Responsive design
- Mobile-friendly

### 🔒 Security
- API key authentication
- Domain restriction enforcement (backend)
- XSS prevention
- HTML sanitization
- Secure localStorage usage

### 🚀 Performance
- Lazy loading (UI created on first open)
- Efficient DOM updates
- Smooth 60fps animations
- Small footprint (~20KB minified)
- Request retry logic with exponential backoff

## API Endpoints

The widget communicates with these backend endpoints:

### POST /api/widget/chat
Send a chat message and receive AI response.

**Request:**
```json
{
  "message": "What is your return policy?",
  "session_id": "uuid-here" // optional
}
```

**Response:**
```json
{
  "data": {
    "session_id": "abc-123",
    "message": {
      "id": 456,
      "role": "assistant",
      "content": "Our return policy allows...",
      "citations": [
        {
          "chunk_id": 789,
          "document_name": "Return Policy.pdf",
          "similarity_score": 0.92,
          "page_number": 1
        }
      ],
      "confidence_score": 0.85,
      "created_at": "2024-01-01T12:00:00Z"
    }
  }
}
```

### GET /api/widget/sessions/{uuid}/messages
Load chat history for a session.

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "role": "user",
      "content": "Hello",
      "created_at": "2024-01-01T11:59:00Z"
    },
    {
      "id": 124,
      "role": "assistant",
      "content": "Hi! How can I help?",
      "citations": [],
      "created_at": "2024-01-01T12:00:00Z"
    }
  ]
}
```

### POST /api/widget/sessions/{uuid}/feedback
Submit feedback for a message.

**Request:**
```json
{
  "message_id": 124,
  "type": "thumbs_up"
}
```

**Response:**
```json
{
  "success": true
}
```

## Error Handling

The widget handles errors gracefully:

### Network Errors
- Automatic retry with exponential backoff (3 attempts)
- User-friendly error messages
- Retry button for failed messages

### API Errors
- **401 Unauthorized**: Invalid API key
- **429 Rate Limit**: Too many requests
- **500 Server Error**: Internal server error

### Validation Errors
- Empty messages prevented
- Character limit warnings
- Input validation

## Browser Support

### Supported Browsers
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile Safari (iOS 14+)
- Chrome Mobile (Android)

### Required Features
- ES6+ JavaScript
- Fetch API
- localStorage
- CSS Grid & Flexbox
- CSS Custom Properties

## CORS Configuration

Your DocWise backend must allow CORS requests from your website domain.

**Laravel Configuration (config/cors.php):**
```php
return [
    'paths' => ['api/*'],
    'allowed_origins' => ['https://yourdomain.com'],
    'allowed_methods' => ['GET', 'POST'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
```

## Testing

### Local Development

1. Start your Laravel backend:
```bash
php artisan serve
```

2. Open the demo page:
```
http://localhost:8000/widget-demo.html
```

3. Configure with your API key and test all features

### Production Testing

1. Deploy widget.js to your production server
2. Test on actual website with real API key
3. Verify domain restrictions work
4. Check mobile responsiveness
5. Test with different browsers

### Testing Checklist

- [ ] Widget loads without errors
- [ ] Button appears in correct position
- [ ] Click opens/closes widget smoothly
- [ ] Send message works
- [ ] AI responses display correctly
- [ ] Citations render (if present)
- [ ] Feedback buttons work
- [ ] Session persists across page reloads
- [ ] Rate limiting shows warning
- [ ] Network errors show retry option
- [ ] Works on mobile screens
- [ ] No CSS conflicts with host website
- [ ] Keyboard navigation works

## Troubleshooting

### Widget Not Appearing

**Check:**
1. Script loaded correctly (check Network tab)
2. No JavaScript errors in console
3. API key is valid
4. `DocWiseChat.init()` was called

**Solution:**
```javascript
// Add error handling
try {
  const widget = DocWiseChat.init({
    apiKey: 'your-key'
  });
  console.log('Widget initialized:', widget);
} catch (error) {
  console.error('Widget failed:', error);
}
```

### Messages Not Sending

**Check:**
1. Backend is running
2. CORS is configured correctly
3. API key has correct permissions
4. Network tab shows API requests

**Solution:**
```bash
# Check backend logs
tail -f storage/logs/laravel.log

# Test API endpoint directly
curl -X POST http://localhost:8000/api/widget/chat \
  -H "Authorization: Bearer your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"message":"test"}'
```

### CORS Errors

**Error:**
```
Access to fetch at 'http://localhost:8000/api/widget/chat' from origin 'http://example.com' 
has been blocked by CORS policy
```

**Solution:**
Add your domain to `config/cors.php`:
```php
'allowed_origins' => [
    'http://example.com',
    'https://example.com'
],
```

### Session Not Persisting

**Check:**
1. localStorage is available
2. No browser extensions blocking storage
3. Not in incognito/private mode

**Solution:**
```javascript
// Check localStorage
console.log(localStorage.getItem('docwise-widget-session'));

// Clear and restart
localStorage.removeItem('docwise-widget-session');
location.reload();
```

### Style Conflicts

If widget styles conflict with your website:

**Solution 1: Increase z-index**
```javascript
DocWiseChat.init({
  apiKey: 'your-key',
  zIndex: 9999999
});
```

**Solution 2: Check for CSS conflicts**
```javascript
// Widget uses these IDs (should be unique):
// - docwise-chat-button
// - docwise-chat-container
// - docwise-widget-styles
```

## Advanced Usage

### Multiple Widgets

You can initialize multiple widgets with different configurations:

```javascript
// Widget for sales
const salesWidget = DocWiseChat.init({
  apiKey: 'sales-api-key',
  primaryColor: '#10B981',
  position: 'bottom-right',
  greeting: 'Sales Support'
});

// Widget for support
const supportWidget = DocWiseChat.init({
  apiKey: 'support-api-key',
  primaryColor: '#3B82F6',
  position: 'bottom-left',
  greeting: 'Technical Support'
});
```

### Programmatic Control

Access widget instance for programmatic control:

```javascript
const widget = DocWiseChat.init({
  apiKey: 'your-key'
});

// Open widget programmatically
widget.openWidget();

// Close widget
widget.closeWidget();

// Check state
console.log(widget.state.isOpen);
console.log(widget.state.sessionId);
console.log(widget.state.messages);
```

### Custom Events

Monitor widget activity:

```javascript
const widget = DocWiseChat.init({
  apiKey: 'your-key'
});

// Override methods to add custom behavior
const originalAddMessage = widget.addMessage;
widget.addMessage = function(message) {
  // Custom logic
  console.log('New message:', message);
  
  // Call original
  originalAddMessage.call(this, message);
  
  // Post-processing
  if (message.role === 'assistant') {
    // Track analytics, etc.
  }
};
```

## Best Practices

### 1. API Key Security
- ✅ Use environment-specific API keys
- ✅ Enable domain restrictions
- ✅ Rotate keys periodically
- ❌ Don't commit keys to git
- ❌ Don't use production keys in development

### 2. Performance
- ✅ Load widget.js asynchronously if possible
- ✅ Initialize widget after page load
- ✅ Use CDN for widget.js in production
- ✅ Enable gzip compression
- ✅ Monitor API response times

### 3. User Experience
- ✅ Choose appropriate position for your layout
- ✅ Match primary color to your brand
- ✅ Use clear, friendly greeting messages
- ✅ Test on mobile devices
- ✅ Ensure readable contrast ratios

### 4. Monitoring
- ✅ Track chat volume
- ✅ Monitor error rates
- ✅ Analyze feedback scores
- ✅ Review citation usage
- ✅ Set up alerts for API errors

## Examples

### Example 1: E-commerce Website
```html
<script src="https://cdn.yourstore.com/widget.js"></script>
<script>
  DocWiseChat.init({
    apiKey: 'dwc_live_store123',
    apiUrl: 'https://api.yourstore.com',
    primaryColor: '#FF6B6B',
    greeting: 'Welcome to Our Store! How can we help you find the perfect product?',
    theme: 'light',
    position: 'bottom-right'
  });
</script>
```

### Example 2: SaaS Documentation
```html
<script src="https://cdn.saasapp.com/widget.js"></script>
<script>
  DocWiseChat.init({
    apiKey: 'dwc_live_docs456',
    apiUrl: 'https://api.saasapp.com',
    primaryColor: '#6366F1',
    greeting: 'Need help with our platform? Ask me anything!',
    theme: 'dark',
    position: 'bottom-left',
    showCitations: true
  });
</script>
```

### Example 3: Customer Support Portal
```html
<script src="https://cdn.support.com/widget.js"></script>
<script>
  DocWiseChat.init({
    apiKey: 'dwc_live_support789',
    apiUrl: 'https://api.support.com',
    primaryColor: '#10B981',
    greeting: 'Technical Support - We\'re here to help!',
    theme: 'light',
    position: 'bottom-right',
    windowHeight: 700,
    showFeedback: true
  });
</script>
```

## Migration Guide

### From Iframe Widget to Standalone

If you're migrating from the iframe-based widget:

**Old (Iframe):**
```html
<script src="/widget.js"></script>
<script>
  CustomerSupport.init({
    companyId: 'acme-corp',
    apiKey: 'key123',
    baseUrl: 'https://app.com'
  });
</script>
```

**New (Standalone):**
```html
<script src="/widget.js"></script>
<script>
  DocWiseChat.init({
    apiKey: 'key123',
    apiUrl: 'https://app.com'
  });
</script>
```

**Changes:**
- `CustomerSupport` → `DocWiseChat`
- `companyId` removed (inferred from API key)
- `baseUrl` → `apiUrl`
- Direct API communication (no iframe)
- Better performance and UX

## Support

### Documentation
- [API Reference](./api-reference.md)
- [Architecture Overview](./CHAT_API_ARCHITECTURE.md)
- [Quick Reference](./QUICK_REFERENCE.md)

### Contact
- GitHub Issues: [Report bugs or request features]
- Documentation: [Read full documentation]
- API Status: [Check system status]

## License

MIT License - See LICENSE file for details

---

**Version:** 1.0.0  
**Last Updated:** 2026-01-21  
**Maintained by:** DocWise Team
