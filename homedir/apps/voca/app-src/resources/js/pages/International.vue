<template>
    <section class="section">
        <div class="container">
            <div class="section-header">
                <p class="eyebrow">VOCA Apostolic Fellowship</p>
                <h1>Leaders connected for the good of Africa.</h1>
                <p class="section-subtitle">A trusted relational space where leaders strengthen one another, collaborate across boundaries and help the Church flourish across Africa.</p>
            </div>
            <img class="voca-section-image" :src="communityImages.leaders.src" :alt="communityImages.leaders.alt" width="1672" height="941" fetchpriority="high" />
            <article class="card note"><h2>Relationship before strategy.</h2><p>Through prayer, conversation and shared mission, leaders find encouragement and practical connection with people serving in different contexts with the same heart for Africa.</p></article>
            <div class="website-ministry-grid">
                <article v-for="feature in features" :key="feature.title" class="website-ministry-card card"><h3>{{ feature.title }}</h3><p>{{ feature.description }}</p></article>
            </div>
            <div class="card note"><h2>Your voice belongs in the room.</h2><p>Join a growing network of churches, ministries and leaders committed to one voice, one continent and one purpose.</p><div class="website-hero-actions"><a class="button" href="https://voca.africa/register">Join the VOCA Network</a><a class="button outline" href="https://voca.africa/apostolic-fellowship">Explore Apostolic Fellowship</a></div></div>
        </div>
    </section>
</template>

<script setup>
import { communityImages } from '../data/voca';
const features = [
    { title: 'Trusted relationships', description: 'Connect with leaders who understand the privilege and pressure of ministry.' },
    { title: 'Shared wisdom', description: 'Learn across generations, traditions, nations and ministry contexts.' },
    { title: 'Collaborative mission', description: 'Connect through projects, initiatives and practical service across Africa.' },
    { title: 'Strength for the journey', description: 'Receive prayer, encouragement and accountability for faithful, healthy leadership.' },
];
</script>
