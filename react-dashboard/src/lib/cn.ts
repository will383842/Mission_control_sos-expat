/**
 * Small utility to conditionally join classNames.
 * Lighter alternative to `clsx` — no dependency needed.
 */
export function cn(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(' ');
}
