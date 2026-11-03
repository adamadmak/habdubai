import type { GoodsDirection } from "@/lib/api";

/*
 * The flight path in the goods board's band: home on the left (the
 * highlands, as on the horizon below) and Dubai on the right, two points
 * joined by a dashed arc. When the page opens, a plane flies the arc once,
 * the way the board's direction points, and a gold trail draws in behind it
 * on the same curve and the same easing. Nothing loops. Under reduced motion
 * (and when `still`) the plane rests mid-arc with the trail drawn to it.
 * Drawn in the ink of wherever it sits: dark on the dawn band, white on a
 * night tile. Decoration only: the words are on the page.
 */

const HOME = { x: 70, y: 176 };
const GULF = { x: 410, y: 150 };
const CONTROL = { x: 240, y: -14 };

/** An airliner seen from above, nose along +x, centred on its wings. */
const PLANE =
  "M13 0c0-1.4-1.4-2-3-2H3L-4-11h-3l4 9h-5l-2.5-3h-2L-11 0l-1.5 5h2L-8 2h5l-4 9h3l7-9h7c1.6 0 3-.6 3-2Z";

export function FlightPath({ direction, home, gulf, still = false, className = "" }: {
  direction: GoodsDirection;
  home: { name: string; code: string };
  gulf: { name: string; code: string };
  still?: boolean;
  className?: string;
}) {
  const [a, b] = direction === "to_et" ? [GULF, HOME] : [HOME, GULF];
  const path = `M${a.x} ${a.y}Q${CONTROL.x} ${CONTROL.y} ${b.x} ${b.y}`;
  // Halfway along the curve, and which way the plane faces there.
  const mid = { x: 0.25 * a.x + 0.5 * CONTROL.x + 0.25 * b.x, y: 0.25 * a.y + 0.5 * CONTROL.y + 0.25 * b.y };
  const angle = (Math.atan2(b.y - a.y, b.x - a.x) * 180) / Math.PI;

  return (
    <div aria-hidden="true" className={`relative aspect-[2/1] w-full ${className}`}>
      <svg viewBox="0 0 480 240" className="absolute inset-0 size-full overflow-visible">
        <defs>
          <radialGradient id="flight-glow">
            <stop offset="0" stopColor="#f2b705" stopOpacity="0.45" />
            <stop offset="1" stopColor="#f2b705" stopOpacity="0" />
          </radialGradient>
        </defs>
        <path d={path} fill="none" stroke="var(--ink)" strokeOpacity="0.5" strokeWidth="1.6" strokeDasharray="2 7" strokeLinecap="round" />
        <path d={path} pathLength={1} fill="none" stroke="var(--color-accent)" strokeWidth="2.2" strokeLinecap="round" className={still ? "flight-trail-rest" : "flight-trail"} />
        {[HOME, GULF].map((p) => (
          <g key={p.x} transform={`translate(${p.x} ${p.y})`}>
            <circle r="26" fill="url(#flight-glow)" />
            <circle r="8.5" fill="none" stroke="var(--color-accent)" strokeOpacity="0.7" strokeWidth="1.4" />
            <circle r="4.5" fill="var(--color-accent)" />
          </g>
        ))}
        {!still && (
          <g className="flight-moving">
            <path d={PLANE} fill="currentColor" transform="scale(1.3)" />
            <animateMotion dur="2.6s" fill="freeze" rotate="auto" calcMode="spline" keyPoints="0;0.93" keyTimes="0;1" keySplines="0.2 0.8 0.2 1" path={path} />
          </g>
        )}
        <g className={still ? undefined : "flight-still"} transform={`translate(${mid.x} ${mid.y}) rotate(${angle})`}>
          <path d={PLANE} fill="currentColor" transform="scale(1.3)" />
        </g>
      </svg>
      <span className="absolute left-[3%] top-[calc(73%+1.25rem)] text-left leading-tight">
        <span className="block text-[0.6875rem] font-bold tracking-wider text-gild">{home.code}</span>
        <span className="block text-[0.8125rem] font-semibold">{home.name}</span>
      </span>
      <span className="absolute right-[3%] top-[calc(62%+1.25rem)] text-right leading-tight">
        <span className="block text-[0.6875rem] font-bold tracking-wider text-gild">{gulf.code}</span>
        <span className="block text-[0.8125rem] font-semibold">{gulf.name}</span>
      </span>
    </div>
  );
}
