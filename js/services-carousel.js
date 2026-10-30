/* SnapVenture - Services page package carousels (camera + photobooth).
   Pulls live data from ../api/public/packages.php (MySQL-backed).

   How the carousel works now:
   - Every package gets ONE card, built once. Navigating never rebuilds the
     DOM; it only changes each card's data-pos (-3..3) and CSS animates the
     move (see css/services-carousel.css).
   - Every card has the same fixed size. Only the "What's included" list
     scrolls inside the card, so no detail is ever cut off. */
(function () {
  "use strict";

  function peso(n) {
    return "\u20B1" + Number(n || 0).toLocaleString("en-US");
  }
  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  }

  /* Database stores paths like "images/cameras/x.jpg" (relative to the
     project root). This page lives in /pages/, so we need to climb one
     folder up first. Leave absolute URLs (http...) or already-prefixed
     paths (../..., /...) untouched. */
  function resolveImg(url) {
    if (!url) return url;
    if (
      /^(https?:)?\/\//.test(url) ||
      url.charAt(0) === "/" ||
      url.indexOf("../") === 0
    ) {
      return url;
    }
    return "../" + url;
  }

  var ICON_PREV =
    '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' +
    '<path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var ICON_NEXT =
    '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' +
    '<path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

  var ICON_PHOTO =
    '<svg class="pkg-media-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
    '<path d="M4 8a2 2 0 0 1 2-2h1.5l1-1.5h7L16.5 6H18a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>' +
    '<circle cx="12" cy="12.5" r="3.4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>';

  /* ---------- price formatting (camera) ---------- */
  function cameraPriceHTML(cam) {
    if (
      cam.pricing_type === "per_day" &&
      cam.rate_tiers &&
      cam.rate_tiers.length
    ) {
      var lines = cam.rate_tiers.map(function (t) {
        var range = t.max_days
          ? t.min_days + "-" + t.max_days + " days"
          : t.min_days + "+ days";
        return peso(t.rate_per_day) + "/day <small>(" + range + ")</small>";
      });
      return lines.join("<br>");
    }
    if (cam.pricing_type === "per_day_and_hour" && cam.flat_hourly) {
      var f = cam.flat_hourly;
      return (
        peso(f.flat_price) +
        " <small>(1-" +
        f.flat_days +
        " days)</small><br>+" +
        peso(f.extra_hour_rate) +
        " <small>/additional hr</small>"
      );
    }
    if (cam.pricing_type === "per_photo" && cam.photo_tiers) {
      var lines2 = cam.photo_tiers.map(function (t) {
        var range = t.max_qty
          ? t.min_qty + "-" + t.max_qty + " pcs"
          : t.min_qty + "+ pcs";
        return (
          peso(t.price_per_photo) + "/photo <small>(" + range + ")</small>"
        );
      });
      return lines2.join("<br>");
    }
    return "Contact us for pricing";
  }

  /* ---------- card building blocks ---------- */
  function mediaHTML(url, alt) {
    if (!url) {
      return (
        '<div class="pkg-media pkg-media--empty" aria-hidden="true">' +
        ICON_PHOTO +
        "</div>"
      );
    }
    return (
      '<div class="pkg-media"><img src="' +
      esc(resolveImg(url)) +
      '" alt="' +
      esc(alt) +
      '" loading="lazy" decoding="async"></div>'
    );
  }

  function headHTML(name, subtitle) {
    return (
      '<div class="pkg-head"><h3 class="pkg-name">' +
      esc(name) +
      "</h3>" +
      (subtitle ? '<p class="pkg-subtitle">' + esc(subtitle) + "</p>" : "") +
      "</div>"
    );
  }

  /* The inclusions list is the ONLY part of the card that scrolls. */
  function detailsHTML(name, items) {
    if (!items.length) return '<div class="pkg-details"></div>';
    return (
      '<div class="pkg-details">' +
      '<p class="pkg-details-title">What\u2019s included</p>' +
      '<div class="pkg-details-scroll" tabindex="0" role="region" aria-label="Details for ' +
      esc(name) +
      '">' +
      '<ul class="check-list">' +
      items
        .map(function (i) {
          return "<li>" + esc(i) + "</li>";
        })
        .join("") +
      "</ul></div></div>"
    );
  }

  function actionsHTML(href) {
    return (
      '<div class="pkg-actions"><a class="btn btn-primary" href="' +
      href +
      '">Book Now</a></div>'
    );
  }

  /* ---------- card renderers ---------- */
  function cameraCardHTML(cam) {
    return (
      mediaHTML(cam.image_url, cam.name) +
      headHTML(cam.name, cam.subtitle) +
      '<div class="pkg-pricebox"><div class="pkg-price">' +
      cameraPriceHTML(cam) +
      "</div></div>" +
      detailsHTML(cam.name, cam.inclusions || []) +
      actionsHTML(
        "booking.php?service=camera_rental&amp;camera_id=" +
          encodeURIComponent(cam.camera_id),
      )
    );
  }

  function photoboothCardHTML(pkg) {
    var incl = (pkg.extra_inclusions || []).concat(pkg.shared_inclusions || []);
    return (
      mediaHTML(null, pkg.name) +
      headHTML(pkg.name, pkg.pax_min + "-" + pkg.pax_max + " pax") +
      '<div class="pkg-pricebox"><div class="pkg-price pkg-price--lg">' +
      peso(pkg.price) +
      "</div>" +
      (pkg.duration_note
        ? '<p class="pkg-meta">' + esc(pkg.duration_note) + "</p>"
        : "") +
      "</div>" +
      detailsHTML(pkg.name, incl) +
      actionsHTML(
        "booking.php?service=photobooth&amp;photobooth_id=" +
          encodeURIComponent(pkg.photobooth_id),
      )
    );
  }

  /* ---------- center-focused looping carousel ---------- */
  function make(tag, cls) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    return node;
  }

  function Carousel(root, items, cardHTMLFn, label) {
    var n = items.length;
    if (!n) {
      root.innerHTML = '<p class="pkg-loading">No packages available.</p>';
      return;
    }

    var current = 0;
    /* how many cards are visible on each side of the centre card */
    var span = Math.min(2, Math.floor((n - 1) / 2));
    var LOCK_MS = 380; /* ignore clicks while a slide is still travelling */
    var busy = false;
    var queued = null;
    var resetTimer = null;
    var reduceMotion = window.matchMedia
      ? window.matchMedia("(prefers-reduced-motion: reduce)")
      : { matches: false };

    root.innerHTML = "";
    root.removeAttribute("aria-live");
    root.setAttribute("role", "region");
    root.setAttribute("aria-roledescription", "carousel");
    root.setAttribute("aria-label", label || "Packages");
    root.classList.add("is-booting", "is-intro");

    var stage = make("div", "pkg-stage");
    var slides = items.map(function (item, i) {
      var s = make("article", "pkg-slide");
      s.setAttribute("data-idx", i);
      s.setAttribute("role", "group");
      s.setAttribute("aria-roledescription", "slide");
      s.setAttribute("aria-label", i + 1 + " of " + n);
      s.innerHTML = cardHTMLFn(item);
      return s;
    });
    slides.forEach(function (s) {
      stage.appendChild(s);
    });
    root.appendChild(stage);

    var status = make("p", "pkg-sr");
    status.setAttribute("aria-live", "polite");
    status.setAttribute("aria-atomic", "true");
    root.appendChild(status);

    var dots = [];
    if (n > 1) {
      var navRow = make("div", "pkg-nav-row");
      var prevBtn = make("button", "pkg-nav-btn");
      prevBtn.type = "button";
      prevBtn.setAttribute("aria-label", "Previous");
      prevBtn.innerHTML = ICON_PREV;
      var nextBtn = make("button", "pkg-nav-btn");
      nextBtn.type = "button";
      nextBtn.setAttribute("aria-label", "Next");
      nextBtn.innerHTML = ICON_NEXT;
      var dotsWrap = make("div", "pkg-dots");
      items.forEach(function (_, i) {
        var d = make("button", "pkg-dot");
        d.type = "button";
        d.setAttribute("data-idx", i);
        d.setAttribute("aria-label", "Go to slide " + (i + 1));
        dotsWrap.appendChild(d);
        dots.push(d);
      });
      navRow.appendChild(prevBtn);
      navRow.appendChild(dotsWrap);
      navRow.appendChild(nextBtn);
      root.appendChild(navRow);

      prevBtn.addEventListener("click", function () {
        step(-1);
      });
      nextBtn.addEventListener("click", function () {
        step(1);
      });
      dotsWrap.addEventListener("click", function (e) {
        var dot = e.target.closest(".pkg-dot");
        if (dot) jump(parseInt(dot.getAttribute("data-idx"), 10));
      });
    }

    function wrap(i) {
      return ((i % n) + n) % n;
    }

    /* shortest signed distance from the centre card to card i */
    function offsetOf(i) {
      var d = wrap(i - current);
      if (d > n / 2) d -= n;
      return d;
    }

    function update() {
      for (var i = 0; i < n; i++) {
        var s = slides[i];
        var d = offsetOf(i);
        var hidden = Math.abs(d) > span;
        var isCenter = d === 0;
        var pos = Math.max(-(span + 1), Math.min(span + 1, d));
        var wasParked = s.classList.contains("is-parked");

        s.setAttribute("data-pos", pos);
        s.style.setProperty("--enter", Math.abs(d));
        s.classList.toggle("is-center", isCenter);
        s.classList.toggle("is-side", !hidden && !isCenter);
        s.classList.toggle("is-parked", hidden);
        /* cards that stay off-screen skip the animation so they never
           fly across the stage while invisible */
        s.classList.toggle("is-jump", hidden && wasParked);

        /* only the centre card is reachable by keyboard / screen reader */
        if (isCenter) s.removeAttribute("aria-hidden");
        else s.setAttribute("aria-hidden", "true");
        var links = s.querySelectorAll("a");
        for (var k = 0; k < links.length; k++) {
          if (isCenter) links[k].removeAttribute("tabindex");
          else links[k].setAttribute("tabindex", "-1");
        }
        var scroller = s.querySelector(".pkg-details-scroll");
        if (scroller) scroller.setAttribute("tabindex", isCenter ? "0" : "-1");
      }
      for (var j = 0; j < dots.length; j++) {
        var on = j === current;
        dots[j].classList.toggle("is-active", on);
        if (on) dots[j].setAttribute("aria-current", "true");
        else dots[j].removeAttribute("aria-current");
      }
    }

    function scrollToTop(slide) {
      var sc = slide.querySelector(".pkg-details-scroll");
      if (sc) sc.scrollTop = 0;
    }

    function commit(idx) {
      var target = wrap(idx);
      if (target === current) return;
      root.classList.remove("is-intro");
      current = target;
      update();

      /* the card that just arrived starts at the top of its details; the
         card that just left is reset after it has slid away */
      scrollToTop(slides[current]);
      clearTimeout(resetTimer);
      resetTimer = setTimeout(function () {
        for (var i = 0; i < n; i++) if (i !== current) scrollToTop(slides[i]);
      }, 750);

      status.textContent =
        items[current].name + ", " + (current + 1) + " of " + n;

      if (!reduceMotion.matches) {
        busy = true;
        setTimeout(function () {
          busy = false;
          if (queued) {
            var fn = queued;
            queued = null;
            fn();
          }
        }, LOCK_MS);
      }
    }

    /* If a slide is still travelling, remember the latest click and run it
       as soon as the animation settles (no overlapping animations). */
    function request(fn) {
      if (busy) {
        queued = fn;
        return;
      }
      fn();
    }
    function step(dir) {
      request(function () {
        commit(current + dir);
      });
    }
    function jump(idx) {
      request(function () {
        commit(idx);
      });
    }

    /* click a faded side card to bring it to the centre */
    stage.addEventListener("click", function (e) {
      var slide = e.target.closest(".pkg-slide.is-side");
      if (!slide || e.target.closest("a")) return;
      jump(parseInt(slide.getAttribute("data-idx"), 10));
    });

    /* keyboard: left / right arrows */
    root.addEventListener("keydown", function (e) {
      if (e.key === "ArrowLeft") {
        e.preventDefault();
        step(-1);
      } else if (e.key === "ArrowRight") {
        e.preventDefault();
        step(1);
      }
    });

    /* touch swipe. The stage uses touch-action: pan-y, so the browser keeps
       vertical gestures (page scroll + scrolling the details) and we only
       see clearly horizontal swipes. */
    var sx = 0;
    var sy = 0;
    var tracking = false;
    stage.addEventListener("pointerdown", function (e) {
      if (e.pointerType === "mouse") return;
      tracking = true;
      sx = e.clientX;
      sy = e.clientY;
    });
    stage.addEventListener("pointerup", function (e) {
      if (!tracking) return;
      tracking = false;
      var dx = e.clientX - sx;
      var dy = e.clientY - sy;
      if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy) * 1.4) {
        step(dx < 0 ? 1 : -1);
      }
    });
    stage.addEventListener("pointercancel", function () {
      tracking = false;
    });

    /* if a photo fails to load, fall back to the plain placeholder */
    stage.addEventListener(
      "error",
      function (e) {
        var t = e.target;
        if (t && t.tagName === "IMG" && t.parentNode) {
          var holder = t.parentNode;
          holder.classList.add("pkg-media--empty");
          holder.removeChild(t);
          holder.insertAdjacentHTML("afterbegin", ICON_PHOTO);
        }
      },
      true,
    );

    update();
    /* first paint: place cards without animating, then play the intro */
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        root.classList.remove("is-booting");
      });
    });
    setTimeout(function () {
      root.classList.remove("is-intro");
    }, 1200);
  }

  /* ---------- accessory / add-on mini cards ---------- */
  function renderMiniCards(root, items, unitSuffix) {
    root.innerHTML = items
      .map(function (item) {
        return (
          '<div class="card addon-mini"><h3>' +
          esc(item.name) +
          '</h3><div class="price">' +
          peso(item.price) +
          (unitSuffix ? " <small>" + unitSuffix + "</small>" : "") +
          "</div></div>"
        );
      })
      .join("");
  }

  /* ---------- boot ---------- */
  var camRoot = document.getElementById("cameraCarousel");
  var pbRoot = document.getElementById("photoboothCarousel");
  if (!camRoot && !pbRoot) return;

  fetch("../api/public/packages.php")
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      if (camRoot)
        new Carousel(
          camRoot,
          data.cameras || [],
          cameraCardHTML,
          "Camera rental packages",
        );
      if (pbRoot)
        new Carousel(
          pbRoot,
          data.photobooth || [],
          photoboothCardHTML,
          "Photobooth packages",
        );

      var camAddonsRoot = document.getElementById("cameraAddons");
      if (camAddonsRoot)
        renderMiniCards(camAddonsRoot, data.camera_accessories || []);

      var pbAddonsRoot = document.getElementById("photoboothAddons");
      if (pbAddonsRoot)
        renderMiniCards(pbAddonsRoot, data.photobooth_addons || []);
    })
    .catch(function () {
      if (camRoot)
        camRoot.innerHTML =
          '<p class="pkg-loading">Could not load camera packages.</p>';
      if (pbRoot)
        pbRoot.innerHTML =
          '<p class="pkg-loading">Could not load photobooth packages.</p>';
    });
})();
