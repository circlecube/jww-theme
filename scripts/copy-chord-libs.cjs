/**
 * Copy VexTab (div.prod.js) from node_modules to assets/vendor for enqueue.
 * ChordSheetJS and VexChords are bundled in build/theme-song-chords-tabs.js; VexTab is loaded separately.
 * Run: npm run copy:chord-libs
 */
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const vendors = [
  { from: 'node_modules/vextab/dist/div.prod.js', to: 'assets/vendor/vextab/div.prod.js' },
];

vendors.forEach(({ from, to }) => {
  const src = path.join(root, from);
  const dest = path.join(root, to);
  if (!fs.existsSync(src)) {
    console.warn('Skip (missing):', from);
    return;
  }
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.copyFileSync(src, dest);
  console.log('Copied:', to);
});

console.log('Done. VexTab is in assets/vendor/vextab/ (ChordSheetJS + VexChords are in the theme build).');
