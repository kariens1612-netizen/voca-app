<template>
    <section class="section">
        <div class="container">
            <div class="section-header">
                <p class="eyebrow">Voca International</p>
                <h1>Our global family of ministries</h1>
                <p class="section-subtitle">
                    Voca describes itself as a fresh wind of the Spirit reaching thirsty people across nations
                    with family, love, and genuine acceptance.
                </p>
            </div>

            <div class="international-feature">
                <img :src="worldMapUrl" alt="Voca international map" loading="lazy" />
                <div class="international-feature-copy">
                    <p class="spotlight-kicker">International</p>
                    <h3>House churches and ministry points across regions</h3>
                    <p>The app now uses the same international visual language as the main website, with a real map image pulled from the site.</p>
                </div>
            </div>

            <div class="international-grid">
                <article v-for="region in regions" :key="region.name" class="country-card">
                    <button class="accordion-trigger" @click="toggle(region.name)">
                        <span>{{ region.name }}</span>
                        <span class="accordion-icon">{{ open === region.name ? '−' : '+' }}</span>
                    </button>
                    <div class="country-card-body">
                        <p class="country-card-status">
                            {{ `${region.locations.length} listed House${region.locations.length > 1 ? 's' : ''} of Power / region point${region.locations.length > 1 ? 's' : ''}` }}
                        </p>
                        <div v-show="open === region.name" class="accordion-panel">
                            <ul class="list">
                                <li v-for="location in region.locations" :key="location">{{ location }}</li>
                            </ul>
                        </div>
                    </div>
                </article>
            </div>

            <div class="card note">
                <h3>Need to add a new House of Power or regional point?</h3>
                <p>
                    The app is seeded with Houses of Power (also called House Churches) already listed on the website,
                    including Brackenfell, Darling, Mitchell's Plain, Stellenberg, Chatsworth, Benin City, and online ministry. More can be added
                    as the network grows.
                </p>
                <a class="button outline" href="https://www.voca.co.za/house-churches/" target="_blank" rel="noopener">View Houses of Power</a>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue';

const open = ref('');
const worldMapUrl = '/images/voca/world-map.webp';

const toggle = (name) => {
    open.value = open.value === name ? '' : name;
};

const regions = [
    {
        name: 'South Africa',
        locations: [
            'Brackenfell',
            'Brackenfell Media Online',
            'Darling',
            "Mitchell's Plain",
            "Mitchell's Plain",
            'Stellenberg',
            'Stellenberg',
            'Piketberg',
            'Kuilsrivier',
            'Loevenstein',
            'Ridgeworth, Bellville',
            'Du Noon',
            'Chatsworth',
            'Newfields / Hanover Park',
        ],
    },
    {
        name: 'Online',
        locations: ['Brackenfell Media Online'],
    },
    {
        name: 'Nigeria',
        locations: ['Benin City, Nigeria'],
    },
];
</script>
