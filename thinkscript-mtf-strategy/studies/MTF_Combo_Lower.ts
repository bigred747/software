# MTF Combo Lower Panel
# Squeeze Pro histogram/dots + TMO cloud on one lower study
# Companion to MTF_Combo_Dashboard (upper)
#
# Install: Studies > Create > paste > name: MTF_Combo_Lower

declare lower;

input showTMO = yes;
input showSqueeze = yes;
input showZero = yes;

# =========================
# SQUEEZE PRO
# =========================
input sqzLength = 20;
input sqzBBMult = 2.0;
input sqzKCHigh = 1.0;
input sqzKCMid = 1.5;
input sqzKCLow = 2.0;
input sqzAvgType = AverageType.SIMPLE;

def bbBasis = MovingAverage(sqzAvgType, close, sqzLength);
def bbDev = sqzBBMult * StDev(close, sqzLength);
def bbUpper = bbBasis + bbDev;
def bbLower = bbBasis - bbDev;
def atrKC = MovingAverage(sqzAvgType, TrueRange(high, close, low), sqzLength);

def kcUpperHigh = bbBasis + atrKC * sqzKCHigh;
def kcLowerHigh = bbBasis - atrKC * sqzKCHigh;
def kcUpperMid = bbBasis + atrKC * sqzKCMid;
def kcLowerMid = bbBasis - atrKC * sqzKCMid;
def kcUpperLow = bbBasis + atrKC * sqzKCLow;
def kcLowerLow = bbBasis - atrKC * sqzKCLow;

def highCompression = bbLower >= kcLowerHigh and bbUpper <= kcUpperHigh;
def midCompression = bbLower >= kcLowerMid and bbUpper <= kcUpperMid;
def lowCompression = bbLower >= kcLowerLow and bbUpper <= kcUpperLow;

def hh = Highest(high, sqzLength);
def ll = Lowest(low, sqzLength);
def avgHL = (hh + ll) / 2.0;
def avgSMA = (avgHL + bbBasis) / 2.0;
def momo = Inertia(close - avgSMA, sqzLength);

def pos = momo >= 0;
def neg = momo < 0;
def up = momo >= momo[1];
def dn = momo < momo[1];

plot SqueezeMom = if showSqueeze then momo else Double.NaN;
SqueezeMom.SetPaintingStrategy(PaintingStrategy.HISTOGRAM);
SqueezeMom.AssignValueColor(
    if pos and up then Color.GREEN
    else if pos and dn then Color.DARK_GREEN
    else if neg and dn then Color.DARK_ORANGE
    else Color.YELLOW
);
SqueezeMom.SetLineWeight(3);

plot SqueezeDots = if showSqueeze then 0 else Double.NaN;
SqueezeDots.SetPaintingStrategy(PaintingStrategy.POINTS);
SqueezeDots.SetLineWeight(3);
SqueezeDots.AssignValueColor(
    if highCompression then Color.RED
    else if midCompression then Color.YELLOW
    else if lowCompression then Color.DARK_ORANGE
    else Color.GRAY
);

# =========================
# TMO
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
def tmoMainVal = ExpAverage(tmoEma, tmoSmoothLength);
def tmoSignalVal = ExpAverage(tmoMainVal, tmoSmoothLength);

plot TMO_Main = if showTMO then tmoMainVal else Double.NaN;
plot TMO_Signal = if showTMO then tmoSignalVal else Double.NaN;
TMO_Main.SetDefaultColor(Color.CYAN);
TMO_Signal.SetDefaultColor(Color.MAGENTA);
TMO_Main.SetLineWeight(2);
TMO_Signal.SetLineWeight(2);
AddCloud(TMO_Main, TMO_Signal, Color.DARK_GREEN, Color.DARK_RED);

plot ZeroLine = if showZero then 0 else Double.NaN;
ZeroLine.SetDefaultColor(Color.GRAY);
ZeroLine.HideBubble();
ZeroLine.HideTitle();

plot TMO_OB = if showTMO then Round(tmoLength * 0.7) else Double.NaN;
plot TMO_OS = if showTMO then -Round(tmoLength * 0.7) else Double.NaN;
TMO_OB.SetDefaultColor(Color.DARK_GRAY);
TMO_OS.SetDefaultColor(Color.DARK_GRAY);
TMO_OB.SetStyle(Curve.SHORT_DASH);
TMO_OS.SetStyle(Curve.SHORT_DASH);

AddLabel(yes,
    if highCompression then "HIGH SQUEEZE"
    else if midCompression then "MID SQUEEZE"
    else if lowCompression then "LOW SQUEEZE"
    else "NO SQUEEZE",
    if highCompression then Color.RED
    else if midCompression then Color.YELLOW
    else if lowCompression then Color.DARK_ORANGE
    else Color.GRAY);

AddLabel(yes,
    if tmoMainVal > tmoSignalVal then "TMO BULL" else "TMO BEAR",
    if tmoMainVal > tmoSignalVal then Color.GREEN else Color.RED);
