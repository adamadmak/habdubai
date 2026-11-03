const PageTitle = ({ title, description, children }: { title: string; description?: string; children?: React.ReactNode }) => (
  <div className="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div>
      <h1 className="text-2xl font-semibold text-default-900">{title}</h1>
      {description && <p className="mt-1 text-sm text-default-600">{description}</p>}
    </div>
    {children && <div className="flex items-center gap-2">{children}</div>}
  </div>
);

export default PageTitle;
