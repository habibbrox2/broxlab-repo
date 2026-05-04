// Bangla Converter - Unicode to Bijoy and Bijoy to Unicode
// Simplified working version

// Bijoy to Unicode mapping
const bijoyToUnicodeMap = {
  '|': '।',
  'Ô': "'",
  'Õ': "'",
  'Ò': '"',
  'Ó': '"',
  '°': 'ক্ক',
  '±': 'ক্ট',
  '³': 'ক্ত',
  'K¡': 'ক্ব',
  'µ': 'ক্র',
  'K¬': 'ক্ল',
  '¶': 'ক্ষ',
  '·': 'ক্স',
  '¸': 'গু',
  '»': 'গ্ধ',
  '¼': 'ঙ্ক',
  '½': 'ঙ্গ',
  'Æ': 'ট্ট',
  'Ç': 'ড্ড',
  'È': 'ণ্ট',
  'Ë': 'ত্ত',
  'Ì': 'ত্থ',
  'Î': 'ত্র',
  'Ï': 'দ্দ',
  '×': 'দ্ধ',
  'Ù': 'দ্ম',
  'à': 'প্প',
  'á': 'প্স',
  'â': 'ব্জ',
  'ä': 'ব্ধ',
  'å': 'ভ্র',
  'ç': 'ম্ফ',
  '¤^': 'ম্ব',
  '¤§': 'ম্ম',
  'ï': 'শু',
  'ð': 'শ্চ',
  'ó': 'ষ্ট',
  '÷': 'স্ট',
  'ù': 'স্ফ',
  'û': 'হু',
  'þ': 'হ্ম',
  '©': 'র্',
  'ª': '্র',
  '¨': '্য',
  '&': '্',
  'Av': 'আ',
  'A': 'অ',
  'B': 'ই',
  'C': 'ঈ',
  'D': 'উ',
  'E': 'ঊ',
  'F': 'ঋ',
  'G': 'এ',
  'H': 'ঐ',
  'I': 'ও',
  'J': 'ঔ',
  'K': 'ক',
  'L': 'খ',
  'M': 'গ',
  'N': 'ঘ',
  'O': 'ঙ',
  'P': 'চ',
  'Q': 'ছ',
  'R': 'জ',
  'S': 'ঝ',
  'T': 'ঞ',
  'U': 'ট',
  'V': 'ঠ',
  'W': 'ড',
  'X': 'ঢ',
  'Y': 'ণ',
  'Z': 'ত',
  '_': 'থ',
  '`': 'দ',
  'a': 'ধ',
  'b': 'ন',
  'c': 'প',
  'd': 'ফ',
  'e': 'ব',
  'f': 'ভ',
  'g': 'ম',
  'h': 'য',
  'i': 'র',
  'j': 'ল',
  'k': 'শ',
  'l': 'ষ',
  'm': 'স',
  'n': 'হ',
  'o': 'ড়',
  'p': 'ঢ়',
  'q': 'য়',
  'r': 'ৎ',
  '0': '০',
  '1': '১',
  '2': '২',
  '3': '৩',
  '4': '৪',
  '5': '৫',
  '6': '৬',
  '7': '৭',
  '8': '৮',
  '9': '৯',
  'v': 'া',
  'w': 'ি',
  'x': 'ী',
  'y': 'ু',
  'z': 'ু',
  '~': 'ূ',
  '„': 'ৃ',
  '‡': 'ে',
  '†': 'ে',
  '‰': 'ৈ',
  'ˆ': 'ৈ',
  'Š': 'ৗ',
  's': 'ং',
  't': 'ঃ',
  'u': 'ঁ',
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
  result = result.replace(/অা/g, 'আ');
  result = result.replace(/ো/g, 'ো');
  result = result.replace(/ৌ/g, 'ৌ');

  return result;
}

// Convert Unicode to Bijoy
function convertUnicodeToBijoy(text) {
  let result = text;

  // Replace complex characters first
  result = result.replace(/ো/g, 'ো');
  result = result.replace(/ৌ/g, 'ৌ');

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
        alert('Error converting text. Please check the console for details.');
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
        alert('Error converting text. Please check the console for details.');
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
