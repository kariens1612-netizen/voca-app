<template>
    <section class="section account-page" :class="`account-${accountLayout}`"><div class="container access-wrap">
    <aside class="access-intro" aria-labelledby="access-intro-title">
        <div class="access-intro-copy">
            <p class="eyebrow">{{ name }}</p>
            <h2 id="access-intro-title">Your community, connected.</h2>
            <p>{{ memberAccount ? 'Connect with the VOCA community, discover events and grow in faith.' : 'Access your ministry tools, team updates and community portal.' }}</p>
            <RouterLink to="/">Back to home <span aria-hidden="true">→</span></RouterLink>
        </div>
    </aside>
    <form class="glass-card access-form" @submit.prevent="submit">
        <p class="eyebrow">{{ name }}</p>
        <h1>{{ registering ? 'Join VOCA' : resetting ? 'Set your password' : recovering ? 'Recover your account' : memberAccount ? 'Member sign in' : 'Team sign in' }}</h1>
        <p v-if="resetting">{{ memberAccount ? 'Use at least 12 characters. This updates your password for the VOCA website and app.' : 'Use at least 12 characters, including letters and numbers. Your password is shared across your Voca organisations.' }}</p>
        <p v-if="recovering">Enter your account email address to request a password reset link. Open the link, choose a new password, then return here to sign in.</p>
        <p v-if="memberAccount">One VOCA account for the website and app.</p>
        <p v-else-if="!recovering && !resetting">For ministry teams, administrators and superadmins.</p>
        <label v-if="registering">Full name<input v-model.trim="form.name" autocomplete="name" required /></label>
        <label v-if="registering">Phone number<input v-model.trim="form.phone" type="tel" autocomplete="tel" required /></label>
        <label>Email address<input v-model.trim="form.email" type="email" autocomplete="email" required /></label>
        <label v-if="!recovering">{{ resetting ? 'New password' : 'Password' }}<PasswordInput :key="route.fullPath" v-model="form.password" :autocomplete="resetting || registering ? 'new-password' : 'current-password'" :minlength="resetting ? 12 : undefined" required /></label>
        <label v-if="resetting || registering">Confirm password<PasswordInput :key="route.fullPath" v-model="form.password_confirmation" autocomplete="new-password" :minlength="resetting ? 12 : 8" required /></label>
        <p v-if="error" role="alert" class="access-error">{{ error }}</p>
        <p v-if="message" role="status">{{ message }}</p>
        <button class="button" :disabled="busy">{{ busy ? 'Please wait…' : registering ? 'Create account' : resetting ? 'Save password' : recovering ? 'Send password link' : 'Sign in' }}</button>
        <RouterLink v-if="!recovering && !registering && !resetting" :to="memberAccount ? '/member-forgot-password' : '/forgot-password'">Forgot password?</RouterLink>
        <RouterLink v-if="recovering || resetting" :to="memberAccount ? '/member-login' : '/login'">Back to sign in</RouterLink>
        <RouterLink v-if="memberAccount && !registering" to="/register">New to VOCA? Create an account</RouterLink>
        <a v-if="registering" href="https://voca.africa/register">Register a business, church, ministry or pastor profile</a>
        <RouterLink v-if="registering" to="/member-login">Already a member? Sign in</RouterLink>
        <RouterLink v-if="memberAccount && !registering && !recovering && !resetting" to="/login">Team or superadmin? Use team sign in</RouterLink>
        <RouterLink v-if="!memberAccount && !recovering && !resetting" to="/member-login">VOCA member? Use member sign in</RouterLink>
        <RouterLink to="/brackenfell">Browse ministry portals</RouterLink>
    </form></div></section>
