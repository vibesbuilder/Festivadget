import { useEffect, useState } from "react";
import { DateTime } from "luxon";
import { now } from "./time";

/** Ticks at the given interval (default 30 s) - for NowLine/now-up-next. */
export function useNow(intervalMs = 30_000): DateTime {
  const [current, setCurrent] = useState(() => now());

  useEffect(() => {
    const id = setInterval(() => setCurrent(now()), intervalMs);
    return () => clearInterval(id);
  }, [intervalMs]);

  return current;
}
