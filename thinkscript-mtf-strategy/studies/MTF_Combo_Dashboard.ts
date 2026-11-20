# MTF Combo Dashboard
# Squeeze Pro + TMO + B4 (Big Four) + TenX + Multi-Timeframe Analysis
# Upper study: paint bars, arrows, MTF labels, confluence score
#
# Install: Studies > Create > paste this script > name: MTF_Combo_Dashboard
#
# Signal logic (long):
#   1) Squeeze Pro fired (was in squeeze, now expanding) OR currently compressed and ready
#   2) Squeeze momentum rising and positive (or flipping up)
#   3) TMO Main > Signal and preferably Main crossing up through zero / from OS
#   4) B4 confirmation count >= threshold (AK Trend, Z-Score, Ehlers DCF, Anchored Mom)
#   5) TenX bullish (DI+ > DI- and ADX > threshold)
#   6) Higher timeframe TenX + TMO agree (MTF filter)
#
# Short is the mirror.

declare upper;
declare once_per_bar;

# =========================
# GLOBAL INPUTS
# =========================
input showLabels = yes;
input paintBars = yes;
input showArrows = yes;
input showVerticals = no;
input tradeSizeHint = 1; # informational only for bot sizing notes

input requireSqueezeFire = yes;
input allowSqueezeOnEntry = no; # if yes, allow entry while still compressed
input minConfluence = 4; # of 5 core votes: SqueezeMom, TMO, B4, TenX, HTF
input useHigherTimeframeFilter = yes;

input htf1 = AggregationPeriod.FIFTEEN_MIN;
input htf2 = AggregationPeriod.HOUR;
input htf3 = AggregationPeriod.FOUR_HOURS;

# =========================
# SQUEEZE PRO
# =========================
input sqzLength = 20;
input sqzBBMult = 2.0;
input sqzKCHigh = 1.0;  # extreme / high compression
input sqzKCMid = 1.5;   # original squeeze
input sqzKCLow = 2.0;   # pre / low compression
input sqzAvgType = AverageType.SIMPLE;

def bbBasis = MovingAverage(sqzAvgType, close, sqzLength);
def bbDev = sqzBBMult * StDev(close, sqzLength);
def bbUpper = bbBasis + bbDev;
def bbLower = bbBasis - bbDev;

def tr = TrueRange(high, close, low);
def atrKC = MovingAverage(sqzAvgType, tr, sqzLength);

def kcUpperHigh = bbBasis + atrKC * sqzKCHigh;
def kcLowerHigh = bbBasis - atrKC * sqzKCHigh;
def kcUpperMid = bbBasis + atrKC * sqzKCMid;
def kcLowerMid = bbBasis - atrKC * sqzKCMid;
def kcUpperLow = bbBasis + atrKC * sqzKCLow;
def kcLowerLow = bbBasis - atrKC * sqzKCLow;

def highCompression = bbLower >= kcLowerHigh and bbUpper <= kcUpperHigh;
def midCompression = bbLower >= kcLowerMid and bbUpper <= kcUpperMid;
def lowCompression = bbLower >= kcLowerLow and bbUpper <= kcUpperLow;
def inSqueeze = highCompression or midCompression or lowCompression;

def sqzLevel = if highCompression then 3 else if midCompression then 2 else if lowCompression then 1 else 0;
def squeezeFired = inSqueeze[1] and !inSqueeze;

def hh = Highest(high, sqzLength);
def ll = Lowest(low, sqzLength);
def avgHL = (hh + ll) / 2.0;
def avgSMA = (avgHL + bbBasis) / 2.0;
def sqzMom = Inertia(close - avgSMA, sqzLength);
def sqzMomUp = sqzMom > sqzMom[1];
def sqzMomDown = sqzMom < sqzMom[1];
def sqzBull = sqzMom > 0 and sqzMomUp;
def sqzBear = sqzMom < 0 and sqzMomDown;

# =========================
# TMO (Mobius True Momentum Oscillator)
# =========================
input tmoLength = 14;
input tmoCalcLength = 5;
input tmoSmoothLength = 3;

def tmoData = fold i = 0 to tmoLength
              with s
              do s + (if close > GetValue(open, i) then 1
                      else if close < GetValue(open, i) then -1
                      else 0);
