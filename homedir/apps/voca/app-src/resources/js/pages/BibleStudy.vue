<template>
    <section class="section bible-reader-page">
        <div class="container">
            <div class="bible-reader-hero">
                <div>
                    <p class="eyebrow">Bible Study</p>
                    <h1>Read the Bible in the app.</h1>
                    <p>
                        Choose a Bible version, book, and chapter, or search for a passage reference for personal
                        devotion and study.
                    </p>
                </div>
                <RouterLink class="button outline" to="/ministries/bible-college">
                    Open Bible College
                </RouterLink>
            </div>

            <img class="voca-section-image voca-study-image" :src="communityImages.bible.src" :alt="communityImages.bible.alt" width="1672" height="941" loading="lazy" decoding="async" />

            <div class="bible-reader-layout">
                <aside class="bible-reader-controls">
                    <label>
                        Version
                        <select v-model="selectedBible" :disabled="loadingBibles || !bibles.length">
                            <option value="">Select a Bible</option>
                            <option v-for="bible in bibles" :key="bible.id" :value="bible.id">
                                {{ bible.abbreviationLocal || bible.abbreviation || bible.nameLocal || bible.name }}
                            </option>
                        </select>
                    </label>

                    <label>
                        Book
                        <select v-model="selectedBook" :disabled="loadingBooks || !books.length">
                            <option value="">Select a book</option>
                            <option v-for="book in books" :key="book.id" :value="book.id">
                                {{ book.name }}
                            </option>
                        </select>
                    </label>

                    <label>
                        Chapter
                        <select v-model="selectedChapter" :disabled="loadingChapters || !chapters.length">
                            <option value="">Select a chapter</option>
                            <option v-for="chapter in chapters" :key="chapter.id" :value="chapter.id">
                                {{ chapter.reference }}
                            </option>
                        </select>
                    </label>

                    <form class="bible-passage-search" @submit.prevent="loadPassage">
                        <label>
                            Passage lookup
                            <input v-model.trim="passageQuery" type="search" placeholder="John 3:16" maxlength="120" />
                        </label>
                        <button class="button" type="submit" :disabled="!selectedBible || !passageQuery || loadingContent">
                            Read passage
                        </button>
                    </form>

                    <p v-if="configError" class="bible-reader-alert">{{ configError }}</p>
                    <p v-else-if="error" class="bible-reader-alert">{{ error }}</p>
                </aside>

                <article class="bible-reader-content">
                    <div class="bible-reader-heading">
                        <p class="card-label">{{ activeBibleLabel }}</p>
                        <h2>{{ contentReference || 'Select a passage' }}</h2>
                    </div>

                    <div v-if="loadingContent" class="bible-reader-empty">Loading Scripture...</div>
                    <div v-else-if="contentHtml" class="bible-scripture" v-html="contentHtml"></div>
                    <div v-else class="bible-reader-empty">
                        Select a Bible version, book, and chapter to begin reading.
                    </div>
                </article>
            </div>
        </div>
    </section>
</template>

<script setup>
import { communityImages } from '../data/voca';
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';

const bibles = ref([]);
const books = ref([]);
const chapters = ref([]);
const selectedBible = ref('');
const selectedBook = ref('');
const selectedChapter = ref('');
const passageQuery = ref('');
const contentHtml = ref('');
const contentReference = ref('');
const loadingBibles = ref(false);
const loadingBooks = ref(false);
const loadingChapters = ref(false);
const loadingContent = ref(false);
const error = ref('');
const configError = ref('');

const activeBibleLabel = computed(() => {
    const bible = bibles.value.find((item) => item.id === selectedBible.value);
    return bible?.nameLocal || bible?.name || 'API.Bible reader';
});

const apiMessage = (err, fallback) => err?.response?.data?.message || fallback;

const resetReader = () => {
    books.value = [];
    chapters.value = [];
    selectedBook.value = '';
    selectedChapter.value = '';
    contentHtml.value = '';
    contentReference.value = '';
};

const loadBibles = async () => {
    loadingBibles.value = true;
    error.value = '';
    configError.value = '';

    try {
        const { data } = await window.axios.get('/api/bible/bibles', { params: { language: 'eng' } });
        bibles.value = data.data || [];
        const preferred = bibles.value.find((item) => /kjv/i.test(`${item.abbreviation || ''} ${item.name || ''}`));
        selectedBible.value = preferred?.id || bibles.value[0]?.id || '';
    } catch (err) {
        const message = apiMessage(err, 'Bible versions could not be loaded.');
        if (err?.response?.status === 503) {
            configError.value = message;
        } else {
            error.value = message;
        }
    } finally {
        loadingBibles.value = false;
    }
};

const loadBooks = async () => {
    if (!selectedBible.value) return;

    loadingBooks.value = true;
    error.value = '';
    resetReader();

    try {
        const { data } = await window.axios.get(`/api/bible/bibles/${selectedBible.value}/books`, {
            params: { include_chapters: true },
        });
        books.value = data.data || [];
        selectedBook.value = books.value.find((book) => book.id === 'JHN')?.id || books.value[0]?.id || '';
    } catch (err) {
        error.value = apiMessage(err, 'Books could not be loaded for this Bible.');
    } finally {
        loadingBooks.value = false;
    }
};

const loadChapters = async () => {
    if (!selectedBible.value || !selectedBook.value) return;

    loadingChapters.value = true;
    error.value = '';
    chapters.value = [];
    selectedChapter.value = '';
    contentHtml.value = '';
    contentReference.value = '';

    try {
        const selected = books.value.find((book) => book.id === selectedBook.value);
        chapters.value = selected?.chapters?.length
            ? selected.chapters
            : (await window.axios.get(`/api/bible/bibles/${selectedBible.value}/books/${selectedBook.value}/chapters`)).data.data || [];
        selectedChapter.value = chapters.value.find((chapter) => chapter.number === '1')?.id || chapters.value[0]?.id || '';
    } catch (err) {
        error.value = apiMessage(err, 'Chapters could not be loaded for this book.');
    } finally {
        loadingChapters.value = false;
    }
};

const loadChapter = async () => {
    if (!selectedBible.value || !selectedChapter.value) return;

    loadingContent.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(`/api/bible/bibles/${selectedBible.value}/chapters/${selectedChapter.value}`);
        contentHtml.value = data.data?.content || '';
        contentReference.value = data.data?.reference || '';
    } catch (err) {
        error.value = apiMessage(err, 'Scripture content could not be loaded.');
    } finally {
        loadingContent.value = false;
    }
};

const loadPassage = async () => {
    if (!selectedBible.value || !passageQuery.value) return;

    loadingContent.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(`/api/bible/bibles/${selectedBible.value}/passage`, {
            params: { query: passageQuery.value },
        });
        contentHtml.value = data.data?.content || '';
        contentReference.value = data.data?.reference || passageQuery.value;
    } catch (err) {
        error.value = apiMessage(err, 'Passage could not be loaded.');
    } finally {
        loadingContent.value = false;
    }
};

watch(selectedBible, loadBooks);
watch(selectedBook, loadChapters);
watch(selectedChapter, loadChapter);
onMounted(loadBibles);
</script>
