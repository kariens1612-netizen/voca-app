<template>
    <section class="section"><div class="container access-wrap"><form class="glass-card access-form" @submit.prevent="submit">
        <p class="eyebrow">{{ name }}</p>
        <h1>{{ resetting ? 'Set your password' : recovering ? 'Recover your account' : 'Sign in' }}</h1>
        <p v-if="resetting">Use at least 12 characters, including letters and numbers. Your password is shared across your Voca organisations.</p>
        <label>Email address<input v-model.trim="form.email" type="email" autocomplete="email" required /></label>
        <label v-if="!recovering">{{ resetting ? 'New password' : 'Password' }}<input v-model="form.password" type="password" :autocomplete="resetting ? 'new-password' : 'current-password'" :minlength="resetting ? 12 : undefined" required /></label>
        <label v-if="resetting">Confirm password<input v-model="form.password_confirmation" type="password" autocomplete="new-password" minlength="12" required /></label>
        <p v-if="error" role="alert" class="access-error">{{ error }}</p>
        <p v-if="message" role="status">{{ message }}</p>
        <button class="button" :disabled="busy">{{ busy ? 'Please wait…' : resetting ? 'Save password' : recovering ? 'Send password link' : 'Sign in' }}</button>
        <RouterLink v-if="!recovering" to="/forgot-password">Forgot your password or need a new invitation link?</RouterLink>
        <RouterLink v-if="recovering || resetting" to="/login">Back to sign in</RouterLink>
        <RouterLink to="/brackenfell">Browse ministry portals</RouterLink>
    </form></div></section>
</template>
<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import { useTenant } from '../composables/useTenant';
const route = useRoute(), router = useRouter();
const { login, memberships, user } = useAuth();
const { name, load } = useTenant();
const resetting = computed(() => route.name === 'reset-password');
const recovering = computed(() => route.name === 'forgot-password');
const form = reactive({ email: '', password: '', password_confirmation: '' });
const busy = ref(false), message = ref(''), error = ref('');
watch(() => route.fullPath, () => {
    form.email = typeof route.query.email === 'string' ? route.query.email : '';
    form.password = ''; form.password_confirmation = ''; message.value = ''; error.value = '';
}, { immediate: true });
const submit = async () => {
    busy.value = true; message.value = ''; error.value = '';
    try {
        if (recovering.value || resetting.value) {
            const { data } = await window.axios.post(resetting.value ? '/auth/reset-password' : '/auth/forgot-password', { ...form, token: route.params.token });
            form.password = ''; form.password_confirmation = ''; message.value = data.message;
            if (resetting.value) await useAuth().load();
            return;
        }
        const payload = await login({ email: form.email, password: form.password });
        await load(true);
        const redirect = route.query.redirect;
        if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') && !redirect.includes('\\')) return router.replace(redirect);
        if (user.value?.is_superadmin) return router.replace('/platform');
        if (['admin', 'owner'].includes(payload.organisation_role)) return router.replace('/admin');
        if (memberships.value.length) return router.replace('/portal/ministries/' + memberships.value[0].slug);
        return router.replace('/');
    } catch (e) {
        error.value = Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || 'Unable to complete this request. Please try again.';
    } finally { busy.value = false; }
};
</script>
<style scoped>
.access-wrap { max-width: 580px; }
.access-form { padding: 2rem; display: grid; gap: 1rem; }
.access-form label { display: grid; gap: .4rem; }
.access-form input { width: 100%; padding: .8rem; border-radius: .5rem; border: 1px solid #94a3b8; color: #172033; background: white; }
.access-error { color: #b91c1c; }
</style>
