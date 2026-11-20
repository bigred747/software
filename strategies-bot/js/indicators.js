/** Closed-bar indicator helpers. All series are aligned to bars[]; null = not ready. */

export function sma(values, length) {
  const out = new Array(values.length).fill(null);
  let sum = 0;
  for (let i = 0; i < values.length; i++) {
    sum += values[i];
    if (i >= length) sum -= values[i - length];
    if (i >= length - 1) out[i] = sum / length;
  }
  return out;
}

export function ema(values, length) {
  const out = new Array(values.length).fill(null);
  const k = 2 / (length + 1);
  let prev = null;
  for (let i = 0; i < values.length; i++) {
    if (i < length - 1) continue;
    if (prev == null) {
      let sum = 0;
      for (let j = i - length + 1; j <= i; j++) sum += values[j];
      prev = sum / length;
      out[i] = prev;
    } else {
      prev = values[i] * k + prev * (1 - k);
      out[i] = prev;
    }
  }
  return out;
}

export function stdev(values, length) {
  const out = new Array(values.length).fill(null);
  for (let i = length - 1; i < values.length; i++) {
    let sum = 0;
    for (let j = i - length + 1; j <= i; j++) sum += values[j];
    const mean = sum / length;
    let v = 0;
    for (let j = i - length + 1; j <= i; j++) v += (values[j] - mean) ** 2;
    out[i] = Math.sqrt(v / length);
  }
  return out;
}

export function rsi(closes, length = 14) {
  const out = new Array(closes.length).fill(null);
  if (closes.length <= length) return out;
  let gain = 0;
  let loss = 0;
  for (let i = 1; i <= length; i++) {
    const d = closes[i] - closes[i - 1];
    if (d >= 0) gain += d;
    else loss -= d;
  }
  let avgGain = gain / length;
  let avgLoss = loss / length;
  out[length] = avgLoss === 0 ? 100 : 100 - 100 / (1 + avgGain / avgLoss);
  for (let i = length + 1; i < closes.length; i++) {
    const d = closes[i] - closes[i - 1];
    const g = d > 0 ? d : 0;
    const l = d < 0 ? -d : 0;
    avgGain = (avgGain * (length - 1) + g) / length;
    avgLoss = (avgLoss * (length - 1) + l) / length;
    out[i] = avgLoss === 0 ? 100 : 100 - 100 / (1 + avgGain / avgLoss);
  }
  return out;
}

export function trueRange(bars) {
  const out = new Array(bars.length).fill(null);
  for (let i = 0; i < bars.length; i++) {
    if (i === 0) {
      out[i] = bars[i].h - bars[i].l;
      continue;
    }
    out[i] = Math.max(
      bars[i].h - bars[i].l,
      Math.abs(bars[i].h - bars[i - 1].c),
      Math.abs(bars[i].l - bars[i - 1].c)
    );
  }
  return out;
}

export function atr(bars, length = 14) {
  return sma(trueRange(bars), length);
}

export function highest(values, length) {
  const out = new Array(values.length).fill(null);
  for (let i = length - 1; i < values.length; i++) {
    let m = -Infinity;
    for (let j = i - length + 1; j <= i; j++) m = Math.max(m, values[j]);
    out[i] = m;
  }
  return out;
}

export function lowest(values, length) {
  const out = new Array(values.length).fill(null);
  for (let i = length - 1; i < values.length; i++) {
    let m = Infinity;
    for (let j = i - length + 1; j <= i; j++) m = Math.min(m, values[j]);
    out[i] = m;
  }
  return out;
}

export function macd(closes, fast = 12, slow = 26, signal = 9) {
  const ef = ema(closes, fast);
  const es = ema(closes, slow);
  const line = closes.map((_, i) => (ef[i] == null || es[i] == null ? null : ef[i] - es[i]));
  const valid = line.map((v) => (v == null ? 0 : v));
  // EMA of MACD line — only meaningful once line is ready
  const sig = new Array(closes.length).fill(null);
  const k = 2 / (signal + 1);
  let prev = null;
  let count = 0;
  for (let i = 0; i < line.length; i++) {
    if (line[i] == null) continue;
    count += 1;
    if (count < signal) continue;
    if (prev == null) {
      let sum = 0;
      let n = 0;
      for (let j = i; n < signal; j--) {
        if (line[j] != null) {
          sum += line[j];
          n += 1;
        }
      }
      prev = sum / signal;
      sig[i] = prev;
    } else {
      prev = line[i] * k + prev * (1 - k);
      sig[i] = prev;
    }
  }
  return { line, signal: sig };
}

/** Squeeze Pro-style compression levels using BB inside KC */
export function squeezeState(bars, length = 20) {
  const closes = bars.map((b) => b.c);
  const basis = sma(closes, length);
  const sd = stdev(closes, length);
  const tr = trueRange(bars);
  const atrMa = sma(tr, length);
  const out = bars.map(() => ({ level: 0, mom: null, fired: false, inSqueeze: false }));

  const mid = bars.map((_, i) => {
    if (basis[i] == null) return null;
    const hh = highest(bars.map((b) => b.h), length)[i];
    const ll = lowest(bars.map((b) => b.l), length)[i];
    if (hh == null || ll == null) return null;
    return (hh + ll) / 2;
  });

  // Linear regression momentum proxy (closed-bar): close - avg of midline/basis
  for (let i = 0; i < bars.length; i++) {
    if (basis[i] == null || sd[i] == null || atrMa[i] == null || mid[i] == null) continue;
    const bbUpper = basis[i] + 2 * sd[i];
    const bbLower = basis[i] - 2 * sd[i];
    const highC = bbLower >= basis[i] - atrMa[i] * 1.0 && bbUpper <= basis[i] + atrMa[i] * 1.0;
    const midC = bbLower >= basis[i] - atrMa[i] * 1.5 && bbUpper <= basis[i] + atrMa[i] * 1.5;
    const lowC = bbLower >= basis[i] - atrMa[i] * 2.0 && bbUpper <= basis[i] + atrMa[i] * 2.0;
    const level = highC ? 3 : midC ? 2 : lowC ? 1 : 0;
    const avgSMA = (mid[i] + basis[i]) / 2;
    // simple momentum: distance of close from midline average, smoothed
    const mom = closes[i] - avgSMA;
    out[i] = { level, mom, fired: false, inSqueeze: level > 0 };
  }
  for (let i = 1; i < out.length; i++) {
    out[i].fired = out[i - 1].inSqueeze && !out[i].inSqueeze;
  }
  return out;
}

