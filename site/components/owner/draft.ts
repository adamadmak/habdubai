/**
 * What an owner typed, kept in this tab while they sign in again. A save
 * refused because the session ran out stores the form; the same form restores
 * it when they come back, and a successful save clears it. Hidden fields (the
 * listing's id, the language) always come from the page itself.
 */
const PREFIX = "hd:draft:";

export type Draft = Record<string, string[]>;

export function keepDraft(key: string, form: HTMLFormElement): void {
  const data: Draft = {};
  new FormData(form).forEach((value, name) => {
    if (typeof value === "string") (data[name] ??= []).push(value);
  });
  try {
    sessionStorage.setItem(PREFIX + key, JSON.stringify(data));
  } catch {
    // Storage off or full: the form itself still holds what was typed.
  }
}

/** A kept draft, once: it is removed as it is read. For a form whose fields live in React state (the goods form). */
export function takeDraft(key: string): Draft | null {
  try {
    const raw = sessionStorage.getItem(PREFIX + key);
    sessionStorage.removeItem(PREFIX + key);
    return raw ? (JSON.parse(raw) as Draft) : null;
  } catch {
    return null;
  }
}

/** Puts a kept draft back into the form, once: it is removed as it is read. */
export function restoreDraft(key: string, form: HTMLFormElement): void {
  const data = takeDraft(key);
  if (!data) return;

  for (const element of Array.from(form.elements)) {
    if (!(element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement)) continue;
    if (!element.name || element.type === "hidden" || element.type === "file") continue;
    const values = data[element.name];
    if (element instanceof HTMLInputElement && (element.type === "checkbox" || element.type === "radio")) {
      // An unticked box is simply absent from the draft.
      element.checked = (values ?? []).includes(element.value);
    } else if (values) {
      element.value = values[0] ?? "";
    }
  }
}

export function clearDraft(key: string): void {
  try {
    sessionStorage.removeItem(PREFIX + key);
  } catch {
    // Nothing to clear.
  }
}
