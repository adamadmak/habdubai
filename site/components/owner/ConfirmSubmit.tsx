"use client";

/** A submit button for a destructive action: it asks once, in the browser's own dialog. */
export function ConfirmSubmit({ text, confirm }: { text: string; confirm: string }) {
  return (
    <button
      type="submit"
      className="btn min-h-9 px-3 text-sm text-danger hover:bg-danger-soft"
      onClick={(event) => {
        if (!window.confirm(confirm)) event.preventDefault();
      }}
    >
      {text}
    </button>
  );
}
