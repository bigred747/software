const CACHE_NAME = "harbor-2026-11-13d";
const STORAGE_KEY = "harbor.v1";

const FALLBACK_EMERGENCY = [
  "The pain is so bad you cannot sit still or find a position that eases it.",
  "You keep vomiting or cannot keep water down.",
  "You have a fever, trouble breathing, a racing heart, or you feel faint.",
  "Your skin or eyes look yellow.",
  "You vomit blood, or your stool is black or bloody.",
  "You have chest pain or pressure."
];

const FILTERS = [
  ["all", "All"],
  ["easy", "No cooking"],
  ["sip", "Sips"],
  ["soft", "Soft"],
  ["plate", "Plates"],
  ["calm", "Calmer"],
  ["mine", "Works for me"],
  ["shop", "Shop"],
  ["skip", "Skip"]
];

const GROUP_LABEL = {
  sip: "Sips, if drinks stay down",
  soft: "Soft foods",
  plate: "Small plates",
  calm: "As the flare calms",
  avoid: "Leave these alone during a flare"
};

const BOTHERS = [
  ["dairy", "Dairy"],
  ["eggs", "Eggs"],
  ["fish", "Fish"],
  ["shellfish", "Shellfish"],
  ["soy", "Soy"]
];

let pack = null;
let packOk = false;
let packProblem = "";
let checks = [];
let foodQuery = "";
let foodFilter = "all";
let lastScreen = "";
let breathTimer = null;
let currentUtterance = null;
let eraseArmed = false;
let gateIndex = 0;
let gateDanger = false;
let clockTimer = null;

const GATE = [
  { ask: "Can you keep a sip of water down?", safe: "yes" },
  { ask: "Can you find a position that eases the pain, even a little?", safe: "yes" },
  { ask: "Do you have a fever, trouble breathing, faintness, yellow skin, blood, or chest pain?", safe: "no" }
];

const state = loadState();

function loadState() {
  const blank = {
    onboarded: false,
    name: "",
    doctorName: "",
    doctorPhone: "",
    personName: "",
    personPhone: "",
    flarePlan: "",
    medicines: "",
    enzymes: false,
    theme: "auto",
    big: false,
    emergencyNumber: "911",
    allergies: "",
    nursePhone: "",
    pharmacyName: "",
    pharmacyPhone: "",
    painPlace: "",
    flareStart: 0,
    installHintSeen: false,
    bothers: [],
    sat: {},
    shop: {},
    sips: { date: "", count: 0 },
    flares: [],
    eaten: []
  };
  try {
    const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
    if (!saved || typeof saved !== "object") return blank;
    return {
      ...blank,
      ...saved,
      bothers: saved.bothers || [],
      sat: saved.sat || {},
      shop: saved.shop || {},
      flares: saved.flares || [],
      eaten: saved.eaten || []
    };
  } catch (error) {
    return blank;
  }
}

function saveState() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function h(tag, attrs, children) {
  const el = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs || {})) {
    if (value == null || value === false) continue;
    if (key === "class") el.className = value;
    else if (key.startsWith("on") && typeof value === "function") el.addEventListener(key.slice(2).toLowerCase(), value);
    else el.setAttribute(key, String(value));
  }
  for (const child of [].concat(children || [])) {
    if (child == null || child === false) continue;
    el.append(child.nodeType ? child : document.createTextNode(String(child)));
  }
  return el;
}

function icon(paths) {
  const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("aria-hidden", "true");
  svg.classList.add("ico");
  for (const d of paths) {
    const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
    path.setAttribute("d", d);
    path.setAttribute("fill", "none");
    path.setAttribute("stroke", "currentColor");
    path.setAttribute("stroke-width", "1.8");
    path.setAttribute("stroke-linecap", "round");
    path.setAttribute("stroke-linejoin", "round");
    svg.append(path);
  }
  return svg;
}

const ICONS = {
  now: ["M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18", "M12 7v5l3 2"],
  flare: ["M12 3l8 18H4L12 3z", "M12 10v4", "M12 17h.01"],
  foods: ["M4 11h16", "M6 11c0 5 2.5 8 6 8s6-3 6-8", "M8 11V7a4 4 0 0 1 8 0v4"],
  guide: ["M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 0-3 3V4z", "M8 4v16"],
  signals: ["M5 7h.01", "M9 7h10", "M5 12h.01", "M9 12h10", "M5 17h.01", "M9 17h10"]
};

