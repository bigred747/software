# ============================================================
# Edge · five checks  —  Thinkorswim Strategies tab
# ============================================================
# INSTALL (copy/paste into your bot Strategies tab):
#   1. Charts → Studies → Edit Studies → Strategies tab
#   2. Create...
#   3. Delete template → paste this entire script
#   4. Name: Edge_Five_Checks
#   5. OK → Apply
#
# Five closed-bar checks (public formulas, not a trademarked pack):
#   1) Squeeze release (BB inside KC compression → fire + momentum)
#   2) Momentum oscillator (TMO-style price-delta)
#   3) Four-factor trend vote (AK spread, Z-score, Ehlers-style, anchored mom)
#   4) Directional strength (DI+/DI-/ADX / TenX-style)
#   5) Higher-timeframe agreement (HTF TenX + TMO votes)
#
# Ranking bot UI scores this on past closed bars only. Not a forecast.
# ============================================================

input tradeSize = 100;
input minConfluence = 4;
input requireSqueezeFire = yes;
input useHigherTimeframeFilter = yes;

input htf1 = AggregationPeriod.FIFTEEN_MIN;
input htf2 = AggregationPeriod.HOUR;
input htf3 = AggregationPeriod.FOUR_HOURS;

# ---- 1) Squeeze release ----
input sqzLength = 20;
input sqzAvgType = AverageType.SIMPLE;

def bbBasis = MovingAverage(sqzAvgType, close, sqzLength);
def bbDev = 2.0 * StDev(close, sqzLength);
def bbUpper = bbBasis + bbDev;
def bbLower = bbBasis - bbDev;
def atrKC = MovingAverage(sqzAvgType, TrueRange(high, close, low), sqzLength);

def highCompression = bbLower >= (bbBasis - atrKC * 1.0) and bbUpper <= (bbBasis + atrKC * 1.0);
def midCompression = bbLower >= (bbBasis - atrKC * 1.5) and bbUpper <= (bbBasis + atrKC * 1.5);
def lowCompression = bbLower >= (bbBasis - atrKC * 2.0) and bbUpper <= (bbBasis + atrKC * 2.0);
def inSqueeze = highCompression or midCompression or lowCompression;
def squeezeFired = inSqueeze[1] and !inSqueeze;

def avgSMA = ((Highest(high, sqzLength) + Lowest(low, sqzLength)) / 2.0 + bbBasis) / 2.0;
def sqzMom = Inertia(close - avgSMA, sqzLength);
def sqzMomUp = sqzMom > sqzMom[1];

# ---- 2) TMO-style oscillator ----
input tmoLength = 14;
input tmoCalcLength = 5;
input tmoSmoothLength = 3;

def tmoData = fold i = 0 to tmoLength
              with s
              do s + (if close > GetValue(open, i) then 1
                      else if close < GetValue(open, i) then -1
                      else 0);
def tmoMain = ExpAverage(ExpAverage(tmoData, tmoCalcLength), tmoSmoothLength);
def tmoSignal = ExpAverage(tmoMain, tmoSmoothLength);
def tmoBull = tmoMain > tmoSignal;
def tmoBear = tmoMain < tmoSignal;

# ---- 3) Four-factor trend vote (B4-style) ----
input b4Confirmation = 3;

def akSpread = (ExpAverage(close, 3) - ExpAverage(close, 8)) * 1.001;
def zSd = StDev(close, 20);
def zScore = if zSd != 0 then (close - Average(close, 20)) / zSd else 0;

def eLen = 34;
def ePrice = (high + low) / 2.0;
def eCoeff = eLen * ePrice * ePrice
             - 2 * ePrice * Sum(ePrice, eLen)[1]
             + Sum(ePrice * ePrice, eLen)[1];
def ehlers = if Sum(eCoeff, eLen) != 0
             then Sum(eCoeff * ePrice, eLen) / Sum(eCoeff, eLen)
             else ePrice;

def amP = 21;
def amom = 100 * ((close / Average(close, amP)) - 1);

