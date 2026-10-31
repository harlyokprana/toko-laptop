<script>
(function(){
  'use strict';
  var _NativeParseFloat = window.parseFloat;
  function parseMoney(value){
    if (value === null || value === undefined) return 0;
    if (typeof value === 'number') return isFinite(value) ? value : 0;
    var s = String(value).trim();
    s = s.replace(/[^\d,.\-]/g, '');
    if (s === '' || s === '-' ) return 0;
    var hasComma = s.indexOf(',') > -1;
    var hasDot = s.indexOf('.') > -1;
    if (hasComma && hasDot) {
      if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
        s = s.replace(/\./g, '').replace(',', '.');
      } else {
        s = s.replace(/,/g, '');
      }
    } else if (hasComma) {
      var parts = s.split(',');
      if (parts.length === 2 && parts[1].length > 0 && parts[1].length <= 2) {
        s = parts[0].replace(/\./g, '') + '.' + parts[1];
      } else {
        s = s.replace(/,/g, '');
      }
  } else if (hasDot) {
    var parts = s.split('.');
    if (parts.length > 2) { s = s.replace(/\./g, ''); }
    else if (parts.length === 2) {
      if (parts[1].length === 3 && /^\d+$/.test(parts[0])) { s = s.replace(/\./g, ''); }
      else if (parts[1].length > 2) { s = s.replace(/\./g, ''); }
    }
    }
    var n = _NativeParseFloat(s);
    return isNaN(n) ? 0 : n;
  }
  function formatMoney(value, opts){
    opts = opts || {};
    var n = parseMoney(value);
    var decimals = opts.decimals;
    if (decimals === undefined) {
      var vs = String(value).trim();
      var m = vs.match(/[.,](\d+)$/);
      decimals = m ? Math.min(m[1].length, 2) : 0;
    }
    var fixed = n.toFixed(decimals);
    var parts = fixed.split('.');
    var intPart = parts[0];
    var sign = intPart.indexOf('-') === 0 ? '-' : '';
    if (sign) intPart = intPart.slice(1);
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    var out = sign + intPart;
    if (decimals > 0 && parts[1]) out += ',' + parts[1];
    return out;
  }
  function formatRupiah(value, opts){ return 'Rp ' + formatMoney(value, opts); }

  window.parseMoney = parseMoney;
  window.formatMoney = formatMoney;
  window.formatRupiah = formatRupiah;

  // Patch Number() so existing code reading formatted money inputs gets raw numbers
  try {
    var _NativeNumber = window.Number;
    function _MoneyNumber(value) {
      if (this instanceof _MoneyNumber) {
        return new _NativeNumber(arguments.length ? (typeof value === 'string' ? parseMoney(value) : value) : 0);
      }
      if (arguments.length === 0) return _NativeNumber();
      if (typeof value === 'string') return parseMoney(value);
      return _NativeNumber(value);
    }
    _MoneyNumber.__proto__ = _NativeNumber;
    _MoneyNumber.prototype = _NativeNumber.prototype;
    window.Number = _MoneyNumber;
  } catch(e){}

  // Patch parseFloat similarly
  try {
    var _NativeParseFloat = window.parseFloat;
    window.parseFloat = function(value){ 
      if (typeof value === 'string') return parseMoney(value);
      return _NativeParseFloat(value);
    };
  } catch(e){}

  var EXCLUDE_RE = /method|type|status|name|email|phone|telepon|kode|code|date|alamat|address|desc|keterangan|note|quantity|qty|stok|stock|jumlah/i;
  var MONEY_RE = /(price|harga|cost|hpp|amount|nominal|total|subtotal|grand|bayar|paid|payment|change|kembalian|saldo|balance|income|expense|debit|credit|cash|donation|donasi|iuran|fee|biaya|diskon|discount|tax|pajak|revenue|profit|laba|target|collected|budget|anggaran)/i;

  function isMoneyInput(el){
    if (!el || el.nodeName !== 'INPUT') return false;
    if (el.dataset.money === 'false') return false;
    if (el.dataset.money === 'true') return true;
    if (el.type === 'hidden' || el.type === 'checkbox' || el.type === 'radio' || el.type === 'file' || el.type === 'date' || el.type === 'email' || el.type === 'password' || el.type === 'search') return false;
    var name = (el.name || '') + ' ' + (el.id || '') + ' ' + (el.getAttribute('placeholder') || '');
    if (EXCLUDE_RE.test(name)) return false;
    if (!MONEY_RE.test(name)) return false;
    if (el.type === 'text' && el.value && /[a-zA-Z]/.test(el.value)) return false;
    return (el.type === 'number' || el.type === 'text' || el.type === '');

  }

  function enhanceInput(el){
    if (!el || el.dataset.moneyEnhanced === '1') return;
    if (!isMoneyInput(el)) return;
    el.dataset.moneyEnhanced = '1';
    if (el.type === 'number') { el.type = 'text'; el.inputMode = 'numeric'; }
    if (!el.inputMode) el.inputMode = 'numeric';

    if (el.value !== '' && el.value !== null && el.value !== undefined) {
      el.dataset.raw = String(parseMoney(el.value));
      el.value = formatMoney(el.value);
    } else {
      el.dataset.raw = '';
    }

    el.addEventListener('input', function(){
      var cursor = (el.selectionStart === null) ? el.value.length : el.selectionStart;
      var oldVal = el.value;
      var digitsBefore = oldVal.slice(0, cursor).replace(/\D/g, '').length;

      // Preserve at most one decimal separator (comma or dot) if present at end-ish
      var rawStr = oldVal.replace(/[^\d.,]/g, '');
      var decMatch = rawStr.match(/[.,](\d{0,2})$/);
      var intDigits = '';
      var decDigits = '';
      if (decMatch) {
        decDigits = decMatch[1];
        var idx = rawStr.lastIndexOf(decMatch[0]);
        intDigits = rawStr.slice(0, idx).replace(/\D/g, '');
      } else {
        intDigits = rawStr.replace(/\D/g, '');
      }
      if (intDigits === '' && decDigits === '') { el.dataset.raw = ''; el.value = ''; return; }
      if (intDigits === '') intDigits = '0';

      var num = parseFloat(intDigits + (decDigits ? '.' + decDigits : ''));
      if (isNaN(num)) num = 0;
      el.dataset.raw = String(num);

      var formattedInt = intDigits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      var formatted = decDigits ? (formattedInt + ',' + decDigits) : formattedInt;
      el.value = formatted;

      // restore cursor by digit count
      var pos = 0, count = 0;
      for (var i = 0; i < formatted.length; i++) {
        if (/\d/.test(formatted[i])) count++;
        if (count >= digitsBefore) { pos = i + 1; break; }
      }
      if (digitsBefore === 0) pos = 0;
      if (count < digitsBefore) pos = formatted.length;
      try { el.setSelectionRange(pos, pos); } catch(e){}
    });
    el.addEventListener('blur', function(){
      if (el.value === '') { el.dataset.raw = ''; return; }
      var raw = parseMoney(el.value);
      el.dataset.raw = String(raw);
      el.value = formatMoney(raw);
    });
  }

  function enhanceAll(root){
    root = root || document;
    if (root.querySelectorAll) {
      root.querySelectorAll('input').forEach(enhanceInput);
    } else if (root && root.nodeName === 'INPUT') {
      enhanceInput(root);
    }
  }

  document.addEventListener('submit', function(e){
    var form = e.target;
    if (!form || form.nodeName !== 'FORM') return;
    var inputs = form.querySelectorAll('input[data-money-enhanced="1"]');
    inputs.forEach(function(el){
      var raw = (el.dataset.raw !== undefined && el.dataset.raw !== '') ? el.dataset.raw : String(parseMoney(el.value));
      el.dataset.displayValue = el.value;
      el.value = raw;
    });
    setTimeout(function(){
      inputs.forEach(function(el){
        if (el.dataset.displayValue) {
          el.value = el.dataset.displayValue;
          delete el.dataset.displayValue;
        }
      });
    }, 0);
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ enhanceAll(document); });
  } else {
    enhanceAll(document);
  }
  try {
    var obs = new MutationObserver(function(muts){
      muts.forEach(function(m){
        m.addedNodes.forEach(function(n){
          if (n && n.nodeType === 1) {
            if (n.nodeName === 'INPUT') enhanceInput(n);
            else enhanceAll(n);
          }
        });
      });
    });
    obs.observe(document.documentElement, { childList: true, subtree: true });
  } catch(e){}
})();
</script>