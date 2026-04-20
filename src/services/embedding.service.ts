import crypto from 'crypto';

const DEFAULT_DIMENSIONS = 384;
const TOKEN_PATTERN = /[\p{L}\p{N}]+/gu;

export function normalizeEmbeddingText(text: string): string[] {
  if (!text) {
    return [];
  }

  return text
    .toLowerCase()
    .match(TOKEN_PATTERN)
    ?.filter((token) => token.length > 1)
    ?? [];
}

export function generateEmbedding(text: string, dimensions = DEFAULT_DIMENSIONS): number[] {
  const vector = new Array<number>(dimensions).fill(0);
  const tokens = normalizeEmbeddingText(text);

  if (tokens.length === 0) {
    return vector;
  }

  for (const token of tokens) {
    const digest = crypto.createHash('sha256').update(token).digest();

    for (let index = 0; index < dimensions; index += 1) {
      const byte = digest[index % digest.length];
      vector[index] += byte / 255 - 0.5;
    }
  }

  const norm = Math.sqrt(vector.reduce((sum, value) => sum + value * value, 0));
  if (norm > 0) {
    return vector.map((value) => value / norm);
  }

  return vector;
}