def b4Up = (if akSpread > 0 then 1 else 0)
         + (if zScore > 0 then 1 else 0)
         + (if close > ehlers then 1 else 0)
         + (if amom > 0 then 1 else 0);
def b4Dn = (if akSpread <= 0 then 1 else 0)
         + (if zScore <= 0 then 1 else 0)
         + (if close <= ehlers then 1 else 0)
         + (if amom <= 0 then 1 else 0);
def b4Bull = b4Up >= b4Confirmation;
def b4Bear = b4Dn >= b4Confirmation;

# ---- 4) Directional strength (TenX-style) ----
input tenXLength = 14;
input tenXAvgType = AverageType.WILDERS;
input tenXAdxThresh = 20.0;

def hiDiff = high - high[1];
def loDiff = low[1] - low;
def plusDM = if hiDiff > loDiff and hiDiff > 0 then hiDiff else 0;
def minusDM = if loDiff > hiDiff and loDiff > 0 then loDiff else 0;
def atrX = MovingAverage(tenXAvgType, TrueRange(high, close, low), tenXLength);
def diPlus = if atrX != 0 then 100 * MovingAverage(tenXAvgType, plusDM, tenXLength) / atrX else 0;
def diMinus = if atrX != 0 then 100 * MovingAverage(tenXAvgType, minusDM, tenXLength) / atrX else 0;
def dx = if (diPlus + diMinus) > 0 then 100 * AbsValue(diPlus - diMinus) / (diPlus + diMinus) else 0;
def adx = MovingAverage(tenXAvgType, dx, tenXLength);
def tenXBull = diPlus > diMinus and adx > tenXAdxThresh;
def tenXBear = diMinus > diPlus and adx > tenXAdxThresh;

# ---- 5) Multi-timeframe agreement ----
script TenXState {
    input length = 14;
    input avgType = AverageType.WILDERS;
    input adxThresh = 20.0;
    input h = high;
    input l = low;
    input c = close;
    def hiD = h - h[1];
    def loD = l[1] - l;
    def pDM = if hiD > loD and hiD > 0 then hiD else 0;
    def mDM = if loD > hiD and loD > 0 then loD else 0;
    def a = MovingAverage(avgType, TrueRange(h, c, l), length);
    def dip = if a != 0 then 100 * MovingAverage(avgType, pDM, length) / a else 0;
    def dim = if a != 0 then 100 * MovingAverage(avgType, mDM, length) / a else 0;
    def dxx = if (dip + dim) > 0 then 100 * AbsValue(dip - dim) / (dip + dim) else 0;
    def adxx = MovingAverage(avgType, dxx, length);
    plot state = if dip > dim and adxx > adxThresh then 1
                 else if dim > dip and adxx > adxThresh then -1
                 else 0;
}

script TmoState {
    input length = 14;
    input calcLength = 5;
    input smoothLength = 3;
    input o = open;
    input c = close;
    def data = fold i = 0 to length
               with s
               do s + (if c > GetValue(o, i) then 1
                       else if c < GetValue(o, i) then -1
                       else 0);
    def main = ExpAverage(ExpAverage(data, calcLength), smoothLength);
    def sig = ExpAverage(main, smoothLength);
    plot state = if main > sig then 1 else -1;
}

def tenX_htf1 = TenXState(tenXLength, tenXAvgType, tenXAdxThresh, high(period = htf1), low(period = htf1), close(period = htf1));
def tenX_htf2 = TenXState(tenXLength, tenXAvgType, tenXAdxThresh, high(period = htf2), low(period = htf2), close(period = htf2));
def tenX_htf3 = TenXState(tenXLength, tenXAvgType, tenXAdxThresh, high(period = htf3), low(period = htf3), close(period = htf3));
def tmo_htf1 = TmoState(tmoLength, tmoCalcLength, tmoSmoothLength, open(period = htf1), close(period = htf1));
def tmo_htf2 = TmoState(tmoLength, tmoCalcLength, tmoSmoothLength, open(period = htf2), close(period = htf2));
def tmo_htf3 = TmoState(tmoLength, tmoCalcLength, tmoSmoothLength, open(period = htf3), close(period = htf3));

