"use client";
import React from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { useSearchParams } from "next/navigation";
import { signIn } from "next-auth/react";
import { Loader2 } from "lucide-react";
import { Icon } from "@iconify/react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

const schema = z.object({
  email: z.string().email({ message: "Enter the email of your admin account." }),
  password: z.string().min(1, { message: "Enter your password." }),
});

type Values = z.infer<typeof schema>;

const LogInForm = () => {
  const [isPending, startTransition] = React.useTransition();
  const [showPassword, setShowPassword] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const params = useSearchParams();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<Values>({ resolver: zodResolver(schema), mode: "onBlur" });

  const onSubmit = (data: Values) => {
    setError(null);
    startTransition(async () => {
      const response = await signIn("credentials", { email: data.email, password: data.password, redirect: false });
      if (response?.ok) {
        const next = params.get("next");
        window.location.assign(next && next.startsWith("/") ? next : "/dashboard");
      } else {
        setError("That email and password do not match an admin account.");
      }
    });
  };

  return (
    <div className="w-full py-10">
      <div className="text-2xl 2xl:text-3xl font-bold text-default-900">Habesha Dubai</div>
      <div className="mt-2 text-base text-default-600">Sign in to the admin dashboard.</div>

      <form onSubmit={handleSubmit(onSubmit)} className="mt-6 space-y-4">
        <div>
          <Label htmlFor="email" className="mb-2 font-medium text-default-600">Email</Label>
          <Input disabled={isPending} {...register("email")} type="email" id="email" autoComplete="username" size="lg" className={cn({ "border-destructive": errors.email })} />
          {errors.email && <div className="text-destructive mt-2 text-sm">{errors.email.message}</div>}
        </div>

        <div>
          <Label htmlFor="password" className="mb-2 font-medium text-default-600">Password</Label>
          <div className="relative">
            <Input disabled={isPending} {...register("password")} type={showPassword ? "text" : "password"} id="password" autoComplete="current-password" size="lg" />
            <button type="button" className="absolute top-1/2 -translate-y-1/2 right-4 text-default-400" onClick={() => setShowPassword((v) => !v)} aria-label={showPassword ? "Hide password" : "Show password"}>
              <Icon icon={showPassword ? "heroicons:eye-slash" : "heroicons:eye"} className="w-5 h-5" />
            </button>
          </div>
          {errors.password && <div className="text-destructive mt-2 text-sm">{errors.password.message}</div>}
        </div>

        {error && <div className="text-destructive text-sm">{error}</div>}

        <Button className="w-full" disabled={isPending} size="lg">
          {isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
          {isPending ? "Signing in…" : "Sign in"}
        </Button>
      </form>

      <p className="mt-6 text-sm text-default-500">
        Admin accounts are created on the server with <code className="font-mono">php artisan admin:create</code>.
      </p>
    </div>
  );
};

export default LogInForm;
