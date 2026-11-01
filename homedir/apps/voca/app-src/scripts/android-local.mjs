import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { resolve } from 'node:path';

// Same-origin local development preserves Laravel sessions and CSRF cookies.
// Production apps must use their deployed backend configuration instead.
const server = new URL(process.env.CAPACITOR_SERVER_URL || 'http://localhost:8012');
if (!['localhost', '127.0.0.1'].includes(server.hostname)) throw new Error('Use a loopback server URL with this local Android command.');
const adb = process.env.ADB_PATH || resolve(process.env.ANDROID_HOME || resolve(homedir(), 'Library/Android/sdk'), 'platform-tools/adb');
function run(command, args, env = process.env) {
    const result = spawnSync(command, args, { stdio: 'inherit', env });
    if (result.status !== 0) process.exit(result.status || 1);
}
run('npm', ['run', 'build']);
run('npx', ['cap', 'sync', 'android'], { ...process.env, CAPACITOR_SERVER_URL: server.origin });
run(adb, ['reverse', `tcp:${server.port || 80}`, `tcp:${server.port || 80}`]);
if (existsSync('../hot')) {
    const vite = new URL(readFileSync('../hot', 'utf8').trim());
    run(adb, ['reverse', `tcp:${vite.port || 5173}`, `tcp:${vite.port || 5173}`]);
}
console.log(`Android local setup ready: ${server.origin}. Run the app from Android Studio. Keep the Laravel server running; rerun this command after restarting the emulator. Run npm run mobile:sync before preparing a bundled release.`);
