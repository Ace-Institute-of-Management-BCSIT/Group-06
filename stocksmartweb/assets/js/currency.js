/**
 * Client-side twin of helpers/currency.php — same "Rs. 1,25,000" Nepali
 * digit grouping everywhere a peso amount is rendered in the browser.
 */
function fmtRs(amount) {
  const n = Math.round(Number(amount) || 0);
  const sign = n < 0 ? '-' : '';
  return sign + 'Rs. ' + Math.abs(n).toLocaleString('en-IN');
}
