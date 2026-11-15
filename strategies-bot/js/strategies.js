import {
  sma,
  ema,
  rsi,
  atr,
  highest,
  lowest,
  macd,
  stdev,
  squeezeState,
  tmo,
  tenX,
  bigFour,
} from "./indicators.js";

/**
 * Ten classic public closed-bar rules + Edge five-checks.
 * None of these is a third-party or trademarked product name in code comments beyond plain description.
 */

function holdBenchmark(bars, lookback = 60) {
  const i0 = Math.max(0, bars.length - 1 - lookback);
  const start = bars[i0].c;
  const end = bars[bars.length - 1].c;
  return ((end - start) / start) * 100;
}

function backtestLongOnly(bars, signalAt) {
  // signalAt(i) => 1 enter/hold long, 0 flat, -1 force exit. Uses next-bar open fills on closed-bar signals.
  const trades = [];
  let pos = null;
  for (let i = 1; i < bars.length - 1; i++) {
    const sig = signalAt(i);
    if (sig == null) continue;
    if (pos == null && sig === 1) {
      pos = { entryIdx: i + 1, entry: bars[i + 1].o, entryDate: bars[i + 1].t };
    } else if (pos != null && sig <= 0) {
      const exitIdx = i + 1;
      const exit = bars[exitIdx].o;
      const ret = ((exit - pos.entry) / pos.entry) * 100;
      trades.push({
        side: "BUY",
        entryDate: pos.entryDate,
        exitDate: bars[exitIdx].t,
        entry: pos.entry,
        exit,
        ret,
        barsHeld: exitIdx - pos.entryIdx,
      });
      pos = null;
    }
  }
  if (pos != null) {
    const exitIdx = bars.length - 1;
    const exit = bars[exitIdx].c;
    const ret = ((exit - pos.entry) / pos.entry) * 100;
    trades.push({
      side: "BUY",
      entryDate: pos.entryDate,
      exitDate: bars[exitIdx].t,
      entry: pos.entry,
      exit,
      ret,
      barsHeld: exitIdx - pos.entryIdx,
      open: true,
    });
  }
  return summarize(trades, bars);
}

function summarize(trades, bars) {
  const closed = trades.filter((t) => !t.open);
  const wins = closed.filter((t) => t.ret > 0).length;
  const result = closed.reduce((s, t) => s + t.ret, 0);
  const worst = closed.length ? Math.min(...closed.map((t) => t.ret)) : 0;
  const hold = holdBenchmark(bars, 60);
  return {
    trades: closed.length,
    wins,
    winRate: closed.length ? (wins / closed.length) * 100 : 0,
    result,
    hold,
    worst,
    tradeList: trades,
  };
}

function nowFromLast(signalSeries) {
  // signalSeries[i] in {"WAIT","HOLD","BUY","SELL"}
  for (let i = signalSeries.length - 1; i >= 0; i--) {
    if (signalSeries[i]) return signalSeries[i];
  }
  return "WAIT";
}

