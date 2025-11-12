# JavaScript Refactoring Guide

## Overview

The WP Safe Mode application has been refactored to use modern JavaScript for a more dynamic, SPA-like experience. This eliminates the need for full page reloads and provides a smoother user experience.

## Architecture

### Core Components

1. **app.js** - Main application framework
   - Router: Client-side routing
   - API: AJAX request handler
   - UI: UI management and form handling
   - Utils: Utility functions

2. **API Controller** (`controller/api.controller.php`)
   - Handles all AJAX requests
   - Returns JSON responses
   - Validates CSRF tokens
   - Routes to appropriate handlers

3. **Modules** (`assets/js/modules/`)
   - Individual modules for each feature
   - Handles view rendering and interactions
   - Manages module-specific state

## How It Works

### 1. Initial Load
- Page loads normally with PHP rendering
- JavaScript application initializes
- Router detects current view from URL
- Module loads dynamically

### 2. Navigation
- User clicks menu link with `data-view` attribute
- Router intercepts click
- Loads view via AJAX
- Updates URL without page reload
- Renders content dynamically

### 3. Form Submissions
- Forms with `data-ajax` attribute submit via AJAX
- API controller processes request
- Returns JSON response
- JavaScript updates UI
- Shows success/error messages

### 4. Actions
- Buttons with `data-action` and `data-ajax` attributes
- Execute actions via AJAX
- Update UI dynamically
- No page reload needed

## Usage

### Making Links Dynamic

```html
<!-- Old way (causes page reload) -->
<a href="?view=plugins">Plugins</a>

<!-- New way (AJAX navigation) -->
<a href="#" data-view="plugins">Plugins</a>
```

### Making Forms AJAX

```html
<!-- Add data-ajax and data-endpoint attributes -->
<form data-ajax data-endpoint="/api/submit?form=plugins">
    <!-- form fields -->
    <button type="submit">Submit</button>
</form>
```

### Making Buttons AJAX

```html
<!-- Add data-action and data-ajax attributes -->
<button data-action="optimize_tables" data-ajax>Optimize Tables</button>
```

### Creating a New Module

1. Create module file: `assets/js/modules/yourmodule.module.js`

```javascript
window.YourModule = class {
    async load(view, action) {
        // Load data
        const response = await WPSafeMode.API.get('/api/data', {type: 'yourdata'});
        
        // Render view
        this.render(response.data);
        
        // Initialize handlers
        this.initHandlers();
    }
    
    render(data) {
        const content = document.getElementById('main-content');
        content.innerHTML = `
            <div class="row">
                <div class="columns large-12">
                    <h2>Your Module</h2>
                    <!-- Your HTML here -->
                </div>
            </div>
        `;
    }
    
    initHandlers() {
        // Event handlers
    }
    
    cleanup() {
        // Cleanup when leaving module
    }
};
```

2. Register in router (`app.js`):
```javascript
this.routes = {
    'yourview': {module: 'YourModule', view: 'yourview'},
    // ...
};
```

3. Include in footer:
```html
<script src="assets/js/modules/yourmodule.module.js"></script>
```

## API Endpoints

### GET /api/view
Load view HTML
- Parameters: `view`, `action`
- Returns: `{success: true, data: {html: "...", view: "..."}}`

### GET /api/data
Get data
- Parameters: `type` (plugins, themes, tables, backups)
- Returns: `{success: true, data: {...}}`

### GET /api/action
Execute action
- Parameters: `action`
- Returns: `{success: true, message: "..."}`

### POST /api/submit
Submit form
- Parameters: Form data + `form` (form type)
- Returns: `{success: true, message: "...", redirect: {...}}`

### GET /api/csrf
Get CSRF token
- Parameters: `form` (form name)
- Returns: `{success: true, data: {token: "..."}}`

## Benefits

1. **No Page Reloads** - Smooth navigation
2. **Faster** - Only loads necessary content
3. **Better UX** - Loading indicators, smooth transitions
4. **Modern** - Uses Fetch API, async/await
5. **Maintainable** - Modular architecture
6. **Backward Compatible** - Still works without JavaScript

## Migration Path

### Phase 1: Core Framework ✅
- [x] Create app.js framework
- [x] Create API controller
- [x] Update menu links
- [x] Add loading states

### Phase 2: Key Modules
- [x] Login module
- [x] Plugins module
- [ ] Themes module
- [ ] WP Config module
- [ ] Backup modules
- [ ] Htaccess module
- [ ] Error Log module

### Phase 3: All Features
- [ ] Convert all views to modules
- [ ] Update all forms to use AJAX
- [ ] Update all actions to use AJAX
- [ ] Add real-time updates where applicable

### Phase 4: Enhancements
- [ ] Add WebSocket support for real-time updates
- [ ] Add offline support
- [ ] Add push notifications
- [ ] Optimize bundle size

## Testing

### Test Navigation
1. Click menu items - should load without page reload
2. Use browser back/forward - should work correctly
3. Direct URL access - should load correctly

### Test Forms
1. Submit forms - should show loading state
2. Success messages - should appear
3. Error handling - should show errors
4. CSRF validation - should work

### Test Actions
1. Click action buttons - should execute via AJAX
2. Loading states - should show
3. Success/error messages - should appear

## Troubleshooting

### Module Not Loading
- Check browser console for errors
- Verify module is included in footer
- Check module is registered in router

### Forms Not Submitting
- Verify `data-ajax` attribute is present
- Check `data-endpoint` is correct
- Verify CSRF token is included

### API Errors
- Check network tab in browser dev tools
- Verify API controller is loaded
- Check PHP error logs

## Best Practices

1. **Always use modules** for new features
2. **Use data attributes** for JavaScript hooks
3. **Handle errors gracefully** with try/catch
4. **Show loading states** for async operations
5. **Update URL** when navigating
6. **Clean up** when leaving modules
7. **Validate on both client and server**

## Future Improvements

1. **State Management** - Add Redux or similar
2. **Component Library** - Create reusable components
3. **Build System** - Use Webpack/Babel for modern JS
4. **TypeScript** - Add type safety
5. **Testing** - Add unit and integration tests
6. **PWA** - Make it a Progressive Web App

---

**Status**: Core framework complete. Modules in progress.

**Last Updated**: $(date)


