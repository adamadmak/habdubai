/**
 * Listing photos at the width a card needs. The API serves an approved photo
 * at /public/photos/{id}?v=… and, with &w=480 or &w=960, the same photo
 * shrunk to at most that width. A photo still in review comes as a signed
 * link, which must not be touched, so it gets no smaller candidates.
 */

/** The longest side the API keeps (PhotoStore::MAX_SIDE), so the most an original can be. */
const ORIGINAL_WIDTH = 1600;

function isPublicPhoto(url: string): boolean {
  try {
    const parsed = new URL(url);
    return /\/public\/photos\/\d+$/.test(parsed.pathname) && parsed.searchParams.has("v") && !parsed.searchParams.has("signature") && !parsed.searchParams.has("w");
  } catch {
    return false;
  }
}

/** A srcSet of the 480px copy and the original, or undefined for anything that is not a public listing photo. */
export function photoSrcSet(url: string | null | undefined): string | undefined {
  if (!url || !isPublicPhoto(url)) return undefined;
  return `${url}&w=480 480w, ${url} ${ORIGINAL_WIDTH}w`;
}