/** TMO-style delta momentum (close vs prior opens) */
export function tmo(bars, length = 14, calc = 5, smooth = 3) {
  const raw = new Array(bars.length).fill(null);
  for (let i = 0; i < bars.length; i++) {
    if (i < length - 1) continue;
    let s = 0;
    for (let j = 0; j < length; j++) {
      const o = bars[i - j].o;
      const c = bars[i].c;
      if (c > o) s += 1;
      else if (c < o) s -= 1;
    }
    raw[i] = s;
  }
  const e1 = ema(raw.map((v) => (v == null ? 0 : v)), calc);
  const main = ema(e1.map((v, i) => (raw[i] == null ? 0 : v)), smooth);
  const signal = ema(main.map((v, i) => (raw[i] == null ? 0 : v)), smooth);
  return bars.map((_, i) => {
    if (raw[i] == null || main[i] == null || signal[i] == null) return null;
    return { main: main[i], signal: signal[i], bull: main[i] > signal[i] };
  });
}

/** TenX-style DI+/DI-/ADX */
export function tenX(bars, length = 14, adxThresh = 20) {
  const plusDM = new Array(bars.length).fill(0);
  const minusDM = new Array(bars.length).fill(0);
  for (let i = 1; i < bars.length; i++) {
    const up = bars[i].h - bars[i - 1].h;
    const down = bars[i - 1].l - bars[i].l;
    plusDM[i] = up > down && up > 0 ? up : 0;
    minusDM[i] = down > up && down > 0 ? down : 0;
  }
  const atrs = atr(bars, length);
  const smPlus = sma(plusDM, length);
  const smMinus = sma(minusDM, length);
  const diPlus = bars.map((_, i) => (atrs[i] ? (100 * smPlus[i]) / atrs[i] : null));
  const diMinus = bars.map((_, i) => (atrs[i] ? (100 * smMinus[i]) / atrs[i] : null));
  const dx = bars.map((_, i) => {
    if (diPlus[i] == null || diMinus[i] == null) return null;
    const den = diPlus[i] + diMinus[i];
    return den === 0 ? 0 : (100 * Math.abs(diPlus[i] - diMinus[i])) / den;
  });
  const adx = sma(dx.map((v) => (v == null ? 0 : v)), length);
  return bars.map((_, i) => {
    if (diPlus[i] == null || diMinus[i] == null || adx[i] == null || i < length * 2) return null;
    const bull = diPlus[i] > diMinus[i] && adx[i] > adxThresh;
    const bear = diMinus[i] > diPlus[i] && adx[i] > adxThresh;
    return { bull, bear, neutral: !bull && !bear, adx: adx[i] };
  });
}

/** B4-style four public checks */
export function bigFour(bars) {
  const closes = bars.map((b) => b.c);
  const akFast = ema(closes, 3);
  const akSlow = ema(closes, 8);
  const zSd = stdev(closes, 20);
  const zMa = sma(closes, 20);
  const amLen = 21;
  const amMa = sma(closes, amLen);

  // Ehlers distance-coefficient style smoother (public formula approximation)
  const eLen = 34;
  const ehlers = new Array(bars.length).fill(null);
  for (let i = eLen; i < bars.length; i++) {
    let num = 0;
    let den = 0;
    for (let j = 0; j < eLen; j++) {
      const idx = i - j;
      const price = (bars[idx].h + bars[idx].l) / 2;
      // distance from window mean as coefficient proxy
      let mean = 0;
      for (let k = 0; k < eLen; k++) mean += (bars[i - k].h + bars[i - k].l) / 2;
      mean /= eLen;
      const coeff = Math.abs(price - mean) + 1e-9;
      num += coeff * price;
      den += coeff;
    }
    ehlers[i] = num / den;
  }

  return bars.map((b, i) => {
    if (
      akFast[i] == null ||
      akSlow[i] == null ||
      zSd[i] == null ||
      zMa[i] == null ||
      amMa[i] == null ||
      ehlers[i] == null
    ) {
      return null;
    }
    const c1 = akFast[i] > akSlow[i] ? 1 : -1;
    const z = (closes[i] - zMa[i]) / (zSd[i] || 1e-9);
    const c2 = z > 0 ? 1 : -1;
    const c3 = closes[i] > ehlers[i] ? 1 : -1;
    const amom = 100 * (closes[i] / amMa[i] - 1);
    const c4 = amom > 0 ? 1 : -1;
    const up = (c1 > 0) + (c2 > 0) + (c3 > 0) + (c4 > 0);
    const dn = (c1 < 0) + (c2 < 0) + (c3 < 0) + (c4 < 0);
    return { up, dn, bull: up >= 3, bear: dn >= 3 };
  });
}
