import { test } from 'node:test';
import assert from 'node:assert/strict';
import 'vue';

globalThis.document = { documentElement: { style: { setProperty() {} } }, title: '', querySelector: () => ({}) };
globalThis.window = { axios: {} };
const { useAuth } = await import('../resources/js/composables/useAuth.js');
const { useTenant } = await import('../resources/js/composables/useTenant.js');

test('bundled HTML cannot masquerade as a successful bootstrap or team sign-in', async () => {
    window.axios.get = async () => ({ data: '<html><div id="app"></div></html>' });
    window.axios.post = window.axios.get;
    await assert.rejects(useTenant().load(true), /not connected/);
    assert.equal(useTenant().loaded.value, false);
    assert.match(useTenant().loadError.value, /Unable to connect/);
    await assert.rejects(useAuth().login({ email: 'admin@example.org', password: 'example' }), /could not reach/);
    assert.equal(useAuth().user.value, null);
    const user = { id: 1, name: 'Administrator' };
    window.axios.post = async () => ({ data: { user, organisation_role: 'admin', memberships: [] } });
    window.axios.get = async () => ({ data: {
        organisation: { id: 1, slug: 'voca', branding: {}, modules: { events: true } },
        permissions: { manage_organisation: true },
    } });
    await useAuth().login({ email: 'admin@example.org', password: 'example' });
    assert.equal(useAuth().user.value.id, 1);
    assert.equal(useAuth().organisationRole.value, 'admin');
    assert.equal(useTenant().permissions.value.manage_organisation, true);
    assert.equal(useTenant().loadError.value, '');
});
