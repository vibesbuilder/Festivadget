import { useEffect, useState } from "react";
import { DateTime } from "luxon";
import { now } from "./time";

/** Tickt im angegebenen Intervall (Default 30 s) – für NowLine/Now-Up-Next. */
export function useNow(intervalMs = 30_000): DateTime {
  const [current, setCurrent] = useState(() => now());

  useEffect(() => {
    const id = setInterval(() => setCurrent(now()), intervalMs);
    return () => clearInterval(id);
  }, [intervalMs]);

  return current;
}
