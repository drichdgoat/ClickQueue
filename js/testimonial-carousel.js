/* SnapVenture - Homepage testimonial carousel (pulls admin-featured feedback from the DB) */
(function () {
  "use strict";

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
      return (
        { "&": "&lt;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[
          c
        ] || c
      );
    });
  }
  function stars(n) {
    return "★".repeat(n) + "☆".repeat(5 - n);
  }

  function cardHTML(f) {
    return (
      '<div class="t-stars">' +
      stars(f.rating) +
      "</div>" +
      '<p class="t-quote">"' +
      esc(f.message) +
      '"</p>' +
      '<div><span class="t-name">' +
      esc(f.customer_name) +
      "</span>" +
      (f.service
        ? '<span class="t-service">' + esc(f.service) + "</span>"
        : "") +
      "</div>"
    );
  }

  function Carousel(root, items) {
    var current = 0;
    var stage = document.createElement("div");
    stage.className = "t-stage";
    var navRow = document.createElement("div");
    navRow.className = "t-nav-row";
    navRow.innerHTML =
      '<button type="button" class="t-nav-btn" data-dir="-1" aria-label="Previous">&#8249;</button>' +
      '<div class="t-dots"></div>' +
      '<button type="button" class="t-nav-btn" data-dir="1" aria-label="Next">&#8250;</button>';

    root.innerHTML = "";
    root.appendChild(stage);
    if (items.length > 1) root.appendChild(navRow);
    var dotsWrap = navRow.querySelector(".t-dots");

    function wrap(i) {
      var n = items.length;
      return ((i % n) + n) % n;
    }
    function render() {
      var n = items.length;
      var order;
      if (n <= 1) {
        order = [0];
      } else {
        var span = Math.min(1, Math.floor((n - 1) / 2));
        order = [];
        for (var d = -span; d <= span; d++) order.push(wrap(current + d));
      }
      stage.innerHTML = order
        .map(function (idx) {
          var cls = "t-slide " + (idx === current ? "is-center" : "is-side");
          return (
            '<article class="' +
            cls +
            '" data-idx="' +
            idx +
            '">' +
            cardHTML(items[idx]) +
            "</article>"
          );
        })
        .join("");
      if (dotsWrap) {
        dotsWrap.innerHTML = items
          .map(function (_, idx) {
            return (
              '<button type="button" class="t-dot' +
              (idx === current ? " is-active" : "") +
              '" data-idx="' +
              idx +
              '"></button>'
            );
          })
          .join("");
      }
    }
    function goTo(i) {
      current = wrap(i);
      render();
    }
    navRow.addEventListener("click", function (e) {
      var b = e.target.closest("[data-dir]");
      if (b) {
        goTo(current + parseInt(b.getAttribute("data-dir"), 10));
        return;
      }
      var d = e.target.closest(".t-dot");
      if (d) goTo(parseInt(d.getAttribute("data-idx"), 10));
    });
    stage.addEventListener("click", function (e) {
      var slide = e.target.closest(".t-slide.is-side");
      if (slide) goTo(parseInt(slide.getAttribute("data-idx"), 10));
    });
    render();
  }

  var root = document.getElementById("testimonialCarousel");
  if (!root) return;

  fetch("api/public/featured-feedback.php")
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      var items = data.feedback || [];
      if (!items.length) {
        root.innerHTML = '<p class="t-loading">No reviews to show yet.</p>';
        return;
      }
      new Carousel(root, items);
    })
    .catch(function () {
      root.innerHTML = '<p class="t-loading">Could not load reviews.</p>';
    });
})();
