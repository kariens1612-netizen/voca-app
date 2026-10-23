// Public information verified against https://voca.africa on 23 October 2026.
export const voca = {
    name: 'VOCA',
    subtitle: 'Voice of Christian Africa',
    tagline: 'Informing Africa. Inspiring Hope. Influencing Nations.',
    statement: 'One Voice. One Continent. One Purpose.',
    email: 'info@voca.africa',
    loginUrl: 'https://voca.africa/login',
    registrationUrl: 'https://voca.africa/register',
    vision: 'A united, informed and influential Christian community shaping a stronger Africa and blessing the nations.',
    mission: 'To inform, inspire and influence Africa by connecting the Church, amplifying credible Christian voices, equipping leaders and mobilising faith into action.',
};

export const communityImages = {
    youth: { src: '/images/voca/community/young-artists.webp', alt: 'Children and mentors creating a colourful artwork together' },
    women: { src: '/images/voca/community/women-fellowship.webp', alt: 'Women sharing conversation and fellowship around a table' },
    radio: { src: '/images/voca/community/radio-studio.webp', alt: 'Two speakers in conversation at a radio studio' },
    business: { src: '/images/voca/community/entrepreneurs.webp', alt: 'Entrepreneurs collaborating around a laptop and handmade products' },
    leaders: { src: '/images/voca/community/christian-leaders.webp', alt: 'Christian leaders sharing conversation and fellowship' },
    bible: { src: '/images/voca/community/bible-fellowship.webp', alt: 'A diverse group praying around an open Bible' },
    volunteers: { src: '/images/voca/community/community-volunteers.webp', alt: 'Volunteers sharing seedlings in a community garden' },
    tv: { src: '/images/voca/community/tv-conversation.webp', alt: 'An interviewer and guest talking in a warm television studio' },
    community: { src: '/images/voca/community/terrace-fellowship.webp', alt: 'A diverse group enjoying fellowship on a sunlit terrace' },
};

export const pillars = [
    { title: 'Faith', image: communityImages.bible, description: 'Our foundation is faith in God. It shapes our confidence and everything we do.' },
    { title: 'Truth', image: communityImages.tv, description: 'Truth guides our relationships and reporting: accurate, verified information, with no hidden agendas.' },
    { title: 'Unity', image: communityImages.community, description: 'A shared voice, shared resources and shared opportunity across denominations and traditions.' },
    { title: 'Impact', image: communityImages.volunteers, description: 'A Christian voice that brings practical change to communities, policy and nations across Africa.' },
];

export const platforms = [
    { title: 'VOCA Radio', image: communityImages.radio, description: 'Continental Christian radio for teaching, testimony, worship and public conversation.', url: 'https://voca.africa/radio' },
    { title: 'VOCA TV', image: communityImages.tv, description: 'Broadcast and streaming content that reports truth and inspires hope.', url: 'https://voca.africa/tv' },
    { title: 'Ministry App', image: communityImages.community, description: 'A mobile home for ministry updates, media, registrations and community access.', to: '/login' },
    { title: 'Apostolic Fellowship', image: communityImages.leaders, description: 'Relationships, oversight and collaboration for leaders serving across Africa.', to: '/international' },
];

export const pathways = [
    { title: 'Members', image: communityImages.leaders, description: 'Organizations, churches and individuals can align with VOCA for shared mission, credibility and reach.', url: 'https://voca.africa/affiliates' },
    { title: 'Register', image: communityImages.community, description: 'Create your VOCA profile, choose your membership type and access media, events, courses and ministry tools.', url: 'https://voca.africa/register' },
    { title: 'Business Directory', image: communityImages.business, description: 'Discover approved VOCA member businesses organized by country, city and suburb.', url: 'https://voca.africa/directory' },
    { title: 'Events', image: communityImages.volunteers, description: 'Explore events across the VOCA network.', url: 'https://voca.africa/events' },
    { title: 'Youth / Children', image: communityImages.youth, description: 'Resources and programming for emerging generations.', url: 'https://voca.africa/#youth' },
    { title: 'Women Ministry', image: communityImages.women, description: 'Connection, discipleship and ministry support for women.', url: 'https://voca.africa/#women' },
    { title: 'FaithChannel', image: communityImages.tv, description: 'Explore FaithChannel through the VOCA media network.', url: 'https://www.faithchannel.com/' },
];
