import { Suspense } from "react";
import LogInForm from "@/components/auth/login-form";

export const metadata = { title: "Sign in" };

const LoginPage = () => {
  return (
    <div className="min-h-screen bg-background flex items-center overflow-hidden w-full">
      <div className="min-h-screen basis-full flex flex-wrap w-full justify-center overflow-y-auto">
        <div className="basis-1/2 w-full relative hidden xl:flex justify-center items-center bg-gradient-to-br from-primary-600 via-primary-400 to-primary-600">
          <div className="relative z-10 backdrop-blur bg-primary-foreground/40 py-14 px-16 rounded max-w-[560px]">
            <div className="text-4xl leading-[50px] 2xl:text-5xl 2xl:leading-[64px] font-semibold text-default-900 dark:text-default-50">
              ሐበሻ ዱባይ
            </div>
            <div className="mt-4 text-xl text-default-800 dark:text-default-200 font-medium">
              Verified members, jobs in Amharic and Tigrinya, questions, announcements, and a trusted goods board.
            </div>
          </div>
        </div>
        <div className="min-h-screen basis-full md:basis-1/2 w-full px-4 py-5 flex justify-center items-center">
          <div className="lg:w-[480px]">
            <Suspense>
              <LogInForm />
            </Suspense>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;
