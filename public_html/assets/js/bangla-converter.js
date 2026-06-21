// Bangla Converter - Unicode to Bijoy and Bijoy to Unicode
// Simplified working version

// Bijoy to Unicode mapping
const bijoyToUnicodeMap = {
  '|': '?',
  '&': '?',
  'Av': '?',
  'A': '?',
  'B': '?',
  'C': '?',
  'D': '?',
  'E': '?',
  'F': '?',
  'G': '?',
  'H': '?',
  'I': '?',
  'J': '?',
  'K': '?',
  'L': '?',
  'M': '?',
  'N': '?',
  'O': '?',
  'P': '?',
  'Q': '?',
  'R': '?',
  'S': '?',
  'T': '?',
  'U': '?',
  'V': '?',
  'W': '?',
  'X': '?',
  'Y': '?',
  'Z': '?',
  '_': '?',
  '`': '?',
  'a': '?',
  'b': '?',
  'c': '?',
  'd': '?',
  'e': '?',
  'f': '?',
  'g': '?',
  'h': '?',
  'i': '?',
  'j': '?',
  'k': '?',
  'l': '?',
  'm': '?',
  'n': '?',
  'o': '?',
  'p': '?',
  'q': '?',
  'r': '?',
  '0': '?',
  '1': '?',
  '2': '?',
  '3': '?',
  '4': '?',
  '5': '?',
  '6': '?',
  '7': '?',
  '8': '?',
  '9': '?',
  'v': '?',
  'w': '?',
  'x': '?',
  'y': '?',
  'z': '?',
  '~': '?',
  's': '?',
  't': '?',
  'u': '?',
};

// Unicode to Bijoy mapping (reverse of above)
const unicodeToBijoyMap = {};
for (const [bijoy, unicode,] of Object.entries(bijoyToUnicodeMap)) {
  unicodeToBijoyMap[unicode] = bijoy;
}


// Convert Bijoy to Unicode
function convertBijoyToUnicode(text) {
  let result = text;

  // Apply character mapping - sort by length (longest first) to handle multi-character mappings
  const sortedEntries = Object.entries(bijoyToUnicodeMap).sort((a, b) => b[0].length - a[0].length);
  for (const [bijoyChar, unicodeChar,] of sortedEntries) {
    const regex = new RegExp(bijoyChar.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
    result = result.replace(regex, unicodeChar);
  }

  // Fix some common combinations
  result = result.replace(/\?\?/g, '?');
  result = result.replace(/\?\?/g, '?');
  result = result.replace(/\?\?/g, '?');

  return result;
}

// Convert Unicode to Bijoy
function convertUnicodeToBijoy(text) {
  let result = text;

  // Replace complex characters first
  result = result.replace(/\?/g, '??');
  result = result.replace(/\?/g, '??');

  // Apply character mapping - sort by length (longest first) to handle multi-character mappings
  const sortedEntries = Object.entries(unicodeToBijoyMap).sort((a, b) => b[0].length - a[0].length);
  for (const [unicodeChar, bijoyChar,] of sortedEntries) {
    const regex = new RegExp(unicodeChar.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
    result = result.replace(regex, bijoyChar);
  }

  return result;
}

// DOM ready function
document.addEventListener('DOMContentLoaded', () => {
  const unicodeInput = document.getElementById('unicode-input');
  const bijoyOutput = document.getElementById('bijoy-output');
  const unicodeToBijoyBtn = document.getElementById('unicode-to-bijoy');
  const bijoyToUnicodeBtn = document.getElementById('bijoy-to-unicode');
  const clearUnicodeBtn = document.getElementById('clear-unicode');
  const clearBijoyBtn = document.getElementById('clear-bijoy');

  if (!unicodeInput || !bijoyOutput) {
    console.error('Converter elements not found');
    return;
  }

  // Convert Unicode to Bijoy
  unicodeToBijoyBtn.addEventListener('click', () => {
    const unicodeText = unicodeInput.value;
    if (unicodeText.trim()) {
      try {
        const bijoyText = convertUnicodeToBijoy(unicodeText);
        bijoyOutput.value = bijoyText;
      } catch (error) {
        console.error('Conversion error:', error);
        window.showMessage('Error converting text. Please check the console for details.', 'danger');
      }
    }
  });

  // Convert Bijoy to Unicode
  bijoyToUnicodeBtn.addEventListener('click', () => {
    const bijoyText = bijoyOutput.value;
    if (bijoyText.trim()) {
      try {
        const unicodeText = convertBijoyToUnicode(bijoyText);
        unicodeInput.value = unicodeText;
      } catch (error) {
        console.error('Conversion error:', error);
        window.showMessage('Error converting text. Please check the console for details.', 'danger');
      }
    }
  });

  // Clear buttons
  clearUnicodeBtn.addEventListener('click', () => {
    unicodeInput.value = '';
    unicodeInput.focus();
  });

  clearBijoyBtn.addEventListener('click', () => {
    bijoyOutput.value = '';
    bijoyOutput.focus();
  });
});

export { convertBijoyToUnicode, convertUnicodeToBijoy };
