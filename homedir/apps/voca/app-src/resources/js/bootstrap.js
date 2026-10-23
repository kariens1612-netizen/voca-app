import axios from 'axios';
window.axios = axios;

const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '');
window.apiUrl = (path) => `${apiBaseUrl}${path}`;
if (apiBaseUrl) {
    window.axios.defaults.baseURL = apiBaseUrl;
}

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

// Rely on the XSRF cookie so the token stays in sync after login/session regeneration.
