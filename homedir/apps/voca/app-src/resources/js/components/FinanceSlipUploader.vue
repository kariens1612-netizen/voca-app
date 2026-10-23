<template>
    <form class="portal-card finance-slip-uploader" @submit.prevent="submit">
        <div>
            <p class="portal-tag">Finance slips</p>
            <h3>Upload a purchase slip</h3>
            <p>Send receipts, invoices, and reimbursement documents to the finance team.</p>
        </div>

        <div class="field-row">
            <label>Supplier<input v-model="form.supplier" placeholder="Shop or supplier" /></label>
            <label>Amount<input v-model="form.amount" type="number" min="0" step="0.01" placeholder="0.00" /></label>
            <label>Purchase date<input v-model="form.purchased_at" type="date" /></label>
        </div>

        <label>Notes<textarea v-model="form.notes" rows="3" placeholder="What was bought and why?"></textarea></label>
        <label>Slip file<input ref="fileInput" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv" required @change="selectFile" /></label>

        <p v-if="message" class="builder-notice">{{ message }}</p>
        <p v-if="error" class="builder-error">{{ error }}</p>
        <button class="button" :disabled="saving || !file">{{ saving ? 'Uploading...' : 'Submit slip' }}</button>
    </form>
</template>

<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
    ministryId: { type: Number, required: true },
});

const file = ref(null);
const fileInput = ref(null);
const saving = ref(false);
const message = ref('');
const error = ref('');
const form = reactive({
    supplier: '',
    amount: '',
    purchased_at: '',
    notes: '',
});

const selectFile = (event) => {
    error.value = '';
    file.value = event.target.files?.[0] || null;
    if (file.value && file.value.size > 8 * 1024 * 1024) {
        error.value = 'Choose a file smaller than 8 MB.';
        file.value = null;
        event.target.value = '';
    }
};

const submit = async () => {
    if (!file.value) return;

    saving.value = true;
    error.value = '';
    message.value = '';

    const payload = new FormData();
    payload.append('ministry_id', props.ministryId);
    payload.append('slip', file.value);
    Object.entries(form).forEach(([key, value]) => {
        if (value !== '') payload.append(key, value);
    });

    try {
        await window.axios.post('/api/finance/slips', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        Object.assign(form, { supplier: '', amount: '', purchased_at: '', notes: '' });
        file.value = null;
        if (fileInput.value) fileInput.value.value = '';
        message.value = 'Slip submitted to finance.';
    } catch (requestError) {
        const validationErrors = requestError?.response?.data?.errors;
        error.value = validationErrors
            ? Object.values(validationErrors).flat()[0]
            : requestError?.response?.data?.message || 'Slip upload failed.';
    } finally {
        saving.value = false;
    }
};
</script>
