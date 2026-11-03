/** The heading of every admin page: a Fraunces title, one line of purpose, actions on the right. */
const PageTitle = ({ title, description, eyebrow, children }: { title: string; description?: string; eyebrow?: string; children?: React.ReactNode }) => (
  <div className="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div className="min-w-0 max-w-3xl">
      {eyebrow && <div className="mb-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-primary">{eyebrow}</div>}
      <h1 className="font-display text-[28px] leading-[1.15] font-semibold text-default-900 md:text-[32px]">{title}</h1>
      {description && <p className="mt-2 text-sm leading-relaxed text-default-600">{description}</p>}
    </div>
    {children && <div className="flex flex-wrap items-center gap-2">{children}</div>}
  </div>
);

export default PageTitle;
