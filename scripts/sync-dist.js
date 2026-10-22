/**
 * Copies the built assets (assets/dist/{css,js}) into upload/assets/dist, the
 * folder the web server actually serves (it is git-ignored; `npm run release`
 * does the same copy into its staging folder). Runs at the end of `npm run build`
 * and `npm run dev`, so a local store never serves stale CSS/JS.
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const from = path.join(root, 'assets', 'dist');
const to = path.join(root, 'upload', 'assets', 'dist');

for (const dir of ['css', 'js']) {
    const source = path.join(from, dir);

    if (!fs.existsSync(source)) {
        continue;
    }

    fs.mkdirSync(path.join(to, dir), { recursive: true });

    for (const file of fs.readdirSync(source)) {
        fs.copyFileSync(path.join(source, file), path.join(to, dir, file));
    }
}

console.log('assets copied to upload/assets/dist');
