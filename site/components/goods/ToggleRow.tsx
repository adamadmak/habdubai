"use client";
import { useRouter } from "next/navigation";
import { useEffect, useState, useTransition, type FormEvent } from "react";

/**
 * A row of toggle buttons that filters the goods board. The choice lives in
 * the address and the page is drawn on the server: underneath it is a plain
 * GET form whose buttons carry the value, with the other filters as hidden
 * fields, so it works before (or without) JavaScript. Once the page is live
 * the press is a soft navigation instead, and the lit thumb slides to the
 * pressed button at once rather than waiting for the new page.
 */
export function ToggleRow({ action, name, label, current, options, keep, className = "" }: {
  action: string;
  name: string;
  label: string;
  current: string | undefined;
  /** An option without a value is "all": its button sends nothing for this filter. */
  options: { value?: string; label: string }[];
  keep: Record<string, string | undefined>;
  className?: string;
}) {
  const router = useRouter();
  const [pending, start] = useTransition();
  const [chosen, setChosen] = useState(current);
  useEffect(() => setChosen(current), [current]);

  const index = Math.max(0, options.findIndex((o) => o.value === chosen));

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const button = (event.nativeEvent as SubmitEvent).submitter as HTMLButtonElement | null;
    const params = new URLSearchParams();
    new FormData(event.currentTarget).forEach((value, key) => {
      if (typeof value === "string" && value) params.set(key, value);
    });
    if (button?.name && button.value) params.set(button.name, button.value);
    setChosen(button?.value || undefined);
    const query = params.toString();
    start(() => router.push(query ? `${action}?${query}` : action, { scroll: false }));
  };

  return (
    <form action={action} method="get" onSubmit={submit} className={`min-w-0 ${className}`}>
      {Object.entries(keep).map(([key, value]) => (value ? <input key={key} type="hidden" name={key} value={value} /> : null))}
      <div role="group" aria-label={label} aria-busy={pending || undefined} className="toggle-group">
        <span
          aria-hidden="true"
          className="toggle-thumb"
          style={{ width: `calc((100% - 0.5rem) / ${options.length})`, translate: `${index * 100}% 0` }}
        />
        {options.map((option, i) => (
          <button
            key={option.value ?? "all"}
            type="submit"
            name={option.value ? name : undefined}
            value={option.value}
            aria-pressed={i === index}
          >
            {option.label}
          </button>
        ))}
      </div>
    </form>
  );
}
