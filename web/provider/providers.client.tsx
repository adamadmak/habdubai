"use client";
import React, { useState } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ApiError } from "@/lib/api";

/**
 * A client error (4xx) will not change on a retry: a 404 from an endpoint the
 * API has not shipped yet, a 403, a validation error. Only network failures and
 * server errors are tried again, twice.
 */
const retry = (failures: number, error: unknown) => {
  if (error instanceof ApiError && error.status >= 400 && error.status < 500) return false;
  return failures < 2;
};

const TanstackProvider = ({ children }: { children: React.ReactNode }) => {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: { retry, staleTime: 10_000 },
          mutations: { retry: false },
        },
      })
  );
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
};

export default TanstackProvider;
