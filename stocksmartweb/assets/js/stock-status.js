/**
 * ============================================================================
 *  StockSmart — Stock & Expiry Status (client mirror)
 * ============================================================================
 *  Browser-side twin of helpers/stock_status.php. The tables on products.html,
 *  inventory.html and dashboard.html classify rows in JS after the API hands
 *  them raw numbers, so the rules have to exist in both languages.
 *
 *  THESE RULES MUST STAY IDENTICAL TO helpers/stock_status.php.
 *  If you change a threshold here, change it there too (and vice versa) —
 *  the whole point of both files is that a product can never be "Low Stock"
 *  on one screen and "In Stock" on another.
 *
 *  Stock (available = on_hand - reserved, summed across warehouses):
 *    available <= 0                    -> Out of Stock
 *    available <= ceil(reorder * 0.4)  -> Critical
 *    available <= reorder              -> Low Stock
 *    otherwise                         -> In Stock
 *
 *  Expiry (batch level; null = non-perishable, never alerts):
 *    null                              -> No Expiry
 *    date <  today                     -> Expired
 *    date <= today + 2 calendar months -> Expiring Soon
 *    otherwise                         -> Valid
 * ========================================================================== */
(function (global) {
  'use strict';

  var STOCK_CRITICAL_RATIO = 0.4;
  var EXPIRY_WARNING_MONTHS = 2;

  /**
   * @param {number} available on_hand - reserved
   * @param {number} reorderLevel the product's own products.reorder_level
   * @returns {{key:string,label:string,severity:string,cls:string}}
   */
  function stockState(available, reorderLevel) {
    available = Number(available) || 0;
    reorderLevel = Number(reorderLevel) || 0;

    if (available <= 0) {
      return { key: 'out_of_stock', label: 'Out of Stock', severity: 'critical', cls: 'out' };
    }
    if (reorderLevel > 0 && available <= Math.ceil(reorderLevel * STOCK_CRITICAL_RATIO)) {
      return { key: 'critical', label: 'Critical', severity: 'critical', cls: 'critical' };
    }
    if (available <= reorderLevel) {
      return { key: 'low_stock', label: 'Low Stock', severity: 'warning', cls: 'low' };
    }
    return { key: 'in_stock', label: 'In Stock', severity: 'info', cls: 'in' };
  }

  /** Out of Stock, Critical and Low Stock all need restocking. */
  function needsRestock(available, reorderLevel) {
    return (Number(available) || 0) <= (Number(reorderLevel) || 0);
  }

  function startOfToday() {
    var d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
  }

  /** Parses 'YYYY-MM-DD' as a LOCAL date — new Date(str) would read it as UTC. */
  function parseDate(value) {
    if (!value) return null;
    var parts = String(value).slice(0, 10).split('-');
    if (parts.length !== 3) return null;
    var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    return isNaN(d.getTime()) ? null : d;
  }

  /**
   * @param {?string} expiryDate 'YYYY-MM-DD', or null/'' for non-perishable
   * @returns {{key:string,label:string,severity:string,cls:string,daysLeft:?number}}
   */
  function expiryState(expiryDate) {
    var expiry = parseDate(expiryDate);
    if (!expiry) {
      return { key: 'none', label: 'No Expiry', severity: 'info', cls: 'none', daysLeft: null };
    }

    var today = startOfToday();
    var daysLeft = Math.round((expiry - today) / 86400000);

    // Calendar-month horizon, matching MySQL's INTERVAL 2 MONTH: setMonth
    // clamps overflow (31 Dec + 2 months -> 28/29 Feb), same as MySQL.
    var horizon = new Date(today.getFullYear(), today.getMonth() + EXPIRY_WARNING_MONTHS, today.getDate());

    if (expiry < today) {
      return { key: 'expired', label: 'Expired', severity: 'critical', cls: 'expired', daysLeft: daysLeft };
    }
    if (expiry <= horizon) {
      return { key: 'expiring_soon', label: 'Expiring Soon', severity: 'warning', cls: 'soon', daysLeft: daysLeft };
    }
    return { key: 'valid', label: 'Valid', severity: 'info', cls: 'valid', daysLeft: daysLeft };
  }

  /** '15 Oct 2026' — the format used across alert text and batch tables. */
  function formatExpiry(expiryDate) {
    var d = parseDate(expiryDate);
    if (!d) return 'No expiry';
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  /** 'Expired 4 days ago' / 'Expires today' / 'in 21 days' — for right-aligned cells. */
  function expiryCountdown(expiryDate) {
    var state = expiryState(expiryDate);
    if (state.key === 'none') return '—';
    if (state.daysLeft === 0) return 'Expires today';
    if (state.daysLeft < 0) {
      var ago = Math.abs(state.daysLeft);
      return 'Expired ' + ago + ' day' + (ago === 1 ? '' : 's') + ' ago';
    }
    return 'in ' + state.daysLeft + ' day' + (state.daysLeft === 1 ? '' : 's');
  }

  global.StockStatus = {
    STOCK_CRITICAL_RATIO: STOCK_CRITICAL_RATIO,
    EXPIRY_WARNING_MONTHS: EXPIRY_WARNING_MONTHS,
    stockState: stockState,
    needsRestock: needsRestock,
    expiryState: expiryState,
    formatExpiry: formatExpiry,
    expiryCountdown: expiryCountdown
  };
})(window);
