<template>
    <section class="roster">
        <div class="roster-header">
            <div>
                <h2>Team roster</h2>
                <p>Add members, update roles, and assign team leaders.</p>
            </div>
            <button class="button ghost" type="button" @click="showPanel = !showPanel">
                {{ showPanel ? 'Close' : 'Manage roster' }}
            </button>
        </div>

        <div v-if="showPanel" class="roster-panel">
            <div class="roster-grid">
                <div class="roster-card">
                    <h3>Register a member</h3>
                    <p class="roster-help">
                        Register someone directly in this ministry. If their email already exists,
                        their existing account will be added.
                    </p>
                    <form class="roster-form" @submit.prevent="registerMember">
                        <label>
                            Full name
                            <input v-model.trim="registration.name" type="text" required maxlength="255" />
                        </label>
                        <label>
                            Email address
                            <input v-model.trim="registration.email" type="email" required maxlength="255" />
                        </label>
                        <label>
                            Role
                            <select v-model="registration.role">
                                <option v-for="role in roles" :key="role" :value="role">
                                    {{ roleLabels[role] || role }}
                                </option>
                            </select>
                        </label>
                        <button class="button" type="submit" :disabled="saving">
                            {{ saving ? 'Registering...' : 'Register member' }}
                        </button>
                    </form>
                    <p v-if="registrationMessage" class="roster-success">{{ registrationMessage }}</p>
                    <p v-if="errorMessage" class="roster-errors">{{ errorMessage }}</p>
                </div>

                <div class="roster-card">
                    <h3>Add an existing user</h3>
                    <form class="roster-form" @submit.prevent="addMember">
                        <label>
                            Search users
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search by name or email"
                                @input="debouncedSearch"
                            />
                        </label>
                        <label>
                            Select user
                            <select v-model="selectedUserId" required>
                                <option value="" disabled>Select a user</option>
                                <option v-for="user in available" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </label>
                        <label>
                            Role
                            <select v-model="selectedRole">
                                <option v-for="role in roles" :key="role" :value="role">
                                    {{ roleLabels[role] || role }}
                                </option>
                            </select>
                        </label>
                        <button class="button" type="submit" :disabled="saving || !selectedUserId">
                            Add member
                        </button>
                    </form>
                </div>

                <div class="roster-card">
                    <h3>Bulk import</h3>
                    <p class="roster-help">
                        Upload CSV with columns: <strong>name,email,role</strong>.
                        Roles: member, leader, admin.
                    </p>
                    <form class="roster-form" @submit.prevent="importRoster">
                        <input type="file" accept=".csv,text/csv" @change="onImportFileChange" />
                        <button class="button" type="submit" :disabled="saving || !importFile">
                            Import CSV
                        </button>
                    </form>
                    <p v-if="importMessage" class="roster-success">{{ importMessage }}</p>
                    <ul v-if="importErrors.length" class="roster-errors">
                        <li v-for="error in importErrors" :key="error">{{ error }}</li>
                    </ul>
                </div>

                <div class="roster-card">
                    <div class="roster-card-header">
                        <h3>Current members</h3>
                        <div class="roster-header-actions">
                            <label class="roster-filter">
                                Filter
                                <select v-model="roleFilter">
                                    <option value="all">All</option>
                                    <option v-for="role in roles" :key="role" :value="role">
                                        {{ roleLabels[role] || role }}
                                    </option>
                                </select>
                            </label>
                            <button class="button ghost" type="button" @click="loadMembers">
                                Refresh
                            </button>
                            <button class="button outline" type="button" @click="exportRoster">
                                Export CSV
                            </button>
                        </div>
                    </div>

                    <div v-if="loading" class="roster-loading">Loading members...</div>
                    <div v-else-if="!members.length" class="roster-empty">No members yet.</div>
                    <div v-else class="roster-list">
                        <article v-for="member in filteredMembers" :key="member.id" class="roster-item">
                            <div class="roster-info">
                                <p class="roster-name">{{ member.name }}</p>
                                <p class="roster-email">{{ member.email }}</p>
                            </div>
                            <div class="roster-actions">
                                <label class="roster-role">
                                    Role
                                    <select
                                        v-model="member.role"
                                        @change="updateRole(member)"
                                    >
                                        <option v-for="role in roles" :key="role" :value="role">
                                            {{ roleLabels[role] || role }}
                                        </option>
                                    </select>
                                </label>
                                <button
                                    class="button outline"
                                    type="button"
                                    :disabled="removingId === member.id"
                                    @click="removeMember(member)"
                                >
                                    {{ removingId === member.id ? 'Removing...' : 'Remove from ministry' }}
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    entity: { type: String, required: true },
    ministrySlug: { type: String, required: true },
});

