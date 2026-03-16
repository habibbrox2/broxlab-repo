/**
 * BroxBhai DatePicker (no dependencies)
 * - Auto CSS load when JS is included
 * - Namespaced classes to avoid style collision
 * - Input remains read-only (manual typing blocked)
 */
(function () {
    'use strict';

    const INPUT_ATTR = 'data-bxdp-input';
    const STYLE_ATTR = 'data-bxdp-style';
    const CLS = {
        cal: 'bxdp-calendar',
        open: 'is-open',
        head: 'bxdp-header',
        nav: 'bxdp-nav',
        btn: 'bxdp-btn',
        selects: 'bxdp-selects',
        select: 'bxdp-select',
        weekdaysWrap: 'bxdp-weekdays-wrap',
        weekdays: 'bxdp-weekdays',
        weekday: 'bxdp-weekday',
        grid: 'bxdp-dates',
        day: 'bxdp-day',
        footer: 'bxdp-footer',
        footerBtn: 'bxdp-footer-btn',
        primary: 'is-primary',
        other: 'is-other-month',
        today: 'is-today',
        selected: 'is-selected',
        disabled: 'is-disabled',
        rangeStart: 'is-range-start',
        rangeEnd: 'is-range-end',
        inRange: 'is-in-range',
        multi: 'is-multi-selected'
    };

    const U = {
        format(date, format = 'DD-MM-YYYY') {
            if (!date) return '';
            const d = new Date(date);
            const dd = String(d.getDate()).padStart(2, '0');
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const yyyy = d.getFullYear();
            return format.replace('DD', dd).replace('MM', mm).replace('YYYY', yyyy);
        },
        // convert bengali digits to western
        normalizeDigits(str) {
            if (!str) return str;
            const map = {
                '০':'0','১':'1','২':'2','৩':'3','৪':'4','৫':'5','৬':'6','৭':'7','৮':'8','৯':'9'
            };
            return str.replace(/[০-৯]/g, (ch) => map[ch] || ch);
        },
        parse(value, format = 'DD-MM-YYYY') {
            if (!value) return null;
            let dd; let mm; let yyyy;
            if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                [yyyy, mm, dd] = value.split('-');
            } else {
                const parts = value.split(/[-/]/);
                if (parts.length !== 3) return null;
                if (format === 'MM/DD/YYYY') [mm, dd, yyyy] = parts;
                else if (format === 'YYYY-MM-DD') [yyyy, mm, dd] = parts;
                else[dd, mm, yyyy] = parts;
            }
            const parsed = new Date(yyyy, mm - 1, dd);
            if (Number.isNaN(parsed.getTime())) return null;
            if (parsed.getDate() !== parseInt(dd, 10)) return null;
            return parsed;
        },
        sameDay(a, b) {
            return !!(a && b &&
                a.getDate() === b.getDate() &&
                a.getMonth() === b.getMonth() &&
                a.getFullYear() === b.getFullYear());
        },
        between(d, s, e) {
            const t = d && d.getTime();
            return !!(t && s && e && t >= s.getTime() && t <= e.getTime());
        },
        daysInMonth(y, m) { return new Date(y, m + 1, 0).getDate(); },
        // return ISO week number for a date
        isoWeek(date) {
            const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNum = d.getUTCDay() || 7;
            d.setUTCDate(d.getUTCDate() + 4 - dayNum);
            const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
            return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        },
        getMonthNames(locale = 'en') {
            const fmt = new Intl.DateTimeFormat(locale, { month: 'long' });
            return Array.from({ length: 12 }, (_, i) => fmt.format(new Date(2020, i, 1)));
        },
        getWeekdayNames(locale = 'en', firstDay = 0) {
            const fmt = new Intl.DateTimeFormat(locale, { weekday: 'short' });
            let names = Array.from({ length: 7 }, (_, i) => fmt.format(new Date(2020, 0, i + 4))); // 4 is Sunday
            if (firstDay === 1) names.push(names.shift());
            return names;
        }
    };

    function normalizeHref(href) {
        try { return new URL(href, window.location.href).href.split('#')[0]; } catch (_) { return href; }
    }

    function resolveScriptUrl() {
        if (document.currentScript && document.currentScript.src) {
            return new URL(document.currentScript.src, window.location.href);
        }
        const scripts = Array.from(document.querySelectorAll('script[src]'));
        for (let i = scripts.length - 1; i >= 0; i--) {
            const src = scripts[i].getAttribute('src') || '';
            if (/datepicker(\.min)?\.js(\?.*)?$/i.test(src)) return new URL(src, window.location.href);
        }
        return null;
    }

    function ensureStylesheet(overridePath) {
        const candidates = [];
        if (overridePath) candidates.push(normalizeHref(overridePath));
        const scriptUrl = resolveScriptUrl();
        if (scriptUrl) {
            const p = scriptUrl.pathname.toLowerCase();
            if (p.includes('/assets/js/dist/')) {
                const prefix = scriptUrl.pathname.split('/assets/js/dist/')[0] || '';
                candidates.push(new URL(`${prefix}/assets/datepicker/datepicker.css`, scriptUrl.origin).href);
            }
            if (p.includes('/assets/datepicker/')) {
                candidates.push(new URL('./datepicker.css', scriptUrl.href).href);
            }
            candidates.push(new URL('./datepicker.css', scriptUrl.href).href);
        }
        candidates.push(new URL('/assets/datepicker/datepicker.css', window.location.origin).href);
        const unique = Array.from(new Set(candidates.map(normalizeHref)));

        const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
        for (const link of links) {
            const href = normalizeHref(link.href || link.getAttribute('href') || '');
            if (unique.includes(href)) {
                link.setAttribute(STYLE_ATTR, '1');
                return href;
            }
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = unique[0];
        link.setAttribute(STYLE_ATTR, '1');
        document.head.appendChild(link);
        return link.href;
    }

    class Instance {
        constructor(input, config) {
            this.input = input;
            this.config = { ...config };
            this.calendar = null;
            this.currentMonth = new Date().getMonth();
            this.currentYear = new Date().getFullYear();
            this.selectedDate = null;
            this.selectedDates = [];
            this.rangeStart = null;
            this.rangeEnd = null;
            this.isOpen = false;
            this.init();
        }

        init() {
            this.parseDataAttributes();
            // detect rtl languages if not manually specified
            if (!this.config.rtl && this.config.locale) {
                const rtlLangs = ['ar', 'he', 'fa', 'ur'];
                for (const l of rtlLangs) {
                    if (this.config.locale.startsWith(l)) { this.config.rtl = true; break; }
                }
            }
            this.convertToTextInput();
            if (this.input.value) this.setDateFromInput();
            this.createCalendar();
            this.attachEvents();
            this.setAria();
        }

        parseDataAttributes() {
            const data = this.input.dataset;
            if (data.format) this.config.format = data.format;
            if (data.minDate) this.config.minDate = new Date(data.minDate);
            if (data.maxDate) this.config.maxDate = new Date(data.maxDate);
            if (data.disablePast === 'true') this.config.disablePast = true;
            if (data.disableFuture === 'true') this.config.disableFuture = true;
            if (data.disableWeekends === 'true') this.config.disableWeekends = true;
            if (data.firstDay) this.config.firstDayOfWeek = parseInt(data.firstDay, 10);
            if (data.mode) this.config.mode = data.mode;
            if (data.locale) this.config.locale = data.locale;
            if (data.rtl === 'true' || data.rtl === '1') this.config.rtl = true;
            if (data.withTime === 'true' || data.withTime === '1') this.config.withTime = true;
            if (data.weekNumbers === 'true' || data.weekNumbers === '1') this.config.weekNumbers = true;
            if (data.placeholder) this.config.placeholder = data.placeholder;
            if (data.allowManual === 'true' || data.allowManual === '1') this.config.allowManual = true;
        }

        convertToTextInput() {
            if (this.input.type === 'date') {
                this.input.setAttribute('data-original-type', 'date');
                this.input.type = 'text';
            }
            this.input.setAttribute('autocomplete', 'on');
            // always allow typing/copy-paste; readonly only used to prevent mobile keyboards when desired
            this.input.removeAttribute('inputmode');
            this.input.readOnly = false;
            if (this.config.allowManual) {
                this.input.classList.add('bxdp-manual');
                this.input.setAttribute('data-allow-manual', '1');
            } else {
                // still show calendar icon unless manual is allowed
                this.input.classList.remove('bxdp-manual');
                this.input.removeAttribute('data-allow-manual');
            }
            this.input.setAttribute(INPUT_ATTR, '1');
            if (!this.input.placeholder) {
                if (this.config.placeholder) this.input.placeholder = this.config.placeholder;
                else this.input.placeholder = this.config.format || 'DD-MM-YYYY';
            }
        }

        setAria() {
            this.input.setAttribute('role', 'combobox');
            this.input.setAttribute('aria-haspopup', 'dialog');
            this.input.setAttribute('aria-expanded', 'false');
            this.input.setAttribute('aria-readonly', 'true');
            this.input.setAttribute('aria-label', 'Choose date');
        }

        createCalendar() {
            this.calendar = document.createElement('div');
            this.calendar.className = CLS.cal;
            if (this.config.rtl) this.calendar.setAttribute('dir', 'rtl');
            this.calendar.setAttribute('role', 'dialog');
            this.calendar.setAttribute('aria-modal', 'true');
            this.calendar.setAttribute('aria-label', 'Choose date');
            document.body.appendChild(this.calendar);
            this.render();
        }

        render() {
            this.calendar.innerHTML = '';
            this.calendar.append(this.createHeader(), this.createWeekdays(), this.createDays(), this.createFooter());
        }

        createHeader() {
            const header = document.createElement('div');
            header.className = CLS.head;

            const left = document.createElement('div');
            left.className = CLS.nav;
            const prev = document.createElement('button');
            prev.type = 'button';
            prev.className = CLS.btn;
            prev.innerHTML = this.config.rtl ? '&#8250;' : '&#8249;';
            prev.setAttribute('aria-label', 'Previous month');
            prev.addEventListener('click', () => this.previousMonth());
            left.appendChild(prev);

            const selects = document.createElement('div');
            selects.className = CLS.selects;
            selects.append(this.createMonthSelect(), this.createYearSelect());

            const right = document.createElement('div');
            right.className = CLS.nav;
            const next = document.createElement('button');
            next.type = 'button';
            next.className = CLS.btn;
            next.innerHTML = this.config.rtl ? '&#8249;' : '&#8250;';
            next.setAttribute('aria-label', 'Next month');
            next.addEventListener('click', () => this.nextMonth());
            right.appendChild(next);

            header.append(left, selects, right);
            return header;
        }

        createMonthSelect() {
            const select = document.createElement('select');
            select.className = CLS.select;
            select.setAttribute('aria-label', 'Select month');
            const months = U.getMonthNames(this.config.locale);
            months.forEach((month, i) => {
                const opt = document.createElement('option');
                opt.value = String(i);
                opt.textContent = month;
                if (i === this.currentMonth) opt.selected = true;
                select.appendChild(opt);
            });
            select.addEventListener('change', (e) => {
                this.currentMonth = parseInt(e.target.value, 10);
                this.render();
                if (this.config.onMonthChange) this.config.onMonthChange(this.currentMonth, this);
            });
            return select;
        }

        createYearSelect() {
            const select = document.createElement('select');
            select.className = CLS.select;
            select.setAttribute('aria-label', 'Select year');
            const now = new Date().getFullYear();
            for (let year = now - 100; year <= now + 10; year++) {
                const opt = document.createElement('option');
                opt.value = String(year);
                opt.textContent = String(year);
                if (year === this.currentYear) opt.selected = true;
                select.appendChild(opt);
            }
            select.addEventListener('change', (e) => {
                this.currentYear = parseInt(e.target.value, 10);
                this.render();
                if (this.config.onYearChange) this.config.onYearChange(this.currentYear, this);
            });
            return select;
        }

        createWeekdays() {
            const wrap = document.createElement('div');
            wrap.className = CLS.weekdaysWrap;
            const days = document.createElement('div');
            days.className = CLS.weekdays;
            days.setAttribute('role', 'row');
            // optionally add blank header for week numbers
            if (this.config.weekNumbers) {
                const cell = document.createElement('div');
                cell.className = 'bxdp-weeknumber-header';
                cell.textContent = '#';
                cell.setAttribute('role', 'columnheader');
                days.appendChild(cell);
            }
            const names = U.getWeekdayNames(this.config.locale, this.config.firstDayOfWeek);
            names.forEach((name) => {
                const cell = document.createElement('div');
                cell.className = CLS.weekday;
                cell.textContent = name;
                cell.setAttribute('role', 'columnheader');
                days.appendChild(cell);
            });
            wrap.appendChild(days);
            return wrap;
        }

        createDays() {
            const grid = document.createElement('div');
            grid.className = CLS.grid;
            if (this.config.weekNumbers) grid.classList.add('has-week-numbers');
            grid.setAttribute('role', 'grid');

            const first = new Date(this.currentYear, this.currentMonth, 1);
            const total = U.daysInMonth(this.currentYear, this.currentMonth);
            let start = first.getDay();
            if (this.config.firstDayOfWeek === 1) start = start === 0 ? 6 : start - 1;

            const prevTotal = U.daysInMonth(
                this.currentMonth === 0 ? this.currentYear - 1 : this.currentYear,
                this.currentMonth === 0 ? 11 : this.currentMonth - 1
            );

            // build flat list of date entries
            const dates = [];
            for (let i = start - 1; i >= 0; i--) {
                dates.push({
                    date: new Date(
                        this.currentMonth === 0 ? this.currentYear - 1 : this.currentYear,
                        this.currentMonth === 0 ? 11 : this.currentMonth - 1,
                        prevTotal - i), other: true
                });
            }
            for (let d = 1; d <= total; d++) {
                dates.push({ date: new Date(this.currentYear, this.currentMonth, d), other: false });
            }
            while (dates.length < 42) {
                const next = dates.length - (start + total) + 1;
                dates.push({
                    date: new Date(
                        this.currentMonth === 11 ? this.currentYear + 1 : this.currentYear,
                        this.currentMonth === 11 ? 0 : this.currentMonth + 1,
                        next), other: true
                });
            }

            // render cells row by row
            for (let i = 0; i < dates.length; i++) {
                if (this.config.weekNumbers && i % 7 === 0) {
                    const wn = document.createElement('div');
                    wn.className = 'bxdp-week-number';
                    wn.textContent = U.isoWeek(dates[i].date).toLocaleString(this.config.locale);
                    wn.setAttribute('role', 'gridcell');
                    grid.appendChild(wn);
                }
                grid.appendChild(this.createDay(dates[i].date, dates[i].other));
            }

            return grid;
        }

        createDay(date, isOtherMonth) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = CLS.day;
            if (date.getDay() === 0 || date.getDay() === 6) button.classList.add('bxdp-weekend');
            button.textContent = date.getDate().toLocaleString(this.config.locale);
            button.setAttribute('role', 'gridcell');
            button.setAttribute('aria-label', U.format(date, 'DD-MM-YYYY'));
            if (isOtherMonth) button.classList.add(CLS.other);
            if (U.sameDay(date, new Date())) button.classList.add(CLS.today);

            if (this.config.mode === 'range') {
                if (this.rangeStart && U.sameDay(date, this.rangeStart)) button.classList.add(CLS.selected, CLS.rangeStart);
                if (this.rangeEnd && U.sameDay(date, this.rangeEnd)) button.classList.add(CLS.selected, CLS.rangeEnd);
                if (this.rangeStart && this.rangeEnd && U.between(date, this.rangeStart, this.rangeEnd)) button.classList.add(CLS.inRange);
            } else if (this.config.mode === 'multiple') {
                if (this.selectedDates.some((d) => U.sameDay(d, date))) button.classList.add(CLS.selected, CLS.multi);
            } else if (this.selectedDate && U.sameDay(date, this.selectedDate)) {
                button.classList.add(CLS.selected);
            }

            if (this.isDateDisabled(date)) {
                button.classList.add(CLS.disabled);
                button.disabled = true;
            }

            button.addEventListener('click', (e) => {
                e.stopPropagation(); // keep outside handler from firing
                this.selectDate(date);
                // close immediately after selection
                this.close();
            });
            button.addEventListener('keydown', (e) => this.handleDayKeyboard(e, date));
            return button;
        }

        createFooter() {
            const footer = document.createElement('div');
            footer.className = CLS.footer;

            if (this.config.withTime) {
                const timeWrap = document.createElement('div');
                timeWrap.className = 'bxdp-time';
                // hours select
                const hour = document.createElement('select');
                hour.className = 'bxdp-time-hour';
                for (let h = 0; h < 24; h++) {
                    const opt = document.createElement('option');
                    opt.value = String(h);
                    opt.textContent = String(h).padStart(2, '0');
                    hour.appendChild(opt);
                }
                hour.addEventListener('change', () => {
                    if (this.selectedDate) {
                        this.selectedDate.setHours(parseInt(hour.value, 10));
                        this.updateInput();
                    }
                });
                // minutes select
                const minute = document.createElement('select');
                minute.className = 'bxdp-time-minute';
                for (let m = 0; m < 60; m += 5) {
                    const opt = document.createElement('option');
                    opt.value = String(m);
                    opt.textContent = String(m).padStart(2, '0');
                    minute.appendChild(opt);
                }
                minute.addEventListener('change', () => {
                    if (this.selectedDate) {
                        this.selectedDate.setMinutes(parseInt(minute.value, 10));
                        this.updateInput();
                    }
                });
                timeWrap.append(hour, minute);
                footer.appendChild(timeWrap);
                // populate selects when date changes
                this.updateTimeSelects = () => {
                    if (this.selectedDate) {
                        hour.value = String(this.selectedDate.getHours());
                        minute.value = String(this.selectedDate.getMinutes());
                    }
                };
            }

            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = CLS.footerBtn;
            clearBtn.textContent = 'Clear';
            clearBtn.addEventListener('click', () => this.clear());
            const todayBtn = document.createElement('button');
            todayBtn.type = 'button';
            todayBtn.className = `${CLS.footerBtn} ${CLS.primary}`;
            todayBtn.textContent = 'Today';
            todayBtn.addEventListener('click', () => this.selectToday());
            footer.append(clearBtn, todayBtn);
            return footer;
        }

        isDateDisabled(date) {
            if (this.config.minDate && date < this.config.minDate) return true;
            if (this.config.maxDate && date > this.config.maxDate) return true;
            if (this.config.disableWeekends && (date.getDay() === 0 || date.getDay() === 6)) return true;
            if (this.config.disablePast) {
                const now = new Date(); now.setHours(0, 0, 0, 0);
                if (date < now) return true;
            }
            if (this.config.disableFuture) {
                const now = new Date(); now.setHours(23, 59, 59, 999);
                if (date > now) return true;
            }
            return !!(Array.isArray(this.config.disabledDates) && this.config.disabledDates.some((d) => U.sameDay(d, date)));
        }

        selectDate(date) {
            if (this.isDateDisabled(date)) return;
            if (this.config.mode === 'range') this.selectRangeDate(date);
            else if (this.config.mode === 'multiple') this.selectMultipleDate(date);
            else {
                this.selectedDate = date;
                this.updateInput();
                this.close();
            }
            this.render();
            if (this.config.onChange) this.config.onChange(this.getSelectedValue(), this);
        }

        selectRangeDate(date) {
            if (!this.rangeStart || this.rangeEnd) {
                this.rangeStart = date;
                this.rangeEnd = null;
                return;
            }
            if (date < this.rangeStart) {
                this.rangeEnd = this.rangeStart;
                this.rangeStart = date;
            } else {
                this.rangeEnd = date;
            }
            this.updateInput();
            this.close();
        }

        selectMultipleDate(date) {
            const idx = this.selectedDates.findIndex((d) => U.sameDay(d, date));
            if (idx >= 0) this.selectedDates.splice(idx, 1);
            else this.selectedDates.push(date);
            this.updateInput();
        }

        selectToday() {
            const today = new Date();
            if (!this.isDateDisabled(today)) this.selectDate(today);
        }

        clear() {
            this.selectedDate = null;
            this.selectedDates = [];
            this.rangeStart = null;
            this.rangeEnd = null;
            this.input.value = '';
            this.render();
            this.close();
            if (this.config.onChange) this.config.onChange(null, this);
        }

        updateInput() {
            if (this.config.mode === 'range') {
                if (this.rangeStart && this.rangeEnd) this.input.value = `${U.format(this.rangeStart, this.config.format)} - ${U.format(this.rangeEnd, this.config.format)}`;
                else if (this.rangeStart) this.input.value = U.format(this.rangeStart, this.config.format);
                return;
            }
            if (this.config.mode === 'multiple') {
                this.input.value = this.selectedDates.map((d) => U.format(d, this.config.format)).join(', ');
                return;
            }
            let val = U.format(this.selectedDate, this.config.format);
            if (this.config.withTime && this.selectedDate) {
                const h = String(this.selectedDate.getHours()).padStart(2, '0');
                const m = String(this.selectedDate.getMinutes()).padStart(2, '0');
                if (!this.config.format.includes('H')) val += ` ${h}:${m}`;
            }
            this.input.value = val;
            if (this.config.withTime && this.updateTimeSelects) this.updateTimeSelects();
        }

        setDateFromInput() {
            let value = U.normalizeDigits(this.input.value.trim());
            if (!value) return;
            // if time present, separate
            let timePart = '';
            if (this.config.withTime) {
                const parts = value.split(' ');
                if (parts.length > 1) {
                    timePart = parts.pop();
                    value = parts.join(' ');
                }
            }
            if (this.config.mode === 'range') {
                const parts = value.split('-').map((s) => s.trim());
                if (parts.length === 2) {
                    this.rangeStart = U.parse(parts[0], this.config.format);
                    this.rangeEnd = U.parse(parts[1], this.config.format);
                    if (this.rangeStart) {
                        this.currentMonth = this.rangeStart.getMonth();
                        this.currentYear = this.rangeStart.getFullYear();
                    }
                }
                this.updateInput();
                return;
            }
            if (this.config.mode === 'multiple') {
                this.selectedDates = value.split(',').map((s) => U.parse(s.trim(), this.config.format)).filter(Boolean);
                this.updateInput();
                return;
            }
            this.selectedDate = U.parse(value, this.config.format);
            if (this.selectedDate) {
                this.currentMonth = this.selectedDate.getMonth();
                this.currentYear = this.selectedDate.getFullYear();
                if (this.config.withTime && timePart) {
                    const [h, m] = timePart.split(':').map((n) => parseInt(n, 10));
                    if (!isNaN(h)) this.selectedDate.setHours(h);
                    if (!isNaN(m)) this.selectedDate.setMinutes(m);
                }
            }
            this.updateInput();
        }

        handleManualInput() {
            // called when user types a date manually and leaves the field
            const before = this.getSelectedValue();
            this.setDateFromInput();
            this.render();
            const after = this.getSelectedValue();
            if (this.config.onChange && JSON.stringify(before) !== JSON.stringify(after)) {
                this.config.onChange(after, this);
            }
        }

        getSelectedValue() {
            if (this.config.mode === 'range') return this.rangeStart && this.rangeEnd ? { start: this.rangeStart, end: this.rangeEnd } : null;
            if (this.config.mode === 'multiple') return this.selectedDates;
            return this.selectedDate;
        }

        previousMonth() {
            if (this.currentMonth === 0) { this.currentMonth = 11; this.currentYear--; } else this.currentMonth--;
            this.render();
            if (this.config.onMonthChange) this.config.onMonthChange(this.currentMonth, this);
        }

        nextMonth() {
            if (this.currentMonth === 11) { this.currentMonth = 0; this.currentYear++; } else this.currentMonth++;
            this.render();
            if (this.config.onMonthChange) this.config.onMonthChange(this.currentMonth, this);
        }

        open() {
            if (this.isOpen) return;
            this.isOpen = true;
            this.calendar.classList.add(CLS.open);
            this.input.setAttribute('aria-expanded', 'true');
            this.positionCalendar();
            // when manual typing is allowed we keep focus on input so the user can type
            if (!this.config.allowManual) {
                setTimeout(() => {
                    const target = this.calendar.querySelector(`.${CLS.day}.${CLS.selected}:not(.${CLS.disabled})`) ||
                        this.calendar.querySelector(`.${CLS.day}.${CLS.today}:not(.${CLS.disabled})`) ||
                        this.calendar.querySelector(`.${CLS.day}:not(.${CLS.other}):not(.${CLS.disabled})`);
                    if (target) target.focus();
                }, 60);
            }
            if (this.config.onOpen) this.config.onOpen(this);
        }

        close() {
            if (!this.isOpen) return;
            this.isOpen = false;
            this.calendar.classList.remove(CLS.open);
            this.input.setAttribute('aria-expanded', 'false');
            // blur input so user can focus elsewhere
            this.input.blur();
            if (this.config.allowManual) {
                this.input.focus({ preventScroll: true });
            }
            if (this.config.onClose) this.config.onClose(this);
        }

        positionCalendar() {
            const rect = this.input.getBoundingClientRect();
            const h = this.calendar.offsetHeight;
            let top = rect.bottom + window.scrollY + 8;
            let left = rect.left + window.scrollX;
            if (rect.bottom + h + 8 > window.innerHeight && rect.top > h) top = rect.top + window.scrollY - h - 8;
            const w = this.calendar.offsetWidth;
            if (left + w > window.innerWidth) left = Math.max(8, window.innerWidth - w - 8);
            this.calendar.style.top = `${top}px`;
            this.calendar.style.left = `${left}px`;
        }

        handleDayKeyboard(e, date) {
            const all = Array.from(this.calendar.querySelectorAll(`.${CLS.grid} .${CLS.day}:not(.${CLS.disabled})`));
            const i = all.indexOf(e.target);
            let next;
            switch (e.key) {
                case 'ArrowLeft': e.preventDefault(); next = i - 1; break;
                case 'ArrowRight': e.preventDefault(); next = i + 1; break;
                case 'ArrowUp': e.preventDefault(); next = i - 7; break;
                case 'ArrowDown': e.preventDefault(); next = i + 7; break;
                case 'Enter':
                case ' ': e.preventDefault(); this.selectDate(date); return;
                case 'Escape': e.preventDefault(); this.close(); this.input.focus(); return;
                case 'PageUp': e.preventDefault(); this.previousMonth(); return;
                case 'PageDown': e.preventDefault(); this.nextMonth(); return;
                default: return;
            }
            if (next >= 0 && next < all.length) all[next].focus();
        }

        attachEvents() {
            this.input.addEventListener('pointerdown', (e) => {
                if (e.button !== 0) return;
                if (!this.config.allowManual) e.preventDefault();
                this.input.focus({ preventScroll: true });
                this.open();
            });
            this.input.addEventListener('focus', () => this.open());
            this.input.addEventListener('click', (e) => { if (!this.config.allowManual) e.preventDefault(); this.open(); });
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Tab' || e.ctrlKey || e.metaKey || e.altKey) return;
                if (this.config.allowManual) return; // allow typing
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') { e.preventDefault(); this.open(); return; }
                if (e.key === 'Escape') { e.preventDefault(); this.close(); return; }
                // do not prevent default so copy/paste/navigation works
            });
            // synchronize calendar whenever the input value changes (typing/paste)
            this.input.addEventListener('input', () => {
                // convert bangla digits on the fly then parse
                this.input.value = U.normalizeDigits(this.input.value);
                this.setDateFromInput();
                this.render();
                this.updateInput();
            });
            this.input.addEventListener('blur', () => this.handleManualInput());

            // close on mousedown outside (captures before focus shifts)
            document.addEventListener('mousedown', (e) => {
                if (!this.isOpen) return;
                const target = e.target;
                if (this.calendar.contains(target) || this.input.contains(target)) return;
                if (e.composedPath) {
                    const path = e.composedPath();
                    if (path.includes(this.calendar) || path.includes(this.input)) return;
                }
                this.close();
                if (target !== this.input) target.focus && target.focus();
            }, true);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) { this.close(); this.input.focus(); }
            });
            window.addEventListener('scroll', () => { if (this.isOpen) this.positionCalendar(); }, { passive: true });
            window.addEventListener('resize', () => { if (this.isOpen) this.positionCalendar(); }, { passive: true });
        }

        destroy() {
            if (this.calendar && this.calendar.parentNode) this.calendar.parentNode.removeChild(this.calendar);
        }
    }

    const DatePicker = {
        instances: new Map(),
        stylesheetHref: null,
        config: {
            selector: 'input[type="date"], .datepicker',
            format: 'DD-MM-YYYY',
            locale: 'en',                  // language code for month/weekday names
            rtl: false,                    // flipped layout for rtl languages
            placeholder: '',               // custom placeholder
            firstDayOfWeek: 0,             // 0=Sun, 1=Mon
            minDate: null,
            maxDate: null,
            disablePast: false,
            disableFuture: false,
            disableWeekends: false,
            disabledDates: [],
            mode: 'single',
            withTime: false,              // show time picker controls
            timeFormat: 'HH:mm',          // format appended to date
            weekNumbers: false,           // display ISO week numbers
            cssPath: null,
            allowManual: false,
            onChange: null,
            onOpen: null,
            onClose: null,
            onMonthChange: null,
            onYearChange: null
        },
        ensureStylesheet() {
            if (!this.stylesheetHref) this.stylesheetHref = ensureStylesheet(this.config.cssPath);
            return this.stylesheetHref;
        },
        init(customConfig = {}) {
            this.config = { ...this.config, ...customConfig };
            this.ensureStylesheet();
            this.initInputs();
            this.observeDOM();
        },
        initInputs() {
            document.querySelectorAll(this.config.selector).forEach((input) => this.attachToInput(input));
        },
        attachToInput(input) {
            if (this.instances.has(input)) return;
            this.instances.set(input, new Instance(input, this.config));
        },
        observeDOM() {
            new MutationObserver((mutations) => {
                mutations.forEach((m) => m.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;
                    if (node.matches && node.matches(this.config.selector)) this.attachToInput(node);
                    if (node.querySelectorAll) node.querySelectorAll(this.config.selector).forEach((input) => this.attachToInput(input));
                }));
            }).observe(document.body, { childList: true, subtree: true });
        },
        getInstance(input) { return this.instances.get(input); },
        destroyAll() {
            this.instances.forEach((instance) => instance.destroy());
            this.instances.clear();
        }
    };

    window.DatePicker = DatePicker;
})();

document.addEventListener('DOMContentLoaded', function () {
    window.DatePicker.init({
        selector: 'input[type="date"], .datepicker',
        format: 'DD-MM-YYYY',
        locale: 'en',          // change to 'ar', 'fr', etc.
        mode: 'single',
        weekNumbers: false,
        withTime: false,
        allowManual: true
    });
});
