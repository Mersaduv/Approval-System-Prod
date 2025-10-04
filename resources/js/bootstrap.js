/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.headers.common['Content-Type'] = 'application/json';

// Get CSRF token from meta tag
const token = document.head.querySelector('meta[name="csrf-token"]');

if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Function to get fresh CSRF token
window.getCsrfToken = function() {
    const token = document.head.querySelector('meta[name="csrf-token"]');
    return token ? token.content : null;
};

// Function to refresh CSRF token
window.refreshCsrfToken = async function() {
    try {
        const response = await axios.get('/api/csrf-token');
        if (response.data.csrf_token) {
            // Update meta tag
            const metaTag = document.head.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.content = response.data.csrf_token;
            }
            // Update axios default header
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = response.data.csrf_token;
            return response.data.csrf_token;
        }
    } catch (error) {
        console.error('Failed to refresh CSRF token:', error);
    }
    return null;
};

// Add response interceptor to handle authentication errors
window.axios.interceptors.response.use(
    response => response,
    async error => {
        console.log('Axios error:', error);
        console.log('Error response:', error.response);

        if (error.response?.status === 419) {
            // CSRF token mismatch - try to refresh token and retry
            console.log('CSRF token mismatch, attempting to refresh token...');
            const newToken = await window.refreshCsrfToken();
            if (newToken) {
                // Retry the original request with new token
                const originalRequest = error.config;
                originalRequest.headers['X-CSRF-TOKEN'] = newToken;
                return window.axios(originalRequest);
            }
        } else if (error.response?.status === 401) {
            // Redirect to login if unauthorized
            window.location.href = '/login';
        } else if (error.response?.status === 403) {
            // Log 403 errors for debugging
            console.error('Access denied error:', error.response.data);
        }
        return Promise.reject(error);
    }
);

// Add request interceptor to log all requests and ensure CSRF token
window.axios.interceptors.request.use(
    config => {
        console.log('Making request to:', config.url);
        console.log('With credentials:', config.withCredentials);
        console.log('Headers:', config.headers);

        // Ensure CSRF token is present for POST/PUT/DELETE requests
        if (['post', 'put', 'patch', 'delete'].includes(config.method?.toLowerCase())) {
            const token = window.getCsrfToken();
            if (token) {
                config.headers['X-CSRF-TOKEN'] = token;
            }
        }

        return config;
    },
    error => {
        console.error('Request error:', error);
        return Promise.reject(error);
    }
);

// Add route helper (simple version)
window.route = (name, params = {}) => {
    const routes = {
        'login': '/login',
        'logout': '/logout',
        'home': '/',
        'password.request': '/password/reset'
    };
    return routes[name] || '#';
};

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
//     wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });
