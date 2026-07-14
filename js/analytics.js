/**
 * Analytics first-party + consentimento de cookies (GA4 opcional).
 */
(function () {
  var STORAGE_CONSENT = "jota_cookie_consent";
  var STORAGE_VISITOR = "jota_vid";
  var STORAGE_SESSION = "jota_sid";
  var GA_ID = "G-S6CGMRRYNT";
  var TRACK_URL = "php/track.php";
  var sessionStartedAt = Date.now();

  function uid(prefix) {
    return (
      prefix +
      "_" +
      Math.random().toString(36).slice(2, 10) +
      Date.now().toString(36)
    );
  }

  function getOrCreate(key, prefix, sessionOnly) {
    try {
      var store = sessionOnly ? sessionStorage : localStorage;
      var value = store.getItem(key);
      if (!value) {
        value = uid(prefix);
        store.setItem(key, value);
      }
      return value;
    } catch (e) {
      return uid(prefix);
    }
  }

  function getConsent() {
    try {
      return localStorage.getItem(STORAGE_CONSENT);
    } catch (e) {
      return null;
    }
  }

  function setConsent(value) {
    try {
      localStorage.setItem(STORAGE_CONSENT, value);
    } catch (e) {}
  }

  function sessionDuration() {
    return Math.max(0, Math.round((Date.now() - sessionStartedAt) / 1000));
  }

  function send(type, extra) {
    var payload = {
      type: type,
      path: window.location.pathname + window.location.search + window.location.hash,
      referrer: document.referrer || "",
      visitor_id: getOrCreate(STORAGE_VISITOR, "v", false),
      session_id: getOrCreate(STORAGE_SESSION, "s", true),
      duration: sessionDuration(),
    };
    if (extra) {
      Object.keys(extra).forEach(function (key) {
        payload[key] = extra[key];
      });
    }
    var body = JSON.stringify(payload);
    try {
      if (navigator.sendBeacon) {
        navigator.sendBeacon(TRACK_URL, new Blob([body], { type: "application/json" }));
        return;
      }
    } catch (e) {}
    fetch(TRACK_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: body,
      keepalive: true,
    }).catch(function () {});
  }

  window.jotaTrack = send;

  function loadGa() {
    if (window.__jotaGaLoaded || !GA_ID) return;
    window.__jotaGaLoaded = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () {
      window.dataLayer.push(arguments);
    };
    window.gtag("js", new Date());
    window.gtag("config", GA_ID, { anonymize_ip: true });
    var s = document.createElement("script");
    s.async = true;
    s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(GA_ID);
    document.head.appendChild(s);
  }

  function hideBanner() {
    var el = document.getElementById("cookieBanner");
    if (el) el.classList.remove("is-visible");
  }

  function showBanner() {
    var el = document.getElementById("cookieBanner");
    if (el) el.classList.add("is-visible");
  }

  function isExternalLink(href) {
    if (!href || href.charAt(0) === "#" || href.indexOf("javascript:") === 0) {
      return false;
    }
    try {
      var url = new URL(href, window.location.href);
      return url.origin !== window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function bindClicks() {
    document.addEventListener("click", function (event) {
      var link = event.target.closest("a");
      if (!link) return;
      var href = (link.getAttribute("href") || "").trim();

      if (href.indexOf("wa.me/") !== -1 || href.indexOf("whatsapp.com") !== -1) {
        send("whatsapp_click");
        return;
      }

      if (href.indexOf("mailto:") === 0) {
        send("email_click");
        return;
      }

      if (
        link.classList.contains("btn") ||
        link.classList.contains("whatsapp-float") ||
        link.closest(".mobile-cta-bar")
      ) {
        send("cta_click");
      }

      if (isExternalLink(href)) {
        send("link_click");
      }
    });
  }

  function startHeartbeat() {
    setInterval(function () {
      if (document.visibilityState === "hidden") return;
      send("heartbeat");
    }, 45000);

    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "hidden") {
        send("heartbeat");
      }
    });

    window.addEventListener("pagehide", function () {
      send("heartbeat");
    });
  }

  function initConsent() {
    var consent = getConsent();
    if (consent === "accepted") {
      loadGa();
      hideBanner();
      return;
    }
    if (consent === "rejected") {
      hideBanner();
      return;
    }
    showBanner();
    var accept = document.getElementById("cookieAccept");
    var reject = document.getElementById("cookieReject");
    if (accept) {
      accept.addEventListener("click", function () {
        setConsent("accepted");
        loadGa();
        hideBanner();
      });
    }
    if (reject) {
      reject.addEventListener("click", function () {
        setConsent("rejected");
        hideBanner();
      });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    send("pageview");
    bindClicks();
    startHeartbeat();
    initConsent();
  });
})();