function route() {
  const raw = decodeURIComponent((location.hash || "").replace(/^#/, ""));
  const [name, extra] = raw.split("/");
  if (name === "food") return { name: "food", id: extra || "" };
  if (name === "foods") return { name: "foods", filter: extra || "all" };
  if (name === "flare" || name === "guide" || name === "signals" || name === "card") return { name };
  return { name: "now" };
}

function go(hash) {
  if (location.hash === hash) render();
  else location.hash = hash;
}

function digitsTel(raw) {
  const cleaned = String(raw || "").replace(/[^\d+*#]/g, "");
  return /^[0-9+*#]{3,16}$/.test(cleaned) ? cleaned : "";
}

function telHref(raw) {
  return "tel:" + (digitsTel(raw) || "911");
}

function callLabel() {
  return "Call " + (digitsTel(state.emergencyNumber) || "911");
}

function todayKey() {
  const now = new Date();
  return now.getFullYear() + "-" + (now.getMonth() + 1) + "-" + now.getDate();
}

function sipCount() {
  const key = todayKey();
  if (!state.sips || state.sips.date !== key) state.sips = { date: key, count: 0 };
  return state.sips.count;
}

function applyChrome() {
  const choice = state.theme || "auto";
  const systemDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  const dark = choice === "dark" || (choice === "auto" && systemDark);
  document.documentElement.dataset.theme = dark ? "dark" : "light";
  document.documentElement.dataset.size = state.big ? "big" : "normal";
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute("content", dark ? "#101411" : "#f3f0e8");
}

function overall() {
  if (!checks.length) return "wait";
  if (checks.some((item) => item.state === "stop")) return "stop";
  if (checks.some((item) => item.state === "wait")) return "wait";
  return "go";
}

function statusWord() {
  const level = overall();
  if (level === "go") return "Ready";
  if (level === "stop") return "Not ready";
  return "Check";
}

function announce(text) {
  const live = document.getElementById("live");
  if (live && live.textContent !== text) live.textContent = text;
}

function paintDot() {
  const level = overall();
  const header = document.querySelector(".header");
  if (header) header.className = "header " + level;
  const lamp = document.querySelector("[data-dot]");
  if (lamp) lamp.className = "lamp " + level;
  const word = document.querySelector("[data-status]");
  if (word) word.textContent = statusWord();
  const btn = document.querySelector(".status-btn");
  if (btn) btn.setAttribute("aria-label", "App signals: " + statusWord());
  announce(statusWord() === "Ready" ? "All signals are green. Harbor is ready." : "Harbor status: " + statusWord());
}

function shell(screen, node) {
  const level = overall();
  const active = screen === "food" ? "foods" : screen;
  const header = h("header", { class: "header " + level }, [
    h("div", { class: "brand" }, [
      h("div", { class: "wordmark" }, "Harbor"),
      h("button", {
        class: "status-btn",
        type: "button",
        "aria-label": "App signals: " + statusWord(),
        onclick: () => go("#signals")
      }, [
        h("span", { class: "lamp " + level, "data-dot": "1" }),
        h("span", { "data-status": "1" }, statusWord())
      ])
    ]),
    h("a", { class: "emergency-link", href: telHref(state.emergencyNumber) }, callLabel())
  ]);
  const main = h("main", { class: "main", id: "main" }, [node]);
  const tabs = h("nav", { class: "tabs", "aria-label": "Sections" }, [
    tab("now", "Now", active),
    tab("flare", "Flare", active),
    tab("foods", "Foods", active),
    tab("guide", "Guide", active),
    tab("signals", "Signals", active)
  ]);
  return [header, main, h("div", { class: "tabs-wrap" }, [tabs])];
}

function tab(id, label, active) {
  return h("button", {
    type: "button",
    "aria-current": active === id ? "page" : null,
    onclick: () => go("#" + id)
  }, [icon(ICONS[id]), label]);
}

function render() {
  stopBreath();
  applyChrome();
  const where = route();
  if (where.name !== lastScreen && window.speechSynthesis) window.speechSynthesis.cancel();
  const root = document.getElementById("root");
  let node;
  if (!state.onboarded && where.name === "now") node = screenOnboard();
  else if (where.name === "flare") node = screenFlare();
  else if (where.name === "foods") node = screenFoods(where.filter || "all");
  else if (where.name === "food") node = screenFood(where.id);
  else if (where.name === "guide") node = screenGuide();
  else if (where.name === "signals") node = screenSignals();
  else if (where.name === "card") node = screenCard();
  else node = screenNow();
  const screenName = !state.onboarded && where.name === "now" ? "onboard" : where.name;
  root.replaceChildren(...shell(screenName === "onboard" ? "now" : screenName, node));
  const header = document.querySelector(".header");
  if (header) document.documentElement.style.setProperty("--header-h", header.offsetHeight + "px");
  if (where.name === "foods" && (state.onboarded || where.name !== "now")) paintFoodList();
  if (screenName !== lastScreen) {
    lastScreen = screenName;
    window.scrollTo(0, 0);
    const title = document.querySelector("#main h1");
    if (title) {
      title.tabIndex = -1;
      title.focus({ preventScroll: true });
    }
    window.scrollTo(0, 0);
  }
  paintDot();
  armClock();
}

function screenOnboard() {
  return h("div", {}, [
    h("p", { class: "eyebrow" }, "For chronic pancreatitis"),
    h("h1", {}, "A calm place for hard days"),
    h("p", { class: "lead" }, "Harbor is not a doctor. It will not decide if you are safe. If the pain is severe, get emergency care."),
    h("div", { class: "card" }, [
      h("p", {}, "The Flare tab asks three yes-or-no questions, then shows three steps in large type."),
      h("p", {}, "The Foods tab is a long list for a flare. The easiest ones need no cooking."),
      h("p", {}, "Your clinician's own words can be saved on this phone. They are not sent anywhere."),
      h("p", {}, "Green means the app itself is working. It does not mean a food or a symptom is safe.")
    ]),
    h("div", { class: "stack" }, [
      h("button", { class: "button solid", type: "button", onclick: () => { state.onboarded = true; saveState(); render(); } }, "I understand"),
      h("a", { class: "button danger", href: telHref(state.emergencyNumber) }, callLabel())
    ])
  ]);
}

function screenNow() {
  const mode = state.mode || "ask";
  const hello = state.name ? "Hello, " + state.name : "Hello";
  const parts = [
    h("p", { class: "eyebrow" }, hello),
    h("h1", {}, "How is your body?")
  ];
  if (state.flareStart) {
    parts.push(h("button", { class: "choice flare", type: "button", onclick: () => go("#flare") }, [
      h("strong", {}, "Flare clock is running"),
      h("span", { id: "now-clock" }, formatElapsed(Date.now() - Number(state.flareStart)))
    ]));
  }
  if (!installed() && !state.installHintSeen) parts.push(installCard());
  parts.push(h("div", { class: "stack" }, [
      choice("steady", "Steady", "I can eat and move", () => setMode("steady")),
      choice("sore", "Sore", "Food hurts. The pain is there.", () => setMode("sore")),
      choice("flare", "Flare", "This is the bad pain.", () => go("#flare"))
    ]));
  if (mode === "steady") parts.push(steadyPanel());
  if (mode === "sore") parts.push(sorePanel());
  parts.push(sipCard());
  if (!state.flarePlan) {
    parts.push(h("div", { class: "card" }, [
      h("h2", {}, "Before the next flare"),
      h("p", {}, "On a calm day, save the words your clinician already gave you. Harbor will show them in large type when the pain is bad."),
      h("button", { class: "button quiet", type: "button", onclick: () => go("#signals") }, "Save my plan")
    ]));
  }
  return h("div", {}, parts);
}

function choice(kind, title, text, action) {
  return h("button", { class: "choice " + kind, type: "button", onclick: action }, [
    h("strong", {}, title),
    h("span", {}, text)
  ]);
}

function setMode(mode) {
  state.mode = mode;
  saveState();
  render();
}

function steadyPanel() {
  const list = h("ul", { class: "rules" });
  for (const rule of (packOk ? pack.steadyRules : [])) {
    const item = document.createElement("li");
    item.textContent = rule;
    list.append(item);
  }
  const card = h("div", { class: "card" }, [
    h("h2", {}, "A steady day"),
    packOk ? list : h("p", {}, "The guide is not ready, so the daily rules are hidden. Emergency help still works.")
  ]);
  if (packOk) {
    const ideas = pack.foods.filter((food) => food.group === "calm" || food.group === "plate");
    const pick = ideas[Math.floor(Date.now() / 86400000) % ideas.length];
    card.append(
      h("p", {}, "One small idea for today: " + pick.name + "."),
      h("button", { class: "button quiet", type: "button", onclick: () => go("#food/" + pick.id) }, "Open that food")
    );
  }
  return card;
}

function sorePanel() {
  const wrap = h("div", { class: "card" }, [
    h("h2", {}, "A sore day"),
    h("p", {}, "Sit up. Keep the meal small. If this becomes pain you cannot sit with, open Flare."),
    h("button", { class: "button solid", type: "button", onclick: () => go("#foods/easy") }, "Foods that need no cooking")
  ]);
  const known = packOk ? pack.foods.filter((food) => state.sat[food.id] === "well" && food.group !== "avoid").slice(0, 3) : [];
  for (const food of known) {
    wrap.append(h("button", { class: "button quiet", type: "button", onclick: () => go("#food/" + food.id) }, food.name));
  }
  return wrap;
}

function installed() {
  return window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
}

function installCard() {
  return h("div", { class: "card" }, [
    h("h2", {}, "Put Harbor on this iPhone"),
    h("p", {}, "Open this page in Safari. Tap Share, the square with the arrow. Tap Add to Home Screen, then Add. It then opens like an app, full screen."),
    h("div", { class: "row-buttons" }, [
      h("button", { class: "button quiet", type: "button", onclick: () => { state.installHintSeen = true; saveState(); render(); } }, "Not now"),
      h("button", { class: "button solid", type: "button", onclick: () => { state.installHintSeen = true; saveState(); render(); } }, "I added it")
    ])
  ]);
}

function sipCard() {
  const count = sipCount();
  return h("div", { class: "card" }, [
    h("h2", {}, "Sips today"),
    h("p", { class: "lead", id: "sip-count" }, String(count)),
    h("p", { class: "note" }, "This is a counter, not a fluid order. If you are vomiting, do not force drinks."),
    h("button", {
      class: "button quiet",
      type: "button",
      onclick: () => {
        state.sips = { date: todayKey(), count: sipCount() + 1 };
        saveState();
        const node = document.getElementById("sip-count");
        if (node) node.textContent = String(state.sips.count);
      }
    }, "I took a small sip")
  ]);
}

function screenFlare() {
  const items = packOk ? pack.emergency.items : FALLBACK_EMERGENCY;
  const steps = packOk ? pack.flareSteps : [
    { title: "Sit up", text: "Pillows behind you. Knees a little bent. Lying flat can make this pain worse." },
    { title: "Tiny sips", text: "If you are vomiting, do not force food or drink. If water stays down, take small sips." },
    { title: "Only your plan", text: "Take only what your own clinician already told you to take. This app will not give a dose." }
  ];
  const list = h("ul", { class: "plain" });
  for (const item of items) {
    const li = document.createElement("li");
    li.textContent = item;
    list.append(li);
  }
  const stepBox = h("div", {});
  steps.forEach((step, index) => {
    stepBox.append(h("div", { class: "step" }, [
      h("b", {}, String(index + 1)),
      h("div", {}, [h("strong", {}, step.title), h("p", {}, step.text)])
    ]));
  });
  const signs = h("details", { class: "card" }, [
    h("summary", {}, "Every warning sign"),
    list
  ]);
  const passed = gateIndex >= GATE.length || Boolean(state.flareStart);
  const page = [
    h("p", { class: "eyebrow" }, "The bad pain"),
    h("h1", {}, gateDanger ? "Call now" : "First, are you safe?"),
    h("a", { class: "button danger", href: telHref(state.emergencyNumber) }, callLabel())
  ];
  if (gateDanger) {
    page.push(
      h("p", { class: "lead" }, "That answer means call now. Do not wait this out at home."),
      h("button", { class: "button quiet", type: "button", onclick: () => { gateDanger = false; gateIndex = 0; render(); window.scrollTo(0, 0); } }, "Ask me again")
    );
  } else if (!passed) {
    const question = GATE[gateIndex];
    page.push(
      h("p", { class: "lead" }, question.ask),
      h("p", { class: "note" }, "If you cannot choose, use the call button."),
      gateButtons(question.safe)
    );
  } else {
    page.push(
      h("h2", {}, "If you are still here"),
      h("p", {}, "These three steps do not end a flare. They keep the next minutes simpler."),
      stepBox,
      h("div", { class: "stack" }, [
        h("button", { class: "button quiet", type: "button", onclick: () => speak(flareScript(items, steps)) }, "Read the steps aloud"),
        h("button", { class: "button quiet", type: "button", onclick: () => { if (window.speechSynthesis) window.speechSynthesis.cancel(); } }, "Stop reading")
      ]),
      clockCard(),
      planCard(),
      careCard(),
      h("button", { class: "button quiet", type: "button", onclick: () => go("#card") }, "Show my pocket card"),
      h("button", { class: "button solid", type: "button", onclick: () => go("#foods/easy") }, "Foods for this flare")
    );
  }
  page.push(signs, breathCard(), flareLogCard());
  return h("div", {}, page);
}

function gateButtons(safe) {
  const yesSafe = safe === "yes";
  return h("div", { class: "row-buttons" }, [
    h("button", { class: yesSafe ? "button solid" : "button danger", type: "button", onclick: () => answerGate("yes", safe) }, "Yes"),
    h("button", { class: yesSafe ? "button danger" : "button solid", type: "button", onclick: () => answerGate("no", safe) }, "No")
  ]);
}

function answerGate(answer, safe) {
  if (answer === safe) {
    gateIndex += 1;
    gateDanger = false;
  } else {
    gateDanger = true;
  }
  render();
  window.scrollTo(0, 0);
}

function formatElapsed(ms) {
  const total = Math.max(0, Math.floor(Number(ms) / 60000));
  const hours = Math.floor(total / 60);
  const mins = total % 60;
  if (hours <= 0) return total + (total === 1 ? " minute" : " minutes");
  return hours + (hours === 1 ? " hour " : " hours ") + mins + " min";
}

function armClock() {
  if (clockTimer) clearInterval(clockTimer);
  clockTimer = null;
  const tick = () => {
    if (!state.flareStart) return;
    const text = formatElapsed(Date.now() - Number(state.flareStart));
    for (const id of ["flare-clock", "now-clock"]) {
      const node = document.getElementById(id);
      if (node) node.textContent = text;
    }
  };
  tick();
  if (state.flareStart && (document.getElementById("flare-clock") || document.getElementById("now-clock"))) {
    clockTimer = setInterval(tick, 1000);
  }
}

function clockCard() {
  const running = Boolean(state.flareStart);
  const read = h("p", { class: "clock-read", id: "flare-clock" }, running ? formatElapsed(Date.now() - Number(state.flareStart)) : "Not started");
  const button = h("button", { class: "button quiet", type: "button" }, running ? "The pain has eased. Stop the clock." : "The pain started now");
  button.addEventListener("click", () => {
    state.flareStart = running ? 0 : Date.now();
    saveState();
    render();
  });
  return h("div", { class: "card" }, [
    h("h2", {}, "Flare clock"),
    h("p", { class: "note" }, "Doctors ask when it started. This is a timer, not a treatment."),
    read,
    button
  ]);
}

function careCard() {
  const links = [
    callLink(state.doctorPhone, state.doctorName ? "Call " + state.doctorName : "Call my clinician"),
    callLink(state.nursePhone, "Call the nurse line"),
    callLink(state.pharmacyPhone, state.pharmacyName ? "Call " + state.pharmacyName : "Call the pharmacy"),
    callLink(state.personPhone, state.personName ? "Call " + state.personName : "Call my person")
  ].filter(Boolean);
  if (!links.length) {
    return h("div", { class: "card" }, [
      h("h2", {}, "People to call"),
      h("p", {}, "Save a clinician, a nurse line, a pharmacy, or a person on a calm day. The buttons will be here next time."),
      h("button", { class: "button quiet", type: "button", onclick: () => go("#signals") }, "Save numbers")
    ]);
  }
  return h("div", { class: "stack" }, links);
}

function callLink(phone, label) {
  if (!digitsTel(phone)) return null;
  return h("a", { class: "button quiet", href: telHref(phone) }, label);
}

function screenCard() {
  const lines = [
    ["Name", state.name || "Not typed"],
    ["Condition", "Chronic pancreatitis"],
    ["Allergies", state.allergies || "None typed on this phone"],
    ["Medicines typed here", state.medicines || "None typed"],
    ["Clinician", [state.doctorName, state.doctorPhone].filter(Boolean).join(" ") || "Not typed"],
    ["If I am vomiting", "Do not give me food. I may have been told to take nothing by mouth."]
  ];
  const facts = h("div", {});
  for (const [label, value] of lines) {
    const block = h("div", { class: "card" }, [h("p", { class: "eyebrow" }, label), h("p", { class: "lead" })]);
    block.lastChild.textContent = value;
    facts.append(block);
  }
  return h("div", {}, [
    h("p", { class: "eyebrow" }, "Show this to someone helping"),
    h("h1", {}, "Pocket card"),
    h("p", {}, "This is what was typed on this phone. It is not a medical record."),
    facts,
    careCard(),
    h("a", { class: "button danger", href: telHref(state.emergencyNumber) }, callLabel()),
    h("button", { class: "button quiet", type: "button", onclick: () => go("#flare") }, "Back to the flare")
  ]);
}

function flareScript(items, steps) {
  const red = items.join(" ");
  const three = steps.map((step, index) => (index + 1) + ". " + step.title + ". " + step.text).join(" ");
  return "Emergency signs. " + red + " If none of those are happening, three steps. " + three + " These steps do not end a flare.";
}

function speak(text) {
  if (!window.speechSynthesis) return;
  window.speechSynthesis.cancel();
  currentUtterance = new SpeechSynthesisUtterance(text);
  currentUtterance.lang = "en-US";
  currentUtterance.rate = 0.9;
  window.speechSynthesis.speak(currentUtterance);
}

function planCard() {
  const card = h("div", { class: "card" }, [h("h2", {}, "Your clinician's words")]);
  if (state.flarePlan) {
    const plan = h("p", { class: "lead" });
    plan.textContent = state.flarePlan;
    card.append(plan);
  } else {
    card.append(h("p", {}, "Nothing is saved on this phone yet. Harbor will not invent a plan. If the pain is severe and you were not given one, get care."));
  }
  if (state.medicines) {
    const meds = h("p", {});
    meds.textContent = "Medicines you typed: " + state.medicines;
    card.append(meds);
  }
  const box = h("div", { id: "plan-edit" });
  card.append(box);
  card.append(h("button", {
    class: "button quiet",
    type: "button",
    onclick: () => {
      box.replaceChildren(planEditor(() => {
        saveState();
        render();
      }));
    }
  }, state.flarePlan ? "Edit these words" : "Add the words I was given"));
  if (digitsTel(state.doctorPhone)) {
    const who = state.doctorName ? "Call " + state.doctorName : "Call my clinician";
    card.append(h("a", { class: "button quiet", href: telHref(state.doctorPhone), style: "margin-top:10px" }, who));
  }
  return card;
}

function planEditor(done) {
  const plan = h("textarea", { rows: "5" });
  plan.value = state.flarePlan;
  const meds = h("input", { type: "text", autocomplete: "off" });
  meds.value = state.medicines;
  return h("div", {}, [
    h("label", { class: "field" }, [h("span", {}, "What they told you to do in a flare"), plan]),
    h("label", { class: "field" }, [h("span", {}, "Medicines they already prescribed, in your words"), meds]),
    h("button", {
      class: "button solid",
      type: "button",
      onclick: () => {
        state.flarePlan = plan.value.trim();
        state.medicines = meds.value.trim();
        done();
      }
    }, "Save on this phone")
  ]);
}

function personCard() {
  if (!digitsTel(state.personPhone)) {
    return h("div", { class: "card" }, [
      h("h2", {}, "A person to call"),
      h("p", {}, "Save someone on a calm day. The button will be here next time."),
      h("button", { class: "button quiet", type: "button", onclick: () => go("#signals") }, "Save a person")
    ]);
  }
  const label = state.personName ? "Call " + state.personName : "Call my person";
  return h("a", { class: "button quiet", href: telHref(state.personPhone) }, label);
}

function breathCard() {
  const readout = h("div", { class: "breath", id: "breath" }, "Ready when you are");
  let running = false;
  const button = h("button", { class: "button quiet", type: "button" }, "Start a breathing clock");
  button.addEventListener("click", () => {
    if (running) {
      stopBreath();
      running = false;
      readout.textContent = "Stopped";
      button.textContent = "Start a breathing clock";
      return;
    }
    running = true;
    button.textContent = "Stop the clock";
    let phase = "in";
    let left = 4;
    const tick = () => {
      readout.textContent = (phase === "in" ? "Breathe in " : "Breathe out ") + left;
      left -= 1;
      if (left < 0) {
        phase = phase === "in" ? "out" : "in";
        left = phase === "in" ? 4 : 6;
      }
    };
    tick();
    breathTimer = setInterval(tick, 1000);
  });
  return h("div", { class: "card" }, [
    h("h2", {}, "A clock for breathing"),
    h("p", { class: "note" }, "This does not treat pancreatitis. It is a slow count while you sit up."),
    readout,
    button
  ]);
}

function stopBreath() {
  if (breathTimer) clearInterval(breathTimer);
  breathTimer = null;
}

function flareLogCard() {
  const saved = h("p", { id: "flare-saved", class: "note" }, "Optional. You can show this list at a visit.");
  const scores = h("div", { class: "scores" });
  for (let n = 1; n <= 10; n += 1) {
    const button = h("button", { type: "button" }, String(n));
    button.addEventListener("click", () => {
      const minutes = state.flareStart ? Math.max(0, Math.round((Date.now() - Number(state.flareStart)) / 60000)) : null;
      state.flares.unshift({ at: new Date().toISOString(), score: n, place: state.painPlace || "", minutes });
      state.flares = state.flares.slice(0, 40);
      saveState();
      saved.textContent = "Saved on this phone. Pain " + n + " of 10.";
      for (const peer of scores.querySelectorAll("button")) peer.removeAttribute("aria-pressed");
      button.setAttribute("aria-pressed", "true");
    });
    scores.append(button);
  }
  const places = h("div", { class: "stack" });
  for (const place of ["Upper belly", "Through to my back", "Both"]) {
    places.append(h("button", {
      class: "button quiet",
      type: "button",
      "aria-pressed": state.painPlace === place ? "true" : "false",
      onclick: () => { state.painPlace = place; saveState(); render(); }
    }, place));
  }
  return h("div", { class: "card" }, [
    h("h2", {}, "Mark this flare"),
    h("p", {}, "Where is it, then how strong. 1 is milder. 10 is the worst. This is your note, not a diagnosis."),
    places,
    scores,
    saved
  ]);
}

function screenFoods(filter) {
  foodFilter = FILTERS.some(([id]) => id === filter) ? filter : "all";
  const tools = h("div", { class: "food-tools" }, [
    h("input", {
      class: "search",
      type: "search",
      placeholder: "Search foods, like rice or broth",
      "aria-label": "Search foods",
      enterkeyhint: "search",
      autocapitalize: "none",
      autocorrect: "off",
      spellcheck: "false",
      value: foodQuery,
      oninput: (event) => {
        foodQuery = event.target.value;
        paintFoodList();
      }
    }),
    chipRow()
  ]);
  const list = h("div", { id: "food-list" });
  const page = h("div", {}, [
    h("p", { class: "eyebrow" }, packOk ? pack.foods.filter((food) => food.group !== "avoid").length + " foods" : "Foods"),
    h("h1", {}, "Foods for a flare"),
    h("p", { class: "lead" }, "Start at the top. No cooking is fine. These foods do not turn the pain off."),
    h("p", { class: "note" }, "If you are vomiting, do not eat. If your clinician said no food yet, wait."),
    tools,
    list
  ]);
  return page;
}

function chipRow() {
  const row = h("div", { class: "chips", role: "toolbar", "aria-label": "Food groups" });
  for (const [id, label] of FILTERS) {
    row.append(h("button", {
      class: "chip",
      type: "button",
      "aria-pressed": foodFilter === id ? "true" : "false",
      onclick: () => go(id === "all" ? "#foods" : "#foods/" + id)
    }, label));
  }
  return row;
}

function paintFoodList() {
  const list = document.getElementById("food-list");
  if (!list) return;
  if (!packOk) {
    list.replaceChildren(h("div", { class: "card" }, [
      h("h2", {}, "The food list is not ready"),
      h("p", {}, packProblem || "The guide did not pass its seal. Do not use food advice until Signals is green."),
      h("a", { class: "button danger", href: telHref(state.emergencyNumber) }, callLabel())
    ]));
    return;
  }
  if (foodFilter === "shop") {
    list.replaceChildren(shopList());
    return;
  }
  const q = foodQuery.trim().toLowerCase();
  let foods = pack.foods.slice();
  if (!q || foodFilter !== "all") {
    if (foodFilter === "skip") foods = foods.filter((food) => food.group === "avoid");
    else if (foodFilter === "easy") foods = foods.filter((food) => food.group !== "avoid" && food.cook === "none");
    else if (foodFilter === "mine") foods = foods.filter((food) => state.sat[food.id] === "well");
    else if (foodFilter !== "all") foods = foods.filter((food) => food.group === foodFilter);
    else foods = foods.filter((food) => food.group !== "avoid");
  }
  const hidden = [];
  foods = foods.filter((food) => {
    if (food.group === "avoid") return true;
    const blocked = (food.tags || []).some((tag) => state.bothers.includes(tag));
    if (blocked) hidden.push(food);
    return !blocked;
  });
  if (q) {
    foods = foods.filter((food) => {
      const hay = [food.name, food.line, food.find || "", ...(food.tags || [])].join(" ").toLowerCase();
      return hay.includes(q);
    });
  }
  const blocks = [];
  if (hidden.length && foodFilter !== "skip") {
    blocks.push(h("p", { class: "note" }, hidden.length + " foods are hidden because you asked to skip that ingredient."));
  }
  if (!foods.length) {
    blocks.push(h("div", { class: "card" }, [
      h("p", {}, foodFilter === "mine"
        ? "Nothing is marked yet. Open a food and tap “This sat well.” It will wait here for the next flare."
        : "Nothing matches. Clear the search, or try another group.")
    ]));
    list.replaceChildren(...blocks);
    return;
  }
  if (foodFilter === "all" && !q) {
    const start = pack.startHere.map((id) => pack.foods.find((food) => food.id === id)).filter(Boolean)
      .filter((food) => !(food.tags || []).some((tag) => state.bothers.includes(tag)));
    blocks.push(section("Start here", start));
    for (const group of ["sip", "soft", "plate", "calm"]) {
      blocks.push(section(GROUP_LABEL[group], foods.filter((food) => food.group === group && !pack.startHere.includes(food.id))));
    }
    blocks.push(h("button", { class: "button quiet", type: "button", onclick: () => go("#foods/skip") }, "Foods to leave alone during a flare"));
  } else {
    blocks.push(h("p", { class: "note" }, foods.length + (foods.length === 1 ? " food" : " foods")));
    const ordered = foods.slice().sort((a, b) => satRank(a) - satRank(b));
    const box = h("div", {});
    for (const food of ordered) box.append(foodRow(food));
    blocks.push(box);
  }
  list.replaceChildren(...blocks);
}

function satRank(food) {
  if (state.sat[food.id] === "well") return 0;
  if (state.sat[food.id] === "badly") return 2;
  return 1;
}

function section(label, foods) {
  if (!foods.length) return h("div", {});
  const ordered = foods.slice().sort((a, b) => satRank(a) - satRank(b));
  const box = h("div", {}, [h("h2", { class: "section-label" }, label)]);
  for (const food of ordered) box.append(foodRow(food));
  return box;
}

function cookLabel(food) {
  if (food.group === "avoid") return "Skip";
  if (food.cook === "none") return "No cooking";
  if (food.cook === "warm") return "Warm it";
  return "Needs the stove";
}

function foodRow(food) {
  const meta = [cookLabel(food), food.portion].filter(Boolean).join(" · ");
  const sat = state.sat[food.id] === "well" ? " · Sat well for you" : state.sat[food.id] === "badly" ? " · Sat badly last time" : "";
  const name = h("span", { class: "food-name" }, food.name);
  return h("button", { class: "food-row", type: "button", onclick: () => go("#food/" + food.id) }, [
    food.group === "avoid" ? h("span", { class: "skip-word" }, "Skip") : null,
    name,
    h("span", { class: "food-meta" }, meta + sat),
    h("span", { class: "food-line" }, food.line)
  ]);
}

function screenFood(id) {
  const food = packOk && pack.foods.find((item) => item.id === id);
  if (!food) {
    return h("div", {}, [
      h("h1", {}, "That food is not in the guide"),
      h("button", { class: "button quiet", type: "button", onclick: () => go("#foods") }, "Back to foods")
    ]);
  }
  const back = foodFilter === "all" ? "#foods" : "#foods/" + foodFilter;
  const needs = h("ul", { class: "needs" });
  for (const item of food.needs || []) {
    const li = document.createElement("li");
    li.textContent = item;
    needs.append(li);
  }
  const steps = h("div", {});
  (food.steps || []).forEach((step, index) => {
    steps.append(h("div", { class: "step" }, [
      h("b", {}, String(index + 1)),
      h("div", {}, [h("p", {}, step)])
    ]));
  });
  const parts = [
    h("button", { class: "button quiet", type: "button", onclick: () => go(back) }, "Back to the list"),
    h("p", { class: "eyebrow" }, food.group === "avoid" ? "Leave this" : cookLabel(food)),
    h("h1", {}, food.name),
    h("p", { class: "lead" }, food.line),
    h("p", { class: "note" }, [food.portion, food.fat].filter(Boolean).join(" · "))
  ];
  if (food.note) parts.push(h("div", { class: "warn" }, [h("p", {}, food.note)]));
  if (food.needs && food.needs.length) parts.push(h("h2", {}, "You need"), needs);
  if (food.steps && food.steps.length) parts.push(h("h2", {}, "Do this"), steps);
  if (food.enzymes && state.enzymes) {
    parts.push(h("div", { class: "card" }, [
      h("p", {}, "If enzyme pills were prescribed, take them with the first bites, the way you were shown. Do not swallow them with a scorching drink.")
    ]));
  }
  if (food.group !== "avoid") {
    const ate = h("p", { id: "ate-note", class: "note" }, "");
    parts.push(
      h("button", {
        class: "button solid",
        type: "button",
        onclick: () => {
          state.eaten = [{ at: new Date().toISOString(), id: food.id, name: food.name }, ...(state.eaten || [])].slice(0, 40);
          saveState();
          ate.textContent = "Saved. " + food.name + " is on the visit list.";
        }
      }, "I ate this"),
      ate
    );
    parts.push(h("div", { class: "row-buttons" }, [
      h("button", {
        class: "button quiet",
        type: "button",
        "aria-pressed": state.sat[food.id] === "well" ? "true" : "false",
        onclick: () => { state.sat[food.id] = "well"; saveState(); render(); }
      }, "This sat well"),
      h("button", {
        class: "button quiet",
        type: "button",
        "aria-pressed": state.sat[food.id] === "badly" ? "true" : "false",
        onclick: () => { state.sat[food.id] = "badly"; saveState(); render(); }
      }, "This sat badly")
    ]));
    if (state.sat[food.id]) {
      parts.push(h("button", {
        class: "button quiet",
        type: "button",
        onclick: () => { delete state.sat[food.id]; saveState(); render(); }
      }, "Clear that mark"));
    }
  }
  return h("div", {}, parts);
}

function shopList() {
  const box = h("div", {}, [
    h("h2", { class: "section-label" }, "Fill the kitchen"),
    h("p", {}, "Buy these on a calm day, before the next flare. Checked items stay on this phone.")
  ]);
  for (const item of pack.staples) {
    const input = h("input", { type: "checkbox" });
    input.checked = Boolean(state.shop[item.id]);
    input.addEventListener("change", () => {
      state.shop[item.id] = input.checked;
      saveState();
    });
    const label = h("label", { class: "checkline" }, [input, item.name]);
    box.append(label);
  }
  return box;
}

function screenGuide() {
  if (!packOk) {
    return h("div", {}, [
      h("h1", {}, "The guide is not ready"),
      h("p", {}, packProblem || "Advice stays hidden until the seal matches."),
      h("a", { class: "button danger", href: telHref(state.emergencyNumber) }, callLabel())
    ]);
  }
  const page = h("div", {}, [
    h("p", { class: "eyebrow" }, "Pointers"),
    h("h1", {}, "What the clinics say"),
    h("p", { class: "lead" }, "Short notes checked on " + pack.reviewed + " against major clinic pages. If a page and your clinician disagree, follow your clinician. Harbor does not browse the web during a flare.")
  ]);
  for (const guide of pack.guides) {
    const body = h("div", { class: "guide-body" });
    for (const paragraph of guide.body) body.append(h("p", {}, paragraph));
    const links = h("div", {});
    for (const sourceId of guide.sourceIds) {
      const source = pack.sources.find((item) => item.id === sourceId);
      if (!source) continue;
      links.append(h("a", { class: "source-link", href: source.url, target: "_blank", rel: "noopener noreferrer" }, source.name + " — " + source.title));
    }
    const details = h("details", { class: "card" }, [
      h("summary", { class: "guide-card" }, h("h2", {}, guide.title)),
      body,
      links
    ]);
    if (guide.id === "help-now") details.open = true;
    page.append(details);
  }
  const sources = h("div", { class: "card" }, [h("h2", {}, "Pages this guide was checked against")]);
  for (const source of pack.sources) {
    sources.append(h("a", { class: "source-link", href: source.url, target: "_blank", rel: "noopener noreferrer" }, source.name + " — " + source.title));
  }
  sources.append(h("p", { class: "note" }, "NIDDK’s diet page was last reviewed by them in November 2017. The same no-alcohol, small-meal, fluids advice is repeated on newer Mayo, Cleveland Clinic, and National Pancreas Foundation pages. This is not every medical website. It is the set Harbor keeps on the phone."));
  page.append(sources);
  return page;
}

function screenSignals() {
  const level = overall();
  const title = level === "go" ? "All signals are green" : level === "stop" ? "Harbor is not ready" : "One signal needs a look";
  const detail = level === "go"
    ? "The guide is loaded, sealed, and saved on this phone."
    : level === "stop"
      ? "Do not rely on the food list or the advice until the red signal clears. Emergency help still works."
      : "You can still read the guide. Open the row that is not green.";
  const rows = h("div", { id: "signal-list" });
  for (const item of checks) {
    rows.append(h("div", { class: "signal" }, [
      h("span", { class: "lamp " + item.state }),
      h("div", {}, [h("b", {}, item.name), h("span", {}, item.detail)])
    ]));
  }
  return h("div", {}, [
    h("p", { class: "eyebrow" }, "Is the app working?"),
    h("h1", {}, "Signals"),
    h("div", { class: "banner " + level }, [
      h("span", { class: "lamp " + level }),
      h("div", {}, [h("strong", {}, title), h("p", {}, detail)])
    ]),
    rows,
    h("button", { class: "button solid", type: "button", onclick: () => refreshChecks().then(() => render()) }, "Check again"),
    settingsCard(),
    visitCard(),
    h("div", { class: "card" }, [
      h("h2", {}, "Put Harbor on the Home Screen"),
      h("p", {}, "On this iPhone, open Harbor in Safari. Tap the Share button, the square with the arrow. Tap Add to Home Screen, then Add."),
      h("p", { class: "note" }, "Do this on the iPhone that will be used during a flare. Notes stay in that phone’s browser. They are not on a server, and they will not appear on a different phone.")
    ]),
    eraseCard()
  ]);
}

function settingsCard() {
  const name = field("What should Harbor call you?", "text", state.name, "nickname", (value) => { state.name = value; });
  const doctorName = field("Clinician’s name", "text", state.doctorName, "name", (value) => { state.doctorName = value; });
  const doctorPhone = field("Clinician’s phone", "tel", state.doctorPhone, "tel", (value) => { state.doctorPhone = value; });
  const personName = field("A person to call", "text", state.personName, "name", (value) => { state.personName = value; });
  const personPhone = field("Their phone", "tel", state.personPhone, "tel", (value) => { state.personPhone = value; });
  const nursePhone = field("Nurse line", "tel", state.nursePhone, "tel", (value) => { state.nursePhone = value; });
  const pharmacyName = field("Pharmacy name", "text", state.pharmacyName, "organization", (value) => { state.pharmacyName = value; });
  const pharmacyPhone = field("Pharmacy phone", "tel", state.pharmacyPhone, "tel", (value) => { state.pharmacyPhone = value; });
  const allergies = field("Allergies", "text", state.allergies, "off", (value) => { state.allergies = value; });
  const emergency = field("Emergency number", "tel", state.emergencyNumber, "tel", (value) => { state.emergencyNumber = value.trim() || "911"; });
  const plan = h("textarea", { rows: "4" });
  plan.value = state.flarePlan;
  plan.addEventListener("change", () => { state.flarePlan = plan.value.trim(); saveState(); });
  const meds = h("input", { type: "text" });
  meds.value = state.medicines;
  meds.addEventListener("change", () => { state.medicines = meds.value.trim(); saveState(); });
  const enzymes = h("input", { type: "checkbox" });
  enzymes.checked = Boolean(state.enzymes);
  enzymes.addEventListener("change", () => { state.enzymes = enzymes.checked; saveState(); });
  const big = h("input", { type: "checkbox" });
  big.checked = Boolean(state.big);
  big.addEventListener("change", () => { state.big = big.checked; saveState(); applyChrome(); });
  const bothers = h("div", {});
  for (const [id, label] of BOTHERS) {
    const input = h("input", { type: "checkbox" });
    input.checked = state.bothers.includes(id);
    input.addEventListener("change", () => {
      state.bothers = BOTHERS.map(([key]) => key).filter((key) => {
        if (key === id) return input.checked;
        return state.bothers.includes(key);
      });
      saveState();
    });
    bothers.append(h("label", { class: "checkline" }, [input, "Skip " + label.toLowerCase()]));
  }
  return h("div", { class: "card" }, [
    h("h2", {}, "On this phone"),
    name, allergies, doctorName, doctorPhone, nursePhone, pharmacyName, pharmacyPhone, personName, personPhone, emergency,
    h("button", { class: "button quiet", type: "button", onclick: () => go("#card") }, "Open my pocket card"),
    h("label", { class: "field" }, [h("span", {}, "Flare plan, in your clinician’s words"), plan]),
    h("label", { class: "field" }, [h("span", {}, "Medicines they already prescribed"), meds]),
    h("label", { class: "checkline" }, [enzymes, "Enzyme pills were prescribed"]),
    h("label", { class: "checkline" }, [big, "Even bigger type"]),
    h("p", { class: "note" }, "Screen"),
    h("div", { class: "row-buttons" }, [
      themeButton("auto", "Match phone"),
      themeButton("light", "Day"),
      themeButton("dark", "Night")
    ]),
    h("h2", {}, "Hide foods"),
    bothers,
    h("p", { class: "note", id: "saved-note" }, "Changes save on this phone when you leave a box.")
  ]);
}

function field(label, type, value, autocomplete, onchange) {
  const input = h("input", { type, autocomplete });
  input.value = value || "";
  input.addEventListener("change", () => {
    onchange(input.value.trim());
    saveState();
    paintDot();
    const note = document.getElementById("saved-note");
    if (note) note.textContent = "Saved on this phone.";
  });
  return h("label", { class: "field" }, [h("span", {}, label), input]);
}

function themeButton(id, label) {
  return h("button", {
    class: "button quiet",
    type: "button",
    "aria-pressed": (state.theme || "auto") === id ? "true" : "false",
    onclick: () => { state.theme = id; saveState(); render(); }
  }, label);
}

function visitCard() {
  const lines = ["Harbor notes from this phone"];
  if (state.name) lines.push("Name: " + state.name);
  if (state.doctorName || state.doctorPhone) lines.push("Clinician: " + [state.doctorName, state.doctorPhone].filter(Boolean).join(" "));
  if (state.flarePlan) lines.push("Flare plan: " + state.flarePlan);
  if (state.allergies) lines.push("Allergies typed here: " + state.allergies);
  if (state.medicines) lines.push("Medicines typed here: " + state.medicines);
  for (const bite of (state.eaten || []).slice(0, 8)) {
    lines.push(new Date(bite.at).toLocaleString() + " — ate " + bite.name);
  }
  const well = Object.entries(state.sat).filter(([, mark]) => mark === "well").map(([id]) => foodName(id));
  const badly = Object.entries(state.sat).filter(([, mark]) => mark === "badly").map(([id]) => foodName(id));
  if (well.length) lines.push("Foods that sat well: " + well.join(", "));
  if (badly.length) lines.push("Foods that sat badly: " + badly.join(", "));
  for (const flare of state.flares.slice(0, 8)) {
    const extra = [flare.place, flare.minutes == null ? "" : flare.minutes + " min"].filter(Boolean).join(", ");
    lines.push(new Date(flare.at).toLocaleString() + " — pain " + flare.score + " of 10" + (extra ? " (" + extra + ")" : ""));
  }
  lines.push("These are notes typed on the phone. They are not a medical record.");
  const box = h("textarea", { class: "summary", readonly: "true", rows: "8" });
  box.value = lines.join("\n");
  return h("div", { class: "card" }, [
    h("h2", {}, "For the next visit"),
    box,
    h("button", {
      class: "button quiet",
      type: "button",
      onclick: async () => {
        let ok = false;
        try { await navigator.clipboard.writeText(box.value); ok = true; } catch (error) { ok = false; }
        box.focus();
        box.select();
        const live = document.getElementById("live");
        if (live) live.textContent = ok ? "Visit notes copied." : "Select the notes and copy them.";
      }
    }, "Copy the notes")
  ]);
}

function foodName(id) {
  const food = pack && pack.foods.find((item) => item.id === id);
  return food ? food.name : id;
}

function eraseCard() {
  const button = h("button", { class: "button quiet", type: "button" }, "Erase notes on this phone");
  button.addEventListener("click", () => {
    if (!eraseArmed) {
      eraseArmed = true;
      button.textContent = "Tap again to erase";
      setTimeout(() => {
        eraseArmed = false;
        button.textContent = "Erase notes on this phone";
      }, 5000);
      return;
    }
    localStorage.removeItem(STORAGE_KEY);
    const fresh = loadState();
    for (const key of Object.keys(state)) delete state[key];
    Object.assign(state, fresh);
    eraseArmed = false;
    render();
  });
  return h("div", { class: "card" }, [
    h("h2", {}, "Erase"),
    h("p", {}, "This removes the plan, food marks, and flare notes on this phone only."),
    button
  ]);
}

function validate(data) {
  if (!data || !Array.isArray(data.foods) || !data.emergency || !Array.isArray(data.emergency.items)) return ["Guide file is incomplete."];
  const eat = data.foods.filter((food) => food.group !== "avoid");
  if (eat.length < 50) return ["The food list is shorter than it should be."];
  if (data.emergency.items.length < 8) return ["The emergency list is incomplete."];
  if (!Array.isArray(data.flareSteps) || data.flareSteps.length !== 3) return ["The flare steps are incomplete."];
  for (const food of eat) {
    if (!food.steps || food.steps.length < 3 || !food.name) return ["A food card is incomplete."];
  }
  return [];
}

async function sha256(buffer) {
  const digest = await crypto.subtle.digest("SHA-256", buffer);
  return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function loadPack() {
  try {
    const response = await fetch("./content.json");
    if (!response.ok) throw new Error("missing");
    const buffer = await response.arrayBuffer();
    const parsed = JSON.parse(new TextDecoder().decode(buffer));
    const problems = validate(parsed);
    let sealOk = false;
    if (crypto.subtle) {
      const hash = await sha256(buffer);
      const sealResponse = await fetch("./pack-hash.txt");
      const seal = (await sealResponse.text()).trim();
      sealOk = hash === seal;
    }
    pack = parsed;
    if (!crypto.subtle) {
      packOk = problems.length === 0;
      packProblem = packOk ? "" : problems[0];
      return { seal: "wait", detail: "This browser cannot seal-check the guide. The food count still looks complete." };
    }
    packOk = sealOk && problems.length === 0;
    packProblem = !sealOk ? "The guide file does not match its seal." : (problems[0] || "");
    return sealOk
      ? { seal: "go", detail: "The guide matches its seal." }
      : { seal: "stop", detail: "The guide file does not match its seal." };
  } catch (error) {
    pack = null;
    packOk = false;
    packProblem = "The guide did not open. If this is the first visit, you need a connection once.";
    return { seal: "stop", detail: packProblem };
  }
}

function storageWorks() {
  try {
    const key = "harbor.probe";
    localStorage.setItem(key, "1");
    const ok = localStorage.getItem(key) === "1";
    localStorage.removeItem(key);
    return ok;
  } catch (error) {
    return false;
  }
}

async function refreshChecks() {
  const seal = pack ? null : await loadPack();
  const loaded = seal || { seal: packOk ? "go" : "stop", detail: packOk ? "The guide matches its seal." : (packProblem || "The guide is not ready.") };
  const eat = packOk ? pack.foods.filter((food) => food.group !== "avoid").length : 0;
  let offline = { state: "wait", detail: "Offline copy is not on this phone yet. Open Harbor once on Wi-Fi." };
  try {
    if (!("caches" in window)) offline = { state: "wait", detail: "This browser may not keep an offline copy." };
    else {
      const keys = await caches.keys();
      offline = keys.includes(CACHE_NAME)
        ? { state: "go", detail: "An offline copy is on this phone." }
        : offline;
    }
  } catch (error) {
    offline = { state: "wait", detail: "Offline copy could not be checked." };
  }
  const cssOk = getComputedStyle(document.documentElement).getPropertyValue("--ink").trim() !== "";
  const year = new Date().getFullYear();
  const overflow = document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1;
  checks = [
    { id: "css", name: "Screen design", state: cssOk ? "go" : "stop", detail: cssOk ? "The visual design loaded." : "The visual design did not load." },
    { id: "seal", name: "Guide seal", state: loaded.seal, detail: loaded.detail },
    { id: "foods", name: "Flare food list", state: eat >= 50 ? "go" : "stop", detail: eat ? eat + " foods loaded on this phone." : "The food list is not available." },
    { id: "emergency", name: "Emergency list", state: packOk ? "go" : "stop", detail: packOk ? pack.emergency.items.length + " warning signs are in place." : "Using the short backup list only." },
    { id: "storage", name: "Notes on this phone", state: storageWorks() ? "go" : "stop", detail: storageWorks() ? "This phone can save your plan." : "This phone cannot save notes. Write the plan on paper." },
    { id: "number", name: "Emergency number", state: digitsTel(state.emergencyNumber) ? "go" : "wait", detail: digitsTel(state.emergencyNumber) ? "The button can dial " + digitsTel(state.emergencyNumber) + "." : "That number is not dialable. The button will use 911." },
    { id: "clock", name: "Clock", state: year >= 2026 && year < 2100 ? "go" : "wait", detail: year >= 2026 && year < 2100 ? "The phone date looks usable." : "The phone date looks unusual." },
    { id: "fit", name: "Page fit", state: overflow ? "go" : "wait", detail: overflow ? "The page fits the screen width." : "The page is wider than the screen." },
    { id: "offline", name: "Offline copy", state: offline.state, detail: offline.detail },
    { id: "speech", name: "Read aloud", state: "speechSynthesis" in window ? "go" : "wait", detail: "speechSynthesis" in window ? "The flare steps can be read aloud." : "Read-aloud is not on this browser. The steps are still on screen." }
  ];
  paintDot();
  return checks;
}

function registerSw() {
  if (!("serviceWorker" in navigator)) return;
  navigator.serviceWorker.register("./sw.js").then(() => navigator.serviceWorker.ready).then(() => {
    refreshChecks();
  }).catch(() => {});
}

applyChrome();
window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
  if ((state.theme || "auto") === "auto") applyChrome();
});
window.addEventListener("hashchange", render);

refreshChecks().then(() => {
  render();
  registerSw();
}).catch(() => {
  packOk = false;
  packProblem = "The guide could not be checked.";
  render();
});

setInterval(() => { refreshChecks(); }, 30000);
document.addEventListener("visibilitychange", () => {
  if (!document.hidden) refreshChecks();
});
