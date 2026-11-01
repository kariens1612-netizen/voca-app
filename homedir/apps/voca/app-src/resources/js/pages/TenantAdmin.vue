<template>
    <section class="section"><div class="container">
        <div class="section-heading"><div><p class="eyebrow">Organisation administration</p><h1>Configure {{ tenant?.name }}</h1></div></div>
        <RouterLink v-if="permissions.manage_owners" class="button" to="/admin/setup">Guided owner setup</RouterLink>
        <p v-if="message" class="status-card">{{ message }}</p>
        <div class="admin-grid">
            <form class="glass-card admin-panel" @submit.prevent="saveBrand">
                <h2>Brand and identity</h2>
                <label>Display name<input v-model="brand.name" required /></label>
                <label>Tagline<input v-model="brand.tagline" /></label>
                <label>About this ministry<textarea v-model="brand.description" rows="4"></textarea></label>
                <label>Logo URL<input v-model="brand.logo_url" type="url" /></label>
                <label>Home page thumbnail URL<input v-model="brand.thumbnail_url" type="url" /></label>
                <label>Rumble channel or live URL<input v-model="brand.rumble_url" type="url" /></label>
                <div class="field-row"><label>Primary colour<input v-model="brand.primary_colour" type="color" /></label><label>Secondary colour<input v-model="brand.secondary_colour" type="color" /></label></div>
                <label>Contact email<input v-model="brand.contact.email" type="email" /></label>
                <label>Timezone<input v-model="settings.timezone" placeholder="Africa/Johannesburg" /></label>
                <div class="social-editor">
                    <h3>Social links</h3>
                    <label>Facebook<input v-model="brand.social_links.facebook" type="url" /></label>
                    <label>Instagram<input v-model="brand.social_links.instagram" type="url" /></label>
                    <label>YouTube<input v-model="brand.social_links.youtube" type="url" /></label>
                    <label>TikTok<input v-model="brand.social_links.tiktok" type="url" /></label>
                    <label>Website<input v-model="brand.social_links.website" type="url" /></label>
                </div>
                <button class="button" :disabled="saving">Save branding</button>
            </form>
            <form class="glass-card admin-panel" @submit.prevent="saveModules">
                <h2>Enabled modules</h2>
                <label v-for="(_, key) in modules" :key="key" class="toggle-row"><input v-model="modules[key]" type="checkbox" /><span>{{ labels[key] || key }}</span></label>
                <button class="button" :disabled="saving">Save modules</button>
            </form>
            <div class="glass-card admin-panel">
                <h2>Ministries</h2>
                <form class="inline-form" @submit.prevent="createMinistry"><input v-model="newMinistry.name" placeholder="Ministry name" required /><input v-model="newMinistry.slug" placeholder="ministry-slug" required /><button class="button">Add</button></form>
                <ul class="admin-list"><li v-for="ministry in ministries" :key="ministry.id"><strong>{{ ministry.name }}</strong><span>{{ ministry.slug }}</span></li></ul>
            </div>
            <div class="glass-card admin-panel">
                <h2>Administrators and users</h2>
                <form class="stack-form" @submit.prevent="inviteUser"><input v-model="invite.name" placeholder="Name" required /><input v-model="invite.email" type="email" placeholder="Email" required /><select v-model="invite.role"><option value="user">User</option><option value="admin">Admin</option><option v-if="permissions.manage_owners" value="owner">Owner</option></select><button class="button">Add user</button></form>
                <ul class="admin-list"><li v-for="membership in users" :key="membership.id"><span><strong>{{ membership.user.name }}</strong><small>{{ membership.user.email }}</small></span><div class="button-row"><select :value="membership.role" :disabled="!permissions.manage_owners && membership.role === 'owner'" aria-label="Organisation role" @change="changeAccess(membership, { role: $event.target.value })"><option value="user">User</option><option value="admin">Admin</option><option v-if="permissions.manage_owners || membership.role === 'owner'" value="owner">Owner</option></select><span>{{ membership.status }}</span><button class="button ghost" :disabled="!permissions.manage_owners && membership.role === 'owner'" @click="changeAccess(membership, { status: membership.status === 'active' ? 'suspended' : 'active' })">{{ membership.status === 'active' ? 'Suspend' : 'Restore' }}</button><button class="button ghost" :disabled="membership.status !== 'active' || (!permissions.manage_owners && membership.role === 'owner')" @click="resendInvite(membership)">Send invitation</button><button class="button ghost" :disabled="!permissions.manage_owners && membership.role === 'owner'" @click="removeAccess(membership)">Remove access</button></div></li></ul>
            </div>
            <div class="glass-card admin-panel media-manager">
                <div class="panel-heading">
                    <div>
                        <h2>Media manager</h2>
                        <p class="muted">Upload images and files up to 8 MB.</p>
                    </div>
                    <select v-model="mediaFilter" @change="loadMedia">
                        <option value="">All media</option>
                        <option value="image">Images</option>
                        <option value="file">Files</option>
                    </select>
                </div>
                <form class="media-upload" @submit.prevent="uploadMedia">
                    <input ref="mediaInput" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" @change="selectMedia" />
                    <button class="button" :disabled="uploading || !selectedMedia">{{ uploading ? 'Uploading...' : 'Upload' }}</button>
                </form>
                <p v-if="mediaError" class="error">{{ mediaError }}</p>
                <div v-if="mediaItems.length" class="media-grid">
                    <article v-for="item in mediaItems" :key="item.id" class="media-card">
                        <img v-if="item.type === 'image'" :src="item.url" :alt="item.name" />
                        <div v-else class="file-preview">{{ fileExtension(item.name) }}</div>
                        <div class="media-card-body">
                            <strong :title="item.name">{{ item.name }}</strong>
                            <span>{{ formatBytes(item.size) }}</span>
                            <input :value="item.url" readonly @focus="$event.target.select()" />
                            <div class="button-row">
                                <button type="button" class="button ghost" @click="copyUrl(item.url)">Copy URL</button>
                                <button type="button" class="button ghost danger" @click="deleteMedia(item)">Delete</button>
                            </div>
                        </div>
                    </article>
                </div>
                <p v-else class="muted">No media uploaded yet.</p>
            </div>
        </div>
        <StreamSettingsManager v-if="tenant?.modules?.livestreaming" :can-manage="permissions.manage_organisation" />
    </div></section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import StreamSettingsManager from '../components/StreamSettingsManager.vue';
