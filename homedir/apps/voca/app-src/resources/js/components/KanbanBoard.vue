<template>
    <div class="kanban">
        <div class="kanban-header">
            <div>
                <h2>Ministry Kanban</h2>
                <p v-if="canManage" class="kanban-subtitle">Manage assignments and updates for this ministry.</p>
                <p v-else class="kanban-subtitle">Your assigned tasks for this ministry.</p>
            </div>
            <button v-if="canManage" class="button" type="button" @click="showCreate = !showCreate">
                {{ showCreate ? 'Close' : 'New card' }}
            </button>
        </div>

        <form v-if="canManage && showCreate" class="kanban-form" @submit.prevent="createCard">
            <div class="kanban-form-row">
                <label>
                    Title
                    <input v-model="newCard.title" type="text" maxlength="140" required />
                </label>
                <label>
                    Status
                    <select v-model="newCard.status">
                        <option value="todo">To do</option>
                        <option value="doing">Doing</option>
                        <option value="done">Done</option>
                    </select>
                </label>
            </div>
            <label>
                Description
                <textarea v-model="newCard.description" maxlength="2000" rows="3"></textarea>
            </label>
            <label>
                Assign to
                <select v-model="newCard.assigned_user_id">
                    <option :value="null">Unassigned</option>
                    <option v-for="member in members" :key="member.id" :value="member.id">
                        {{ member.name }} ({{ member.role }})
                    </option>
                </select>
            </label>
            <div class="kanban-form-actions">
                <button class="button" type="submit" :disabled="saving">Create card</button>
            </div>
        </form>

        <div v-if="loading" class="kanban-loading">Loading board...</div>
        <div v-else class="kanban-columns">
            <div v-for="column in columns" :key="column.key" class="kanban-column">
                <div class="kanban-column-header">
                    <h3>{{ column.label }}</h3>
                    <span class="kanban-count">{{ cardsByStatus[column.key].length }}</span>
                </div>
                <div class="kanban-list">
                    <article v-for="card in cardsByStatus[column.key]" :key="card.id" class="kanban-card">
                        <div class="kanban-card-header">
                            <h4>{{ card.title }}</h4>
                            <span v-if="card.assigned_user" class="kanban-assignee">
                                {{ card.assigned_user.name }}
                            </span>
                        </div>
                        <p v-if="card.description" class="kanban-card-body">{{ card.description }}</p>

                        <div v-if="canManage" class="kanban-controls">
                            <label>
                                Status
                                <select v-model="card.status" @change="updateCard(card)">
                                    <option value="todo">To do</option>
                                    <option value="doing">Doing</option>
                                    <option value="done">Done</option>
                                </select>
                            </label>
                            <label>
                                Assign
                                <select
                                    v-model="card.assigned_user_id"
                                    @change="updateCard(card)"
                                >
                                    <option :value="null">Unassigned</option>
                                    <option v-for="member in members" :key="member.id" :value="member.id">
                                        {{ member.name }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <div v-else class="kanban-meta">
                            <p v-if="card.assigned_user" class="kanban-meta-line">
                                Assigned to {{ card.assigned_user.name }}
                            </p>
                        </div>

                        <div class="kanban-comments">
                            <p class="kanban-comments-title">Comments</p>
                            <div v-if="!card.comments.length" class="kanban-empty">No comments yet.</div>
                            <div v-else class="kanban-comment" v-for="comment in card.comments" :key="comment.id">
                                <p class="kanban-comment-author">{{ comment.user.name }}</p>
                                <p class="kanban-comment-body">{{ comment.body }}</p>
                            </div>
                            <form
                                v-if="canComment(card)"
                                class="kanban-comment-form"
                                @submit.prevent="addComment(card)"
                            >
                                <input
                                    v-model="commentDrafts[card.id]"
                                    type="text"
                                    maxlength="2000"
                                    placeholder="Write a comment"
                                    required
                                />
                                <button class="button ghost" type="submit">Send</button>
                            </form>
                        </div>
                    </article>
                    <div v-if="!cardsByStatus[column.key].length" class="kanban-empty">
                        No cards here yet.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useAuth } from '../composables/useAuth';

const props = defineProps({
    entity: { type: String, required: true },
    ministrySlug: { type: String, required: true },
    canManage: { type: Boolean, default: false },
});

const { user } = useAuth();
const loading = ref(true);
const saving = ref(false);
const showCreate = ref(false);
const cards = ref([]);
const members = ref([]);
const commentDrafts = reactive({});

const columns = [
    { key: 'todo', label: 'To do' },
    { key: 'doing', label: 'Doing' },
    { key: 'done', label: 'Done' },
];

const cardsByStatus = computed(() =>
    columns.reduce((acc, column) => {
        acc[column.key] = cards.value.filter((card) => card.status === column.key);
        return acc;
    }, {})
);

const newCard = reactive({
    title: '',
    description: '',
    status: 'todo',
    assigned_user_id: null,
});

const load = async () => {
    loading.value = true;
    const { data } = await window.axios.get(
        `/${props.entity}/ministries/${props.ministrySlug}/kanban/cards`
    );
    cards.value = data.cards.map((card) => ({
        ...card,
        assigned_user_id: card.assigned_user?.id ?? null,
    }));
    if (props.canManage) {
        const response = await window.axios.get(
            `/${props.entity}/ministries/${props.ministrySlug}/members`
        );
        members.value = response.data.members;
    }
    loading.value = false;
};

const createCard = async () => {
    saving.value = true;
    const payload = {
        title: newCard.title,
        description: newCard.description || null,
        status: newCard.status,
        assigned_user_id: newCard.assigned_user_id ? Number(newCard.assigned_user_id) : null,
    };
    const response = await window.axios.post(
        `/${props.entity}/ministries/${props.ministrySlug}/kanban/cards`,
        payload
    );
    cards.value.unshift({
        ...response.data,
        assigned_user_id: response.data.assigned_user?.id ?? null,
    });
    newCard.title = '';
    newCard.description = '';
    newCard.status = 'todo';
    newCard.assigned_user_id = null;
    saving.value = false;
};

const updateCard = async (card) => {
    if (!props.canManage) return;
    const assignedId = card.assigned_user_id ? Number(card.assigned_user_id) : null;
    await window.axios.patch(
        `/${props.entity}/ministries/${props.ministrySlug}/kanban/cards/${card.id}`,
        {
            status: card.status,
            assigned_user_id: assignedId,
            title: card.title,
            description: card.description || null,
        }
    );
    if (assignedId) {
        const assigned = members.value.find((member) => member.id === assignedId);
        card.assigned_user = assigned
            ? { id: assigned.id, name: assigned.name, email: assigned.email }
            : null;
    } else {
        card.assigned_user = null;
    }
};

const canComment = (card) => {
    if (props.canManage) return true;
    return card.assigned_user?.id === user.value?.id;
};

const addComment = async (card) => {
    const body = commentDrafts[card.id]?.trim();
    if (!body) return;
    const response = await window.axios.post(
        `/${props.entity}/ministries/${props.ministrySlug}/kanban/cards/${card.id}/comments`,
        { body }
    );
    card.comments.push(response.data);
    commentDrafts[card.id] = '';
};

onMounted(load);
</script>
