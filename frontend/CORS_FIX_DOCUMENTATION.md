# CORS Issue Fix Documentation

## Problem Description

The application was experiencing CORS (Cross-Origin Resource Sharing) errors when trying to make requests to `https://api.sigec.artbenshow.com/register`. The error message was:

```
Blocage d'une requête multiorigine (Cross-Origin Request) :
la politique « Same Origin » ne permet pas de consulter la ressource distante
située sur https://api.sigec.artbenshow.com/register.
Raison : échec de la requête CORS. Code d'état : (null)
```

## Root Cause

1. The frontend application was configured to make API requests to `https://api.sigec.artbenshow.com` (via `VITE_API_URL` in `.env`)
2. The external API server doesn't have proper CORS headers configured for the frontend domain
3. Browser security policies block cross-origin requests without proper CORS configuration

## Solutions Implemented

### 1. Vite Development Proxy Configuration

**File: `vite.config.js`**

Added a proxy configuration to handle CORS during development:

```javascript
server: {
  port: 3000,
  cors: true, // Activer CORS pour le serveur de développement
  proxy: {
    '/api': {
      target: 'http://localhost:8000',
      changeOrigin: true,
      rewrite: (path) => path
    },
    // Proxy pour éviter les erreurs CORS en développement
    '/external-api': {
      target: 'https://api.sigec.artbenshow.com',
      changeOrigin: true,
      rewrite: (path) => path.replace(/^\/external-api/, ''),
      headers: {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization'
      }
    }
  }
}
```

### 2. CORS Proxy Service

**File: `src/services/corsProxy.js`**

Created a service to automatically detect and handle CORS proxying:

- Detects when requests are made to external APIs
- Automatically routes them through the development proxy
- Provides flexible configuration for multiple external APIs

### 3. Enhanced API Client

**File: `src/services/apiClient.js`**

- Added `withCredentials: true` for proper CORS handling
- Integrated the CORS proxy service in development mode
- Maintains backward compatibility with existing API calls

### 4. Improved Error Handling

**File: `src/hooks/useApi.js`**

Added specific error handling for CORS issues to provide clearer error messages to users.

## How to Use

### Development Environment

1. The application will automatically use the proxy for external API calls
2. No additional configuration needed - the system detects external APIs automatically
3. CORS errors should be resolved in development

### Production Environment

For production, you have two options:

#### Option 1: Server-Side CORS Configuration (Recommended)

Configure your production server to allow CORS headers:

```javascript
// Example for Nginx
location /api/ {
    if ($http_origin ~ "^https://your-frontend-domain\.com$") {
        set $cors "true";
    }

    if ($cors = "true") {
        add_header Access-Control-Allow-Origin $http_origin;
        add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization";
        add_header Access-Control-Allow-Credentials true;
    }

    if ($request_method = OPTIONS) {
        return 204;
    }
}
```

#### Option 2: Use Environment Variables

Update your `.env` file to use a local API endpoint in production:

```env
VITE_API_URL=https://your-production-api-domain.com
```

## Testing the Fix

1. Start the development server: `npm run dev`
2. Navigate to the registration page
3. Try to register a new account
4. The request should now go through the proxy without CORS errors

## Additional Notes

- The proxy configuration only works in development mode
- For production, ensure your API server has proper CORS configuration
- The CORS proxy service is extensible and can handle multiple external APIs
- All existing functionality remains unchanged

## Files Modified

1. `vite.config.js` - Added proxy configuration
2. `src/services/apiClient.js` - Enhanced with CORS handling
3. `src/services/corsProxy.js` - New CORS proxy service (created)
4. `src/hooks/useApi.js` - Improved error handling

## Troubleshooting

If you still encounter CORS errors:

1. Check that your development server is running on port 3000
2. Verify that the external API URL in `.env` is correct
3. Clear your browser cache and restart the development server
4. Check the browser console for detailed error messages
5. Ensure the API server is accessible and responding

## Production Deployment

For production deployment, ensure:

1. Your production server has proper CORS headers configured
2. The `VITE_API_URL` points to your production API endpoint
3. SSL certificates are properly configured
4. All API endpoints are accessible from your frontend domain
