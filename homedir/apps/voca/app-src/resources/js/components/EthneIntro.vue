<template>
<section class="ethne-intro" id="ethne" aria-labelledby="ethne-title">
 <img :src="'/images/voca/ethne/step-1.jpeg'" alt="Ethne Mobile Bible Application" loading="lazy" />
 <div><p class="eyebrow">Scripture wherever you are</p><h2 id="ethne-title">Ethne Mobile Bible App</h2>
 <p>Listen to the Bible in your heart language and share Scripture with family and friends.</p>
 <p>Download Bible content to your phone, listen offline and share it with others without Wi-Fi or mobile data.</p>
 <div class="ethne-actions"><a class="button" href="https://ethne.tech/download-the-app/" target="_blank" rel="noopener noreferrer">Download the App</a><RouterLink class="ethne-read-more" to="/ethne-app">Read More →</RouterLink></div></div>
</section>
</template>
<script setup>
import { RouterLink } from 'vue-router';
</script>
