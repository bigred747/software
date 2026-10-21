/* Original study runner. Formulas stay in this page. They cannot reach a broker, the network, or files. */
(function (root) {
  const MAX_CHARS = 12000;
  const MAX_STEPS = 2000000;
  const FUNCS = {
    average: "average",
    sma: "average",
    simplemovingavg: "average",
    expaverage: "ema",
    ema: "ema",
    movavgexponential: "ema",
    sum: "sum",
    highest: "highest",
    lowest: "lowest",
    rsi: "rsi",
    atr: "atr",
    averagetruerange: "atr",
    stdev: "stdev",
    standarddev: "stdev",
    abs: "abs",
    min: "min",
    max: "max",
    round: "round",
    sqrt: "sqrt",
    log: "log",
    pow: "pow",
    ceil: "ceil",
    floor: "floor",
    sign: "sign",
    between: "between",
    compoundvalue: "compound",
    iff: "iff",
  };

  function fail(message) {
    const error = new Error(message);
    error.study = true;
    throw error;
  }

  function runStudy(source, bars) {
    const text = String(source || "").replace(/\r\n/g, "\n");
    if (!text.trim()) fail("Write a study before installing it");
    if (text.length > MAX_CHARS) fail("Study is too long");
    const lowered = text.toLowerCase();
    ["http:", "https:", "javascript:", "eval(", "function ", "=>", "xmlhttp", "websocket", "import "].forEach((blocked) => {
      if (lowered.includes(blocked)) fail("This study can only use prices and math");
    });
    if (/\bfold\b/i.test(text) || /\bprofile\b/i.test(text)) {
      fail("Fold and profile studies are not available. Use averages, RSI, highest, lowest, and if/then.");
    }
    const tokens = tokenize(text);
    const program = parse(tokens);
    return execute(program, bars || []);
  }

  function tokenize(source) {
    const tokens = [];
    let index = 0;
    const lineAt = (place) => {
      let line = 1;
      for (let cursor = 0; cursor < place; cursor += 1) if (source[cursor] === "\n") line += 1;
      return line;
    };
    while (index < source.length) {
      const ch = source[index];
      if (ch === " " || ch === "\t" || ch === "\r" || ch === "\n") {
        index += 1;
        continue;
      }
      if (ch === "#" || (ch === "/" && source[index + 1] === "/")) {
        while (index < source.length && source[index] !== "\n") index += 1;
        continue;
      }
      if (ch === "/" && source[index + 1] === "*") {
        index += 2;
        while (index < source.length && !(source[index] === "*" && source[index + 1] === "/")) index += 1;
        if (index >= source.length) fail("A comment was not closed");
        index += 2;
        continue;
      }
      if (ch === "\"" || ch === "'") {
        const quote = ch;
        const start = index;
        index += 1;
        let value = "";
        while (index < source.length && source[index] !== quote) {
          if (source[index] === "\n") fail("Study text cannot span a line");
          value += source[index];
          index += 1;
        }
        if (index >= source.length) fail("Study text was not closed");
        index += 1;
        tokens.push({ t: "str", v: value, line: lineAt(start) });
        continue;
      }
      if ((ch >= "0" && ch <= "9") || (ch === "." && source[index + 1] >= "0" && source[index + 1] <= "9")) {
        const start = index;
        index += 1;
        while (index < source.length && ((source[index] >= "0" && source[index] <= "9") || source[index] === ".")) index += 1;
        const value = Number(source.slice(start, index));
        if (!Number.isFinite(value)) fail("Study has a number this desk cannot use");
        tokens.push({ t: "num", v: value, line: lineAt(start) });
        continue;
      }
      if ((ch >= "A" && ch <= "Z") || (ch >= "a" && ch <= "z") || ch === "_") {
        const start = index;
        index += 1;
        while (index < source.length && /[A-Za-z0-9_]/.test(source[index])) index += 1;
        tokens.push({ t: "id", v: source.slice(start, index), line: lineAt(start) });
        continue;
      }
      const pair = source.slice(index, index + 2);
      if (pair === ">=" || pair === "<=" || pair === "==" || pair === "!=") {
        tokens.push({ t: "op", v: pair, line: lineAt(index) });
        index += 2;
        continue;
      }
      if ("+-*/%><=(),;[].".includes(ch)) {
        tokens.push({ t: "op", v: ch, line: lineAt(index) });
        index += 1;
        continue;
      }
      fail("This study uses a character the desk does not accept");
    }
    tokens.push({ t: "eof", v: "", line: lineAt(source.length) });
    return tokens;
  }

  function parse(tokens) {
    let pos = 0;
    const peek = () => tokens[pos];
    const consume = () => tokens[pos++];
    function expect(type, value) {
      const token = peek();
      if (token.t !== type || (value !== undefined && token.v !== value)) {
        fail("Study could not be read near line " + token.line);
      }
      return consume();
    }
    function word(token) {
      return token.t === "id" ? token.v.toLowerCase() : "";
    }
    function skipUntilSemi() {
      while (peek().t !== "eof" && !(peek().t === "op" && peek().v === ";")) consume();
      expect("op", ";");
    }
    function parseExpr() {
      return parseIf();
    }
    function parseIf() {
      if (word(peek()) === "if" && !(tokens[pos + 1] && tokens[pos + 1].v === "(")) {
        consume();
        const cond = parseOr();
        if (word(peek()) !== "then") fail("Study needs then after if, near line " + peek().line);
        consume();
        const yes = parseOr();
        if (word(peek()) !== "else") fail("Study needs else after then, near line " + peek().line);
        consume();
        const no = parseIf();
        return { k: "if", cond, yes, no };
      }
      return parseOr();
    }
    function parseOr() {
      let left = parseAnd();
      while (word(peek()) === "or") {
        consume();
        left = { k: "bin", op: "or", left, right: parseAnd() };
      }
      return left;
    }
    function parseAnd() {
      let left = parseNot();
      while (word(peek()) === "and") {
        consume();
        left = { k: "bin", op: "and", left, right: parseNot() };
      }
      return left;
    }
    function parseNot() {
      if (word(peek()) === "not") {
        consume();
        return { k: "not", value: parseNot() };
      }
      return parseCmp();
    }
    function parseCmp() {
      const left = parseSum();
      const token = peek();
      if (word(token) === "crosses") {
        consume();
        let op = "crosses";
        if (word(peek()) === "above" || word(peek()) === "below") op = "crosses " + word(consume());
        return { k: "bin", op, left, right: parseSum() };
      }
      if (token.t === "op" && [">", "<", ">=", "<=", "==", "!="].includes(token.v)) {
        consume();
        return { k: "bin", op: token.v, left, right: parseSum() };
      }
      return left;
    }
    function parseSum() {
      let left = parseProd();
      while (peek().t === "op" && (peek().v === "+" || peek().v === "-")) {
        const op = consume().v;
        left = { k: "bin", op, left, right: parseProd() };
      }
      return left;
    }
    function parseProd() {
      let left = parseUnary();
      while (peek().t === "op" && (peek().v === "*" || peek().v === "/" || peek().v === "%")) {
        const op = consume().v;
        left = { k: "bin", op, left, right: parseUnary() };
      }
      return left;
    }
    function parseUnary() {
      if (peek().t === "op" && peek().v === "-") {
        consume();
        return { k: "neg", value: parseUnary() };
      }
      if (peek().t === "op" && peek().v === "+") {
        consume();
        return parseUnary();
      }
      return parsePrimary();
    }
    function parsePrimary() {
      const token = peek();
      if (token.t === "num") {
        consume();
        return { k: "num", v: token.v };
      }
      if (token.t === "str") fail("Plot math cannot contain text, near line " + token.line);
      if (token.t === "op" && token.v === "(") {
        consume();
        const inner = parseExpr();
        expect("op", ")");
        return inner;
      }
      if (token.t !== "id") fail("Study could not be read near line " + token.line);
      const name = consume().v;
      const lower = name.toLowerCase();
      if (lower === "true") return { k: "num", v: 1 };
      if (lower === "false") return { k: "num", v: 0 };
      if (peek().t === "op" && peek().v === "(") {
        consume();
        const args = [];
        if (!(peek().t === "op" && peek().v === ")")) {
          args.push(parseExpr());
          while (peek().t === "op" && peek().v === ",") {
            consume();
            args.push(parseExpr());
          }
        }
        expect("op", ")");
        if (lower === "if") return { k: "if", cond: args[0], yes: args[1], no: args[2] };
        return { k: "call", name: lower, args };
      }
      let dotted = lower;
      while (peek().t === "op" && peek().v === ".") {
        consume();
        dotted += "." + expect("id").v.toLowerCase();
      }
      let offset = 0;
      if (peek().t === "op" && peek().v === "[") {
        consume();
        const inside = parseExpr();
        expect("op", "]");
        if (inside.k !== "num") fail("A bar offset must be a plain number");
        offset = inside.v;
        if (offset < 0 || offset > 300) fail("Bar offset is out of range");
      }
      if (dotted.includes(".")) return { k: "num", v: 0 };
      return { k: "ref", name: dotted, offset };
    }
    function readSide() {
      if (peek().t !== "id") fail("Order marker needs a buy or sell name, near line " + peek().line);
      let name = consume().v;
      while (peek().t === "op" && peek().v === ".") {
        consume();
        name += "." + expect("id").v;
      }
      return name.toLowerCase();
    }
    const statements = [];
    while (peek().t !== "eof") {
      if (statements.length > 80) fail("Study has too many lines");
      const token = peek();
      const lower = word(token);
      if (lower === "declare") {
        skipUntilSemi();
        continue;
      }
      if (lower === "input" || lower === "def" || lower === "plot") {
        consume();
        const name = expect("id").v;
        expect("op", "=");
        const expr = parseExpr();
        expect("op", ";");
        statements.push({ k: lower, name, expr });
        continue;
      }
      if (lower === "addorder") {
        consume();
        expect("op", "(");
        const side = readSide();
        let expr = null;
        if (peek().t === "op" && peek().v === ",") {
          consume();
          expr = parseExpr();
          while (peek().t === "op" && peek().v === ",") {
            consume();
            parseExpr();
          }
        }
        expect("op", ")");
        expect("op", ";");
        if (!expr) fail("Order marker needs a condition");
        const sell = side.includes("sell");
        statements.push({ k: "order", name: sell ? "OrderSell" : "OrderBuy", side: sell ? "sell" : "buy", expr });
        continue;
      }
      if (token.t === "id" && tokens[pos + 1] && tokens[pos + 1].v === ".") {
        skipUntilSemi();
        continue;
      }
      if (["addlabel", "addcloud", "addverticaline", "defineglobalcolor"].includes(lower)) {
        skipUntilSemi();
        continue;
      }
      fail("Study could not be read near line " + token.line);
    }
    return statements;
  }

  function execute(statements, bars) {
    const count = bars.length;
    const series = {
      open: bars.map((bar) => bar.open),
      high: bars.map((bar) => bar.high),
      low: bars.map((bar) => bar.low),
      close: bars.map((bar) => bar.close),
      volume: bars.map((bar) => bar.volume),
      hl2: bars.map((bar) => (bar.high + bar.low) / 2),
      hlc3: bars.map((bar) => (bar.high + bar.low + bar.close) / 3),
      ohlc4: bars.map((bar) => (bar.open + bar.high + bar.low + bar.close) / 4),
    };
    const steps = { n: 0 };
    const reserved = new Set(["open", "high", "low", "close", "volume", "hl2", "hlc3", "ohlc4"]);
    statements.forEach((statement) => {
      const key = statement.name.toLowerCase();
      if (reserved.has(key)) fail(statement.name + " is a price. Choose another name.");
      series[key] = new Array(count).fill(null);
    });
    for (let index = 0; index < count; index += 1) {
      statements.forEach((statement) => {
        series[statement.name.toLowerCase()][index] = evaluate(statement.expr, index);
      });
    }
    const plots = statements.filter((statement) => statement.k === "plot" || statement.k === "order").map((statement) => ({
      name: statement.name,
      side: statement.side || null,
      values: series[statement.name.toLowerCase()].slice(),
    }));
    if (plots.length > 8) fail("A study can plot at most 8 lines");
    return { plots };

    function evaluate(expr, index) {
      steps.n += 1;
      if (steps.n > MAX_STEPS) fail("Study does too much work");
      if (!expr) return null;
      if (expr.k === "num") return expr.v;
      if (expr.k === "ref") {
        const values = series[expr.name];
        const at = index - (expr.offset || 0);
        if (!values || at < 0 || at >= values.length) return null;
        return values[at];
      }
      if (expr.k === "neg") {
        const value = evaluate(expr.value, index);
        return value == null ? null : -value;
      }
      if (expr.k === "not") {
        const value = evaluate(expr.value, index);
        return value == null ? null : value === 0 ? 1 : 0;
      }
      if (expr.k === "if") {
        const cond = evaluate(expr.cond, index);
        if (cond == null) return null;
        return evaluate(cond !== 0 ? expr.yes : expr.no, index);
      }
      if (expr.k === "bin") return binary(expr.op, expr.left, expr.right, index);
      if (expr.k === "call") return call(expr.name, expr.args, index);
      return null;
    }

    function binary(op, leftExpr, rightExpr, index) {
      if (op === "and") {
        const left = evaluate(leftExpr, index);
        if (left == null) return null;
        if (left === 0) return 0;
        const right = evaluate(rightExpr, index);
        return right == null ? null : right === 0 ? 0 : 1;
      }
      if (op === "or") {
        const left = evaluate(leftExpr, index);
        if (left == null) return null;
        if (left !== 0) return 1;
        const right = evaluate(rightExpr, index);
        return right == null ? null : right === 0 ? 0 : 1;
      }
      if (op.startsWith("crosses")) {
        if (index === 0) return 0;
        const leftNow = evaluate(leftExpr, index);
        const rightNow = evaluate(rightExpr, index);
        const leftPrev = evaluate(leftExpr, index - 1);
        const rightPrev = evaluate(rightExpr, index - 1);
        if ([leftNow, rightNow, leftPrev, rightPrev].some((value) => value == null)) return null;
        const up = leftPrev <= rightPrev && leftNow > rightNow;
        const down = leftPrev >= rightPrev && leftNow < rightNow;
        if (op === "crosses above") return up ? 1 : 0;
        if (op === "crosses below") return down ? 1 : 0;
        return up || down ? 1 : 0;
      }
      const left = evaluate(leftExpr, index);
      const right = evaluate(rightExpr, index);
      if (left == null || right == null) return null;
      if (op === "+") return left + right;
      if (op === "-") return left - right;
      if (op === "*") return left * right;
      if (op === "/") return right === 0 ? null : left / right;
      if (op === "%") return right === 0 ? null : left % right;
      if (op === ">") return left > right ? 1 : 0;
      if (op === "<") return left < right ? 1 : 0;
      if (op === ">=") return left >= right ? 1 : 0;
      if (op === "<=") return left <= right ? 1 : 0;
      if (op === "==") return left === right ? 1 : 0;
      if (op === "!=") return left !== right ? 1 : 0;
      return null;
    }

    function call(name, args, index) {
      const fn = FUNCS[name];
      if (!fn) fail("Unknown study math: " + name);
      if (fn === "average" || fn === "sum" || fn === "highest" || fn === "lowest" || fn === "stdev" || fn === "ema") {
        return windowFn(fn, args, index);
      }
      if (fn === "rsi") return rsiFn(args, index);
      if (fn === "atr") return atrFn(args, index);
      if (fn === "compound") {
        const length = evaluate(args[0], index);
        if (length == null) return null;
        return evaluate(index < length ? args[2] : args[1], index);
      }
      if (fn === "iff") {
        const cond = evaluate(args[0], index);
        if (cond == null) return null;
        return evaluate(cond !== 0 ? args[1] : args[2], index);
      }
      if (fn === "between") {
        const value = evaluate(args[0], index);
        const low = evaluate(args[1], index);
        const high = evaluate(args[2], index);
        if (value == null || low == null || high == null) return null;
        return value >= low && value <= high ? 1 : 0;
      }
      const numbers = args.map((arg) => evaluate(arg, index));
      if (numbers.some((value) => value == null)) return null;
      if (fn === "abs") return Math.abs(numbers[0]);
      if (fn === "min") return Math.min(numbers[0], numbers[1]);
      if (fn === "max") return Math.max(numbers[0], numbers[1]);
      if (fn === "round") return Math.round(numbers[0]);
      if (fn === "sqrt") return numbers[0] < 0 ? null : Math.sqrt(numbers[0]);
      if (fn === "log") return numbers[0] <= 0 ? null : Math.log(numbers[0]);
      if (fn === "pow") return numbers[0] ** numbers[1];
      if (fn === "ceil") return Math.ceil(numbers[0]);
      if (fn === "floor") return Math.floor(numbers[0]);
      if (fn === "sign") return Math.sign(numbers[0]);
      return null;
    }

    function lengthAt(arg, index) {
      const value = evaluate(arg, index);
      if (value == null || value < 1 || value > 200) return null;
      return Math.floor(value);
    }

    function windowFn(fn, args, index) {
      const length = lengthAt(args[1], index);
      if (length == null || index + 1 < length) return null;
      const values = [];
      for (let cursor = index - length + 1; cursor <= index; cursor += 1) {
        const value = evaluate(args[0], cursor);
        if (value == null) return null;
        values.push(value);
      }
      if (fn === "sum" || fn === "average") {
        const total = values.reduce((sum, value) => sum + value, 0);
        return fn === "sum" ? total : total / values.length;
      }
      if (fn === "highest") return Math.max(...values);
      if (fn === "lowest") return Math.min(...values);
      if (fn === "stdev") {
        const mean = values.reduce((sum, value) => sum + value, 0) / values.length;
        return Math.sqrt(values.reduce((sum, value) => sum + (value - mean) ** 2, 0) / values.length);
      }
      if (fn === "ema") return emaFn(args, index, length);
      return null;
    }

    function emaFn(args, index, length) {
      const alpha = 2 / (length + 1);
      let seed = 0;
      for (let cursor = 0; cursor < length; cursor += 1) {
        const value = evaluate(args[0], cursor);
        if (value == null) return null;
        seed += value;
      }
      let ema = seed / length;
      for (let cursor = length; cursor <= index; cursor += 1) {
        const value = evaluate(args[0], cursor);
        if (value == null) return null;
        ema = value * alpha + ema * (1 - alpha);
      }
      return ema;
    }

    function rsiFn(args, index) {
      const length = lengthAt(args[1], index);
      if (length == null || index < length) return null;
      let gain = 0;
      let loss = 0;
      for (let cursor = index - length + 1; cursor <= index; cursor += 1) {
        const now = evaluate(args[0], cursor);
        const prev = evaluate(args[0], cursor - 1);
        if (now == null || prev == null) return null;
        const change = now - prev;
        if (change >= 0) gain += change;
        else loss -= change;
      }
      if (loss === 0) return 100;
      const relative = (gain / length) / (loss / length);
      return 100 - 100 / (1 + relative);
    }

    function atrFn(args, index) {
      const length = lengthAt(args[0], index);
      if (length == null || index + 1 < length) return null;
      let total = 0;
      for (let cursor = index - length + 1; cursor <= index; cursor += 1) {
        const high = series.high[cursor];
        const low = series.low[cursor];
        const prev = cursor === 0 ? series.close[cursor] : series.close[cursor - 1];
        total += Math.max(high - low, Math.abs(high - prev), Math.abs(low - prev));
      }
      return total / length;
    }
  }

  root.PaperStudy = { runStudy };
})(typeof globalThis !== "undefined" ? globalThis : this);