def htfBullVotes = (if tenX_htf1 == 1 then 1 else 0) + (if tenX_htf2 == 1 then 1 else 0) + (if tenX_htf3 == 1 then 1 else 0)
                 + (if tmo_htf1 == 1 then 1 else 0) + (if tmo_htf2 == 1 then 1 else 0) + (if tmo_htf3 == 1 then 1 else 0);
def htfBearVotes = (if tenX_htf1 == -1 then 1 else 0) + (if tenX_htf2 == -1 then 1 else 0) + (if tenX_htf3 == -1 then 1 else 0)
                 + (if tmo_htf1 == -1 then 1 else 0) + (if tmo_htf2 == -1 then 1 else 0) + (if tmo_htf3 == -1 then 1 else 0);
def htfOkLong = if useHigherTimeframeFilter then htfBullVotes >= 4 else yes;
def htfOkShort = if useHigherTimeframeFilter then htfBearVotes >= 4 else yes;

# ---- Confluence ----
def fireGate = if requireSqueezeFire then squeezeFired else !inSqueeze or squeezeFired;

def vote1 = if (sqzMom > 0 and sqzMomUp) then 1 else 0;
def vote2 = if tmoBull then 1 else 0;
def vote3 = if b4Bull then 1 else 0;
def vote4 = if tenXBull then 1 else 0;
def vote5 = if htfOkLong then 1 else 0;

def longScore = vote1 + vote2 + vote3 + vote4 + vote5;
def shortScore = (if sqzMom < 0 and sqzMom < sqzMom[1] then 1 else 0)
               + (if tmoBear then 1 else 0)
               + (if b4Bear then 1 else 0)
               + (if tenXBear then 1 else 0)
               + (if htfOkShort then 1 else 0);

def longSetup = fireGate and longScore >= minConfluence and sqzMom >= 0;
def shortSetup = fireGate and shortScore >= minConfluence and sqzMom <= 0;

def buySignal = longSetup and !longSetup[1];
def sellSignal = shortSetup and !shortSetup[1];
def exitLong = sellSignal or (tmoBear and tenXBear and b4Bear);
def exitShort = buySignal or (tmoBull and tenXBull and b4Bull);

# ---- Strategies tab orders ----
AddOrder(OrderType.BUY_AUTO, buySignal, open[-1], tradeSize, Color.CYAN, Color.CYAN, "Edge_Long");
AddOrder(OrderType.SELL_TO_CLOSE, exitLong, open[-1], tradeSize, Color.GRAY, Color.GRAY, "Edge_LongExit");
AddOrder(OrderType.SELL_AUTO, sellSignal, open[-1], tradeSize, Color.MAGENTA, Color.MAGENTA, "Edge_Short");
AddOrder(OrderType.BUY_TO_CLOSE, exitShort, open[-1], tradeSize, Color.GRAY, Color.GRAY, "Edge_ShortExit");

AddLabel(yes, "Edge · five checks", Color.WHITE);
AddLabel(yes, "L" + longScore + "/5", if buySignal then Color.CYAN else if longSetup then Color.GREEN else Color.DARK_GRAY);
AddLabel(yes, "S" + shortScore + "/5", if sellSignal then Color.MAGENTA else if shortSetup then Color.RED else Color.DARK_GRAY);
AddLabel(yes, if inSqueeze then "SQUEEZE" else if squeezeFired then "FIRED" else "OPEN",
         if highCompression then Color.ORANGE else if midCompression then Color.YELLOW else if lowCompression then Color.DARK_ORANGE else if squeezeFired then Color.GREEN else Color.GRAY);

Alert(buySignal, "Edge five checks LONG", Alert.BAR, Sound.Ding);
Alert(sellSignal, "Edge five checks SHORT", Alert.BAR, Sound.Bell);
