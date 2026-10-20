#!/usr/bin/env python3
"""Build the offline Cherokee Trail book. Facts only, from public schedules and stories."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent

def pos(title, align, job):
    return {"title": title, "align": align, "job": job}

def call(cid, name, family, brief, why, fits, rooms, notes):
    return {
        "id": cid, "name": name, "family": family, "brief": brief, "why": why,
        "fits": fits, "positions": rooms, "coachNotes": notes,
    }

CALLS = [
    call("odd-cover3", "Odd Cover 3", "base",
         "Odd front. Cover 3. Fit the run downhill. One safety in the post.",
         "This is the sound early-down call against a run-first Cherokee Trail team. The extra fitter in the box handles a featured back without vacating the deep middle.",
         {"run_inside": 10, "run_edge": 8, "run_explosive": 5, "pass_short": 5, "pass_explosive": 4, "play_action": 4, "goal_line": 6, "third_long": 3},
         {
            "dl": pos("Defensive line", "4-3 odd", "Nose in a shade on the center. Ends in a 5-technique. The end to the back squeezes the C gap and does not chase a boot. The backside end stays home for the cutback and the quarterback keep."),
            "lb": pos("Linebackers", "Sam, Mike, Will", "Mike fits the A gap to the call. Will fits the B gap away from the call. Sam walks to the tight side and fits outside the end, spilling the run back inside."),
            "s": pos("Safeties", "Cover 3", "The strong safety rotates down only after he reads a high hat and a run path. The free safety stays in the deep middle. Both safeties do not bite on the first step of play-action."),
            "cb": pos("Corners", "Deep thirds", "Boundary corner inside leverage. Field corner off at seven yards, outside leverage. Reroute an inside release, then carry the vertical. Do not jump a hitch."),
         },
         {
            "mangnall": "2016 was a run team. Holt had the ball. Live in this front and make him cut back into the Mike.",
            "johnson": "Use this when the back is the story of the week. Check out of it when the quarterback is throwing 30 times.",
            "jajczyk": "A change-up against Jajczyk, not the base, unless the box score that week is a running back game.",
         }),
    call("nickel-quarters", "Nickel Cover 4", "base",
         "Nickel. Quarters match. Safeties high. Fit the back after you read run.",
         "Two deep safeties answer the explosive throw and still fit a long run. This is the base call against the passing years, including the current Peterson offense.",
         {"run_inside": 6, "run_edge": 6, "run_explosive": 9, "pass_short": 6, "pass_explosive": 10, "play_action": 8, "goal_line": 3, "third_long": 9},
         {
            "dl": pos("Defensive line", "Four down, tight", "Ends in a 4i or 5. Tackles in a 3. Get the quarterback's eyes down without leaving a free runner."),
            "lb": pos("Linebackers", "Two backers, nickel on the slot", "Mike and Will fit inside-out. The nickel is the apex on the slot, not a third linebacker in the box. On a run read he fits the alley. On a pass read he matches the slot."),
            "s": pos("Safeties", "Cover 4, both high", "Each safety owns a half until the quarterback declares. Do not drive on play-action until the ball is a run past the line. The post and the deep over are the explosives in this offense."),
            "cb": pos("Corners", "Press alignment, match rules", "Inside leverage, eyes on the receiver, jam, then carry anything vertical. You have a safety over the top. Do not squat on a hitch against Peterson, Brook, or Lavigne."),
         },
         {
            "mangnall": "Too much coverage for 2016 if you never fit the run. Use it on long yardage only.",
            "johnson": "This is the Johnson answer in 2020 and 2021, when the ball was in the air and the explosives were 30 yards and more.",
            "jajczyk": "This is the 2026 install. Peterson, Smith, Stanley, and Mitchell have all scored. Do not lock one safety on one name.",
         }),
    call("bear-goalline", "Bear, Cover 1", "goal",
         "Bear front. Man coverage. One safety on the goal line. No wrap.",
         "Inside the 5, and on 3rd and 1, the call has to be gap-sound. A blitz that leaves a lane is how short touchdowns happen.",
         {"run_inside": 8, "run_edge": 5, "run_explosive": 2, "pass_short": 4, "pass_explosive": 2, "play_action": 3, "goal_line": 10, "third_long": 1},
         {
            "dl": pos("Defensive line", "Bear, five across", "Guards covered. Center covered. Ends wider than the tackle. Each man owns the gap to his inside. No one slants out of his gap."),
            "lb": pos("Linebackers", "Stacked in the gaps", "Sam, Mike, and Will are in the B gaps and the A gap that the nose leaves. They step downhill on the snap. They do not scrape until the ball declares outside."),
            "s": pos("Safeties", "Goal-line safety", "One safety stands on the goal line in the middle. He is not 12 yards deep. He fits a sneak and he is the last man on a fade."),
            "cb": pos("Corners", "Man, inside", "Press the receiver. Inside hand on the fade. You have no deep help other than the goal-line safety, so do not let the receiver stack you."),
         },
         {
            "mangnall": "Holt and James scored from in close. This is the call inside the 5 in that season.",
            "johnson": "Williams in 2019 and Collins in 2024 scored from the 3, the 5, and the 8. Bear on the goal line. Cover 4 everywhere else.",
            "jajczyk": "Lee scored from the 3 in 2023 and Newell from the 1 in 2026. Same rule. Bear only when they are in the low red zone.",
         }),
    call("nickel-cover2", "Nickel Cover 2", "base",
         "Cover 2. Corners sink to the flats. Safeties take the halves. Tackle the catch.",
         "The right call when the published scores are short throws: five, nine, and ten yards, not go balls.",
         {"run_inside": 5, "run_edge": 5, "run_explosive": 4, "pass_short": 10, "pass_explosive": 5, "play_action": 6, "goal_line": 4, "third_long": 5},
         {
            "dl": pos("Defensive line", "Four down", "Rush four from a tight alignment. The ends squeeze. A five-yard stick is not their problem. A boot lane is."),
            "lb": pos("Linebackers", "Wall the hooks", "Mike and Will drop to the hooks at eight yards and rally downhill to the stick. One of them has to be the tackler on a five-yard completion."),
            "s": pos("Safeties", "Deep halves", "Both safeties sink at the snap. They do not come down for a flat. They break on anything that gets past 12 yards."),
            "cb": pos("Corners", "Jam and sink", "Jam the receiver, then fall off into the flat. You are not a deep-third player in this call. Rally to the ball and tackle."),
         },
         {
            "mangnall": "Only on a passing down. The 2016 offense did not live on the short throw.",
            "johnson": "This matches the 2018 Poudre game, when the touchdown throws were four, nine, and ten yards.",
            "jajczyk": "Use it in the red zone when the scores have been 10 and 25 yards, as they were against Fort Collins in 2022.",
         }),
    call("sim-robber", "Sim pressure, robber", "pressure",
         "Four rush. One backer adds late. The other robs the crosser. Safeties stay deep.",
         "A way to hit the quarterback on third and medium without playing zero. The robber is there because this offense throws the crosser after play-action.",
         {"run_inside": 5, "run_edge": 4, "run_explosive": 4, "pass_short": 7, "pass_explosive": 7, "play_action": 8, "goal_line": 3, "third_long": 6},
         {
            "dl": pos("Defensive line", "Four-man rush", "Contain rush. The ends stay on the quarterback's shoulders. The sim is the linebacker, not a fifth lineman trading gaps."),
            "lb": pos("Linebackers", "One blitz, one robber", "The Will adds late through the B gap. The Mike drops to the middle and robs anything crossing his face. They do not both blitz."),
            "s": pos("Safeties", "Two high", "Stay high. The pressure is only good if the shot is covered. Rotate only after the ball is thrown."),
            "cb": pos("Corners", "Man trail or sink", "Play inside leverage and carry the vertical. You are not jumping the hitch on the snap. The robber has the crosser."),
         },
         {
            "mangnall": "A third-down change-up. Not the early-down call against Holt.",
            "johnson": "The change-up behind Cover 4 in the Brook and Lavigne years. Call it once a half.",
            "jajczyk": "Use it on 3rd and 4 to 7 against Peterson. If the backfield is empty, check to Cover 4 and do not blitz.",
         }),
    call("dime-cushion", "Dime, deeper cushion", "prevent",
         "Dime. Safeties deeper. Still fit a draw. This is not a soft prevent.",
         "Only when the lead is safe and the clock is short. A soft prevent gives this offense the first down and the shot.",
         {"run_inside": 2, "run_edge": 3, "run_explosive": 7, "pass_short": 4, "pass_explosive": 9, "play_action": 5, "goal_line": 1, "third_long": 10},
         {
            "dl": pos("Defensive line", "Four down, contain", "Rush contain. Do not pin your ears. A draw or a quarterback keep ends the game if the ends crash inside."),
            "lb": pos("Linebackers", "Off the ball", "Two backers at six yards. They read run first in this package, because the offense will check to the draw."),
            "s": pos("Safeties", "Deeper than usual", "Start two yards deeper than a normal Cover 4. Break late. Do not give up the post in exchange for a five-yard tackle."),
            "cb": pos("Corners", "Off, outside leverage", "Eight yards off, outside leverage. You are protecting the sideline. Tackle the hitch and live for the next down."),
         },
         {
            "mangnall": "You should almost never be in dime against the 2016 team.",
            "johnson": "Last two minutes with a lead, and only then. Cover 4 is still better if they have a timeout.",
            "jajczyk": "Same rule in 2026. Peterson will take the draw if you drop eight and rush three.",
         }),
    call("sam-wrap", "Sam wrap", "wrap",
         "Sam wraps inside the end. The end spills. Mike scrapes over the top.",
         "The run blitz for a downhill back. The wrapper aims inside the tackle, not around the edge.",
         {"run_inside": 8, "run_edge": 6, "run_explosive": 3, "pass_short": 3, "pass_explosive": 2, "play_action": 2, "goal_line": 4, "third_long": 1},
         {
            "dl": pos("Defensive line", "End spills", "The end to the wrap long-arms the tackle and spills every run outside. He does not crash. The wrapper is filling the gap he leaves."),
            "lb": pos("Linebackers", "Sam wraps, Mike scrapes", "Sam's path is the inside hip of the tackle, then the A gap. Mike scrapes late over the top for the cutback. Will stays in the backside A gap."),
            "s": pos("Safeties", "Replace late", "The safety to the wrap spins down only after the ball is handed off. Until then he is a pass defender. A wrap with no replacement is a touchdown throw."),
            "cb": pos("Corners", "Normal Cover 3", "Play the coverage called behind the wrap. If the slot shows a quick screen to the wrap side, check out of the blitz before the snap."),
         },
         {
            "mangnall": "The early-down pressure against Holt. Call it with a back in the box. Check out of it on empty.",
            "johnson": "Use it when Collins or the back is the carry, not when the plan is four verticals.",
            "jajczyk": "A change-up against the 2026 passing game. Once a half, on 2nd and medium, or not at all.",
         }),
    call("will-wrap", "Will wrap", "wrap",
         "Will wraps from the back side and meets the puller inside.",
         "The answer to counter and to a guard pulling across the formation. He hits the pull, he does not chase the fake.",
         {"run_inside": 6, "run_edge": 8, "run_explosive": 3, "pass_short": 3, "pass_explosive": 2, "play_action": 3, "goal_line": 3, "third_long": 1},
         {
            "dl": pos("Defensive line", "Backside end spills", "The backside end stays wide and spills. He does not follow a puller down the line and open the boot lane behind him."),
            "lb": pos("Linebackers", "Will wraps", "Will's landmark is the inside hip of the backside tackle. He meets the pulling guard there. Sam fits the front side. Mike is the cutback player."),
            "s": pos("Safeties", "Backside safety slow", "The safety away from the counter does not run himself across the formation. He is the fit if the quarterback keeps the ball."),
            "cb": pos("Corners", "Stay home", "The counter pass, if they throw it, is the receiver you left."),
         },
         {
            "mangnall": "Call it when the film shows a pull. If you have not seen a pull, Sam wrap is the better run blitz.",
            "johnson": "Good against a counter week. Not the call on 3rd and long.",
            "jajczyk": "Same. One call, early, with a back in the box.",
         }),
    call("bandit-wrap", "Bandit wrap", "wrap",
         "Safety inserts off the edge. The corner sinks into the flat the safety left.",
         "The change-up blitz against a throwing quarterback. It is not the base. The corner has to sink or the flat is a touchdown.",
         {"run_inside": 4, "run_edge": 5, "run_explosive": 3, "pass_short": 4, "pass_explosive": 3, "play_action": 4, "goal_line": 2, "third_long": 2},
         {
            "dl": pos("Defensive line", "Contain away from the wrap", "The end opposite the wrap contains the quarterback. The end to the wrap spills so the safety has a lane. Four hands on the offensive line, not a free runner at the quarterback from the other side."),
            "lb": pos("Linebackers", "Mike and Will fit", "They do not blitz with the safety. Mike fits the A gap. Will walls the cutback. Two blitzers here is how the draw scores."),
            "s": pos("Safeties", "Bandit inserts, free safety stays", "The bandit safety's path is off the edge, aiming at the tight end's outside hip, then flattening. The free safety does not come down. He is the deep man the blitz spends."),
            "cb": pos("Corners", "Corner sinks", "The corner to the blitz sinks to the flat at the snap. He is the hot player. If he covers a vertical and the safety is blitzing, the quick throw is open."),
         },
         {
            "mangnall": "You do not need a safety blitz as the base against a 12-pass-a-game offense.",
            "johnson": "Once a half in 2021, with the coverage replacement called out loud.",
            "jajczyk": "The 2026 change-up. Empty or a quick screen to that side is a check to Cover 4.",
         }),
]

OFFENSE = [
    {"id": "inside-zone", "name": "Inside zone", "coaches": ["mangnall", "johnson", "jajczyk"],
     "years": {"2016": 9, "2017": 7, "2018": 6, "2019": 8, "2020": 5, "2021": 5, "2022": 9, "2023": 5, "2024": 8, "2025": 8, "2026": 6},
     "situations": {"standard": 6, "third_long": 0, "red": 4, "goal": 2, "two_minute": 2, "need_score": 2},
     "summary": "11 personnel. The back's path is the play-side guard. He cuts off the first defender who shows.",
     "why": "The right series against a tackling defense and against the 2022 team that intercepted 10 passes. Run it before you throw it."},
    {"id": "gt-counter", "name": "Guard-tackle counter", "coaches": ["mangnall", "johnson", "jajczyk"],
     "years": {"2016": 8, "2017": 6, "2018": 5, "2019": 6, "2020": 4, "2021": 4, "2022": 8, "2023": 4, "2024": 7, "2025": 7, "2026": 7},
     "situations": {"standard": 3, "third_long": 0, "red": 2, "goal": 1, "two_minute": 1, "need_score": 2},
     "summary": "Fake the zone. The back-side guard and tackle pull. The back takes the ball the other way.",
     "why": "Call it after inside zone has moved the linebackers. It also runs away from a rushing end."},
    {"id": "boot", "name": "Zone boot", "coaches": ["mangnall", "johnson", "jajczyk"],
     "years": {"2016": 8, "2017": 5, "2018": 6, "2019": 5, "2020": 6, "2021": 5, "2022": 6, "2023": 5, "2024": 5, "2025": 5, "2026": 8},
     "situations": {"standard": 2, "third_long": 2, "red": 3, "goal": 0, "two_minute": 3, "need_score": 3},
     "summary": "Fake the zone one way. The quarterback boots away and throws the flat or the crosser.",
     "why": "The 2016 defense had a sack leader, and the 2026 front has published hurries. Get the ball out away from that end."},
    {"id": "mesh", "name": "Mesh", "coaches": ["johnson", "jajczyk"],
     "years": {"2016": 3, "2017": 4, "2018": 6, "2019": 4, "2020": 8, "2021": 8, "2022": 5, "2023": 6, "2024": 5, "2025": 5, "2026": 9},
     "situations": {"standard": 4, "third_long": 3, "red": 3, "goal": 0, "two_minute": 5, "need_score": 5},
     "summary": "Two receivers cross. The ball comes out on time, under the linebackers.",
     "why": "In the years the games were shootouts, you have to score and you cannot hold the ball."},
    {"id": "yankee", "name": "Yankee play-action", "coaches": ["johnson", "jajczyk"],
     "years": {"2016": 3, "2017": 4, "2018": 5, "2019": 4, "2020": 9, "2021": 9, "2022": 4, "2023": 8, "2024": 4, "2025": 4, "2026": 8},
     "situations": {"standard": 2, "third_long": 4, "red": 1, "goal": 0, "two_minute": 4, "need_score": 6},
     "summary": "Fake the run. Throw the post behind it, with an over route from the other side.",
     "why": "The shot when the score says you have to have a 30-yard play. Not the call in a one-score rock fight."},
    {"id": "stick", "name": "Stick", "coaches": ["mangnall", "johnson", "jajczyk"],
     "years": {"2016": 4, "2017": 5, "2018": 8, "2019": 5, "2020": 6, "2021": 6, "2022": 8, "2023": 5, "2024": 6, "2025": 6, "2026": 6},
     "situations": {"standard": 2, "third_long": 1, "red": 5, "goal": 1, "two_minute": 3, "need_score": 2},
     "summary": "Outside man clears. Slot sits at five yards. Throw the sit if the flat defender is deep.",
     "why": "The 2022 defense took the ball away. Throw outside and short. Do not throw a dig into those linebackers."},
    {"id": "duo", "name": "Duo, goal line", "coaches": ["mangnall", "johnson", "jajczyk"],
     "years": {"2016": 6, "2017": 5, "2018": 4, "2019": 7, "2020": 3, "2021": 3, "2022": 5, "2023": 5, "2024": 7, "2025": 7, "2026": 4},
     "situations": {"standard": 1, "third_long": 0, "red": 4, "goal": 8, "two_minute": 1, "need_score": 2},
     "summary": "Double team at the point of attack. The back's path is downhill.",
     "why": "Several of their own games, and several of their losses, were decided inside the 10. Finish the drive."},
    {"id": "screen", "name": "Back screen", "coaches": ["johnson", "jajczyk"],
     "years": {"2016": 3, "2017": 3, "2018": 4, "2019": 3, "2020": 5, "2021": 6, "2022": 4, "2023": 5, "2024": 4, "2025": 4, "2026": 7},
     "situations": {"standard": 1, "third_long": 3, "red": 1, "goal": 0, "two_minute": 2, "need_score": 2},
     "summary": "The ends rush up the field. The back catches it behind them.",
     "why": "A hurry front, which showed up in the 2026 stat snapshot, is the front a screen is drawn for."},
    {"id": "no-huddle", "name": "No-huddle inside zone", "coaches": ["johnson", "jajczyk"],
     "years": {"2016": 2, "2017": 3, "2018": 4, "2019": 4, "2020": 7, "2021": 8, "2022": 3, "2023": 5, "2024": 3, "2025": 3, "2026": 8},
     "situations": {"standard": 3, "third_long": 1, "red": 1, "goal": 0, "two_minute": 4, "need_score": 5},
     "summary": "Same inside zone, snapped while the defense is still pointing.",
     "why": "In 2021 and 2026 the games were high scoring. Tempo keeps a pass rush from pinning its ears every play."},
]

SEASONS = [
    {"id": 2016, "coachId": "mangnall", "coachName": "Dain Mangnall", "record": "5-6", "leagueRecord": "3-2", "league": "5A Mt. Evans", "pf": 268, "pa": 245,
     "prior": {"run_inside": 9, "run_edge": 6, "run_explosive": 3, "pass_short": 3, "pass_explosive": 2, "play_action": 2, "goal_line": 6, "third_long": 2},
     "why": "Through 10 games, before the Valor Christian playoff, Brenden Holt had 900 rushing yards and 10 rushing touchdowns. Ethan Leisge had thrown only 124 passes.",
     "offensePlan": "Run the ball and boot away from the pass rush. Chase King had seven sacks in that same 10-game window. Do not hold it.",
     "weapons": ["Brenden Holt, running back", "Dylan James, running back", "Ethan Leisge, quarterback"]},
    {"id": 2017, "coachId": "johnson", "coachName": "Joe Johnson", "record": "4-6", "leagueRecord": "2-3", "league": "5A Mt. Evans", "pf": 208, "pa": 251,
     "prior": {"run_inside": 6, "run_edge": 4, "run_explosive": 3, "pass_short": 4, "pass_explosive": 3, "play_action": 3, "goal_line": 4, "third_long": 3},
     "why": "Johnson's first season. The published offensive detail is thin. Dylan James scored on a short run against Denver East. The defense and special teams scored the rest of that game.",
     "offensePlan": "There is not a published defensive snap list for this season. Play sound football and do not hand them a short field. They scored on defense in the Denver East game.",
     "weapons": ["Dylan James, running back", "Ethan Leisge, quarterback"]},
    {"id": 2018, "coachId": "johnson", "coachName": "Joe Johnson", "record": "8-4", "leagueRecord": "2-3", "league": "5A Metro East", "pf": 311, "pa": 280,
     "prior": {"run_inside": 5, "run_edge": 4, "run_explosive": 3, "pass_short": 7, "pass_explosive": 4, "play_action": 5, "goal_line": 4, "third_long": 4},
     "why": "Against Poudre in the playoffs, Jayden LaVigne's touchdown throws were four, nine, and ten yards. The season also included a 49-point game against Lakewood. The snap list for that game was not published.",
     "offensePlan": "No defensive snap list was published. The score environment says you can move the ball. Finish drives. They also scored on a 67-yard interception return in the Poudre game.",
     "weapons": ["Jayden LaVigne, quarterback", "Sean Roberts, receiver", "Donovan Swift, receiver"]},
    {"id": 2019, "coachId": "johnson", "coachName": "Joe Johnson", "record": "8-4", "leagueRecord": "3-2", "league": "5A Metro East", "pf": 282, "pa": 236,
     "prior": {"run_inside": 8, "run_edge": 5, "run_explosive": 3, "pass_short": 4, "pass_explosive": 3, "play_action": 4, "goal_line": 9, "third_long": 3},
     "why": "Julian Williams scored from the 10, the 8, the 8, and the 3 against Fountain-Fort Carson. They also came back from a 29-point second-half deficit against Eaglecrest.",
     "offensePlan": "Be sound on the goal line and do not give the ball away. A 10-play drive fits the one-score games in this stretch.",
     "weapons": ["Julian Williams, running back", "Nate Gaye, back"]},
    {"id": 2020, "coachId": "johnson", "coachName": "Joe Johnson", "record": "4-3", "leagueRecord": "3-2", "league": "5A Centennial", "pf": 203, "pa": 218,
     "prior": {"run_inside": 5, "run_edge": 4, "run_explosive": 8, "pass_short": 5, "pass_explosive": 9, "play_action": 6, "goal_line": 4, "third_long": 7},
     "why": "Nate Gaye ran 65 and 51 yards against Overland. Jack Pierce and Alex Stirbis caught throws of 35, 41, 44, and 56. Against Rocky Mountain, Lavigne threw 38 times for 309 yards.",
     "offensePlan": "They allowed 62 to Eaglecrest and 49 to Cherry Creek. You can score. Do not turn the ball over. They also scored on defense in other seasons.",
     "weapons": ["Jayden Lavigne, quarterback", "Jack Pierce, receiver", "Nate Gaye, running back", "Alex Stirbis, receiver"]},
    {"id": 2021, "coachId": "johnson", "coachName": "Joe Johnson", "record": "7-5", "leagueRecord": "2-3", "league": "5A Centennial", "pf": 348, "pa": 342,
     "prior": {"run_inside": 4, "run_edge": 3, "run_explosive": 4, "pass_short": 6, "pass_explosive": 10, "play_action": 7, "goal_line": 4, "third_long": 8},
     "why": "Logan Brook led 5A with 3,226 passing yards and 36 touchdowns. Jack Pierce led 5A with 1,190 receiving yards. The offense scored 348 points. No game-by-game snap list was published.",
     "offensePlan": "They allowed 342 points. Trade scores, take the shot, and protect the ball. Tempo helps.",
     "weapons": ["Logan Brook, quarterback", "Jack Pierce, receiver"]},
    {"id": 2022, "coachId": "jajczyk", "coachName": "Justin Jajczyk", "record": "4-7", "leagueRecord": "1-4", "league": "5A League 4", "pf": 156, "pa": 243,
     "prior": {"run_inside": 7, "run_edge": 5, "run_explosive": 3, "pass_short": 7, "pass_explosive": 4, "play_action": 5, "goal_line": 6, "third_long": 3},
     "why": "A young team at 13.6 points a game in the regular season. Noah Collins rushed for 722 yards. Eliot Ming threw for 1,072 and 11 touchdowns. The published touchdowns against Fort Collins were a 2-yard run and throws of 25 and 10.",
     "offensePlan": "This defense intercepted 10 passes, and Kalib Davis had four of them. Run the ball. Throw outside and short. Do not pepper the middle.",
     "weapons": ["Eliot Ming, quarterback", "Noah Collins, running back", "Noah Greer, receiver", "Peyton Sommers, receiver"]},
    {"id": 2023, "coachId": "jajczyk", "coachName": "Justin Jajczyk", "record": "5-6", "leagueRecord": "2-3", "league": "5A Centennial", "pf": 178, "pa": 296,
     "prior": {"run_inside": 4, "run_edge": 3, "run_explosive": 3, "pass_short": 5, "pass_explosive": 8, "play_action": 6, "goal_line": 5, "third_long": 5},
     "why": "Against Chatfield, Tyson Smith hit Peyton Sommers for 77 yards and Peyton Castro for 23. The running game that night was small. Ian Lee scored from the 3.",
     "offensePlan": "They allowed 296 points. You can hit a shot. Finish the drive. The Chatfield result on the field and the later forfeit listing do not agree. See that game card.",
     "weapons": ["Tyson Smith, quarterback", "Peyton Sommers, receiver", "Peyton Castro, receiver", "Ian Lee, running back"]},
    {"id": 2024, "coachId": "johnson", "coachName": "Joe Johnson", "record": "5-7", "leagueRecord": "2-3", "league": "5A Centennial", "pf": 220, "pa": 287,
     "prior": {"run_inside": 7, "run_edge": 4, "run_explosive": 3, "pass_short": 6, "pass_explosive": 4, "play_action": 5, "goal_line": 7, "third_long": 5},
     "why": "Against Columbine, Noah Collins rushed 15 times for 109 yards and scored from the 3 and the 5. Tyson Smith threw a 19-yard touchdown on 4th and long. Cade Brook and Brandon McCullough caught the intermediate balls.",
     "offensePlan": "Several games this season and the next were decided by one score in the teens. A field goal drive wins those. Do not hunt a highlight on 4th and long unless you have to.",
     "weapons": ["Tyson Smith, quarterback", "Noah Collins, running back", "Brian Cusack, receiver", "Cade Brook, receiver"]},
    {"id": 2025, "coachId": "johnson", "coachName": "Joe Johnson", "record": "4-7", "leagueRecord": "2-3", "league": "5A Centennial", "pf": 180, "pa": 273,
     "prior": {"run_inside": 6, "run_edge": 4, "run_explosive": 3, "pass_short": 5, "pass_explosive": 4, "play_action": 4, "goal_line": 6, "third_long": 4},
     "why": "No snap list was published for this season. They scored 180 points in 11 games. The wins included 14-9, 14-7, and 21-11.",
     "offensePlan": "Treat it like 2024 until your own film says otherwise. Short fields and the red zone matter more than a trick.",
     "weapons": ["No full skill-player snap list was published for 2025."]},
    {"id": 2026, "coachId": "jajczyk", "coachName": "Justin Jajczyk", "record": "4-4", "leagueRecord": "1-4", "league": "5A Centennial", "pf": 263, "pa": 198, "live": True,
     "prior": {"run_inside": 4, "run_edge": 5, "run_explosive": 7, "pass_short": 6, "pass_explosive": 10, "play_action": 6, "goal_line": 4, "third_long": 7},
     "why": "Through October 15, 2026 the record was 4-4 and the points were 263-198. Bryson Peterson is the quarterback. Owen Newell has a published 77-yard touchdown run. Evan Smith, RJ Stanley, and Jordan Mitchell have published receiving touchdowns. The MaxPreps history page left the 2026 coach blank. The team page listed J. Jajczyk as head coach.",
     "offensePlan": "The games are in the 30s and 40s. You have to score. Get the ball out. A September 25 stat snapshot listed pressure from the defensive line. That snapshot is not the full season.",
     "weapons": ["Bryson Peterson, quarterback", "Owen Newell, running back", "Dorian Harris, running back", "Evan Smith, receiver", "RJ Stanley, receiver", "Jordan Mitchell, receiver"]},
]

def game(date, opponent, site, result, ct, opp, league=False, playoff=False, note="", kickoff="", overtime="", conflict=False):
    slug = "".join(ch.lower() if ch.isalnum() else "-" for ch in opponent).strip("-")
    while "--" in slug:
        slug = slug.replace("--", "-")
    year = int(date[:4])
    row = {
        "id": "%s-%s" % (date, slug),
        "season": year,
        "date": date,
        "opponent": opponent,
        "site": site,
        "kickoff": kickoff,
        "league": league,
        "playoff": playoff,
        "note": note,
        "overtime": overtime,
        "coachId": next(s["coachId"] for s in SEASONS if s["id"] == year),
        "plays": [],
        "special": [],
    }
    if conflict:
        row.update({"result": "conflict", "ctScore": ct, "oppScore": opp, "countInPoints": True,
                     "resultLabel": "MaxPreps prints W 27-24 (FF). The season point total only adds up if Cherokee Trail scored 24 and Chatfield scored 27, which is the on-field result Sentinel Colorado reported."})
    elif ct is None:
        row.update({"upcoming": True, "result": "", "countInPoints": False})
    else:
        row.update({"result": result, "ctScore": ct, "oppScore": opp, "countInPoints": True})
    return row

# date, opponent, site, result, ct, opp, league, playoff, note, kickoff, overtime
RAW = []

def add(date, opponent, site, result, ct, opp, league=False, playoff=False, note="", kickoff="", overtime=""):
    RAW.append(game(date, opponent, site, result, ct, opp, league, playoff, note, kickoff, overtime))

add("2016-09-02", "Highlands Ranch", "home", "L", 10, 28)
add("2016-09-08", "Denver East", "away", "W", 60, 21)
add("2016-09-15", "Overland", "home", "W", 42, 0)
add("2016-09-23", "Eaglecrest", "home", "L", 7, 21)
add("2016-09-29", "Grandview", "home", "L", 0, 24)
add("2016-10-07", "Chaparral", "home", "L", 14, 28, True)
add("2016-10-14", "Rock Canyon", "away", "W", 19, 14, True)
add("2016-10-20", "Hinkley", "home", "W", 70, 7, True)
add("2016-10-28", "Mountain Vista", "away", "W", 38, 25, True)
add("2016-11-04", "Pomona", "away", "L", 8, 35, True)
add("2016-11-11", "Valor Christian", "home", "L", 0, 42, False, True, "Playoff preview, not a snap list. Holt, James, and Leisge were the skill players named before this game.")
add("2017-09-01", "Highlands Ranch", "away", "L", 15, 36)
add("2017-09-07", "Denver East", "home", "W", 33, 0, note="The only published offensive score was a short run. The rest of the points were defense and a fake punt.")
add("2017-09-15", "Overland", "home", "W", 41, 7)
add("2017-09-22", "Eaglecrest", "away", "L", 14, 28)
add("2017-09-28", "Grandview", "away", "L", 13, 40)
add("2017-10-06", "Chaparral", "away", "L", 0, 35, True)
add("2017-10-13", "Rock Canyon", "home", "W", 24, 14, True)
add("2017-10-20", "Hinkley", "away", "W", 49, 8, True)
add("2017-10-27", "Mountain Vista", "home", "L", 7, 35, True)
add("2017-11-03", "Pomona", "home", "L", 12, 48, True)
add("2018-08-23", "Rock Canyon", "away", "W", 26, 9)
add("2018-08-31", "Mountain Vista", "home", "W", 38, 21)
add("2018-09-07", "Horizon", "away", "W", 28, 14)
add("2018-09-14", "Poudre", "home", "W", 35, 0)
add("2018-09-22", "Lakewood", "home", "W", 49, 28, note="A 2026 story cited this as a 49-point game. The play list was not published.")
add("2018-09-28", "Arapahoe", "away", "W", 34, 10, True)
add("2018-10-05", "Grandview", "home", "L", 0, 28, True)
add("2018-10-12", "Overland", "away", "W", 36, 24, True)
add("2018-10-19", "Cherry Creek", "away", "L", 14, 35, True)
add("2018-10-25", "Eaglecrest", "home", "L", 14, 28, True)
add("2018-11-02", "Poudre", "home", "W", 37, 31, False, True, overtime="2OT")
add("2018-11-08", "Ralston Valley", "home", "L", 0, 52, False, True)
add("2019-08-30", "Rock Canyon", "home", "W", 17, 13)
add("2019-09-05", "Mountain Vista", "away", "W", 17, 7)
add("2019-09-12", "Horizon", "home", "W", 21, 14)
add("2019-09-20", "Poudre", "away", "L", 21, 28, overtime="OT")
add("2019-09-27", "Lakewood", "away", "W", 31, 14)
add("2019-10-04", "Arapahoe", "home", "L", 7, 10, True)
add("2019-10-11", "Grandview", "away", "W", 26, 7, True)
add("2019-10-17", "Overland", "home", "W", 30, 20, True)
add("2019-10-25", "Cherry Creek", "away", "L", 7, 38, True)
add("2019-11-01", "Eaglecrest", "away", "W", 36, 32, True, note="A later story said they trailed by 29 in the second half of this game.")
add("2019-11-08", "Fountain-Fort Carson", "home", "W", 35, 17, False, True)
add("2019-11-15", "Fairview", "home", "L", 34, 36, False, True, note="MaxPreps lists this as a home game. A preview after the Fountain-Fort Carson win said the next game would be on the road. The published result is a 36-34 loss.")
add("2020-10-09", "Eaglecrest", "away", "L", 50, 62, True)
add("2020-10-16", "Cherry Creek", "home", "L", 7, 49, True)
add("2020-10-23", "Smoky Hill", "away", "W", 35, 21, True)
add("2020-10-29", "Rocky Mountain", "home", "W", 35, 14)
add("2020-11-06", "Grandview", "home", "W", 28, 23, True)
add("2020-11-13", "Overland", "away", "W", 34, 0, True)
add("2020-11-19", "Valor Christian", "away", "L", 14, 49, False, True, note="MaxPreps also lists a December 19 matchup at Mullen with no result. It is not counted as a played game.")
add("2021-08-27", "Mullen", "home", "W", 35, 24)
add("2021-09-02", "Columbine", "away", "L", 21, 52)
add("2021-09-10", "Far Northeast Warriors", "home", "W", 28, 14)
add("2021-09-18", "Overland", "home", "W", 35, 8)
add("2021-09-25", "Denver East", "away", "W", 41, 7)
add("2021-10-01", "Cherry Creek", "home", "L", 10, 41, True)
add("2021-10-07", "Arapahoe", "away", "L", 24, 42, True)
add("2021-10-14", "Smoky Hill", "home", "W", 36, 28, True)
add("2021-10-22", "Grandview", "home", "L", 18, 42, True)
add("2021-10-29", "Eaglecrest", "away", "W", 39, 13, True)
add("2021-11-05", "Fossil Ridge", "home", "W", 41, 29, False, True)
add("2021-11-12", "Ralston Valley", "away", "L", 20, 42, False, True, note="Season quarterfinal. No snap list was published. Brook and Pierce were the skill players of record for the season.")
add("2022-08-26", "Chatfield", "away", "W", 20, 10)
add("2022-09-02", "Rocky Mountain", "home", "L", 14, 17)
add("2022-09-08", "Columbine", "away", "L", 7, 35)
add("2022-09-16", "Fort Collins", "home", "W", 27, 13)
add("2022-09-23", "Castle View", "home", "W", 21, 14)
add("2022-09-29", "Cherry Creek", "home", "L", 7, 34, True)
add("2022-10-07", "Eaglecrest", "away", "L", 0, 30, True)
add("2022-10-13", "Arapahoe", "home", "L", 12, 16, True)
add("2022-10-21", "Grandview", "away", "L", 0, 28, True)
add("2022-10-27", "Smoky Hill", "away", "W", 28, 23, True)
add("2022-11-04", "Rock Canyon", "away", "L", 20, 23, False, True)
add("2023-08-31", "Rocky Mountain", "away", "W", 24, 0)
add("2023-09-08", "Columbine", "home", "L", 7, 35)
add("2023-09-14", "Fort Collins", "away", "W", 35, 31)
add("2023-09-21", "Castle View", "away", "L", 7, 23)
add("2023-09-28", "Cherry Creek", "away", "L", 0, 48, True)
add("2023-10-06", "Eaglecrest", "home", "L", 9, 13, True)
add("2023-10-12", "Arapahoe", "away", "L", 7, 56, True)
add("2023-10-20", "Grandview", "home", "W", 23, 21, True)
add("2023-10-26", "Smoky Hill", "home", "W", 35, 0, True)
add("2023-11-03", "Chatfield", "away", "L", 7, 42, False, True)
add("2024-08-29", "Pine Creek", "home", "L", 14, 56, note="MaxPreps is 14-56 and those points add up to the season total. Another listing had 7 points and the game on the road.")
add("2024-09-06", "ThunderRidge", "away", "W", 35, 7)
add("2024-09-13", "Mountain Vista", "home", "L", 0, 42)
add("2024-09-20", "Rocky Mountain", "home", "W", 37, 6)
add("2024-09-27", "Dakota Ridge", "away", "L", 13, 31)
add("2024-10-05", "Cherry Creek", "home", "L", 0, 45, True)
add("2024-10-11", "Eaglecrest", "away", "W", 14, 13, True)
add("2024-10-17", "Grandview", "home", "L", 13, 14, True)
add("2024-10-25", "Arapahoe", "away", "L", 7, 24, True)
add("2024-10-31", "Smoky Hill", "away", "W", 41, 0, True)
add("2024-11-10", "Castle View", "away", "W", 25, 7, False, True)
add("2024-11-16", "Columbine", "away", "L", 21, 42, False, True)
add("2025-08-28", "Pine Creek", "away", "L", 21, 35)
add("2025-09-05", "ThunderRidge", "home", "W", 21, 11)
add("2025-09-11", "Mountain Vista", "away", "L", 8, 49)
add("2025-09-19", "Rocky Mountain", "away", "W", 14, 9)
add("2025-09-26", "Dakota Ridge", "home", "L", 21, 38)
add("2025-10-03", "Cherry Creek", "away", "L", 12, 49, True)
add("2025-10-10", "Eaglecrest", "home", "L", 14, 27, True)
add("2025-10-17", "Grandview", "away", "W", 14, 7, True)
add("2025-10-23", "Arapahoe", "home", "L", 13, 27, True)
add("2025-10-30", "Smoky Hill", "home", "W", 35, 7, True)
add("2025-11-07", "Fountain-Fort Carson", "home", "L", 7, 14, False, True)
add("2026-08-28", "Arvada West", "away", "W", 45, 15, kickoff="4:00 pm")
add("2026-09-03", "Smoky Hill", "home", "W", 56, 8, kickoff="7:00 pm")
add("2026-09-10", "Rocky Mountain", "away", "W", 31, 7, kickoff="6:30 pm")
add("2026-09-17", "Eaglecrest", "away", "L", 27, 29, True, kickoff="7:00 pm")
add("2026-09-24", "Cherry Creek", "home", "L", 21, 37, True, kickoff="7:00 pm")
add("2026-10-02", "Mullen", "home", "W", 35, 28, True, kickoff="7:00 pm")
add("2026-10-09", "Arapahoe", "away", "L", 41, 43, True, kickoff="7:00 pm")
add("2026-10-15", "Grandview", "away", "L", 7, 31, True, kickoff="6:00 pm")
add("2026-10-23", "Northfield", "home", "", None, None, True, kickoff="7:00 pm")
add("2026-10-29", "Castle View", "home", "", None, None, kickoff="7:00 pm")

# Insert the disputed 2023 opener at the front of that season.
chat = game("2023-08-25", "Chatfield", "home", "L", 24, 27, conflict=True,
            note="Sentinel Colorado reported an on-field loss, 27-24, with a fumble at the goal line. MaxPreps later prints a forfeit win. Season points match the on-field 24.")
games = []
inserted = False
for row in RAW:
    if not inserted and row["season"] == 2023:
        games.append(chat)
        inserted = True
    games.append(row)

SRC = {
    "sentinel-jajczyk": "Sentinel Colorado, Dec. 10, 2021",
    "sentinel-2016": "Sentinel Colorado playoff capsule, 2016",
    "sentinel-east-2017": "Sentinel Colorado, Sept. 7, 2017",
    "sentinel-poudre": "Sentinel Colorado, 2018 Poudre playoff story",
    "sentinel-ffc": "Sentinel Colorado, 2019 Fountain-Fort Carson story",
    "sentinel-overland-2020": "Sentinel Colorado, Overland 2020 scoring summary",
    "sentinel-rocky-2020": "Sentinel Colorado, Rocky Mountain 2020 scoring summary",
    "sentinel-fc-2022": "Sentinel Colorado, Sept. 16, 2022",
    "sentinel-rock-2022": "Sentinel Colorado, 2022 Rock Canyon capsule",
    "sentinel-chatfield": "Sentinel Colorado, 2023 Chatfield story",
    "sentinel-columbine": "Sentinel Colorado, 2024 Columbine playoff story",
    "sentinel-smoky-2026": "Sentinel Colorado, 2026 Week 2 highlights",
    "copreps-2026": "Colorado Preps, Aug. 28, 2026",
    "maxpreps": "MaxPreps schedule and season totals",
    "maxpreps-2026": "MaxPreps game update",
    "sentinel-eaglecrest-2020": "Sentinel Colorado, Oct. 10, 2020",
    "chsaa-columbine-2021": "CHSAA Now, Sept. 2, 2021",
}

def snap(gid, text, tags, source, kind="snap"):
    return {"id": gid, "kind": kind, "text": text, "tags": tags, "source": source}

by = {g["id"]: g for g in games}

by["2017-09-07-denver-east"]["plays"] = [
    snap("2017-east-james", "Dylan James scored on a short rushing touchdown.", ["run_inside", "goal_line"], SRC["sentinel-east-2017"])
]
by["2017-09-07-denver-east"]["special"] = [
    {"id": "2017-east-fake", "trick": True, "trickId": "fake-punt", "text": "Sean Roberts ran a fake punt 36 yards for a touchdown.", "source": SRC["sentinel-east-2017"]},
    {"id": "2017-east-fumble", "text": "Dakota Rodriguez recovered a fumble in the end zone.", "source": SRC["sentinel-east-2017"]},
    {"id": "2017-east-scoop", "text": "Calvin Rodriguez scored on a scoop and score.", "source": SRC["sentinel-east-2017"]},
    {"id": "2017-east-pick", "text": "Dakota Rodriguez returned an interception 65 yards for a touchdown.", "source": SRC["sentinel-east-2017"]},
]
by["2018-11-02-poudre"]["plays"] = [
    snap("2018-poudre-4", "Sean Roberts caught a 4-yard touchdown pass from Jayden LaVigne.", ["pass_short"], SRC["sentinel-poudre"]),
    snap("2018-poudre-9", "Donovan Swift caught a 9-yard touchdown pass from LaVigne.", ["pass_short"], SRC["sentinel-poudre"]),
    snap("2018-poudre-10", "Roberts caught a 10-yard touchdown pass from LaVigne in overtime.", ["pass_short"], SRC["sentinel-poudre"]),
    snap("2018-poudre-10b", "Swift caught a 10-yard touchdown pass from LaVigne in the second overtime.", ["pass_short"], SRC["sentinel-poudre"]),
]
by["2018-11-02-poudre"]["special"] = [
    {"id": "2018-poudre-pick", "text": "Matty Bartels returned an interception 67 yards for a touchdown.", "source": SRC["sentinel-poudre"]}
]
by["2019-11-08-fountain-fort-carson"]["plays"] = [
    snap("2019-ffc-10", "Julian Williams scored on a 10-yard run.", ["run_inside", "goal_line"], SRC["sentinel-ffc"]),
    snap("2019-ffc-8a", "Williams scored on an 8-yard run.", ["run_inside", "goal_line"], SRC["sentinel-ffc"]),
    snap("2019-ffc-8b", "Williams scored on another 8-yard run.", ["run_inside", "goal_line"], SRC["sentinel-ffc"]),
    snap("2019-ffc-3", "Williams scored on a 3-yard run.", ["run_inside", "goal_line"], SRC["sentinel-ffc"]),
    snap("2019-ffc-gaye", "Nate Gaye scored a touchdown. The yardage was not published.", [], SRC["sentinel-ffc"]),
]
by["2020-10-09-eaglecrest"]["plays"] = [
    snap("2020-ec-14", "Nate Gaye Jr. ran 14 yards for a touchdown.", ["run_inside"], SRC["sentinel-eaglecrest-2020"]),
    snap("2020-ec-94", "Jack Pierce ran 94 yards for a touchdown.", ["run_explosive"], SRC["sentinel-eaglecrest-2020"]),
    snap("2020-ec-8", "Jack Pierce caught an 8-yard touchdown from Jayden Lavigne.", ["pass_short"], SRC["sentinel-eaglecrest-2020"]),
    snap("2020-ec-30", "Nate Burke caught a 30-yard touchdown from Jayden Lavigne.", ["pass_explosive"], SRC["sentinel-eaglecrest-2020"]),
    snap("2020-ec-44", "Nate Gaye Jr. ran 44 yards for a touchdown.", ["run_explosive"], SRC["sentinel-eaglecrest-2020"]),
    snap("2020-ec-11", "Jack Pierce ran 11 yards for a touchdown.", ["run_inside"], SRC["sentinel-eaglecrest-2020"]),
]
by["2020-10-09-eaglecrest"]["special"] = [
    {"id": "2020-ec-walker", "text": "Gabe Walker scored on a fumble recovery.", "source": SRC["sentinel-eaglecrest-2020"]}
]
by["2020-10-09-eaglecrest"]["note"] = "Sentinel Colorado printed this scoring summary. The 94-yard run is the longest published Cherokee Trail score in the ten-year book."
by["2021-09-02-columbine"]["plays"] = [
    snap("2021-col-59", "Jack Pierce caught a 59-yard touchdown from Logan Brook.", ["pass_explosive"], SRC["chsaa-columbine-2021"]),
    snap("2021-col-3", "Jack Pierce caught a 3-yard touchdown from Logan Brook.", ["pass_short", "goal_line"], SRC["chsaa-columbine-2021"]),
    snap("2021-col-6", "Gage Gordon caught a 6-yard touchdown from Logan Brook.", ["pass_short"], SRC["chsaa-columbine-2021"]),
]
by["2020-11-13-overland"]["plays"] = [
    snap("2020-ol-65", "Nate Gaye ran 65 yards for a touchdown.", ["run_explosive"], SRC["sentinel-overland-2020"]),
    snap("2020-ol-51", "Gaye ran 51 yards for a touchdown.", ["run_explosive"], SRC["sentinel-overland-2020"]),
    snap("2020-ol-41", "Jack Pierce caught a 41-yard touchdown from Jayden Lavigne.", ["pass_explosive"], SRC["sentinel-overland-2020"]),
    snap("2020-ol-35", "Pierce caught a 35-yard touchdown from Lavigne.", ["pass_explosive"], SRC["sentinel-overland-2020"]),
    snap("2020-ol-56", "Alex Stirbis caught a 56-yard touchdown from Lavigne.", ["pass_explosive"], SRC["sentinel-overland-2020"]),
]
by["2020-11-13-overland"]["special"] = [
    {"id": "2020-ol-safety", "text": "A safety. The snap was covered in the end zone.", "source": SRC["sentinel-overland-2020"]}
]
by["2020-10-29-rocky-mountain"]["plays"] = [
    snap("2020-rm-love", "Jack Love ran 1 yard for a touchdown.", ["run_inside", "goal_line"], SRC["sentinel-rocky-2020"]),
    snap("2020-rm-44", "Jack Pierce caught a 44-yard touchdown from Jayden Lavigne.", ["pass_explosive"], SRC["sentinel-rocky-2020"]),
    snap("2020-rm-10", "Nate Burke caught a 10-yard touchdown from Lavigne.", ["pass_short"], SRC["sentinel-rocky-2020"]),
    snap("2020-rm-32", "Pierce caught a 32-yard touchdown from Lavigne.", ["pass_explosive"], SRC["sentinel-rocky-2020"]),
    snap("2020-rm-hart", "Sam Hart caught a 1-yard touchdown from Lavigne.", ["pass_short", "goal_line"], SRC["sentinel-rocky-2020"]),
]
by["2022-09-16-fort-collins"]["plays"] = [
    snap("2022-fc-collins", "Noah Collins ran 2 yards for a touchdown. He finished with 111 rushing yards.", ["run_inside", "goal_line"], SRC["sentinel-fc-2022"]),
    snap("2022-fc-sommers", "Peyton Sommers caught a 25-yard touchdown from Eliot Ming after a turnover.", ["pass_explosive"], SRC["sentinel-fc-2022"]),
    snap("2022-fc-greer", "Noah Greer caught a 10-yard touchdown from Ming.", ["pass_short"], SRC["sentinel-fc-2022"]),
]
by["2022-09-16-fort-collins"]["special"] = [
    {"id": "2022-fc-pick", "text": "Kalib Davis returned an interception 39 yards for a touchdown.", "source": SRC["sentinel-fc-2022"]}
]
by["2022-10-27-smoky-hill"]["special"] = [
    {"id": "2022-sh-punt", "text": "Noah Greer's punt-return touchdown was described as the difference in a 28-23 win. The rest of the scoring plays were not published.", "source": SRC["sentinel-rock-2022"]}
]
by["2023-08-25-chatfield"]["plays"] = [
    snap("2023-ch-77", "Peyton Sommers caught a 77-yard touchdown from Tyson Smith.", ["pass_explosive"], SRC["sentinel-chatfield"]),
    snap("2023-ch-23", "Peyton Castro caught a 23-yard touchdown from Smith.", ["pass_short"], SRC["sentinel-chatfield"]),
    snap("2023-ch-lee", "Ian Lee ran 3 yards for a touchdown.", ["run_inside", "goal_line"], SRC["sentinel-chatfield"]),
]
by["2024-11-16-columbine"]["plays"] = [
    snap("2024-col-cusack", "Brian Cusack caught a 19-yard touchdown from Tyson Smith on 4th and long.", ["pass_short"], SRC["sentinel-columbine"]),
    snap("2024-col-3", "Noah Collins ran 3 yards for a touchdown. He finished with 109 rushing yards on 15 carries.", ["run_inside", "goal_line"], SRC["sentinel-columbine"]),
    snap("2024-col-5", "Collins ran 5 yards for a touchdown.", ["run_inside", "goal_line"], SRC["sentinel-columbine"]),
]
by["2024-11-16-columbine"]["statTags"] = {"run_inside": 8, "run_edge": 4, "run_explosive": 3, "pass_short": 6, "pass_explosive": 3, "play_action": 4, "goal_line": 7, "third_long": 5}
by["2026-08-28-arvada-west"]["plays"] = [
    snap("2026-aw-77", "Owen Newell ran 77 yards for a touchdown.", ["run_explosive"], SRC["copreps-2026"]),
    snap("2026-aw-1", "Newell ran 1 yard for a touchdown.", ["run_inside", "goal_line"], SRC["copreps-2026"]),
    snap("2026-aw-seam", "Evan Smith caught a 29-yard seam touchdown from Bryson Peterson.", ["pass_explosive"], SRC["copreps-2026"]),
    snap("2026-aw-stan", "Peterson found Stanley to finish an 86-yard drive. The throw length was not published. Colorado Preps spelled the name Rayshon Stanley. MaxPreps lists RJ Stanley.", [], SRC["copreps-2026"]),
    snap("2026-aw-29", "Stanley caught a 29-yard touchdown from Peterson.", ["pass_explosive"], SRC["copreps-2026"]),
]
by["2026-08-28-arvada-west"]["special"] = [
    {"id": "2026-aw-pick", "text": "Camden Carter returned an interception for a touchdown.", "source": SRC["copreps-2026"]},
    {"id": "2026-aw-safeties", "text": "Two safeties, one on special teams and one on defense.", "source": SRC["copreps-2026"]},
]
by["2026-09-03-smoky-hill"]["plays"] = [
    snap("2026-sh-smith", "Evan Smith had 79 receiving yards and 3 receiving touchdowns. Catch lengths were not published.", ["pass_explosive"], SRC["sentinel-smoky-2026"], "highlight"),
    snap("2026-sh-mitch", "Jordan Mitchell caught a touchdown. The length was not published.", ["pass_explosive"], SRC["sentinel-smoky-2026"], "highlight"),
    snap("2026-sh-garza", "Jace Garza caught a touchdown. The length was not published.", ["pass_explosive"], SRC["sentinel-smoky-2026"], "highlight"),
    snap("2026-sh-newell", "Owen Newell rushed for 66 yards and a touchdown.", ["run_inside"], SRC["sentinel-smoky-2026"], "highlight"),
    snap("2026-sh-harris", "Dorian Harris rushed for 64 yards and a touchdown.", ["run_inside"], SRC["sentinel-smoky-2026"], "highlight"),
]
by["2026-09-03-smoky-hill"]["special"] = [
    {"id": "2026-sh-fumble", "text": "Cristian Mbamarah returned a fumble for a touchdown.", "source": SRC["sentinel-smoky-2026"]}
]
by["2026-09-10-rocky-mountain"]["statTags"] = {"run_inside": 3, "run_edge": 3, "run_explosive": 3, "pass_short": 5, "pass_explosive": 9, "play_action": 4, "goal_line": 2, "third_long": 6}
by["2026-09-10-rocky-mountain"]["note"] = "Team line published by MaxPreps: 318 passing yards, 318 receiving yards, 108 rushing yards. Not a snap list."
by["2026-09-17-eaglecrest"]["statTags"] = {"run_inside": 5, "run_edge": 4, "run_explosive": 4, "pass_short": 5, "pass_explosive": 7, "play_action": 4, "goal_line": 3, "third_long": 5}
by["2026-09-17-eaglecrest"]["note"] = "Team line: 210 passing yards and 167 rushing yards. Not a snap list."
by["2026-09-24-cherry-creek"]["statTags"] = {"run_inside": 3, "run_edge": 3, "run_explosive": 4, "pass_short": 5, "pass_explosive": 9, "play_action": 4, "goal_line": 2, "third_long": 6}
by["2026-09-24-cherry-creek"]["note"] = "RJ Stanley had 110 receiving yards, 2 touchdowns, and a long of 82. Peterson threw for 214 yards, 2 touchdowns, and 1 interception. The team rushed for 87 yards."
by["2026-09-24-cherry-creek"]["plays"] = [
    snap("2026-cc-stan", "RJ Stanley caught 2 touchdowns, with a long of 82 yards, for 110 receiving yards.", ["pass_explosive"], "MaxPreps recap, Sept. 24, 2026", "highlight")
]
by["2026-10-02-mullen"]["statTags"] = {"run_inside": 2, "run_edge": 2, "run_explosive": 2, "pass_short": 5, "pass_explosive": 10, "play_action": 4, "goal_line": 2, "third_long": 7}
by["2026-10-02-mullen"]["note"] = "Team line: 383 passing yards and 63 rushing yards. Not a snap list."

CITY_SRC = "MaxPreps Colorado football school directory"
LEAGUE_SRC = "Coloradoan, Aug. 20, 2026"
SENTINEL_W2 = "Sentinel Colorado, Sept. 5, 2026"

def team(name, city, league="", facts=None, city_source=CITY_SRC, league_source=""):
    return {
        "name": name,
        "city": city,
        "citySource": city_source,
        "league": league,
        "leagueSource": (league_source or LEAGUE_SRC) if league else "",
        "facts": facts or [],
    }

def fact(text, source):
    return {"text": text, "source": source}

TEAMS = [
    team("Arapahoe", "Centennial", "5A Centennial"),
    team("Arvada West", "Arvada", "5A Southwest Metro"),
    team("Castle View", "Castle Rock", "5A Southern"),
    team("Chaparral", "Parker", "4A South Metro", [
        fact("MaxPreps listed Chaparral in the 4A South Metro league on the schedule page updated Oct. 11, 2026.", "MaxPreps Chaparral schedule, updated Oct. 11, 2026"),
    ], league_source="MaxPreps Chaparral schedule, updated Oct. 11, 2026"),
    team("Chatfield", "Littleton", "5A Southwest Metro"),
    team("Cherry Creek", "Greenwood Village", "5A Centennial"),
    team("Columbine", "Littleton", "5A Southwest Metro", [
        fact("The Coloradoan marked Columbine as a 4A school placed inside the 5A Southwest Metro League for 2026.", LEAGUE_SRC),
    ]),
    team("Dakota Ridge", "Littleton"),
    team("Denver East", "Denver"),
    team("Eaglecrest", "Centennial", "5A Centennial", [
        fact("Sentinel Colorado printed Eaglecrest 17, Erie 14 on Sept. 4, 2026, with passing touchdowns by William Brinkman and Cash Conley.", SENTINEL_W2),
    ]),
    team("Fairview", "Boulder", "5A Front Range"),
    team("Far Northeast Warriors", "Denver", city_source="MaxPreps Far Northeast Warriors school page"),
    team("Fort Collins", "Fort Collins", "5A Front Range"),
    team("Fossil Ridge", "Fort Collins", "5A Front Range"),
    team("Fountain-Fort Carson", "Fountain", "5A Southern"),
    team("Grandview", "Aurora", "5A Centennial", [
        fact("Sentinel Colorado printed Grandview 45, Fountain-Fort Carson 17 on Sept. 4, 2026. Lander Wise had 131 passing yards and a rushing touchdown. Timothy Arnold III had 103 receiving yards and a receiving touchdown.", SENTINEL_W2),
    ]),
    team("Highlands Ranch", "Highlands Ranch"),
    team("Hinkley", "Aurora", facts=[
        fact("Sentinel Colorado printed Kennedy 69, Hinkley 0 on Sept. 5, 2026.", SENTINEL_W2),
    ]),
    team("Horizon", "Thornton"),
    team("Lakewood", "Lakewood"),
    team("Mountain Vista", "Highlands Ranch", "5A Southwest Metro"),
    team("Mullen", "Denver", "5A Centennial"),
    team("Northfield", "Denver", "5A Centennial"),
    team("Overland", "Aurora", facts=[
        fact("Sentinel Colorado printed Vista Ridge 48, Overland 6 on Sept. 4, 2026, with a receiving touchdown by Josiah West.", SENTINEL_W2),
    ]),
    team("Pine Creek", "Colorado Springs", "5A Southern"),
    team("Pomona", "Arvada"),
    team("Poudre", "Fort Collins"),
    team("Ralston Valley", "Arvada", "5A Southwest Metro"),
    team("Rock Canyon", "Highlands Ranch", "5A Southwest Metro"),
    team("Rocky Mountain", "Fort Collins", "5A Front Range"),
    team("Smoky Hill", "Aurora", "4A South Metro", [
        fact("MaxPreps listed Smoky Hill in the 4A South Metro league on the schedule page updated Oct. 10, 2026.", "MaxPreps Smoky Hill schedule, updated Oct. 10, 2026"),
    ], league_source="MaxPreps Smoky Hill schedule, updated Oct. 10, 2026"),
    team("ThunderRidge", "Highlands Ranch", "5A Southwest Metro"),
    team("Valor Christian", "Highlands Ranch", "5A Southern"),
]

team_names = {row["name"] for row in TEAMS}
opponent_names = {row["opponent"] for row in games}
if team_names != opponent_names:
    raise SystemExit("Team cards do not match opponents: %s" % sorted(team_names.symmetric_difference(opponent_names)))

# Point audit
for season in SEASONS:
    pf = pa = 0
    for g in games:
        if g["season"] != season["id"] or g.get("upcoming") or g.get("countInPoints") is False:
            continue
        pf += g["ctScore"]
        pa += g["oppScore"]
    if pf != season["pf"] or pa != season["pa"]:
        raise SystemExit("Point mismatch %s: got %s-%s expected %s-%s" % (season["id"], pf, pa, season["pf"], season["pa"]))

# Championship page. Not games. These lines are not tagged snaps and do not change a Friday call.
HIST = "Sentinel Colorado playoff history, updated Nov. 7, 2025"
SENTINEL_FINAL = "Sentinel Colorado, Dec. 1, 2012"
POST_FINAL = "Denver Post, Dec. 1, 2012"

def history_year(year, klass, text, bracket=""):
    return {"year": year, "klass": klass, "bracket": bracket, "text": text, "source": HIST}

TITLES = {
    "source": HIST,
    "summary": "Championship games: 1 (2012); Semifinals: 4 (2007, 2010, 2012, 2013); Quarterfinals: 5 (2007, 2010, 2012, 2013, 2020); Playoff appearances: 20.",
    "final": {
        "date": "2012-12-01",
        "round": "Class 5A championship",
        "place": "Sports Authority Field at Mile High",
        "result": "Valor Christian 9, Cherokee Trail 0",
        "coach": "Monte Thelen",
        "source": SENTINEL_FINAL,
        "lines": [
            fact("Quarters: Cherokee Trail 0-0-0-0, Valor Christian 0-0-0-9.", SENTINEL_FINAL),
            fact("Scoring: Christian McCaffrey 1-yard run, kick failed, 6:01 left. Spencer 38-yard field goal, 2:04 left.", SENTINEL_FINAL),
            fact("The box header ranked Valor Christian fourth and Cherokee Trail third.", SENTINEL_FINAL),
            fact("Coach Monte Thelen. Sentinel called it the best season in school history and the first state championship game in the 10th season of the school's existence.", SENTINEL_FINAL),
            fact("Anthony Murray intercepted Luke Del Rio's first pass. The first half was scoreless.", SENTINEL_FINAL),
            fact("Ryan Winke's 43-yard field goal was blocked. Spencer missed a 30-yard attempt.", SENTINEL_FINAL),
            fact("Cherokee Trail held Valor scoreless in the first half. Sentinel said that was Valor's first scoreless half since the 2010 opener against Grandview.", SENTINEL_FINAL),
            fact("McCaffrey's punt on the last play of the third quarter was downed at the Cherokee Trail 1. Thelen called that the play of the game.", SENTINEL_FINAL),
            fact("Cherokee Trail gained 4 yards on three rushes and punted. Aric Johnson's kick went out of the end zone to the Cherokee Trail 39.", SENTINEL_FINAL),
            fact("Del Rio hit Chaz Polson at the 1 on third-and-12. Evan White stopped McCaffrey twice. The touchdown was awarded on the third try. Cherokee Trail said the knee was down.", SENTINEL_FINAL),
            fact("Spencer missed the extra point. Valor led 6-0 with 6:01 left.", SENTINEL_FINAL),
            fact("On third-and-2, McCaffrey dropped Cameron Smith for a 3-yard loss. On fourth down, Johnson scrambled and was tackled short.", SENTINEL_FINAL),
            fact("A facemask on Jackson Burrell's tackle of McCaffrey was marked 10 yards in the backfield. Spencer made a 38-yard field goal. Valor led 9-0 with 2:04 left.", SENTINEL_FINAL),
            fact("Cherokee Trail never reached Valor's red zone and missed another fourth down.", SENTINEL_FINAL),
            fact("Rushing, Cherokee Trail: Cameron Smith 14-25, Sherman Thomas 6-15, Dante Deleonardis 1-4, Cameron Currington 1-2.", SENTINEL_FINAL),
            fact("Rushing, Valor Christian: Christian McCaffrey 28-126, Daryl Hawkins 8-46, Luke Del Rio 1 for minus 3.", SENTINEL_FINAL),
            fact("Passing, Cherokee Trail: Aric Johnson 6-13, 91 yards.", SENTINEL_FINAL),
            fact("Passing, Valor Christian: Luke Del Rio 12-20, 89 yards, 1 interception.", SENTINEL_FINAL),
            fact("Receiving, Cherokee Trail: Brandon Barry 2-44, Cameron Smith 2-20, Anthony Murray 1-17, Dominique Grady 1-10.", SENTINEL_FINAL),
            fact("Receiving, Valor Christian: Chaz Polson 5-42, Christian McCaffrey 4-32, Alex Ulmer 1-10, Levi Waddell 1-5.", SENTINEL_FINAL),
            fact("Cherokee Trail was 12-2. Denver Post called this the first final in its nine-year varsity history.", POST_FINAL),
            fact("Cherokee Trail had 134 yards of offense.", POST_FINAL),
            fact("Christian McCaffrey was held to 114 rushing yards and 52 receiving yards.", POST_FINAL),
            fact("The game MVP punt was 46 yards and was downed at the Cherokee Trail 1.", POST_FINAL),
            fact("Valor Christian was 12-2. Denver Post called this its fourth consecutive title, after 3A in 2009 and 4A twice.", POST_FINAL),
            fact("This was the lowest-scoring championship since Chatfield beat Fairview 9-3 in 2001.", POST_FINAL),
        ],
    },
    "gaps": [
        fact("This list has no 2017 line. Sentinel Colorado did not print a 2017 playoff game.", HIST),
    ],
    "years": [
        history_year(2025, "5A", "Fountain-Fort Carson 14, Cherokee Trail 7 (1st round)", "24 teams"),
        history_year(2024, "5A", "Cherokee Trail 25, Castle View 7; Columbine 42, Cherokee Trail 21 (2nd round)", "24 teams"),
        history_year(2023, "5A", "Chatfield 42, Cherokee Trail 7 (1st round)", "24 teams"),
        history_year(2022, "5A", "Rock Canyon 23, Cherokee Trail 20 (1st round)", "24 teams"),
        history_year(2021, "5A", "Cherokee Trail 41, Fossil Ridge 29; Ralston Valley 42, Cherokee Trail 20 (2nd round)", "24 teams"),
        history_year(2020, "5A", "Valor Christian 49, Cherokee Trail 14 (quarterfinals)", "8 teams"),
        history_year(2019, "5A", "Cherokee Trail 35, Fountain-Fort Carson 17; Fairview 36, Cherokee Trail 34 (2nd round)"),
        history_year(2018, "5A", "Cherokee Trail 37, Poudre 31 (2OT); Ralston Valley 52, Cherokee Trail 0 (2nd round)"),
        history_year(2016, "5A", "Valor Christian 42, Cherokee Trail 0 (1st round)"),
        history_year(2015, "5A", "Heritage 19, Cherokee Trail 14 (1st round)"),
        history_year(2014, "5A", "Mountain Vista 24, Cherokee Trail 14 (1st round)"),
        history_year(2013, "5A", "Cherokee Trail 40, Arvada West 21; Cherokee Trail 41, Mountain Range 7; Cherokee Trail 27, Cherry Creek 14; Valor Christian 42, Cherokee Trail 23 (semifinals)"),
        history_year(2012, "5A", "Cherokee Trail 42, Mountain Range 6; Cherokee Trail 24, Mullen 21; Cherokee Trail 17, Eaglecrest 14; Cherokee Trail 17, Ralston Valley 10; Valor Christian 9, Cherokee Trail 0 (championship game)"),
        history_year(2011, "5A", "Fountain-Fort Carson 31, Cherokee Trail 21 (1st round)"),
        history_year(2010, "5A", "Cherokee Trail 27, Legacy 14; Cherokee Trail 27, ThunderRidge 6; Cherokee Trail 14, Ralston Valley 2; Regis Jesuit 21, Cherokee Trail 20 (semifinals)"),
        history_year(2009, "5A", "Cherokee Trail 14, Rocky Mountain 9; Pomona 28, Cherokee Trail 7 (2nd round)"),
        history_year(2008, "5A", "Cherokee Trail 28, Mountain Vista 6; Rocky Mountain 28, Cherokee Trail 14 (2nd round)"),
        history_year(2007, "4A", "Cherokee Trail 15, Pine Creek 14; Cherokee Trail 10, Air Academy 0; Monarch 6, Cherokee Trail 0 (OT) (semifinals)"),
        history_year(2006, "4A", "Montrose 23, Cherokee Trail 21 (1st round)"),
        history_year(2005, "3A", "Eagle Valley 31, Cherokee Trail 14 (1st round)"),
    ],
}

if len(TITLES["years"]) != 20:
    raise SystemExit("Playoff history must list 20 years")
if any(row["year"] == 2017 for row in TITLES["years"]):
    raise SystemExit("2017 is not in the Sentinel playoff history")
if TITLES["final"]["result"] != "Valor Christian 9, Cherokee Trail 0":
    raise SystemExit("Championship result changed")

book = {
    "meta": {
        "name": "Cougar Defense",
        "version": "1.0.0",
        "built": "2026-10-17",
        "for": "Defensive coordinator",
        "school": "Cherokee Trail High School",
        "mascot": "Cougars",
        "city": "Aurora, Colorado",
        "class": "CHSAA Class 5A",
        "tenYear": "2016-2025",
        "standalone": True,
        "note": "This program runs on your computer. It is not part of a website. It is not affiliated with Cherokee Trail High School or CHSAA.",
    },
    "coaches": [
        {"id": "mangnall", "name": "Dain Mangnall", "years": "2016", "note": "Head coach in 2016. The offense was run-first. MaxPreps listed him as an assistant on the 2026 staff page."},
        {"id": "johnson", "name": "Joe Johnson", "years": "2017-2021, 2024-2025", "note": "Hired in 2017 from ThunderRidge. Stepped down after 2021. MaxPreps lists him as head coach again in 2024 and 2025."},
        {"id": "jajczyk", "name": "Justin Jajczyk", "years": "2022-2023, and the 2026 staff page", "note": "Offensive coordinator under Johnson, then head coach in 2022 and 2023. The October 2026 MaxPreps staff list names him head coach. The history page left that coach field blank."},
    ],
    "seasons": SEASONS,
    "calls": CALLS,
    "offense": OFFENSE,
    "games": games,
    "teams": TEAMS,
    "titles": TITLES,
}

out = ROOT / "book.js"
out.write_text("var COUGAR_BOOK = " + json.dumps(book, indent=2) + ";\n", encoding="utf-8")
print("games", len(games), "seasons", len(SEASONS), "bytes", out.stat().st_size)
