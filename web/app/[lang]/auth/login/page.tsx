import { Suspense } from "react";
import LogInForm from "@/components/auth/login-form";
import BrandMark from "@/components/brand-mark";

export const metadata = { title: "Sign in" };

const LoginPage = () => {
  return (
    <div className="min-h-screen w-full lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      {/* The brand side: the Gulf night, the tibeb thread, the two homes. */}
      <aside className="relative hidden lg:flex flex-col justify-between overflow-hidden bg-[linear-gradient(160deg,hsl(225_67%_14%)_0%,hsl(226_70%_9%)_55%,hsl(224_78%_22%)_100%)] p-12 text-white">
        <div aria-hidden="true" className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-primary/40 blur-3xl" />
        <div aria-hidden="true" className="pointer-events-none absolute -bottom-32 left-10 h-80 w-80 rounded-full bg-gold/20 blur-3xl" />

        <div className="relative flex items-center gap-3">
          <BrandMark className="h-11 w-11" />
          <div>
            <div className="font-display text-xl font-semibold">Habesha Dubai</div>
            <div className="text-[11px] font-semibold uppercase tracking-[0.16em] text-gold">Admin</div>
          </div>
        </div>

        <div className="relative max-w-md">
          <div className="font-display text-[44px] leading-[1.1] font-semibold" lang="am">ሐበሻ ዱባይ</div>
          <p className="mt-4 text-lg leading-relaxed text-white/80">
            Verified members, jobs in four languages, questions and answers, announcements, a business directory and a trusted goods board.
          </p>
          <div className="tibeb-rule mt-8 w-40 opacity-80" aria-hidden="true" />
        </div>

        <p className="relative text-sm text-white/60">Two homes, one thread.</p>
      </aside>

      <main className="flex min-h-screen items-center justify-center px-5 py-10">
        <div className="w-full max-w-[420px]">
          <div className="mb-8 flex items-center gap-3 lg:hidden">
            <BrandMark className="h-10 w-10" />
            <div className="font-display text-xl font-semibold text-default-900">Habesha Dubai</div>
          </div>
          <Suspense>
            <LogInForm />
          </Suspense>
        </div>
      </main>
    </div>
  );
};

export default LoginPage;
