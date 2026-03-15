(function () {
  "use strict";

  const STORAGE_DIVISION_KEY = "ramadan_2026_division";
  const STORAGE_TASBIH_KEY = "ramadan_2026_tasbih";
  const ACTIVE_SCROLL_FLAG = "ramadanScrolled";
  const RUNTIME_ERROR_TEXT = "ক্যালেন্ডার লোড করা যায়নি। পেজ রিফ্রেশ করুন।";
  const BANGLA_DIGIT_MAP = {
    "0": "০",
    "1": "১",
    "2": "২",
    "3": "৩",
    "4": "৪",
    "5": "৫",
    "6": "৬",
    "7": "৭",
    "8": "৮",
    "9": "৯",
  };

  const DIVISION_OFFSETS = {
    Dhaka: 0,
    Chattogram: 5,
    Sylhet: 6,
    Rajshahi: -7,
    Khulna: -3,
    Barishal: -1,
    Rangpur: -6,
    Mymensingh: 0,
  };

  const DIVISION_LABEL_BN = {
    Dhaka: "ঢাকা",
    Chattogram: "চট্টগ্রাম",
    Sylhet: "সিলেট",
    Rajshahi: "রাজশাহী",
    Khulna: "খুলনা",
    Barishal: "বরিশাল",
    Rangpur: "রংপুর",
    Mymensingh: "ময়মনসিংহ",
  };

  const RAW_SCHEDULE = [
    { day: 1, date: "2026-02-19", weekday: "Thu", sehri: "05:12", iftar: "05:58" },
    { day: 2, date: "2026-02-20", weekday: "Fri", sehri: "05:11", iftar: "05:58" },
    { day: 3, date: "2026-02-21", weekday: "Sat", sehri: "05:11", iftar: "05:59" },
    { day: 4, date: "2026-02-22", weekday: "Sun", sehri: "05:10", iftar: "05:59" },
    { day: 5, date: "2026-02-23", weekday: "Mon", sehri: "05:09", iftar: "06:00" },
    { day: 6, date: "2026-02-24", weekday: "Tue", sehri: "05:08", iftar: "06:00" },
    { day: 7, date: "2026-02-25", weekday: "Wed", sehri: "05:08", iftar: "06:01" },
    { day: 8, date: "2026-02-26", weekday: "Thu", sehri: "05:07", iftar: "06:01" },
    { day: 9, date: "2026-02-27", weekday: "Fri", sehri: "05:06", iftar: "06:02" },
    { day: 10, date: "2026-02-28", weekday: "Sat", sehri: "05:05", iftar: "06:02" },
    { day: 11, date: "2026-03-01", weekday: "Sun", sehri: "05:05", iftar: "06:03" },
    { day: 12, date: "2026-03-02", weekday: "Mon", sehri: "05:04", iftar: "06:03" },
    { day: 13, date: "2026-03-03", weekday: "Tue", sehri: "05:03", iftar: "06:04" },
    { day: 14, date: "2026-03-04", weekday: "Wed", sehri: "05:02", iftar: "06:04" },
    { day: 15, date: "2026-03-05", weekday: "Thu", sehri: "05:01", iftar: "06:05" },
    { day: 16, date: "2026-03-06", weekday: "Fri", sehri: "05:00", iftar: "06:05" },
    { day: 17, date: "2026-03-07", weekday: "Sat", sehri: "04:59", iftar: "06:06" },
    { day: 18, date: "2026-03-08", weekday: "Sun", sehri: "04:58", iftar: "06:06" },
    { day: 19, date: "2026-03-09", weekday: "Mon", sehri: "04:57", iftar: "06:07" },
    { day: 20, date: "2026-03-10", weekday: "Tue", sehri: "04:57", iftar: "06:07" },
    { day: 21, date: "2026-03-11", weekday: "Wed", sehri: "04:56", iftar: "06:07" },
    { day: 22, date: "2026-03-12", weekday: "Thu", sehri: "04:55", iftar: "06:08" },
    { day: 23, date: "2026-03-13", weekday: "Fri", sehri: "04:54", iftar: "06:08" },
    { day: 24, date: "2026-03-14", weekday: "Sat", sehri: "04:53", iftar: "06:09" },
    { day: 25, date: "2026-03-15", weekday: "Sun", sehri: "04:52", iftar: "06:09" },
    { day: 26, date: "2026-03-16", weekday: "Mon", sehri: "04:51", iftar: "06:10" },
    { day: 27, date: "2026-03-17", weekday: "Tue", sehri: "04:50", iftar: "06:10" },
    { day: 28, date: "2026-03-18", weekday: "Wed", sehri: "04:49", iftar: "06:10" },
    { day: 29, date: "2026-03-19", weekday: "Thu", sehri: "04:48", iftar: "06:11" },
    { day: 30, date: "2026-03-20", weekday: "Fri", sehri: "04:47", iftar: "06:11" },
  ];

  const SCHEDULE = RAW_SCHEDULE.map(function (entry) {
    return {
      day: entry.day,
      date: entry.date,
      weekday: entry.weekday,
      sehri: entry.sehri,
      iftar: entry.iftar,
      sehri24: entry.sehri,
      iftar24: to24FromPm(entry.iftar),
    };
  });

  const SCHEDULE_BY_DATE = new Map(
    SCHEDULE.map(function (entry) {
      return [entry.date, entry];
    })
  );
  const FIRST_DAY = SCHEDULE[0];
  const LAST_DAY = SCHEDULE[SCHEDULE.length - 1];

  function logError(message, error) {
    if (typeof console !== "undefined" && typeof console.error === "function") {
      console.error("[Ramadan2026] " + message, error);
    }
  }

  function logDebug(message, data) {
    if (typeof console !== "undefined" && typeof console.debug === "function") {
      console.debug("[Ramadan2026] " + message, data);
    }
  }

  function safeLocalStorageGet(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (_error) {
      return null;
    }
  }

  function safeLocalStorageSet(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (_error) {
      // Ignore storage failures.
    }
  }

  function toBanglaDigits(value) {
    return String(value == null ? "" : value).replace(/[0-9]/g, function (digit) {
      return BANGLA_DIGIT_MAP[digit] || digit;
    });
  }

  function toBanglaMeridiem(amPm) {
    return amPm === "PM" ? "অপরাহ্ণ" : "পূর্বাহ্ণ";
  }

  function normalizeDivision(value) {
    if (value && Object.prototype.hasOwnProperty.call(DIVISION_OFFSETS, value)) {
      return value;
    }
    return "Dhaka";
  }

  function isValidDateKey(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ""));
  }

  function resolveTodayKey(root) {
    const clientDhakaKey = getDhakaDateKey();
    let serverKey = "";
    if (root && root.dataset && root.dataset.ramadanToday) {
      serverKey = String(root.dataset.ramadanToday).trim();
    }

    if (!isValidDateKey(serverKey)) {
      return clientDhakaKey;
    }

    if (serverKey !== clientDhakaKey) {
      logDebug("today key mismatch resolved", {
        serverKey: serverKey,
        clientDhakaKey: clientDhakaKey,
        chosenKey: clientDhakaKey,
      });
    }

    return clientDhakaKey;
  }

  function resolveActiveDateKey(todayKey) {
    if (SCHEDULE_BY_DATE.has(todayKey)) {
      return todayKey;
    }
    if (todayKey < FIRST_DAY.date) {
      return FIRST_DAY.date;
    }
    if (todayKey > LAST_DAY.date) {
      return LAST_DAY.date;
    }
    return getDhakaDateKey();
  }

  function pad2(value) {
    return String(value).padStart(2, "0");
  }

  function parseTimeToMinutes(time24) {
    const segments = String(time24).split(":");
    const hours = Number(segments[0]);
    const minutes = Number(segments[1]);
    return (hours * 60) + minutes;
  }

  function minutesToTime24(totalMinutes) {
    const inDay = ((totalMinutes % 1440) + 1440) % 1440;
    const hours = Math.floor(inDay / 60);
    const minutes = inDay % 60;
    return pad2(hours) + ":" + pad2(minutes);
  }

  function addOffset(time24, offsetMinutes) {
    return minutesToTime24(parseTimeToMinutes(time24) + offsetMinutes);
  }

  function to24FromPm(time12NoPeriod) {
    const segments = String(time12NoPeriod).split(":");
    const hoursRaw = Number(segments[0]);
    const minutesRaw = Number(segments[1]);
    const hours = hoursRaw === 12 ? 12 : hoursRaw + 12;
    return pad2(hours) + ":" + pad2(minutesRaw);
  }

  function to12hDisplay(time24) {
    const segments = String(time24).split(":");
    const h = Number(segments[0]);
    const m = Number(segments[1]);
    const meridiem = h >= 12 ? "PM" : "AM";
    const hour12 = h % 12 === 0 ? 12 : h % 12;
    return toBanglaDigits(pad2(hour12)) + ":" + toBanglaDigits(pad2(m)) + " " + toBanglaMeridiem(meridiem);
  }

  function dhakaDateToEpochMs(dateKey, time24) {
    const dateSegments = dateKey.split("-");
    const year = Number(dateSegments[0]);
    const month = Number(dateSegments[1]);
    const day = Number(dateSegments[2]);

    const timeSegments = time24.split(":");
    const hour = Number(timeSegments[0]);
    const minute = Number(timeSegments[1]);

    return Date.UTC(year, month - 1, day, hour - 6, minute, 0, 0);
  }

  function hasIntlDateTime() {
    return typeof Intl !== "undefined" && typeof Intl.DateTimeFormat === "function";
  }

  function getDhakaNow(now) {
    const base = now instanceof Date ? now : new Date();
    const utcMs = base.getTime() + (base.getTimezoneOffset() * 60000);
    return new Date(utcMs + (6 * 60 * 60 * 1000));
  }

  function getDhakaDateKey(now) {
    const base = now instanceof Date ? now : new Date();

    if (hasIntlDateTime()) {
      try {
        const formatter = new Intl.DateTimeFormat("en-US", {
          timeZone: "Asia/Dhaka",
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
        });

        if (typeof formatter.formatToParts === "function") {
          const parts = formatter.formatToParts(base);
          let year = "";
          let month = "";
          let day = "";

          for (let i = 0; i < parts.length; i += 1) {
            const part = parts[i];
            if (part.type === "year") year = part.value;
            if (part.type === "month") month = part.value;
            if (part.type === "day") day = part.value;
          }

          if (year && month && day) {
            return year + "-" + month + "-" + day;
          }
        }
      } catch (error) {
        logError("getDhakaDateKey Intl format failed", error);
      }
    }

    const dhaka = getDhakaNow(base);
    const year = dhaka.getUTCFullYear();
    const month = pad2(dhaka.getUTCMonth() + 1);
    const day = pad2(dhaka.getUTCDate());
    return year + "-" + month + "-" + day;
  }

  function formatDhakaDateLabel(now) {
    const base = now instanceof Date ? now : new Date();

    if (hasIntlDateTime()) {
      try {
        const formatted = new Intl.DateTimeFormat("bn-BD", {
          timeZone: "Asia/Dhaka",
          day: "numeric",
          month: "long",
          year: "numeric",
        }).format(base);

        return toBanglaDigits(formatted);
      } catch (error) {
        logError("formatDhakaDateLabel Intl format failed", error);
      }
    }

    const months = [
      "জানুয়ারি", "ফেব্রুয়ারি", "মার্চ", "এপ্রিল", "মে", "জুন",
      "জুলাই", "আগস্ট", "সেপ্টেম্বর", "অক্টোবর", "নভেম্বর", "ডিসেম্বর",
    ];
    const dhaka = getDhakaNow(base);
    return toBanglaDigits(dhaka.getUTCDate()) + " " + months[dhaka.getUTCMonth()] + " " + toBanglaDigits(dhaka.getUTCFullYear());
  }

  function formatShortDate(dateKey) {
    const date = new Date(dateKey + "T00:00:00+06:00");

    if (hasIntlDateTime()) {
      try {
        return toBanglaDigits(new Intl.DateTimeFormat("bn-BD", {
          timeZone: "Asia/Dhaka",
          day: "2-digit",
          month: "short",
          year: "numeric",
        }).format(date));
      } catch (error) {
        logError("formatShortDate Intl format failed", error);
      }
    }

    const monthsShort = ["জানু", "ফেব্রু", "মার্চ", "এপ্রি", "মে", "জুন", "জুলা", "আগ", "সেপ্টে", "অক্টো", "নভে", "ডিসে"];
    const parts = dateKey.split("-");
    const year = parts[0];
    const month = Number(parts[1]);
    const day = parts[2];
    return toBanglaDigits(day) + " " + monthsShort[month - 1] + " " + toBanglaDigits(year);
  }

  function formatWeekdayBn(dateKey) {
    const date = new Date(dateKey + "T00:00:00+06:00");

    if (hasIntlDateTime()) {
      try {
        return new Intl.DateTimeFormat("bn-BD", {
          timeZone: "Asia/Dhaka",
          weekday: "long",
        }).format(date);
      } catch (error) {
        logError("formatWeekdayBn Intl format failed", error);
      }
    }

    const weekdays = ["রবিবার", "সোমবার", "মঙ্গলবার", "বুধবার", "বৃহস্পতিবার", "শুক্রবার", "শনিবার"];
    const utcDate = new Date(Date.UTC(Number(dateKey.slice(0, 4)), Number(dateKey.slice(5, 7)) - 1, Number(dateKey.slice(8, 10))));
    return weekdays[utcDate.getUTCDay()];
  }

  function getAdjustedEntry(rawEntry, division) {
    const offset = Object.prototype.hasOwnProperty.call(DIVISION_OFFSETS, division) ? DIVISION_OFFSETS[division] : 0;
    const sehri24 = addOffset(rawEntry.sehri24, offset);
    const iftar24 = addOffset(rawEntry.iftar24, offset);

    return {
      day: rawEntry.day,
      date: rawEntry.date,
      weekday: rawEntry.weekday,
      sehri24: sehri24,
      iftar24: iftar24,
      sehriDisplay: to12hDisplay(sehri24),
      iftarDisplay: to12hDisplay(iftar24),
    };
  }

  function getTodayRawEntry(todayKey) {
    return SCHEDULE_BY_DATE.get(todayKey) || null;
  }

  function formatRojaLabel(day) {
    if (day === 1) {
      return "১ম রোজা";
    }
    return toBanglaDigits(day) + "তম রোজা";
  }

  function getCountdownState(division, todayKey) {
    const nowMs = Date.now();

    if (todayKey < FIRST_DAY.date) {
      const firstEntry = getAdjustedEntry(FIRST_DAY, division);
      return {
        type: "pre_ramadan",
        targetMs: dhakaDateToEpochMs(firstEntry.date, firstEntry.sehri24),
      };
    }

    if (todayKey > LAST_DAY.date) {
      return {
        type: "completed",
        targetMs: null,
      };
    }

    const todayRaw = SCHEDULE_BY_DATE.get(todayKey);
    if (!todayRaw) {
      return {
        type: "completed",
        targetMs: null,
      };
    }

    const todayEntry = getAdjustedEntry(todayRaw, division);
    const sehriMs = dhakaDateToEpochMs(todayEntry.date, todayEntry.sehri24);
    const iftarMs = dhakaDateToEpochMs(todayEntry.date, todayEntry.iftar24);

    if (nowMs < sehriMs) {
      return {
        type: "before_sehri",
        targetMs: sehriMs,
      };
    }

    if (nowMs < iftarMs) {
      return {
        type: "before_iftar",
        targetMs: iftarMs,
      };
    }

    const nextRaw = SCHEDULE[todayRaw.day] || null;
    if (!nextRaw) {
      return {
        type: "completed",
        targetMs: null,
      };
    }

    const nextEntry = getAdjustedEntry(nextRaw, division);
    return {
      type: "next_sehri",
      targetMs: dhakaDateToEpochMs(nextEntry.date, nextEntry.sehri24),
    };
  }

  function setText(element, value) {
    if (element) {
      element.textContent = value;
    }
  }

  function setCountdownValues(elements, remainingMs) {
    const safeMs = Math.max(0, remainingMs);
    const totalSeconds = Math.floor(safeMs / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    setText(elements.hours, toBanglaDigits(pad2(hours)));
    setText(elements.minutes, toBanglaDigits(pad2(minutes)));
    setText(elements.seconds, toBanglaDigits(pad2(seconds)));
  }

  function applyCountdownMeta(elements, state) {
    if (state.type === "pre_ramadan") {
      setText(elements.status, "রমজান শুরু হতে বাকি");
      setText(elements.title, "প্রথম সেহরির বাকি সময়");
      return;
    }

    if (state.type === "before_sehri") {
      setText(elements.status, "সেহরির সময় ঘনিয়ে এসেছে");
      setText(elements.title, "সেহরির বাকি সময়");
      return;
    }

    if (state.type === "before_iftar") {
      setText(elements.status, "রোজা চলছে");
      setText(elements.title, "ইফতারের বাকি সময়");
      return;
    }

    if (state.type === "next_sehri") {
      setText(elements.status, "ইফতার সম্পন্ন");
      setText(elements.title, "পরবর্তী সেহরির বাকি সময়");
      return;
    }

    setText(elements.status, "রমজান ২০২৬ সমাপ্ত");
    setText(elements.title, "এই বছরের কাউন্টডাউন শেষ");
  }

  function updateCountdown(elements, division, todayKey) {
    const state = getCountdownState(division, todayKey);
    applyCountdownMeta(elements, state);

    if (!state.targetMs) {
      setCountdownValues(elements, 0);
      return;
    }

    setCountdownValues(elements, state.targetMs - Date.now());
  }

  function updateStats(elements, division, todayKey) {
    const todayLabel = formatDhakaDateLabel();
    const todayRaw = getTodayRawEntry(todayKey);

    if (todayRaw) {
      const todayEntry = getAdjustedEntry(todayRaw, division);
      setText(elements.date, todayLabel);
      setText(elements.roja, formatRojaLabel(todayRaw.day));
      setText(elements.sehri, todayEntry.sehriDisplay);
      setText(elements.iftar, todayEntry.iftarDisplay);
    } else if (todayKey < FIRST_DAY.date) {
      const firstEntry = getAdjustedEntry(FIRST_DAY, division);
      setText(elements.date, todayLabel);
      setText(elements.roja, "১ম রোজা শুরু হবে");
      setText(elements.sehri, firstEntry.sehriDisplay);
      setText(elements.iftar, firstEntry.iftarDisplay);
    } else {
      const lastEntry = getAdjustedEntry(LAST_DAY, division);
      setText(elements.date, todayLabel);
      setText(elements.roja, "রমজান সমাপ্ত");
      setText(elements.sehri, lastEntry.sehriDisplay);
      setText(elements.iftar, lastEntry.iftarDisplay);
    }

    if (elements.division) {
      setText(elements.division, DIVISION_LABEL_BN[division] || division);
    }
  }

  function renderWidgetError(root, message, tableBody) {
    const status = root ? root.querySelector("[data-ramadan-status]") : null;
    const title = root ? root.querySelector("[data-ramadan-countdown-title]") : null;
    setText(status, "ত্রুটি");
    setText(title, message);

    if (tableBody) {
      tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">' + message + "</td></tr>";
    }

    if (!status && !title && root) {
      const existing = root.querySelector(".ramadan-inline-error");
      if (!existing) {
        const div = document.createElement("div");
        div.className = "ramadan-inline-error alert alert-warning mb-0";
        div.textContent = message;
        root.prepend(div);
      }
    }
  }

  function renderTable(tableBody, division, todayKey, mode, root) {
    if (!tableBody) {
      return;
    }

    try {
      const activeDate = resolveActiveDateKey(todayKey);
      const rows = SCHEDULE.map(function (rawEntry) {
        const entry = getAdjustedEntry(rawEntry, division);
        const activeClass = rawEntry.date === activeDate ? "ramadan-row-active" : "";
        return [
          '<tr class="' + activeClass + '" data-ramadan-row-date="' + rawEntry.date + '">',
          '<td class="ps-4 fw-semibold">' + toBanglaDigits(entry.day) + "</td>",
          "<td>" + formatShortDate(entry.date) + "</td>",
          "<td>" + formatWeekdayBn(entry.date) + "</td>",
          "<td>" + entry.sehriDisplay + "</td>",
          '<td class="pe-4">' + entry.iftarDisplay + "</td>",
          "</tr>",
        ].join("");
      }).join("");

      tableBody.innerHTML = rows;

      const activeRow = tableBody.querySelector(".ramadan-row-active");
      const hasScrolled = Boolean(root && root.dataset && root.dataset[ACTIVE_SCROLL_FLAG] === "1");

      if (mode === "full" && activeRow && !hasScrolled) {
        if (root && root.dataset) {
          root.dataset[ACTIVE_SCROLL_FLAG] = "1";
        }

        setTimeout(function () {
          activeRow.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
        }, 100);
      }
    } catch (error) {
      logError("renderTable failed", error);
      tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">' + RUNTIME_ERROR_TEXT + "</td></tr>";
    }
  }

  function initTasbih(root) {
    const valueEl = root.querySelector("[data-ramadan-tasbih-value]");
    const incBtn = root.querySelector("[data-ramadan-tasbih-inc]");
    const resetBtn = root.querySelector("[data-ramadan-tasbih-reset]");

    if (!valueEl || !incBtn || !resetBtn) {
      return;
    }

    let value = parseInt(safeLocalStorageGet(STORAGE_TASBIH_KEY) || "0", 10);
    if (!Number.isFinite(value) || value < 0) {
      value = 0;
    }

    const render = function () {
      valueEl.textContent = toBanglaDigits(value);
    };

    incBtn.addEventListener("click", function () {
      value += 1;
      render();
      safeLocalStorageSet(STORAGE_TASBIH_KEY, String(value));

      if (typeof navigator !== "undefined" && "vibrate" in navigator) {
        navigator.vibrate(35);
      }
    });

    resetBtn.addEventListener("click", function () {
      value = 0;
      render();
      safeLocalStorageSet(STORAGE_TASBIH_KEY, "0");
    });

    render();
  }

  function initWidget(root) {
    if (!root || root.getAttribute("data-ramadan-initialized") === "1") {
      return;
    }

    root.setAttribute("data-ramadan-initialized", "1");
    let tableBody = null;

    try {
      const mode = String((root.dataset && root.dataset.ramadanMode) || "compact");
      const elements = {
        status: root.querySelector("[data-ramadan-status]"),
        title: root.querySelector("[data-ramadan-countdown-title]"),
        hours: root.querySelector("[data-ramadan-hours]"),
        minutes: root.querySelector("[data-ramadan-minutes]"),
        seconds: root.querySelector("[data-ramadan-seconds]"),
        date: root.querySelector("[data-ramadan-date]"),
        roja: root.querySelector("[data-ramadan-roja]"),
        sehri: root.querySelector("[data-ramadan-today-sehri]"),
        iftar: root.querySelector("[data-ramadan-today-iftar]"),
        division: root.querySelector("[data-ramadan-division-label]"),
      };

      tableBody = root.querySelector("[data-ramadan-table-body]");
      const requiredElements = [
        elements.status,
        elements.title,
        elements.hours,
        elements.minutes,
        elements.seconds,
        elements.date,
        elements.roja,
        elements.sehri,
        elements.iftar,
      ];

      for (let i = 0; i < requiredElements.length; i += 1) {
        if (!requiredElements[i]) {
          renderWidgetError(root, RUNTIME_ERROR_TEXT, tableBody);
          return;
        }
      }

      const divisionSelect = root.querySelector("[data-ramadan-division]");
      let selectedDivision = normalizeDivision(
        (divisionSelect && divisionSelect.value) || safeLocalStorageGet(STORAGE_DIVISION_KEY)
      );

      if (divisionSelect) {
        divisionSelect.value = selectedDivision;
        divisionSelect.addEventListener("change", function () {
          selectedDivision = normalizeDivision(divisionSelect.value);
          safeLocalStorageSet(STORAGE_DIVISION_KEY, selectedDivision);
          refreshStatic();
          refreshCountdown();
        });
      }

      function getWidgetTodayKey() {
        return resolveTodayKey(root);
      }

      function refreshStatic() {
        try {
          const todayKey = getWidgetTodayKey();
          updateStats(elements, selectedDivision, todayKey);
          renderTable(tableBody, selectedDivision, todayKey, mode, root);
        } catch (error) {
          logError("refreshStatic failed", error);
          renderWidgetError(root, RUNTIME_ERROR_TEXT, tableBody);
        }
      }

      function refreshCountdown() {
        try {
          updateCountdown(elements, selectedDivision, getWidgetTodayKey());
        } catch (error) {
          logError("refreshCountdown failed", error);
          renderWidgetError(root, RUNTIME_ERROR_TEXT, tableBody);
        }
      }

      refreshStatic();
      refreshCountdown();

      setInterval(function () {
        refreshCountdown();
      }, 1000);

      setInterval(function () {
        refreshStatic();
      }, 60000);

      initTasbih(root);
    } catch (error) {
      logError("initWidget failed", error);
      renderWidgetError(root, RUNTIME_ERROR_TEXT, tableBody);
    }
  }

  function init() {
    try {
      const roots = document.querySelectorAll("[data-ramadan-widget]");
      if (!roots.length) {
        return;
      }

      roots.forEach(function (root) {
        initWidget(root);
      });
    } catch (error) {
      logError("init failed", error);
    }
  }

  let initExecuted = false;
  function safeInit() {
    if (initExecuted) {
      return;
    }

    initExecuted = true;
    init();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", safeInit, { once: true });
    setTimeout(safeInit, 0);
  } else {
    safeInit();
    setTimeout(safeInit, 0);
  }
})();
