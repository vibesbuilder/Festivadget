import { useWeather } from "@/data/queries";

// Wetter-Strip (§12.10): pro Tag Symbol + Min/Max aus weather.json (RastaWeather).
// Symbol-Mapping bewusst simpel (Emoji) – später durch Icon-Set ersetzbar.
const SYMBOL: Record<string, string> = {
  clear: "☀️",
  partly: "⛅",
  cloudy: "☁️",
  rain: "🌧️",
  thunder: "⛈️",
  snow: "❄️",
  fog: "🌫️",
};

export function WeatherStrip() {
  const { data } = useWeather();
  if (!data || data.days.length === 0) return null;

  return (
    <div className="flex gap-2 overflow-x-auto pb-1">
      {data.days.map((d) => (
        <div
          key={d.dayId}
          className="rid-card flex min-w-[88px] flex-col items-center gap-1 px-3 py-2"
        >
          <span className="text-xs text-rid-muted">{d.date}</span>
          <span className="text-2xl">{SYMBOL[d.symbolCode] ?? "🌡️"}</span>
          <span className="text-sm">
            <span className="font-semibold">{Math.round(d.tempMax)}°</span>
            <span className="text-rid-muted"> / {Math.round(d.tempMin)}°</span>
          </span>
        </div>
      ))}
    </div>
  );
}
