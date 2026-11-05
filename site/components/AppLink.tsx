import NextLink from "next/link";
import type { ComponentProps } from "react";

/**
 * Next fetches every link in view ahead of time, in case it is tapped. On a slow
 * or metered connection that is data spent on pages nobody asked for, so this
 * site does not: a page loads when it is tapped.
 */
export default function Link(props: ComponentProps<typeof NextLink>) {
  return <NextLink prefetch={false} {...props} />;
}
