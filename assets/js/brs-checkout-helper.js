(function(){
  if (window.__BRS_FETCH_PATCHED__) return;
  window.__BRS_FETCH_PATCHED__ = true;

  var token = (window.BRSCheckout && window.BRSCheckout.token) ? window.BRSCheckout.token : '';

  // Patch fetch
  var origFetch = window.fetch;
  if (origFetch) {
    window.fetch = function(input, init) {
      init = init || {};
      init.headers = init.headers || {};
      try {
        if (typeof Headers !== 'undefined' && init.headers instanceof Headers) {
          if (!init.headers.has('X-BRS-TOKEN')) init.headers.set('X-BRS-TOKEN', token);
        } else if (Array.isArray(init.headers)) {
          var has = init.headers.some(function(p){ return (p[0]+'').toLowerCase() === 'x-brs-token'; });
          if (!has) init.headers.push(['X-BRS-TOKEN', token]);
        } else {
          if (!init.headers['X-BRS-TOKEN']) init.headers['X-BRS-TOKEN'] = token;
        }
      } catch (e) {}
      return origFetch(input, init);
    };
  }

  // Patch XHR (covers jQuery.ajax)
  if (typeof XMLHttpRequest !== 'undefined') {
    var OrigXHR = XMLHttpRequest;
    XMLHttpRequest = function() {
      var xhr = new OrigXHR();
      var _open = xhr.open;
      var _setHeader = xhr.setRequestHeader;
      var headerSet = false;
      xhr.open = function() { headerSet = false; return _open.apply(xhr, arguments); };
      xhr.setRequestHeader = function(k, v) {
        if (k && (k+'').toLowerCase() === 'x-brs-token') headerSet = true;
        return _setHeader.call(xhr, k, v);
      };
      xhr.addEventListener('readystatechange', function(){
        if (!headerSet && xhr.readyState === 1) { try { xhr.setRequestHeader('X-BRS-TOKEN', token); } catch(e) {} headerSet = true; }
      });
      return xhr;
    };
  }
})();