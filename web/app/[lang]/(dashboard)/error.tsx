"use client"; // Error components must be Client Components

import { useEffect } from "react";
import Link from "next/link";
import { AlertTriangle, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

/** A page broke while rendering: say so plainly, offer a retry and a way out. */
export default function Error({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <Card className="mx-auto mt-10 max-w-xl p-8 text-center">
      <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-destructive/10 text-destructive">
        <AlertTriangle className="h-6 w-6" aria-hidden="true" />
      </div>
      <h1 className="font-display text-2xl font-semibold text-default-900">This page ran into a problem</h1>
      <p className="mt-2 text-sm text-default-600">
        Nothing was changed. Try again; if it keeps happening, note what you clicked and check the browser console.
        {error.digest && <span className="block mt-1 font-mono text-xs text-default-500">Reference {error.digest}</span>}
      </p>
      <div className="mt-6 flex justify-center gap-2">
        <Button onClick={() => reset()}>
          <RefreshCw className="mr-1.5 h-4 w-4" aria-hidden="true" /> Try again
        </Button>
        <Button asChild variant="outline" color="secondary">
          <Link href="/dashboard">Go to the dashboard</Link>
        </Button>
      </div>
    </Card>
  );
}
