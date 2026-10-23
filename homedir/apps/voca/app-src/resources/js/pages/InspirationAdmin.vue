<template>
    <section class="section inspiration-admin">
        <div class="container">
            <header class="content-heading">
                <div>
                    <p class="portal-tag">Admin Portal</p>
                    <h1>Content Builder</h1>
                    <p>Create scriptures, newsletters, prophetic words, encouragement and short videos.</p>
                </div>
                <div class="builder-heading-actions">
                    <RouterLink class="button ghost" to="/inspiration">View Inspiration hub</RouterLink>
                    <button class="button" type="button" @click="startNew">Create content</button>
                </div>
            </header>

            <div class="content-filters" aria-label="Content filters">
                <input v-model="filters.search" type="search" placeholder="Search content" @input="debouncedLoad">
                <select v-model="filters.type" @change="loadItems"><option value="">All types</option><option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option></select>
                <select v-model="filters.status" @change="loadItems"><option value="">All statuses</option><option v-for="item in statuses" :key="item" :value="item">{{ titleCase(item) }}</option></select>
                <select v-model="filters.audience" @change="loadItems"><option value="">All audiences</option><option value="public">Public</option><option value="members">Members only</option></select>
            </div>

            <p v-if="notice" class="builder-notice" role="status">{{ notice }}</p>
            <p v-if="error" class="builder-error" role="alert">{{ error }}</p>

            <div class="content-admin-grid">
                <div class="content-list">
                    <article v-for="item in items" :key="item.id" class="content-admin-card">
                        <img v-if="item.media?.cover" class="content-admin-thumbnail" :src="item.media.cover.url" :alt="item.title" loading="lazy">
                        <div>
                            <span class="content-type">{{ labelFor(item.type) }}</span>
                            <h2>{{ item.title }}</h2>
                            <p>{{ item.summary || 'No summary added.' }}</p>
                            <div class="content-meta">
                                <span :class="`status status-${item.status}`">{{ titleCase(item.status) }}</span>
                                <span>{{ item.audience === 'members' ? 'Members only' : 'Public' }}</span>
                                <span>{{ item.ministry?.name }}</span>
                            </div>
                        </div>
                        <div class="content-card-actions">
                            <button type="button" class="text-button" @click="editItem(item)">Edit</button>
                            <button type="button" class="text-button" @click="duplicateItem(item)">Duplicate</button>
                            <button v-if="item.status !== 'published'" type="button" class="text-button" @click="transition(item, 'publish')">Publish</button>
                            <button v-else type="button" class="text-button" @click="transition(item, 'unpublish')">Unpublish</button>
                            <button type="button" class="text-button danger" @click="removeItem(item)">Delete</button>
                        </div>
                    </article>
                    <p v-if="!loading && !items.length" class="empty-state">No content matches these filters.</p>
                    <button v-if="page < lastPage" class="button ghost load-more" type="button" @click="loadMore">Load more</button>
                </div>

                <form v-if="editing" class="content-editor" @submit.prevent="save">
                    <div class="editor-title">
                        <h2>{{ form.id ? 'Edit content' : 'New content' }}</h2>
                        <button type="button" class="editor-close" aria-label="Close editor" @click="editing = false">×</button>
                    </div>
                    <label>Content type<select v-model="form.type" required><option v-for="item in types" :key="item.value" :value="item.value">{{ item.label }}</option></select></label>
                    <label v-if="isSuperadmin">Ministry<select v-model="form.ministry_id" required><option v-for="ministry in ministries" :key="ministry.id" :value="ministry.id">{{ ministry.name }}</option></select></label>
                    <label>Title<input v-model="form.title" required maxlength="255"></label>
                    <label>Summary<textarea v-model="form.summary" rows="3" maxlength="2000"></textarea></label>
                    <div class="editor-two-column">
                        <label>Author / speaker<input v-model="form.author"></label>
                        <label>Audience<select v-model="form.audience"><option value="public">Public</option><option value="members">Members only</option></select></label>
                    </div>
                    <div v-if="form.type === 'scripture'" class="editor-two-column">
                        <label>Scripture reference<input v-model="form.scripture_reference" placeholder="John 3:16"></label>
                        <label>Translation<input v-model="form.scripture_translation" placeholder="NIV"></label>
                    </div>
                    <label v-if="form.type !== 'video' || form.body">Formatted content
                        <textarea v-model="form.body" rows="9" placeholder="Write the full message. Basic HTML formatting is supported."></textarea>
                    </label>
                    <div class="editor-two-column">
                        <label>Thumbnail / cover image<input type="file" accept="image/*" @change="setFile('cover', $event)"><small v-if="form.existingCover">{{ form.existingCover }}</small></label>
                        <label v-if="form.type === 'newsletter'">Newsletter PDF<input type="file" accept="application/pdf" @change="setFile('pdf', $event)"><small v-if="form.existingPdf">{{ form.existingPdf }}</small></label>
                    </div>
                    <template v-if="form.type === 'video'">
                        <label>Video link<input v-model="form.external_video_url" type="url" placeholder="YouTube, Vimeo or Rumble URL"></label>
                        <p class="field-divider">or upload a video</p>
                        <label>MP4 or WebM video<input type="file" accept="video/mp4,video/webm" @change="setFile('video', $event)"><small v-if="form.existingVideo">{{ form.existingVideo }}</small></label>
                    </template>
                    <div class="editor-two-column">
                        <label>Status<select v-model="form.status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="published">Published</option><option value="archived">Archived</option></select></label>
                        <label v-if="form.status === 'scheduled'">Publish date and time<input v-model="form.publish_at" type="datetime-local" required></label>
                    </div>
                    <label class="checkbox-label"><input v-model="form.is_featured" type="checkbox"> Feature this item in the Inspiration hub</label>
                    <div v-if="uploadProgress > 0 && uploadProgress < 100" class="upload-progress"><span :style="{ width: `${uploadProgress}%` }"></span></div>
                    <div class="editor-actions">
                        <button class="button" type="submit" :disabled="saving">{{ saving ? `Saving ${uploadProgress || ''}%` : 'Save content' }}</button>
                        <button class="button ghost" type="button" @click="editing = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import { useMinistries } from '../composables/useMinistries';

