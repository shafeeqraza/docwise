# 🚀 DocWise Chat Widget - Quick Start

Get your chat widget running in 3 minutes!

## Step 1: Get Your API Key

Create an API key from your DocWise backend:

```bash
# Option A: Via Artisan Console
php artisan tinker
>>> $company = App\Models\Company::first();
>>> $apiKey = App\Models\CompanyApiKey::create([
...   'company_id' => $company->id,
...   'name' => 'Website Widget',
...   'key_hash' => hash('sha256', $key = 'dwc_live_' . bin2hex(random_bytes(32))),
...   'permissions' => ['*'],
...   'allowed_domain' => 'localhost',
...   'is_active' => true
... ]);
>>> echo $key;  // Copy this key!
```

```bash
# Option B: Via API
POST /api/api-keys
Authorization: Bearer YOUR_SANCTUM_TOKEN
{
  "name": "Website Widget",
  "permissions": ["*"],
  "allowed_domain": "yourdomain.com"
}
```

## Step 2: Add Widget to Your Website

Add these two lines before `</body>`:

```html
<script src="http://localhost:8000/widget.js"></script>
<script>
  DocWiseChat.init({
    apiKey: 'dwc_live_YOUR_KEY_HERE',
    apiUrl: 'http://localhost:8000'
  });
</script>
```

## Step 3: Test It!

1. Start your backend:
   ```bash
   php artisan serve
   ```

2. Open your website or the demo page:
   ```
   http://localhost:8000/widget-demo.html
   ```

3. Look for the chat button in the bottom-right corner

4. Click to open and send a message!

## 🎨 Customize (Optional)

```javascript
DocWiseChat.init({
  apiKey: 'dwc_live_YOUR_KEY_HERE',
  apiUrl: 'http://localhost:8000',
  
  // Appearance
  position: 'bottom-right',      // or bottom-left, top-right, top-left
  primaryColor: '#3B82F6',       // Your brand color
  theme: 'light',                // or 'dark'
  
  // Content
  greeting: 'Hi! How can we help?',
  placeholder: 'Type your message...'
});
```

## 📝 Common Issues

### Widget Not Appearing?
- Check browser console for errors
- Verify API key is correct
- Make sure backend is running

### Messages Not Sending?
- Check Network tab in DevTools
- Verify CORS is configured
- Check API key permissions

### CORS Error?
Add to `config/cors.php`:
```php
'allowed_origins' => ['http://localhost:8000'],
```

## 📚 Full Documentation

- **Integration Guide:** `docs/WIDGET_INTEGRATION_GUIDE.md`
- **Implementation Summary:** `docs/WIDGET_IMPLEMENTATION_SUMMARY.md`
- **API Reference:** `docs/api-reference.md`

## 🎯 What You Get

✅ Real-time AI chat  
✅ RAG-powered responses  
✅ Source citations  
✅ Feedback buttons  
✅ Session persistence  
✅ Mobile responsive  
✅ Fully customizable  
✅ Zero dependencies  

## 🎉 That's It!

Your chat widget is now live. Customers can ask questions and get AI-powered answers from your documents.

**Next Steps:**
1. Upload documents to your backend
2. Customize the widget appearance
3. Test on mobile devices
4. Deploy to production

---

Need help? Check the full documentation in `docs/WIDGET_INTEGRATION_GUIDE.md`