def tmoEma = ExpAverage(tmoData, tmoCalcLength);
def tmoMain = ExpAverage(tmoEma, tmoSmoothLength);
def tmoSignal = ExpAverage(tmoMain, tmoSmoothLength);
def tmoBull = tmoMain > tmoSignal;
def tmoBear = tmoMain < tmoSignal;
def tmoCrossUp = tmoMain crosses above tmoSignal;
def tmoCrossDn = tmoMain crosses below tmoSignal;
def tmoOB = Round(tmoLength * 0.7);
def tmoOS = -Round(tmoLength * 0.7);

# =========================
# B4 / BIG FOUR
# AK Trend + Z-Score + Ehlers Distance Coefficient + Anchored Momentum
# =========================
input b4Confirmation = 3; # 1-4
input b4HoldTrend = yes;
input b4FilterWithTMO = no;

# AK Trend
def akFast = ExpAverage(close, 3);
def akSlow = ExpAverage(close, 8);
def akSpread = (akFast - akSlow) * 1.001;
def b4c1Up = if akSpread > 0 then 1 else 0;
def b4c1Dn = if akSpread <= 0 then -1 else 0;

# Z-Score
def zLen = 20;
def zSd = StDev(close, zLen);
def zAvg = Average(close, zLen);
def zScore = if zSd != 0 then (close - zAvg) / zSd else 0;
def b4c2Up = if zScore > 0 then 1 else 0;
def b4c2Dn = if zScore <= 0 then -1 else 0;

# Ehlers Distance Coefficient Filter
def eLen = 34;
def ePrice = (high + low) / 2.0;
def eCoeff = eLen * ePrice * ePrice
             - 2 * ePrice * Sum(ePrice, eLen)[1]
             + Sum(ePrice * ePrice, eLen)[1];
def ehlers = if Sum(eCoeff, eLen) != 0
             then Sum(eCoeff * ePrice, eLen) / Sum(eCoeff, eLen)
             else ePrice;
def b4c3Up = if close > ehlers then 1 else 0;
def b4c3Dn = if close <= ehlers then -1 else 0;

# Anchored Momentum
def amPeriod = 10;
def amSignalPeriod = 8;
def amP = 2 * amPeriod + 1;
def amom = 100 * ((close / Average(close, amP)) - 1);
def amoms = Average(amom, amSignalPeriod);
def b4c4Up = if amom > 0 then 1 else 0;
def b4c4Dn = if amom <= 0 then -1 else 0;

def b4UpVotes = b4c1Up + b4c2Up + b4c3Up + b4c4Up;
def b4DnVotes = b4c1Dn + b4c2Dn + b4c3Dn + b4c4Dn;

# optional TMO zero-line timing filter used by Big Four traders
def b4TmoLongOk = if b4FilterWithTMO then tmoMain <= 0 else yes;
def b4TmoShortOk = if b4FilterWithTMO then tmoMain >= 0 else yes;

def b4Dir = if b4UpVotes >= b4Confirmation and b4TmoLongOk then 1
            else if b4DnVotes <= -b4Confirmation and b4TmoShortOk then -1
            else if !b4HoldTrend and b4Dir[1] == 1 and b4UpVotes < b4Confirmation then 0
            else if !b4HoldTrend and b4Dir[1] == -1 and b4DnVotes > -b4Confirmation then 0
            else b4Dir[1];

def b4Bull = b4Dir == 1;
def b4Bear = b4Dir == -1;

# =========================
# TEN X (DMI / ADX bar coloring)
# =========================
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
def tenXNeutral = !tenXBull and !tenXBear;

# =========================
# MULTI-TIMEFRAME TENX + TMO
# =========================
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
    def e1 = ExpAverage(data, calcLength);
    def main = ExpAverage(e1, smoothLength);
    def sig = ExpAverage(main, smoothLength);
    plot state = if main > sig then 1 else -1;
}

def tenX_htf1 = TenXState(tenXLength, tenXAvgType, tenXAdxThresh,
                          high(period = htf1), low(period = htf1), close(period = htf1));
def tenX_htf2 = TenXState(tenXLength, tenXAvgType, tenXAdxThresh,
                          high(period = htf2), low(period = htf2), close(period = htf2));
def tenX_htf3 = TenXState(tenXLength, tenXAvgType, tenXAdxThresh,
                          high(period = htf3), low(period = htf3), close(period = htf3));

