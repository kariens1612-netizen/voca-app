import { ref } from 'vue';

const ministriesByEntity = ref({});
const loadingEntities = ref({});

const loadEntity = async (entity, options = {}) => {
    const force = Boolean(options.force);
    if (loadingEntities.value[entity]) {
        return;
    }

    if (!force && ministriesByEntity.value[entity]) {
        return;
    }

    loadingEntities.value[entity] = true;
    try {
        const response = await window.axios.get(`/${entity}/ministries`);
        ministriesByEntity.value[entity] = response.data.ministries || [];
    } catch (error) {
        ministriesByEntity.value[entity] = [];
    } finally {
        loadingEntities.value[entity] = false;
    }
};

const getMinistry = (entity, slug) => {
    return (ministriesByEntity.value[entity] || []).find((ministry) => ministry.slug === slug);
};

export function useMinistries() {
    return {
        ministriesByEntity,
        loadEntity,
        getMinistry,
    };
}
