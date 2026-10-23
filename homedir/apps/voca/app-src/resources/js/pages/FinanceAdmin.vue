<template>
    <section class="section">
        <div class="container">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Back office finance</p>
                    <h1>Finance control</h1>
                    <p class="muted">Review ministry slips and open the accounting system from one restricted workspace.</p>
                </div>
                <a class="button" :href="akauntingUrl">Open Akaunting</a>
            </div>

            <p v-if="message" class="status-card">{{ message }}</p>

            <div class="admin-grid">
                <article class="glass-card admin-panel">
                    <div class="panel-heading">
                        <div>
                            <h2>Submitted slips</h2>
                            <p class="muted">Only finance, administration, and admins can see this list.</p>
                        </div>
                        <select v-model="status" @change="loadSlips">
                            <option value="">All statuses</option>
                            <option value="submitted">Submitted</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="captured">Captured</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>

                    <p v-if="error" class="builder-error">{{ error }}</p>
                    <div v-if="slips.length" class="finance-slip-list">
                        <article v-for="slip in slips" :key="slip.id" class="finance-slip-row">
                            <div>
                                <strong>{{ slip.supplier || slip.name }}</strong>
                                <span>{{ slip.ministry?.name || 'Ministry' }} · {{ money(slip) }} · {{ dateLabel(slip.purchased_at || slip.created_at) }}</span>
                                <small>{{ slip.uploaded_by?.name || 'Unknown uploader' }}</small>
                            </div>
                            <select v-model="slip.status" @change="updateStatus(slip)">
                                <option value="submitted">Submitted</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="captured">Captured</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <a class="button ghost" :href="slip.url" target="_blank" rel="noopener">View</a>
                        </article>
                    </div>
                    <p v-else class="muted">No slips found.</p>
                </article>

                <article class="glass-card admin-panel">
                    <h2>Akaunting sidecar</h2>
                    <p class="muted">Install Akaunting at the configured URL, then restrict that path or subdomain at the web-server level to the same finance access policy.</p>
                    <label>Configured URL<input :value="akauntingUrl" readonly @focus="$event.target.select()" /></label>
                    <div class="button-row">
                        <a class="button ghost" :href="akauntingUrl">Open accounting</a>
                        <button type="button" class="button ghost" @click="copyUrl">Copy URL</button>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const akauntingUrl = ref('/back-office/accounting');
const slips = ref([]);
const status = ref('submitted');
const error = ref('');
const message = ref('');

const loadSlips = async () => {
    error.value = '';
    try {
        const response = await window.axios.get('/api/finance/slips', {
            params: { status: status.value || undefined },
        });
        slips.value = response.data.data.data || [];
    } catch (requestError) {
        error.value = requestError?.response?.data?.message || 'Finance slips could not be loaded.';
    }
};

const updateStatus = async (slip) => {
    await window.axios.patch(`/api/finance/slips/${slip.id}`, { status: slip.status });
    message.value = 'Slip status updated.';
    window.setTimeout(() => { message.value = ''; }, 3000);
};

const copyUrl = async () => {
    await navigator.clipboard.writeText(new URL(akauntingUrl.value, window.location.origin).href);
    message.value = 'Akaunting URL copied.';
    window.setTimeout(() => { message.value = ''; }, 3000);
};

const money = (slip) => {
    if (!slip.amount) return 'Amount not set';
    return new Intl.NumberFormat('en-ZA', { style: 'currency', currency: slip.currency || 'ZAR' }).format(slip.amount);
};

const dateLabel = (value) => value ? new Date(value).toLocaleDateString('en-ZA') : 'No date';

onMounted(async () => {
    const response = await window.axios.get('/api/finance/config');
    akauntingUrl.value = response.data.akaunting_url || akauntingUrl.value;
    await loadSlips();
});
</script>
