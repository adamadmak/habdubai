"use client"; // Error components must be Client Components

import { useEffect } from "react";
import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";

export default function Error({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <div className="flex min-h-screen items-center justify-center p-6">
      <div className="max-w-md text-center">
        <AlertTriangle className="mx-auto mb-3 h-8 w-8 text-destructive" aria-hidden="true" />
        <h1 className="font-display text-2xl font-semibold text-default-900">Something went wrong</h1>
        <p className="mt-2 text-sm text-default-600">The admin could not show this page. Try again in a moment.</p>
        <Button className="mt-6" onClick={() => reset()}>Try again</Button>
      </div>
    </div>
  );
}
