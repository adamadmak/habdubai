/**
 * The HabUAE logo: the red H beside the word. The word is a shape filled with the
 * text colour (`.logo-word`), so it is ink on paper and white on a dark band.
 * Its proportions are in `globals.css`; size it with `text-[…]`, 1em being the H's height.
 */
export function Logo({ className = "", wordClass = "" }: { className?: string; wordClass?: string }) {
  return (
    <span role="img" aria-label="HabUAE" className={`logo ${className}`}>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src="/brand/habuae-mark.png" alt="" className="logo-mark" width={167} height={166} />
      <span aria-hidden="true" className={`logo-word ${wordClass}`} />
    </span>
  );
}

export function Tibeb({ thin = false, className = "" }: { thin?: boolean; className?: string }) {
  return <div aria-hidden="true" className={`${thin ? "tibeb-thin" : "tibeb"} ${className}`} />;
}
