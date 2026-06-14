const { spawnSync } = require('child_process');
const { readdirSync, statSync } = require('fs');
const { join, extname } = require('path');

const roots = ['public_html', 'public_html/assets'];
const files = [];

function walk(dir) {
    const entries = readdirSync(dir);
    for (const e of entries) {
        const p = join(dir, e);
        try {
            const st = statSync(p);
            if (st.isDirectory()) walk(p);
            else if (st.isFile() && extname(p) === '.js') files.push(p);
        } catch (err) {
            // ignore
        }
    }
}

for (const r of roots) {
    try { walk(r); } catch (err) { }
}

if (!files.length) {
    console.log('No JS files found under', roots.join(', '));
    process.exit(0);
}

for (const f of files) {
    console.log('==== CHECKING:', f);
    const res = spawnSync(process.execPath, ['--check', f], { encoding: 'utf8' });
    if (res.status !== 0) {
        console.log(res.stdout || '');
        console.error(res.stderr || 'Unknown error');
    } else {
        // Node --check prints nothing on success
        console.log('OK');
    }
}