def tmo_htf1 = TmoState(tmoLength, tmoCalcLength, tmoSmoothLength,
                        open(period = htf1), close(period = htf1));
def tmo_htf2 = TmoState(tmoLength, tmoCalcLength, tmoSmoothLength,
                        open(period = htf2), close(period = htf2));
def tmo_htf3 = TmoState(tmoLength, tmoCalcLength, tmoSmoothLength,
                        open(period = htf3), close(period = htf3));

def htfBullVotes = (if tenX_htf1 == 1 then 1 else 0)
                 + (if tenX_htf2 == 1 then 1 else 0)
                 + (if tenX_htf3 == 1 then 1 else 0)
                 + (if tmo_htf1 == 1 then 1 else 0)
                 + (if tmo_htf2 == 1 then 1 else 0)
                 + (if tmo_htf3 == 1 then 1 else 0);
def htfBearVotes = (if tenX_htf1 == -1 then 1 else 0)
                 + (if tenX_htf2 == -1 then 1 else 0)
                 + (if tenX_htf3 == -1 then 1 else 0)
                 + (if tmo_htf1 == -1 then 1 else 0)
                 + (if tmo_htf2 == -1 then 1 else 0)
                 + (if tmo_htf3 == -1 then 1 else 0);

def htfBull = htfBullVotes >= 4;
def htfBear = htfBearVotes >= 4;
def htfOkLong = if useHigherTimeframeFilter then htfBull else yes;
def htfOkShort = if useHigherTimeframeFilter then htfBear else yes;

# =========================
# CONFLUENCE + ENTRIES
# =========================
def squeezeTriggerLong = if requireSqueezeFire then squeezeFired else (squeezeFired or (allowSqueezeOnEntry and inSqueeze));
def squeezeTriggerShort = squeezeTriggerLong;

def voteSqzLong = if (sqzBull or (sqzMom > 0 and sqzMomUp)) and (squeezeTriggerLong or !requireSqueezeFire) then 1 else 0;
def voteSqzShort = if (sqzBear or (sqzMom < 0 and sqzMomDown)) and (squeezeTriggerShort or !requireSqueezeFire) then 1 else 0;

# if requireSqueezeFire, force the fire event into the confluence gate
def fireGateLong = if requireSqueezeFire then squeezeFired else yes;
def fireGateShort = if requireSqueezeFire then squeezeFired else yes;

def voteTmoLong = if tmoBull then 1 else 0;
def voteTmoShort = if tmoBear then 1 else 0;
def voteB4Long = if b4Bull then 1 else 0;
def voteB4Short = if b4Bear then 1 else 0;
def voteTenXLong = if tenXBull then 1 else 0;
def voteTenXShort = if tenXBear then 1 else 0;
def voteHtfLong = if htfOkLong then 1 else 0;
def voteHtfShort = if htfOkShort then 1 else 0;

def longScore = voteSqzLong + voteTmoLong + voteB4Long + voteTenXLong + voteHtfLong;
def shortScore = voteSqzShort + voteTmoShort + voteB4Short + voteTenXShort + voteHtfShort;

def longSetup = fireGateLong and longScore >= minConfluence and sqzMom >= 0;
def shortSetup = fireGateShort and shortScore >= minConfluence and sqzMom <= 0;

def longSignal = longSetup and !longSetup[1];
def shortSignal = shortSetup and !shortSetup[1];

# =========================
# PLOTS / VISUALS
# =========================
plot BuyArrow = if showArrows and longSignal then low else Double.NaN;
BuyArrow.SetPaintingStrategy(PaintingStrategy.ARROW_UP);
BuyArrow.SetDefaultColor(Color.CYAN);
BuyArrow.SetLineWeight(3);

plot SellArrow = if showArrows and shortSignal then high else Double.NaN;
SellArrow.SetPaintingStrategy(PaintingStrategy.ARROW_DOWN);
SellArrow.SetDefaultColor(Color.MAGENTA);
SellArrow.SetLineWeight(3);

AssignPriceColor(
    if !paintBars then Color.CURRENT
    else if tenXBull and b4Bull then Color.GREEN
    else if tenXBear and b4Bear then Color.RED
    else if tenXBull then Color.DARK_GREEN
    else if tenXBear then Color.DARK_RED
    else Color.GRAY
);

