import reserved from '../data/reservedOrganisationSlugs.json';

export function organisationBase(pathname = window.location.pathname) {
    const legacy = pathname.match(/^\/o\/([a-z0-9]+(?:-[a-z0-9]+)*)(?=\/|$)/);
    if (legacy) return legacy[0];
    const match = pathname.match(/^\/([a-z0-9]+(?:-[a-z0-9]+)*)(?=\/|$)/);
    return match && !reserved.includes(match[1]) ? match[0] : '';
}
export function organisationSlug(pathname = window.location.pathname) {
    return organisationBase(pathname).split('/').pop() || '';
}
