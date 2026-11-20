/**
 * Sample OHLC markets for closed-bar strategy ranking.
 * Labeled as sample data — swap for live broker/feed bars in production.
 */

function seededRandom(seed) {
  let s = seed >>> 0;
  return () => {
    s = (s * 1664525 + 1013904223) >>> 0;
    return s / 4294967296;
  };
}

function generateBars({ seed, startPrice, bars = 260, volatility = 0.012, drift = 0.00005 }) {
  const rand = seededRandom(seed);
  const out = [];
  let price = startPrice;
  const start = new Date();
  start.setUTCHours(0, 0, 0, 0);
  start.setUTCDate(start.getUTCDate() - bars);

  for (let i = 0; i < bars; i++) {
    const day = new Date(start);
    day.setUTCDate(start.getUTCDate() + i);
    // skip weekends for equity-like series; keep continuous for futures-like
    const shock = (rand() - 0.48) * volatility + drift;
    const open = price;
    const close = Math.max(0.01, open * (1 + shock));
    const high = Math.max(open, close) * (1 + rand() * volatility * 0.45);
    const low = Math.min(open, close) * (1 - rand() * volatility * 0.45);
    const volume = Math.floor(800000 + rand() * 2200000);
    out.push({
      t: day.toISOString().slice(0, 10),
      o: round(open),
      h: round(high),
      l: round(low),
      c: round(close),
      v: volume,
    });
    price = close;
  }
  return out;
}

function round(n) {
  return Math.round(n * 10000) / 10000;
}

export const MARKETS = [
  {
    symbol: "ZB",
    name: "30Y Treasury Futures",
    kind: "futures",
    bars: generateBars({ seed: 3011, startPrice: 118.5, bars: 280, volatility: 0.0065, drift: -0.00002 }),
  },
  {
    symbol: "SPY",
    name: "S&P 500 ETF",
    kind: "etf",
    bars: generateBars({ seed: 8842, startPrice: 512, bars: 280, volatility: 0.0095, drift: 0.00018 }),
  },
  {
    symbol: "QQQ",
    name: "Nasdaq 100 ETF",
    kind: "etf",
    bars: generateBars({ seed: 5510, startPrice: 448, bars: 280, volatility: 0.012, drift: 0.0002 }),
  },
  {
    symbol: "IWM",
    name: "Russell 2000 ETF",
    kind: "etf",
    bars: generateBars({ seed: 1199, startPrice: 206, bars: 280, volatility: 0.013, drift: 0.00005 }),
  },
  {
    symbol: "GLD",
    name: "Gold ETF",
    kind: "etf",
    bars: generateBars({ seed: 7077, startPrice: 228, bars: 280, volatility: 0.008, drift: 0.00012 }),
  },
  {
    symbol: "TLT",
    name: "20+ Year Treasury ETF",
    kind: "etf",
    bars: generateBars({ seed: 4242, startPrice: 92, bars: 280, volatility: 0.0075, drift: -0.00003 }),
  },
];

export function getMarket(symbol) {
  return MARKETS.find((m) => m.symbol === symbol) || MARKETS[0];
}
