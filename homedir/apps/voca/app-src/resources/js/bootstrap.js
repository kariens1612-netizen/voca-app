import { organisationSlug } from './composables/organisationPath';
import axios from 'axios';
import { isNativeApp } from './composables/useNative';
window.axios = axios;

// Bundled native pages have no Laravel backend at their local asset origin.
// Local live-reload builds keep same-origin sessions; deployed bundles use the documented API host.
const bundledNative = isNativeApp() && ['capacitor:', 'ionic:'].includes(window.location.protocol)
    || (isNativeApp() && window.location.hostname === 'localhost' && !window.location.port);
const apiBaseUrl = (import.meta.env.VITE_API_BASE_URL || (bundledNative ? 'https://app.voca.co.za' : '')).replace(/\/$/, '');
window.apiUrl = (path) => `${apiBaseUrl}${path}`;
if (apiBaseUrl) {
    window.axios.defaults.baseURL = apiBaseUrl;
}

window.axios.defaults.headers.common['Accept'] = 'application/json';
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
// Each tab retains its organisation through its URL, including API requests.
const slug = organisationSlug();
if (slug) window.axios.defaults.headers.common['X-Voca-Organisation'] = slug;

// Rely on the XSRF cookie so the token stays in sync after login/session regeneration.
