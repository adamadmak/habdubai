import { notFound } from "next/navigation";

/** Any path under a language that no page claims shows the language's own not-found page. */
export default function Missing() {
  notFound();
}