const roles = ['member', 'leader', 'admin'];
const roleLabels = {
    member: 'Member',
    leader: 'Team leader',
    admin: 'Admin',
};

const showPanel = ref(false);
const loading = ref(false);
const saving = ref(false);
const search = ref('');
const selectedUserId = ref('');
const selectedRole = ref('member');
const roleFilter = ref('all');
const members = ref([]);
const available = ref([]);
const importFile = ref(null);
const importMessage = ref('');
const importErrors = ref([]);
const removingId = ref(null);
const registrationMessage = ref('');
const errorMessage = ref('');
const registration = ref({
    name: '',
    email: '',
    role: 'member',
});
let searchTimer = null;

const messageFromError = (error) =>
    error?.response?.data?.message || 'Something went wrong. Please try again.';

const loadMembers = async () => {
    loading.value = true;
    const { data } = await window.axios.get(
        `/${props.entity}/ministries/${props.ministrySlug}/members`
    );
    members.value = data.members;
    loading.value = false;
};

const loadAvailable = async () => {
    const { data } = await window.axios.get(
        `/${props.entity}/ministries/${props.ministrySlug}/members/available`,
        { params: { search: search.value } }
    );
    available.value = data.users;
};

const debouncedSearch = () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(loadAvailable, 250);
};

const addMember = async () => {
    if (!selectedUserId.value) return;
    saving.value = true;
    errorMessage.value = '';
    try {
        const { data } = await window.axios.post(
            `/${props.entity}/ministries/${props.ministrySlug}/members`,
            {
                user_id: Number(selectedUserId.value),
                role: selectedRole.value,
            }
        );
        members.value.unshift(data);
        available.value = available.value.filter((user) => user.id !== data.id);
        selectedUserId.value = '';
        selectedRole.value = 'member';
    } catch (error) {
        errorMessage.value = messageFromError(error);
    } finally {
        saving.value = false;
    }
};

const registerMember = async () => {
    saving.value = true;
    errorMessage.value = '';
    registrationMessage.value = '';
    try {
        const { data } = await window.axios.post(
            `/${props.entity}/ministries/${props.ministrySlug}/members/register`,
            registration.value
        );
        const existingIndex = members.value.findIndex((member) => member.id === data.id);
        if (existingIndex === -1) {
            members.value.unshift(data);
        } else {
            members.value[existingIndex] = data;
        }
        available.value = available.value.filter((user) => user.id !== data.id);
        registrationMessage.value = data.created_user
            ? `${data.name} was registered in this ministry.`
            : `${data.name}'s existing account was added to this ministry.`;
        registration.value = { name: '', email: '', role: 'member' };
    } catch (error) {
        errorMessage.value = messageFromError(error);
    } finally {
        saving.value = false;
    }
};

const updateRole = async (member) => {
    await window.axios.patch(
        `/${props.entity}/ministries/${props.ministrySlug}/members/${member.id}`,
        { role: member.role }
    );
};

const removeMember = async (member) => {
    const confirmed = window.confirm(
        `Remove ${member.name} from this ministry? Their account and records will not be deleted.`
    );
    if (!confirmed) return;

    removingId.value = member.id;
    errorMessage.value = '';
    try {
        await window.axios.delete(
            `/${props.entity}/ministries/${props.ministrySlug}/members/${member.id}`
        );
        members.value = members.value.filter((item) => item.id !== member.id);
        available.value.unshift({
            id: member.id,
            name: member.name,
            email: member.email,
        });
    } catch (error) {
        errorMessage.value = messageFromError(error);
    } finally {
        removingId.value = null;
    }
};

const exportRoster = () => {
    window.location.href = `/${props.entity}/ministries/${props.ministrySlug}/members/export`;
};

const onImportFileChange = (event) => {
    importFile.value = event.target.files?.[0] ?? null;
};

const importRoster = async () => {
    if (!importFile.value) return;
    importMessage.value = '';
    importErrors.value = [];
    saving.value = true;
    const formData = new FormData();
    formData.append('file', importFile.value);
    const { data } = await window.axios.post(
        `/${props.entity}/ministries/${props.ministrySlug}/members/import`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } }
    );
    importMessage.value = `Imported: ${data.added_memberships} added, ${data.updated_memberships} updated, ${data.created_users} new users.`;
    importErrors.value = data.errors || [];
    importFile.value = null;
    await loadMembers();
    await loadAvailable();
    saving.value = false;
};

const filteredMembers = computed(() => {
    if (roleFilter.value === 'all') {
        return members.value;
    }
    return members.value.filter((member) => member.role === roleFilter.value);
});

watch(showPanel, async (value) => {
    if (value) {
        await loadMembers();
        await loadAvailable();
    }
});
</script>
