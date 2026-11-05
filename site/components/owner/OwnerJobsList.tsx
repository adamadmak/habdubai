import Link from "@/components/AppLink";
import { Eye, MessageCircle } from "lucide-react";
import { closeJob } from "@/lib/actions";
import type { OwnerJob } from "@/lib/api";
import { number, relative } from "@/lib/format";
import { fill, type Dictionary, type Locale } from "@/lib/i18n";
import { ConfirmSubmit } from "./ConfirmSubmit";

const TONE: Record<OwnerJob["status"], string> = {
  review: "bg-warning-soft text-warning",
  published: "bg-success-soft text-success",
  closed: "bg-surface text-muted",
  rejected: "bg-danger-soft text-danger",
};

/** An owner's positions: where each stands, how many tapped to apply, and one way to close it. */
export function OwnerJobsList({ lang, t, jobs }: { lang: Locale; t: Dictionary["business"]["jobs"]; jobs: OwnerJob[] }) {
  return (
    <ul className="mt-3 grid gap-3 lg:grid-cols-2">
      {jobs.map((job, i) => (
        <li key={job.id ?? `checking-${i}`} className="card p-4">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="title min-w-0 truncate text-[1.0625rem]">{job.title}</h3>
            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${TONE[job.status]}`}>{t.status[job.status]}</span>
          </div>
          <p className="mt-0.5 truncate text-sm text-muted">{[job.company, job.posted_at && relative(job.posted_at, lang)].filter(Boolean).join(" · ")}</p>
          {job.status === "rejected" && job.reason && <p className="mt-2 rounded-lg bg-surface p-2 text-sm">{job.reason}</p>}
          {job.id !== null && (job.status === "published" || job.status === "review") && (
            <div className="mt-3 flex flex-wrap items-center gap-2">
              {job.status === "published" && (
                <>
                  <Link href={`/${lang}/jobs/${job.id}`} className="btn btn-quiet min-h-9 px-3 text-sm"><Eye className="size-3.5" aria-hidden="true" /> {t.view}</Link>
                  <span className="inline-flex items-center gap-1 text-xs text-muted">
                    <MessageCircle className="size-3.5" aria-hidden="true" /> {job.clicks === 1 ? t.tapOne : fill(t.taps, { n: number(job.clicks, lang) })}
                  </span>
                </>
              )}
              <form action={closeJob} className="ml-auto">
                <input type="hidden" name="lang" value={lang} />
                <input type="hidden" name="job" value={job.id} />
                <ConfirmSubmit text={job.status === "published" ? t.close : t.withdraw} confirm={job.status === "published" ? t.closeConfirm : t.withdrawConfirm} />
              </form>
            </div>
          )}
        </li>
      ))}
    </ul>
  );
}
