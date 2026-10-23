<template>
    <section id="schedule-audits" class="schedules">
        <div class="schedules-header">
            <div>
                <h2>Schedule History</h2>
                <p>Track who added, edited, or deleted schedule items.</p>
            </div>
            <div class="schedules-actions">
                <label>
                    Start
                    <input v-model="rangeStart" type="date" />
                </label>
                <label>
                    End
                    <input v-model="rangeEnd" type="date" />
                </label>
                <button class="button ghost" type="button" @click="loadAudits" :disabled="loading">
                    Refresh
                </button>
                <button class="button outline" type="button" @click="exportCsv" :disabled="!audits.length">
                    Export CSV
                </button>
            </div>
        </div>

        <div class="schedules-card">
            <div v-if="loading" class="schedules-loading">Loading activity...</div>
            <div v-else-if="!audits.length" class="schedules-empty">No activity yet.</div>
            <div v-else class="schedules-list">
                <article v-for="item in audits" :key="item.id" class="schedules-item">
                    <div>
                        <p class="schedules-title">{{ formatAction(item) }}</p>
                        <p class="schedules-meta">
                            {{ formatDateTime(item.occurred_at) }}
                        </p>
                        <p v-if="item.schedule" class="schedules-meta">
                            {{ item.schedule.title }} · {{ formatDate(item.schedule.date) }}
                            <span v-if="item.schedule.start_time">· {{ item.schedule.start_time }}</span>
                        </p>
                        <div v-if="item.payload" class="schedules-meta">
                            <details>
                                <summary>View changes</summary>
                                <div class="schedules-audit-changes">
                                    <div class="schedules-audit-column">
                                        <p class="schedules-audit-title">Before</p>
                                        <pre>{{ formatPayload(item.payload.before) }}</pre>
                                    </div>
                                    <div class="schedules-audit-column">
                                        <p class="schedules-audit-title">After</p>
                                        <pre>{{ formatPayload(item.payload.after) }}</pre>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    entity: { type: String, required: true },
    ministrySlug: { type: String, required: true },
    limit: { type: Number, default: 50 },
});

const audits = ref([]);
const loading = ref(false);
const today = new Date();
const rangeEnd = ref(today.toISOString().slice(0, 10));
const rangeStart = ref(new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));

const loadAudits = async () => {
    loading.value = true;
    const { data } = await window.axios.get(
        `/${props.entity}/ministries/${props.ministrySlug}/schedules/audits`,
        { params: { limit: props.limit, start: rangeStart.value, end: rangeEnd.value } }
    );
    audits.value = data.audits || [];
    loading.value = false;
};

const formatDate = (value) =>
    new Date(value).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

const formatDateTime = (value) =>
    new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

const formatAction = (item) => {
    const actor = item.actor?.name || 'Someone';
    if (item.action === 'created') return `${actor} created a schedule`;
    if (item.action === 'updated') return `${actor} updated a schedule`;
    if (item.action === 'deleted') return `${actor} deleted a schedule`;
    return `${actor} changed a schedule`;
};

const formatPayload = (value) => {
    if (!value) return '—';
    return JSON.stringify(value, null, 2);
};

const exportCsv = () => {
    const rows = [
        [
            'id',
            'action',
            'occurred_at',
            'actor_name',
            'actor_email',
            'schedule_id',
            'schedule_title',
            'schedule_date',
            'schedule_start_time',
            'before',
            'after',
        ],
        ...audits.value.map((item) => [
            item.id,
            item.action,
            item.occurred_at,
            item.actor?.name || '',
            item.actor?.email || '',
            item.schedule?.id || '',
            item.schedule?.title || '',
            item.schedule?.date || '',
            item.schedule?.start_time || '',
            JSON.stringify(item.payload?.before || {}),
            JSON.stringify(item.payload?.after || {}),
        ]),
    ];

    const csv = rows
        .map((row) =>
            row
                .map((value) => {
                    const str = String(value ?? '');
                    if (str.includes('"') || str.includes(',') || str.includes('\n')) {
                        return `"${str.replace(/"/g, '""')}"`;
                    }
                    return str;
                })
                .join(',')
        )
        .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const safeSlug = props.ministrySlug.replace(/[^a-z0-9-_]+/gi, '-');
    link.href = url;
    link.download = `schedule-audits-${safeSlug}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

onMounted(async () => {
    await loadAudits();
});
</script>
