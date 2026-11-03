import { cn } from "@/lib/utils";

export type RingTone = "success" | "warning" | "destructive" | "muted";

const STROKE: Record<RingTone, string> = {
  success: "stroke-success",
  warning: "stroke-gold",
  destructive: "stroke-destructive",
  muted: "stroke-default-300",
};

/** The launch score as a ring: 0 to 100, coloured by how close launch is. */
const ScoreRing = ({ score, tone, size = 160, label = "Readiness score" }: { score: number | null; tone: RingTone; size?: number; label?: string }) => {
  const stroke = size >= 120 ? 12 : 8;
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const value = score === null ? 0 : Math.max(0, Math.min(100, Math.round(score)));
  const big = size >= 120;

  return (
    <div className="relative shrink-0" style={{ width: size, height: size }} role="img" aria-label={score === null ? `${label}: not available` : `${label}: ${value} out of 100`}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90" aria-hidden="true">
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth={stroke} className="stroke-default-100" />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          strokeWidth={stroke}
          strokeLinecap="round"
          strokeDasharray={c}
          strokeDashoffset={c - (value / 100) * c}
          className={cn(STROKE[tone], "transition-[stroke-dashoffset] duration-700 ease-out")}
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className={cn("font-display font-semibold tabular-nums text-default-900 leading-none", big ? "text-[44px]" : "text-xl")}>{score === null ? "–" : value}</span>
        {big && <span className="mt-1 text-xs font-medium text-default-500">out of 100</span>}
      </div>
    </div>
  );
};

export default ScoreRing;