export const STRATEGY_DEFS = [
  {
    id: "edge-five",
    name: "Edge · five checks",
    blurb: "This bot",
    describe:
      "Five closed-bar checks: squeeze release, momentum oscillator, four-factor trend vote, directional strength, higher-span agreement.",
    run(bars) {
      const sqz = squeezeState(bars);
      const tm = tmo(bars);
      const tx = tenX(bars);
      const b4 = bigFour(bars);
      const closes = bars.map((b) => b.c);
      const ema20 = ema(closes, 20);
      const ema50 = ema(closes, 50);

      // Higher-span agreement proxy on daily bars: 5-bar and 20-bar EMA slopes
      const ema5 = ema(closes, 5);

      const state = bars.map(() => "WAIT");
      const signalAt = (i) => {
        if (!sqz[i] || !tm[i] || !tx[i] || !b4[i] || ema20[i] == null || ema50[i] == null || ema5[i] == null)
          return null;
        const momUp = i > 0 && sqz[i].mom != null && sqz[i - 1].mom != null && sqz[i].mom > sqz[i - 1].mom;
        const voteSqz = (sqz[i].fired || (sqz[i].level === 0 && momUp)) && sqz[i].mom >= 0 ? 1 : 0;
        const voteTmo = tm[i].bull ? 1 : 0;
        const voteB4 = b4[i].bull ? 1 : 0;
        const voteTen = tx[i].bull ? 1 : 0;
        const htf =
          ema5[i] > ema5[Math.max(0, i - 5)] && ema20[i] > ema50[i] ? 1 : 0;
        const score = voteSqz + voteTmo + voteB4 + voteTen + htf;
        const longSetup = score >= 4 && (sqz[i].fired || sqz[i].level === 0);
        const bearFlip = tm[i].bull === false && tx[i].bear && b4[i].bear;
        if (longSetup) return 1;
        if (bearFlip) return 0;
        // hold while still mostly bullish
        if (score >= 3 && tm[i].bull) return 1;
        return 0;
      };

      for (let i = 0; i < bars.length; i++) {
        const s = signalAt(i);
        if (s === 1) state[i] = "HOLD";
        else state[i] = "WAIT";
      }
      // mark fresh entries
      for (let i = 1; i < bars.length; i++) {
        if (signalAt(i) === 1 && signalAt(i - 1) !== 1) state[i] = "BUY";
      }

      const stats = backtestLongOnly(bars, signalAt);
      return { ...stats, now: nowFromLast(state), state, signalAt };
    },
  },
  {
    id: "squeeze-release",
    name: "Squeeze release",
    blurb: "Volatility compression",
    describe: "Enter when Bollinger bands leave a Keltner squeeze with rising momentum. Exit when momentum rolls over.",
    run(bars) {
      const sqz = squeezeState(bars);
      const state = bars.map(() => "WAIT");
      const signalAt = (i) => {
        if (!sqz[i] || sqz[i].mom == null || i < 1) return null;
        const rising = sqz[i].mom > sqz[i - 1].mom;
        if (sqz[i].fired && sqz[i].mom > 0 && rising) return 1;
        if (sqz[i].mom < 0 || (!rising && sqz[i].mom > 0 && sqz[i].mom < sqz[i - 1].mom && sqz[i - 1].mom < (sqz[i - 2]?.mom ?? sqz[i - 1].mom)))
          return 0;
        if (sqz[i].mom > 0 && rising) return 1;
        return 0;
      };
      for (let i = 0; i < bars.length; i++) {
        const s = signalAt(i);
        state[i] = s === 1 ? "HOLD" : "WAIT";
      }
      for (let i = 1; i < bars.length; i++) {
        if (signalAt(i) === 1 && signalAt(i - 1) !== 1) state[i] = "BUY";
      }
      return { ...backtestLongOnly(bars, signalAt), now: nowFromLast(state), state, signalAt };
    },
  },
  {
    id: "breakout-20",
    name: "20-day breakout",
    blurb: "Range breakout",
    describe: "Buy a close above the prior 20-day high. Exit on a close below the 10-day low.",
    run(bars) {
      const highs = bars.map((b) => b.h);
      const lows = bars.map((b) => b.l);
      const hh20 = highest(highs, 20);
      const ll10 = lowest(lows, 10);
      const state = bars.map(() => "WAIT");
      const signalAt = (i) => {
        if (hh20[i - 1] == null || ll10[i] == null || i < 20) return null;
        if (bars[i].c > hh20[i - 1]) return 1;
        if (bars[i].c < ll10[i]) return 0;
        return null; // keep prior via backtest hold logic — map null as hold if in pos handled below
      };
      // Convert null-while-in-position by sticky state
      let sticky = 0;
      const stickyAt = (i) => {
        const s = signalAt(i);
        if (s === 1) sticky = 1;
        if (s === 0) sticky = 0;
        return sticky;
      };
      // reset sticky per call by recomputing sequence
      const seq = [];
      sticky = 0;
      for (let i = 0; i < bars.length; i++) {
        seq[i] = stickyAt(i);
        state[i] = seq[i] === 1 ? "HOLD" : "WAIT";
      }
      for (let i = 1; i < bars.length; i++) {
        if (seq[i] === 1 && seq[i - 1] !== 1) state[i] = "BUY";
      }
      return {
        ...backtestLongOnly(bars, (i) => seq[i]),
        now: nowFromLast(state),
        state,
        signalAt: (i) => seq[i],
      };
    },
  },
  {
    id: "rsi2",
    name: "RSI 2 pullback",
    blurb: "Short pullback",
    describe: "In an uptrend (close > 200 SMA), buy RSI(2) ≤ 10. Exit RSI(2) ≥ 80.",
    run(bars) {
      const closes = bars.map((b) => b.c);
      const ma200 = sma(closes, 200);
      const r = rsi(closes, 2);
      let sticky = 0;
      const seq = [];
      const state = bars.map(() => "WAIT");
      for (let i = 0; i < bars.length; i++) {
        if (ma200[i] == null || r[i] == null) {
          seq[i] = 0;
          continue;
        }
        if (closes[i] > ma200[i] && r[i] <= 10) sticky = 1;
        if (r[i] >= 80) sticky = 0;
        if (closes[i] < ma200[i]) sticky = 0;
        seq[i] = sticky;
        state[i] = sticky ? "HOLD" : "WAIT";
      }
      for (let i = 1; i < bars.length; i++) {
        if (seq[i] === 1 && seq[i - 1] !== 1) state[i] = "BUY";
      }
      return { ...backtestLongOnly(bars, (i) => seq[i]), now: nowFromLast(state), state, signalAt: (i) => seq[i] };
    },
  },
  {
    id: "sma50-200",
    name: "50/200 average cross",
    blurb: "Long trend",
    describe: "Golden/death cross of the 50 and 200 simple averages.",
    run(bars) {
      const closes = bars.map((b) => b.c);
      const s50 = sma(closes, 50);
      const s200 = sma(closes, 200);
      const seq = bars.map((_, i) => {
        if (s50[i] == null || s200[i] == null) return 0;
        return s50[i] > s200[i] ? 1 : 0;
      });
      const state = seq.map((s) => (s ? "HOLD" : "WAIT"));
      for (let i = 1; i < bars.length; i++) {
        if (seq[i] === 1 && seq[i - 1] !== 1) state[i] = "BUY";
        if (seq[i] === 0 && seq[i - 1] === 1) state[i] = "SELL";
      }
      return { ...backtestLongOnly(bars, (i) => seq[i]), now: nowFromLast(state), state, signalAt: (i) => seq[i] };
    },
  },
  {
    id: "ema20-50",
    name: "20/50 EMA cross",
    blurb: "Swing trend",
    describe: "Swing trend from the 20/50 exponential average cross.",
    run(bars) {
      const closes = bars.map((b) => b.c);
      const e20 = ema(closes, 20);
      const e50 = ema(closes, 50);
      const seq = bars.map((_, i) => {
        if (e20[i] == null || e50[i] == null) return 0;
        return e20[i] > e50[i] ? 1 : 0;
      });
      const state = seq.map((s) => (s ? "HOLD" : "WAIT"));
      for (let i = 1; i < bars.length; i++) {
        if (seq[i] === 1 && seq[i - 1] !== 1) state[i] = "BUY";
        if (seq[i] === 0 && seq[i - 1] === 1) state[i] = "SELL";
      }
      return { ...backtestLongOnly(bars, (i) => seq[i]), now: nowFromLast(state), state, signalAt: (i) => seq[i] };
    },
  },
  {
    id: "macd-cross",
    name: "MACD cross",
    blurb: "Momentum cross",
    describe: "Enter when MACD line crosses above its signal. Exit on the cross down.",
    run(bars) {
      const closes = bars.map((b) => b.c);
      const { line, signal } = macd(closes);
      const seq = bars.map((_, i) => {
        if (line[i] == null || signal[i] == null) return 0;
        return line[i] > signal[i] ? 1 : 0;
      });
      const state = seq.map((s) => (s ? "HOLD" : "WAIT"));
      for (let i = 1; i < bars.length; i++) {
        if (seq[i] === 1 && seq[i - 1] !== 1) state[i] = "BUY";
        if (seq[i] === 0 && seq[i - 1] === 1) state[i] = "SELL";
      }
      return { ...backtestLongOnly(bars, (i) => seq[i]), now: nowFromLast(state), state, signalAt: (i) => seq[i] };
    },
  },
  {
    id: "bollinger-revert",
    name: "Bollinger reversion",
    blurb: "Mean reversion",
    describe: "Buy a close below the lower Bollinger band. Exit at the middle band.",
    run(bars) {
      const closes = bars.map((b) => b.c);
      const mid = sma(closes, 20);
      const sd = stdev(closes, 20);
      let sticky = 0;
      const seq = [];
      const state = bars.map(() => "WAIT");
      for (let i = 0; i < bars.length; i++) {
        if (mid[i] == null || sd[i] == null) {
          seq[i] = 0;
          continue;
        }
        const lower = mid[i] - 2 * sd[i];
        if (closes[i] < lower) sticky = 1;
        if (closes[i] >= mid[i]) sticky = 0;
        seq[i] = sticky;
        state[i] = sticky ? "HOLD" : "WAIT";
      }
      for (let i = 1; i < bars.length; i++) {
        if (seq[i] === 1 && seq[i - 1] !== 1) state[i] = "BUY";
      }
      return { ...backtestLongOnly(bars, (i) => seq[i]), now: nowFromLast(state), state, signalAt: (i) => seq[i] };
    },
  },
  {
    id: "ema-atr-trail",
    name: "EMA trend · ATR trail",
    blurb: "Volatility trail",
    describe: "Stay long while close is above EMA(20). Exit when close loses EMA(20) − 1.5×ATR(14).",
    run(bars) {
      const closes = bars.map((b) => b.c);
      const e20 = ema(closes, 20);
      const a = atr(bars, 14);
      let sticky = 0;
      const seq = [];
      const state = bars.map(() => "WAIT");
      for (let i = 0; i < bars.length; i++) {
        if (e20[i] == null || a[i] == null) {
          seq[i] = 0;
          continue;
        }
        if (closes[i] > e20[i]) sticky = 1;
        if (closes[i] < e20[i] - 1.5 * a[i]) sticky = 0;
        seq[i] = sticky;
        state[i] = sticky ? "HOLD" : "WAIT";
      }
      for (let i = 1; i < bars.length; i++) {
        if (seq[i] === 1 && seq[i - 1] !== 1) state[i] = "BUY";
      }
      return { ...backtestLongOnly(bars, (i) => seq[i]), now: nowFromLast(state), state, signalAt: (i) => seq[i] };
    },
  },
  {
    id: "pullback-7",
    name: "7-day low pullback",
    blurb: "Weekly pullback",
    describe: "In an uptrend (close > 50 SMA), buy a new 7-day low. Exit at a new 7-day high or below the 50.",
    run(bars) {
      const closes = bars.map((b) => b.c);
      const lows = bars.map((b) => b.l);
      const highs = bars.map((b) => b.h);
      const ma50 = sma(closes, 50);
      const ll7 = lowest(lows, 7);
      const hh7 = highest(highs, 7);
      let sticky = 0;
      const seq = [];
      const state = bars.map(() => "WAIT");
      for (let i = 0; i < bars.length; i++) {
        if (ma50[i] == null || ll7[i] == null || hh7[i] == null || i < 1) {
          seq[i] = 0;
          continue;
        }
        const newLow = lows[i] <= ll7[i] && lows[i] < lows[i - 1];
        if (closes[i] > ma50[i] && newLow) sticky = 1;
        if (highs[i] >= hh7[i] && sticky) sticky = 0;
        if (closes[i] < ma50[i]) sticky = 0;
        seq[i] = sticky;
        state[i] = sticky ? "HOLD" : "WAIT";
      }
      for (let i = 1; i < bars.length; i++) {
        if (seq[i] === 1 && seq[i - 1] !== 1) state[i] = "BUY";
      }
      return { ...backtestLongOnly(bars, (i) => seq[i]), now: nowFromLast(state), state, signalAt: (i) => seq[i] };
    },
  },
];

export function rankStrategies(bars) {
  const rows = STRATEGY_DEFS.map((def) => {
    const result = def.run(bars);
    return {
      id: def.id,
      name: def.name,
      blurb: def.blurb,
      describe: def.describe,
      now: result.now,
      trades: result.trades,
      winRate: result.winRate,
      result: result.result,
      hold: result.hold,
      worst: result.worst,
      tradeList: result.tradeList,
      state: result.state,
      signalAt: result.signalAt,
    };
  });

  // Ranking looks backward: best cumulative closed-bar result on this market's history
  const ranked = [...rows].sort((a, b) => b.result - a.result);
  const best = ranked[0];
  return { rows, ranked, best };
}
