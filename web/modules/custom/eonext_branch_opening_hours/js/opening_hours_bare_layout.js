/**
 * @file
 * UK-style date labels for the bare opening hours React layout.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * Monday of ISO week 1..53 in the given calendar year.
   *
   * @param {number} week
   *   ISO week number.
   * @param {number} year
   *   Four-digit year.
   *
   * @return {Date}
   *   Local date at midnight for the Monday of that ISO week.
   */
  function isoWeekMonday(week, year) {
    const jan4 = new Date(year, 0, 4);
    const jan4Dow = jan4.getDay() || 7;
    const mondayWeek1 = new Date(jan4);
    mondayWeek1.setDate(jan4.getDate() - jan4Dow + 1);
    const monday = new Date(mondayWeek1);
    monday.setDate(mondayWeek1.getDate() + (week - 1) * 7);
    monday.setHours(0, 0, 0, 0);
    return monday;
  }

  /**
   * Parses week number and year from the React week strip (any locale).
   *
   * @param {string} text
   *   Raw week label text.
   *
   * @return {{ week: number, year: number } | null}
   *   Parsed values or null.
   */
  function parseWeekYear(text) {
    const m = String(text).match(/(\d+),\s*(\d{4})\s*$/);
    if (!m) {
      return null;
    }
    return { week: parseInt(m[1], 10), year: parseInt(m[2], 10) };
  }

  /**
   * Reformats day headings inside the opening hours app.
   *
   * @param {HTMLElement} layoutRoot
   *   Wrapper including the React mount.
   */
  function applyUkDayDates(layoutRoot) {
    const weekDisplay = layoutRoot.querySelector('.opening-hours__week-display');
    if (!weekDisplay) {
      return;
    }
    const parsed = parseWeekYear(weekDisplay.textContent);
    if (!parsed) {
      return;
    }
    const weekStart = isoWeekMonday(parsed.week, parsed.year);
    const formatter = new Intl.DateTimeFormat('en-GB', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
    });

    layoutRoot.querySelectorAll('h3.opening-hours__individual-day').forEach((heading) => {
      const raw = heading.textContent.trim();
      const m = raw.match(/^(.+?):\s*d\.\s*(\d{1,2})\/(\d{1,2})$/);
      if (!m) {
        return;
      }
      const dayNum = parseInt(m[2], 10);
      const monthNum = parseInt(m[3], 10);
      for (let i = 0; i < 7; i += 1) {
        const candidate = new Date(weekStart);
        candidate.setDate(weekStart.getDate() + i);
        if (candidate.getDate() === dayNum && candidate.getMonth() + 1 === monthNum) {
          heading.textContent = formatter.format(candidate);
          return;
        }
      }
    });
  }

  /**
   * Observes the React subtree for re-renders (week navigation, loading).
   *
   * @param {HTMLElement} layoutRoot
   *   Wrapper including the React mount.
   */
  function observeOpeningHours(layoutRoot) {
    const appRoot = layoutRoot.querySelector('[data-dpl-app="opening-hours"]');
    if (!appRoot) {
      return;
    }
    applyUkDayDates(layoutRoot);
    const observer = new MutationObserver(() => {
      applyUkDayDates(layoutRoot);
    });
    observer.observe(appRoot, { childList: true, subtree: true });
  }

  /**
   * Waits for the React app mount then attaches week/day formatting.
   *
   * @param {HTMLElement} layoutRoot
   *   Wrapper including eventual React mount.
   */
  function bindWhenReady(layoutRoot) {
    if (layoutRoot.querySelector('[data-dpl-app="opening-hours"]')) {
      observeOpeningHours(layoutRoot);
      return;
    }
    const waiter = new MutationObserver(() => {
      if (layoutRoot.querySelector('[data-dpl-app="opening-hours"]')) {
        waiter.disconnect();
        observeOpeningHours(layoutRoot);
      }
    });
    waiter.observe(layoutRoot, { childList: true, subtree: true });
  }

  Drupal.behaviors.eonextBranchOpeningHoursBareLayout = {
    attach(context) {
      once('eonext-branch-opening-hours-bare-layout', '.opening-hours-bare-layout', context).forEach(
        (layoutRoot) => {
          bindWhenReady(layoutRoot);
        },
      );
    },
  };
})(Drupal, once);