const route = useRoute();
const { user, isSuperadmin, load: loadAuth } = useAuth();
const { ministriesByEntity, loadEntity } = useMinistries();
const ministries = computed(() => ministriesByEntity.value.brackenfell || []);
const types = [
    { value: 'scripture', label: 'Scripture' }, { value: 'newsletter', label: 'Newsletter' },
    { value: 'prophetic_word', label: 'Prophetic word' }, { value: 'encouragement', label: 'Word of encouragement' },
    { value: 'video', label: 'Encouragement video' },
];
const statuses = ['draft', 'scheduled', 'published', 'archived'];
const filters = reactive({ search: '', type: '', status: '', audience: '' });
const items = ref([]); const page = ref(1); const lastPage = ref(1); const loading = ref(false);
const editing = ref(false); const saving = ref(false); const error = ref(''); const notice = ref('');
const uploadProgress = ref(0); let searchTimer;
const blank = () => ({ id: null, ministry_id: null, type: 'scripture', title: '', summary: '', body: '', author: '', scripture_reference: '', scripture_translation: '', audience: 'public', status: 'draft', publish_at: '', is_featured: false, external_video_url: '', cover: null, pdf: null, video: null });
const form = reactive(blank());
const titleCase = (value) => value ? value.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase()) : '';
const labelFor = (value) => types.find(item => item.value === value)?.label || titleCase(value);
const selectedMinistry = () => ministries.value.find(item => item.slug === route.params.slug);

const loadItems = async (append = false) => {
    loading.value = true; error.value = '';
    try {
        const response = await window.axios.get('/inspiration/admin/content', { params: { ...filters, page: page.value } });
        items.value = append ? [...items.value, ...response.data.data] : response.data.data;
        lastPage.value = response.data.last_page;
    } catch (e) { error.value = e.response?.data?.message || 'Content could not be loaded.'; }
    finally { loading.value = false; }
};
const debouncedLoad = () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { page.value = 1; loadItems(); }, 300); };
const loadMore = () => { page.value++; loadItems(true); };
const startNew = () => { Object.assign(form, blank(), { ministry_id: selectedMinistry()?.id || ministries.value[0]?.id }); editing.value = true; error.value = ''; };
const editItem = (item) => {
    Object.assign(form, blank(), item, {
        publish_at: item.publish_at ? item.publish_at.slice(0, 16) : '',
        external_video_url: item.media?.video?.external_url || '',
        existingCover: item.media?.cover?.name, existingPdf: item.media?.pdf?.name, existingVideo: item.media?.video?.name,
    });
    editing.value = true; window.scrollTo({ top: 0, behavior: 'smooth' });
};
const setFile = (name, event) => { form[name] = event.target.files?.[0] || null; };
const save = async () => {
    saving.value = true; error.value = ''; notice.value = ''; uploadProgress.value = 0;
    const data = new FormData();
    Object.entries(form).forEach(([key, value]) => {
        if (!key.startsWith('existing') && value !== null && value !== '') data.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value);
    });
    try {
        const url = form.id ? `/inspiration/admin/content/${form.id}` : '/inspiration/admin/content';
        await window.axios.post(url, data, { headers: { 'Content-Type': 'multipart/form-data' }, onUploadProgress: e => { if (e.total) uploadProgress.value = Math.round((e.loaded * 100) / e.total); } });
        notice.value = 'Content saved successfully.'; editing.value = false; page.value = 1; await loadItems();
    } catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Content could not be saved.'; }
    finally { saving.value = false; uploadProgress.value = 0; }
};
const transition = async (item, action) => { await window.axios.post(`/inspiration/admin/content/${item.id}/${action}`); notice.value = `Content ${action === 'publish' ? 'published' : action === 'unpublish' ? 'returned to draft' : 'archived'}.`; loadItems(); };
const duplicateItem = async (item) => { await window.axios.post(`/inspiration/admin/content/${item.id}/duplicate`); notice.value = 'Draft copy created.'; loadItems(); };
const removeItem = async (item) => { if (!window.confirm(`Delete “${item.title}”? This cannot be undone.`)) return; await window.axios.delete(`/inspiration/admin/content/${item.id}`); notice.value = 'Content deleted.'; loadItems(); };

onMounted(async () => { await Promise.all([loadAuth(), loadEntity('brackenfell')]); await loadItems(); });
</script>