AddVerticalLine(showVerticals and longSignal, "LONG", Color.CYAN);
AddVerticalLine(showVerticals and shortSignal, "SHORT", Color.MAGENTA);

# =========================
# LABELS / MTF DASHBOARD
# =========================
AddLabel(showLabels, "MTF Combo", Color.WHITE);

AddLabel(showLabels,
    if sqzLevel == 3 then "SQZ: HIGH"
    else if sqzLevel == 2 then "SQZ: MID"
    else if sqzLevel == 1 then "SQZ: LOW"
    else if squeezeFired then "SQZ: FIRED"
    else "SQZ: OFF",
    if sqzLevel == 3 then Color.ORANGE
    else if sqzLevel == 2 then Color.RED
    else if sqzLevel == 1 then Color.YELLOW
    else if squeezeFired then Color.GREEN
    else Color.DARK_GRAY);

AddLabel(showLabels,
    "MOM " + Round(sqzMom, 2),
    if sqzBull then Color.GREEN else if sqzBear then Color.RED else Color.GRAY);

AddLabel(showLabels,
    if tmoBull then "TMO UP" else "TMO DN",
    if tmoBull then Color.GREEN else Color.RED);

AddLabel(showLabels,
    "B4 " + b4UpVotes + "/4",
    if b4Bull then Color.GREEN else if b4Bear then Color.RED else Color.GRAY);

AddLabel(showLabels,
    if tenXBull then "10X UP"
    else if tenXBear then "10X DN"
    else "10X FLAT",
    if tenXBull then Color.GREEN else if tenXBear then Color.RED else Color.YELLOW);

AddLabel(showLabels, "HTF1 10X", if tenX_htf1 == 1 then Color.GREEN else if tenX_htf1 == -1 then Color.RED else Color.YELLOW);
AddLabel(showLabels, "HTF2 10X", if tenX_htf2 == 1 then Color.GREEN else if tenX_htf2 == -1 then Color.RED else Color.YELLOW);
AddLabel(showLabels, "HTF3 10X", if tenX_htf3 == 1 then Color.GREEN else if tenX_htf3 == -1 then Color.RED else Color.YELLOW);
AddLabel(showLabels, "HTF1 TMO", if tmo_htf1 == 1 then Color.GREEN else Color.RED);
AddLabel(showLabels, "HTF2 TMO", if tmo_htf2 == 1 then Color.GREEN else Color.RED);
AddLabel(showLabels, "HTF3 TMO", if tmo_htf3 == 1 then Color.GREEN else Color.RED);

AddLabel(showLabels,
    "SCORE L" + longScore + " / S" + shortScore,
    if longSetup then Color.CYAN else if shortSetup then Color.MAGENTA else Color.GRAY);

AddLabel(showLabels,
    if longSignal then ">>> BUY <<<"
    else if shortSignal then ">>> SELL <<<"
    else if longSetup then "LONG BIAS"
    else if shortSetup then "SHORT BIAS"
    else "WAIT",
    if longSignal or longSetup then Color.CYAN
    else if shortSignal or shortSetup then Color.MAGENTA
    else Color.DARK_GRAY);

# hidden plots for alerts / scans / bot watchlists
plot LongAlert = longSignal;
LongAlert.Hide();
LongAlert.HideBubble();
LongAlert.HideTitle();

plot ShortAlert = shortSignal;
ShortAlert.Hide();
ShortAlert.HideBubble();
ShortAlert.HideTitle();

plot LongBias = longSetup;
LongBias.Hide();
LongBias.HideBubble();
LongBias.HideTitle();

plot ShortBias = shortSetup;
ShortBias.Hide();
ShortBias.HideBubble();
ShortBias.HideTitle();

plot ConfluenceLong = longScore;
ConfluenceLong.Hide();
ConfluenceLong.HideBubble();
ConfluenceLong.HideTitle();

plot ConfluenceShort = shortScore;
ConfluenceShort.Hide();
ConfluenceShort.HideBubble();
ConfluenceShort.HideTitle();

Alert(longSignal, "MTF Combo LONG", Alert.BAR, Sound.Ding);
Alert(shortSignal, "MTF Combo SHORT", Alert.BAR, Sound.Bell);