import { useTenant } from '../composables/useTenant';
const { tenant, permissions, load } = useTenant();
const saving = ref(false); const message = ref(''); const ministries = ref([]); const users = ref([]);
const mediaItems = ref([]); const mediaFilter = ref(''); const selectedMedia = ref(null); const mediaInput = ref(null); const mediaError = ref(''); const uploading = ref(false);
const brand = reactive({ name: '', tagline: '', description: '', logo_url: '', thumbnail_url: '', rumble_url: '', primary_colour: '#1b4da3', secondary_colour: '#081224', contact: { email: '' }, social_links: {} });
const settings = reactive({ timezone: 'Africa/Johannesburg' });
const modules = reactive({});
const labels = { livestreaming: 'Livestreaming', events: 'Events', inspiration: 'Inspiration', schedules: 'Schedules', announcements: 'Announcements', kanban: 'Kanban board', bookings: 'Bookings', public_ministries: 'Public ministry pages', commerce: 'Commerce', finance: 'Finance' };
const newMinistry = reactive({ name: '', slug: '' }); const invite = reactive({ name: '', email: '', role: 'user' });
const notify = (text) => { message.value = text; window.setTimeout(() => { message.value = ''; }, 3500); };
const refresh = async () => { const [m, u] = await Promise.all([window.axios.get('/api/admin/ministries'), window.axios.get('/api/admin/users')]); ministries.value = m.data.data; users.value = u.data.data.data || u.data.data; };
const loadMedia = async () => { const response = await window.axios.get('/api/media', { params: { type: mediaFilter.value || undefined } }); mediaItems.value = response.data.data.data || []; };
const saveBrand = async () => { saving.value = true; try { await window.axios.patch('/api/admin/settings', { branding: brand, settings }); await load(true); notify('Branding updated.'); } finally { saving.value = false; } };
const saveModules = async () => { saving.value = true; try { await window.axios.patch('/api/admin/modules', { modules }); await load(true); notify('Modules updated.'); } finally { saving.value = false; } };
const createMinistry = async () => { await window.axios.post('/api/admin/ministries', newMinistry); newMinistry.name = ''; newMinistry.slug = ''; await refresh(); notify('Ministry created.'); };
const inviteUser = async () => { try { const { data } = await window.axios.post('/api/admin/users', invite); invite.name = ''; invite.email = ''; invite.role = 'user'; await refresh(); notify(data.invitation_sent ? 'Invitation sent.' : 'Access exists. A password link was sent recently; try resending shortly.'); } catch (e) { notify(e?.response?.data?.message || 'Unable to invite user.'); } };
const changeAccess = async (membership, changes) => { try { await window.axios.patch(`/api/admin/users/${membership.id}`, changes); notify('Access updated.'); } catch (e) { notify(e?.response?.data?.message || 'Unable to update access.'); } finally { await refresh(); } };
const resendInvite = async membership => { try { const { data } = await window.axios.post(`/api/admin/users/${membership.id}/invite`); notify(data.message); } catch (e) { notify(e?.response?.data?.message || 'Unable to send invitation.'); } };
const removeAccess = async membership => { if (!window.confirm(`Remove ${membership.user.name}'s organisation access and ministry memberships?`)) return; try { await window.axios.delete(`/api/admin/users/${membership.id}`); await refresh(); notify('Access removed.'); } catch (e) { notify(e?.response?.data?.message || 'Unable to remove access.'); } };
const selectMedia = (event) => {
    mediaError.value = '';
    selectedMedia.value = event.target.files?.[0] || null;
    if (selectedMedia.value && selectedMedia.value.size > 8 * 1024 * 1024) {
        mediaError.value = 'Choose a file smaller than 8 MB.';
        selectedMedia.value = null;
        event.target.value = '';
    }
};
const uploadMedia = async () => {
    if (!selectedMedia.value) return;
    uploading.value = true; mediaError.value = '';
    const form = new FormData(); form.append('asset', selectedMedia.value);
    try {
        await window.axios.post('/api/media', form, { headers: { 'Content-Type': 'multipart/form-data' } });
        selectedMedia.value = null; if (mediaInput.value) mediaInput.value.value = '';
        await loadMedia(); notify('Media uploaded.');
    } catch (error) {
        mediaError.value = error?.response?.data?.message || error?.response?.data?.errors?.asset?.[0] || 'Upload failed.';
    } finally { uploading.value = false; }
};
const deleteMedia = async (item) => { if (!window.confirm(`Delete ${item.name}? This cannot be undone.`)) return; await window.axios.delete(`/api/admin/media/${item.id}`); await loadMedia(); notify('Media deleted.'); };
const copyUrl = async (url) => { await navigator.clipboard.writeText(url); notify('Media URL copied.'); };
const formatBytes = (bytes) => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
const fileExtension = (name) => (name.split('.').pop() || 'file').slice(0, 5).toUpperCase();
onMounted(async () => { await load(); Object.assign(brand, tenant.value.branding || {}); brand.contact ||= {}; brand.social_links ||= {}; Object.assign(settings, { timezone: tenant.value.timezone }); Object.assign(modules, tenant.value.modules || {}); await Promise.all([refresh(), loadMedia()]); });
</script>
