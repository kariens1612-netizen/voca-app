<template>
    <section class="section"><div class="container platform-login-wrap">
        <div class="glass-card platform-login-brand"><p class="eyebrow">Voca platform</p><h1>Everything under one roof.</h1><p>Manage ministries, administrators, white-label brands, modules and tenant access from the central command centre.</p></div>
        <form class="glass-card platform-login-form" @submit.prevent="submit"><p class="eyebrow">Restricted access</p><h2>Superadmin sign in</h2><p class="muted">Use your platform administrator credentials.</p><label>Email address<input v-model="form.email" type="email" autocomplete="email" required /></label><label>Password<input v-model="form.password" type="password" autocomplete="current-password" required /></label><p v-if="error" class="error">{{ error }}</p><button class="button" :disabled="loading">{{ loading ? 'Signing in…' : 'Open control centre' }}</button><RouterLink to="/forgot-password">Forgot your password?</RouterLink><RouterLink to="/" class="muted">Return to website</RouterLink></form>
    </div></section>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue'; import { RouterLink, useRoute, useRouter } from 'vue-router'; import { useAuth } from '../composables/useAuth';
const form = reactive({ email: '', password: '' }); const loading = ref(false), error = ref(''); const route = useRoute(), router = useRouter(); const { user, login, load } = useAuth();
const submit = async () => { loading.value = true; error.value = ''; try { const payload = await login(form); if (!payload.user?.is_superadmin) { error.value = 'This account does not have platform superadmin access.'; return; } router.replace(typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') && !route.query.redirect.startsWith('//') && !route.query.redirect.includes('\\') ? route.query.redirect : '/platform'); } catch (e) { error.value = e?.response?.data?.message || 'Unable to sign in.'; } finally { loading.value = false; } };
onMounted(async () => { await load(); if (user.value?.is_superadmin) router.replace('/platform'); else if (route.query.denied) error.value = 'A superadmin account is required.'; });
</script>
