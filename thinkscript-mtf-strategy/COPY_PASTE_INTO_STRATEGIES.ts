# Edge · five checks
# Paste into Install study → Name + Formula
# Name: Edge five checks
#
# Allowed ops only: Average, RSI, Highest, Lowest, if/then
# 1 = BUY, -1 = SELL, 0 = WAIT

def a20 = Average(close, 20);
def a50 = Average(close, 50);
def a200 = Average(close, 200);
def r = RSI(14);
def hi20 = Highest(high, 20);
def lo20 = Lowest(low, 20);
def breakout = if close >= hi20 then 1 else 0;
def breakdown = if close <= lo20 then 1 else 0;
def momUp = if r > 50 then 1 else 0;
def momDn = if r < 50 then 1 else 0;
def t20 = if close > a20 then 1 else 0;
def t50 = if a20 > a50 then 1 else 0;
def t200 = if close > a200 then 1 else 0;
def not20 = if t20 < 1 then 1 else 0;
def not50 = if t50 < 1 then 1 else 0;
def not200 = if t200 < 1 then 1 else 0;
def longScore = breakout + momUp + t20 + t50 + t200;
def shortScore = breakdown + momDn + not20 + not50 + not200;
def longOk = if longScore >= 4 then 1 else 0;
def shortOk = if shortScore >= 4 then 1 else 0;
def signal = if longOk > 0 then 1 else 0;
plot Edge = if shortOk > 0 then -1 else signal;
