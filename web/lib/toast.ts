"use client";
import toast from "react-hot-toast";
import { errorText } from "@/lib/api";

/**
 * Toast the outcome of a request in plain words and pass the result on. A
 * failure is toasted and thrown again, so a confirm dialog stays open and a
 * form keeps what was typed.
 */
export function toastResult<T>(promise: Promise<T>, done: string | ((result: T) => string)): Promise<T> {
  return promise.then(
    (result) => {
      toast.success(typeof done === "function" ? done(result) : done);
      return result;
    },
    (error) => {
      toast.error(errorText(error));
      throw error;
    }
  );
}

/** The same, for a click handler that has nothing more to do on failure. */
export function runAction<T>(promise: Promise<T>, done: string | ((result: T) => string)): void {
  toastResult(promise, done).catch(() => undefined);
}
