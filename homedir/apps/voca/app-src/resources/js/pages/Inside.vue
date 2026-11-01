<template>
    <section v-if="user?.is_voca_member && isVoca" class="section"><div class="container">
        <h1>My VOCA dashboard</h1>
        <p>Manage your member profile and organisation using the same dashboard as the VOCA website.</p>
        <button class="button" :disabled="busy" @click="openDashboard">{{ busy ? 'Opening…' : 'Open my dashboard' }}</button>
        <p v-if="error" role="alert">{{ error }}</p>
        <RouterLink v-if="signInAgain" to="/member-login?redirect=/inside">Sign in again</RouterLink>
    </div></section>
    <Home />
</template>
<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import Home from './Home.vue';
import { useAuth } from '../composables/useAuth';
import { useTenant } from '../composables/useTenant';
const { user } = useAuth();
const { isVoca } = useTenant();
const busy = ref(false), error = ref(''), signInAgain = ref(false);
const openDashboard = async () => {
    busy.value = true; error.value = ''; signInAgain.value = false;
    try {
        const { data } = await window.axios.post('/auth/member-dashboard');
        window.location.assign(data.url);
    } catch (e) {
        signInAgain.value = e?.response?.status === 401;
        error.value = e?.response?.data?.message || 'Unable to open your dashboard. Please try again.';
    } finally { busy.value = false; }
};
</script>
