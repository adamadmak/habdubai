/** The round HabUAE logo: the admin's mark everywhere, from the owner's artwork (`public/brand/`). */
const BrandMark = ({ className = "h-8 w-8", title }: { className?: string; title?: string }) => (
  // eslint-disable-next-line @next/next/no-img-element
  <img src="/brand/habuae-round.png" width={128} height={128} alt={title ?? ""} className={className} />
);

export default BrandMark;
