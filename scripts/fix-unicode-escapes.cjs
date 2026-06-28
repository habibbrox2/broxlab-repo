var fs = require('fs');
var path = 'app/Views/kharij/create.twig';
var c = fs.readFileSync(path, 'utf8');

// Count replacements
var count = 0;

// Replace all \\u09XX (double backslash + u09 + 2 hex digits) with actual Bengali characters
// The regex matches: backslash + backslash + u + 09 + two hex digits
// In JS regex literal, \\\\ matches two literal backslashes
var fixed = c.replace(/\u005c\u005cu09([0-9A-Fa-f]{2})/g, function(match, hex) {
    var codePoint = parseInt('09' + hex, 16);
    if (codePoint >= 0x0980 && codePoint <= 0x09FF) {
        count++;
        return String.fromCodePoint(codePoint);
    }
    // Also handle Bengali digits (U+09E6 to U+09EF)
    return match;
});

// Also fix the Bengali digit range (separate regex for clarity)
fixed = fixed.replace(/\u005c\u005cu09[Ee][0-9A-Fa-f]/g, function(match) {
    var hex = match.slice(-2); // last 2 chars after the "u09"
    var codePoint = parseInt('09' + hex, 16);
    if (codePoint >= 0x09E6 && codePoint <= 0x09EF) {
        count++;
        return String.fromCodePoint(codePoint);
    }
    return match;
});

fs.writeFileSync(path, fixed, 'utf8');
console.log('Replacements made: ' + count);

// Verify: check if any double-escaped u09 patterns remain
var remaining = fixed.match(/\u005c\u005cu09[0-9A-Fa-f]{2}/g);
console.log('Remaining double-escaped u09 patterns: ' + (remaining ? remaining.length : 0));

// Check the owner-number-badge context
var idx = fixed.indexOf('owner-number-badge');
if (idx >= 0) {
    var ctx = fixed.substring(idx, idx + 40);
    console.log('Owner-badge context:');
    for (var i = 0; i < ctx.length; i++) {
        var ch = ctx[i];
        if (ch >= '\u0980' && ch <= '\u09FF') {
            console.log('  ' + i + ': BENGALI CHAR: ' + ch + ' (U+' + ch.charCodeAt(0).toString(16).toUpperCase() + ')');
        } else if (ch === '\\') {
            console.log('  ' + i + ': BACKSLASH');
        } else if (ch >= ' ') {
            console.log('  ' + i + ': ' + ch + ' (code=' + ch.charCodeAt(0) + ')');
        }
    }
}
