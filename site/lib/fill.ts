/**
 * "{n} open jobs" with the values filled in. On its own, with no dictionaries
 * attached, so a client component can use it without shipping all three.
 */
export function fill(template: string, values: Record<string, string | number>): string {
  return template.replace(/\{(\w+)\}/g, (_, key: string) => (key in values ? String(values[key]) : `{${key}}`));
}
