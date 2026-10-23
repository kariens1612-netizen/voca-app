<template>
    <section class="section portal-shell">
        <div class="container portal-login">
            <div class="portal-login-panel" :style="portalStyle">
                <p class="portal-tag">Brackenfell</p>
                <h2>{{ ministry?.name || 'Ministry Login' }}</h2>
                <p>{{ ministry?.description || 'Sign in to access your ministry portal.' }}</p>
                <p v-if="isStreamTeam" class="portal-login-stream-note">
                    Sign in here to set up the Rumble live stream, update the public stream link, and manage the
                    broadcast workflow for service.
                </p>
                <div class="portal-login-accent">
                    <span></span>
                </div>
            </div>

            <div class="portal-login-form card">
                <h3>{{ isStreamTeam ? 'Stream Team Sign In' : 'Member Sign In' }}</h3>
                <p class="portal-login-subtitle">
                    {{ isStreamTeam
                        ? 'Use your media-team email and password to access livestream setup tools.'
                        : 'Use the email and password provided by your admin.' }}
                </p>

                <form @submit.prevent="submit">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="email" required />

                    <label class="form-label" for="password">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                    />
                    <label class="form-toggle">
                        <input v-model="showPassword" type="checkbox" />
                        Show password
                    </label>

                    <button class="button" type="submit" :disabled="loading">
                        {{ loading ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>

                <p v-if="error" class="form-error">{{ error }}</p>
                <p v-if="denied" class="form-note">
                    Your account is not linked to this ministry. Please contact your admin.
                </p>

                <RouterLink class="button ghost" to="/brackenfell">Back to ministries</RouterLink>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import { useMinistries } from '../composables/useMinistries';

const entity = 'brackenfell';
const route = useRoute();
const router = useRouter();
const { load, login, hasRole } = useAuth();
const { ministriesByEntity, loadEntity, getMinistry } = useMinistries();

const form = reactive({
    email: '',
    password: '',
});

const loading = ref(false);
const error = ref('');
const showPassword = ref(false);
const denied = computed(() => route.query.denied === '1');
const ministry = computed(() => getMinistry(entity, route.params.slug));
const isStreamTeam = computed(() => route.params.slug === 'power-media');

const portalStyle = computed(() => ({
    '--portal-accent': ministry.value?.theme?.accent || '#1d5fa2',
    '--portal-surface': ministry.value?.theme?.surface || '#ffffff',
    '--portal-gradient': ministry.value?.theme?.gradient || 'linear-gradient(135deg, #1d5fa2, #0b2b4b)',
}));

const redirectForRole = () => {
    if (hasRole(entity, route.params.slug, 'admin')) {
        return `/brackenfell/ministries/${route.params.slug}/admin`;
    }
    if (hasRole(entity, route.params.slug, 'user')) {
        return `/brackenfell/ministries/${route.params.slug}`;
    }
    return null;
};

const submit = async () => {
    error.value = '';
    loading.value = true;
    try {
        await login(form);
        const redirect = redirectForRole();
        if (redirect) {
            router.push(redirect);
            return;
        }
        error.value = 'You do not have access to this ministry.';
    } catch (err) {
        error.value = err?.response?.data?.message || 'Unable to sign in. Please try again.';
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    if (!ministriesByEntity.value[entity]) {
        await loadEntity(entity);
    }
    await load();
    const redirect = redirectForRole();
    if (redirect) {
        router.push(redirect);
    }
});
</script>