</template>
<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import { useTenant } from '../composables/useTenant';
import PasswordInput from '../components/PasswordInput.vue';
import { useAccountLayout } from '../composables/useAccountLayout';
const accountLayout = useAccountLayout();
const route = useRoute(), router = useRouter();
const { login, memberLogin, registerMember, memberships, user } = useAuth();
const { name, load } = useTenant();
const memberAccount = computed(() => String(route.name).startsWith('member-'));
const registering = computed(() => route.name === 'member-register');
const resetting = computed(() => ['reset-password', 'member-reset-password'].includes(route.name));
const recovering = computed(() => ['forgot-password', 'member-forgot-password'].includes(route.name));
const form = reactive({ name: '', phone: '', email: '', password: '', password_confirmation: '' });
const busy = ref(false), message = ref(''), error = ref('');
watch(() => route.fullPath, () => {
    form.email = typeof route.query.email === 'string' ? route.query.email : '';
    form.password = ''; form.password_confirmation = ''; message.value = ''; error.value = '';
}, { immediate: true });
const submit = async () => {
    busy.value = true; message.value = ''; error.value = '';
    try {
        if (recovering.value || resetting.value) {
            const { data } = await window.axios.post(memberAccount.value ? (resetting.value ? '/auth/member-reset-password' : '/auth/member-forgot-password') : (resetting.value ? '/auth/reset-password' : '/auth/forgot-password'), { ...form, token: route.params.token });
            form.password = ''; form.password_confirmation = ''; message.value = data.message;
            if (resetting.value) await useAuth().load();
            return;
        }
        const payload = registering.value ? await registerMember({ ...form }) : memberAccount.value ? await memberLogin({ email: form.email, password: form.password }) : await login({ email: form.email, password: form.password });
        await load(true);
        const redirect = route.query.redirect;
        if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') && !redirect.includes('\\')) return router.replace(redirect);
        if (user.value?.is_superadmin) return router.replace('/platform');
        if (payload.organisation_role === 'owner') return router.replace('/admin/setup');
        if (payload.organisation_role === 'admin') return router.replace('/admin');
        if (memberships.value.length) return router.replace('/portal/ministries/' + memberships.value[0].slug);
        return router.replace('/inside');
    } catch (e) {
        error.value = Object.values(e?.response?.data?.errors || {}).flat().join(' ') || e?.response?.data?.message || e.message || 'Unable to complete this request. Please try again.';
    } finally { busy.value = false; }
};
</script>
<style scoped>
.access-wrap { max-width: 1120px; min-width: 0; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 40px; align-items: start; }
.access-intro { min-width: 0; border-radius: 24px; overflow: hidden; background: rgba(16, 38, 79, .6); }
.access-intro-copy { padding: clamp(24px, 4vw, 48px); display: grid; gap: 20px; }
.access-intro-copy h2 { font-family: var(--display-font); font-size: clamp(2rem, 3vw, 3.4rem); line-height: 1.15; }
.access-intro-copy p, .access-intro-copy a { line-height: 1.65; }
.account-web-app .access-intro { position: sticky; top: 220px; }
.account-mobile-app { padding: 24px 0; }
.account-mobile-app .access-wrap { max-width: 580px; display: block; }
.account-mobile-app .access-intro { display: none; }
.account-mobile-app .access-form { border-radius: 20px; padding: 24px; }
.access-form { padding: clamp(20px, 5vw, 32px); display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
.access-form label { display: grid; gap: .4rem; min-width: 0; }
.access-form :deep(input) { box-sizing: border-box; width: 100%; min-width: 0; min-height: 48px; font: inherit; padding: .8rem; border-radius: .5rem; border: 1px solid #94a3b8; color: #172033; background: white; }
.access-form :deep(.password-field input) { padding-right: 54px; }
.access-form > a { display: block; padding: .5rem 0; line-height: 1.5; overflow-wrap: anywhere; }
.access-form h1 { margin: 0; font-size: clamp(2rem, 7vw, 3rem); }
.access-error { color: #b91c1c; }
@media (max-width: 860px) {
    .account-page { padding: 28px 0; }
    .access-wrap { grid-template-columns: minmax(0, 1fr); gap: 24px; }
    .account-web-app .access-intro { position: static; }
    .account-web-app .access-intro-copy { padding: 24px; gap: 12px; }
}
</style>
