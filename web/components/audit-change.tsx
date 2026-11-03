import React from "react";

/** A logged value, the way a person reads it. */
function show(value: unknown): string {
  if (value === null || value === undefined || value === "") return "—";
  if (typeof value === "boolean") return value ? "yes" : "no";
  if (Array.isArray(value)) return value.map(show).join(", ") || "—";
  if (typeof value === "object") return Object.entries(value as Record<string, unknown>).map(([k, v]) => `${k}: ${show(v)}`).join("; ");
  const text = String(value);
  return text.length > 160 ? `${text.slice(0, 157)}…` : text;
}

const label = (key: string) => key.replace(/_/g, " ");

/**
 * An audit entry's before and after as "field: old → new" lines, instead of
 * two blobs of JSON. A field only in `after` is a new value.
 */
const AuditChange = ({ before, after }: { before: Record<string, unknown> | null; after: Record<string, unknown> | null }) => {
  const keys = Array.from(new Set([...Object.keys(after ?? {}), ...Object.keys(before ?? {})]));
  if (keys.length === 0) return <span className="text-default-400">—</span>;

  return (
    <dl className="space-y-0.5">
      {keys.slice(0, 8).map((key) => {
        const had = before !== null && key in before;
        const has = after !== null && key in after;
        return (
          <div key={key} className="flex flex-wrap gap-x-1.5">
            <dt className="text-default-500">{label(key)}:</dt>
            <dd className="text-default-800 break-words min-w-0">
              {had && has ? (
                <>
                  <span className="text-default-500 line-through decoration-default-300">{show(before![key])}</span>
                  <span className="mx-1 text-default-400" aria-label="changed to">→</span>
                  {show(after![key])}
                </>
              ) : has ? show(after![key]) : <span className="text-default-500">{show(before![key])} (removed)</span>}
            </dd>
          </div>
        );
      })}
      {keys.length > 8 && <div className="text-default-500">and {keys.length - 8} more</div>}
    </dl>
  );
};

export default AuditChange;
