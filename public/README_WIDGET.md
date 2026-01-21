# DocWise Chat Widget

A standalone, embeddable JavaScript chat widget for adding AI-powered customer support to any website.

## 🚀 Quick Start

Add two lines of code to your website:

```html
<script src="https://your-domain.com/widget.js"></script>
<script>
  DocWiseChat.init({
    apiKey: 'dwc_live_abc123...',
    apiUrl: 'https://your-domain.com'
  });
</script>
```

## ✨ Features

- **💬 Real-time Chat** - Instant AI responses powered by RAG
- **📚 Citations** - Show source documents with similarity scores
- **👍 Feedback** - Thumbs up/down for response quality
- **💾 Session Persistence** - Chat history saved across page reloads
- **🎨 Fully Customizable** - Colors, themes, position, and more
- **📱 Mobile Responsive** - Works perfectly on all devices
- **🔒 Secure** - API key authentication with domain restrictions
- **⚡ Fast** - Small footprint (~20KB), lazy loading, optimized

## 📦 Files

- **`widget.js`** - Main widget script (production-ready)
- **`widget-demo.html`** - Interactive demo and testing page
- **`README_WIDGET.md`** - This file
- **`../docs/WIDGET_INTEGRATION_GUIDE.md`** - Comprehensive documentation

## 🎨 Configuration

### Minimal
```javascript
DocWiseChat.init({
  apiKey: 'dwc_live_abc123...'
});
```

### Full Options
```javascript
DocWiseChat.init({
  apiKey: 'dwc_live_abc123...',
  apiUrl: 'https://api.docwise.com',
  position: 'bottom-right',        // bottom-right, bottom-left, top-right, top-left
  primaryColor: '#3B82F6',         // Any hex color
  theme: 'light',                  // light or dark
  greeting: 'Hi! How can we help?',
  placeholder: 'Type your message...',
  windowWidth: 400,
  windowHeight: 600,
  showCitations: true,
  showFeedback: true
});
```

## 🧪 Testing

1. **Start backend:**
   ```bash
   php artisan serve
   ```

2. **Open demo page:**
   ```
   http://localhost:8000/widget-demo.html
   ```

3. **Configure with your API key and test**

## 📚 Documentation

Full documentation available at: `../docs/WIDGET_INTEGRATION_GUIDE.md`

Topics covered:
- Complete configuration reference
- API endpoint details
- Error handling
- Browser support
- CORS configuration
- Troubleshooting
- Advanced usage
- Best practices

## 🌐 Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile Safari (iOS 14+)
- Chrome Mobile

## 🔧 Development

### Structure
```
public/
├── widget.js              # Main widget (single file, ~1000 lines)
├── widget-demo.html       # Demo page
└── README_WIDGET.md       # This file

docs/
└── WIDGET_INTEGRATION_GUIDE.md  # Full documentation
```

### Architecture
```javascript
(function() {
  // 1. Configuration & Constants
  const DEFAULT_CONFIG = { ... };
  
  // 2. Utility Functions
  const Utils = { ... };
  
  // 3. API Client
  class APIClient { ... }
  
  // 4. Main Widget Class
  class DocWiseWidget { ... }
  
  // 5. Global API
  window.DocWiseChat = { init: ... };
})();
```

## 🐛 Troubleshooting

### Widget not appearing?
1. Check browser console for errors
2. Verify API key is valid
3. Ensure backend is running
4. Check CORS configuration

### Messages not sending?
1. Check Network tab in DevTools
2. Verify API endpoint is accessible
3. Check API key permissions
4. Review backend logs

### Session not persisting?
1. Check if localStorage is available
2. Not in incognito/private mode
3. No browser extensions blocking storage

## 🎯 API Endpoints

Widget communicates with these endpoints:

- `POST /api/widget/chat` - Send message
- `GET /api/widget/sessions/{uuid}/messages` - Load history
- `POST /api/widget/sessions/{uuid}/feedback` - Submit feedback

All require `Authorization: Bearer {apiKey}` header.

## 🔒 Security

- API keys visible to client (domain restriction enforced by backend)
- XSS prevention through HTML escaping
- CORS protection
- Secure localStorage usage
- No sensitive data stored client-side

## 📝 License

MIT

## 🤝 Contributing

Contributions welcome! Please check the main project documentation.

## 📞 Support

- **Documentation:** See `WIDGET_INTEGRATION_GUIDE.md`
- **Issues:** Report on GitHub
- **Questions:** Contact support team

---

**Version:** 1.0.0  
**Last Updated:** 2026-01-21
