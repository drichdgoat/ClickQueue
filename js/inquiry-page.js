/* SnapVenture - Help & Feedback page */
(function () {
  "use strict";

  /* =====================================================================
     EDIT YOUR CONTENT HERE
     - QUICK_INFO: the 4 topic cards. Put the text of each card inside `content`.
       Use \n for a new line (blank line = \n\n). Line breaks are kept as typed.
     - FAQS: put each answer inside `a`.
     ===================================================================== */
  var QUICK_INFO = [
    { icon: "camera", title: "Camera Rental Rates", content: "" }, // <-- FILL IN LATER
    { icon: "box", title: "Photobooth Packages", content: "" }, // <-- FILL IN LATER
    {
      icon: "card",
      title: "Payment Methods",
      content: `Available Payment Options:

💳 Bank Transfer
• Bank: Philippine National Bank
• Account Name: SnapVenture Services
• Account Number: 1234-5678-9012

💰 GCash / PayMaya
• GCash Number: 0912-345-6789
• Account Name: SnapVenture Services

🏢 Over-the-Counter
• Visit our office at:
123 Photography St., Makati City
• Business Hours: Mon-Sat, 9AM-6PM

📝 Payment Process:
1. Book your service online
2. Choose payment method
3. Submit proof of payment
4. Receive confirmation within 24 hours`,
    },
    {
      icon: "check",
      title: "Booking Requirements",
      content: `Requirements for Booking:

📋 Customer Information:
• Full Name
• Contact Number
• Facebook/Messenger Name
• Gmail Address

📅 Event Details:
• Event Date & Time
• Event Location
• Type of Event
• Additional Requirements

💵 Payment:
• 50% Down Payment (upon booking)
• 50% Balance (before event)
• Security Deposit: ₱10,000 (refundable)

⏰ Booking Timeline:
• Book at least 2 weeks in advance
• Cancellation: Free up to 48 hours before event
• Late cancellation: 50% fee`,
    },
  ];

  var FAQS = [
    { q: "How far in advance should I book?", a: "" }, // <-- put the answer between the quotes
    { q: "What is the cancellation policy?", a: "" },
    { q: "Do you deliver equipment?", a: "" },
    { q: "Can I extend my rental period?", a: "" },
    { q: "What happens if equipment gets damaged?", a: "" },
    { q: "Do you provide photographers/attendants?", a: "" },
    { q: "What areas do you service?", a: "" },
    { q: "Is there a security deposit?", a: "" },
  ];
  /* ================= end of editable content ================= */

  var $ = function (id) {
    return document.getElementById(id);
  };
  var ic = function (n) {
    return '<svg class="ic"><use href="#i-' + n + '"/></svg>';
  };
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  }

  /* ---------- Quick Information ---------- */
  $("topics").innerHTML = QUICK_INFO.map(function (t, i) {
    return (
      '<button type="button" class="hp-topic" data-i="' +
      i +
      '"><span class="hp-ico">' +
      ic(t.icon) +
      "</span><b>" +
      esc(t.title) +
      "</b><span>Click for details</span></button>"
    );
  }).join("");

  var current = -1;
  function showTopic(i) {
    current = i;
    [].forEach.call(document.querySelectorAll(".hp-topic"), function (el, k) {
      el.classList.toggle("is-active", k === i);
    });
    $("detail").hidden = i < 0;
    if (i >= 0) {
      $("detailTitle").textContent = QUICK_INFO[i].title;
      $("detailBody").textContent = QUICK_INFO[i].content;
      $("detail").scrollIntoView({ behavior: "smooth", block: "nearest" });
    }
  }
  $("topics").addEventListener("click", function (e) {
    var b = e.target.closest(".hp-topic");
    if (b)
      showTopic(
        current === +b.getAttribute("data-i") ? -1 : +b.getAttribute("data-i"),
      );
  });
  $("detailClose").addEventListener("click", function () {
    showTopic(-1);
  });

  /* ---------- FAQ accordion ---------- */
  $("faqList").innerHTML = FAQS.map(function (f) {
    return (
      '<div class="hp-q"><button type="button" class="hp-q-btn" aria-expanded="false">' +
      ic("help") +
      "<span>" +
      esc(f.q) +
      "</span>" +
      ic("down") +
      '</button><div class="hp-a" hidden></div></div>'
    );
  }).join("");
  [].forEach.call($("faqList").querySelectorAll(".hp-a"), function (el, i) {
    el.textContent = FAQS[i].a;
  });

  $("faqList").addEventListener("click", function (e) {
    var btn = e.target.closest(".hp-q-btn");
    if (!btn) return;
    var willOpen = btn.getAttribute("aria-expanded") !== "true";
    [].forEach.call($("faqList").querySelectorAll(".hp-q"), function (q) {
      q.querySelector(".hp-q-btn").setAttribute("aria-expanded", "false");
      q.querySelector(".hp-a").hidden = true;
    });
    if (willOpen) {
      btn.setAttribute("aria-expanded", "true");
      btn.nextElementSibling.hidden = false;
    }
  });

  /* ---------- Star rating ---------- */
  var rating = 0;
  function drawRating() {
    var h = "";
    for (var i = 1; i <= 5; i++) {
      h +=
        '<button type="button" class="hp-star' +
        (i <= rating ? " on" : "") +
        '" data-n="' +
        i +
        '" aria-label="' +
        i +
        (i > 1 ? " stars" : " star") +
        '"><svg viewBox="0 0 24 24"><use href="#i-star"/></svg></button>';
    }
    if (rating)
      h += "<em>" + rating + (rating > 1 ? " stars" : " star") + "</em>";
    $("rating").innerHTML = h;
  }
  $("rating").addEventListener("click", function (e) {
    var s = e.target.closest(".hp-star");
    if (s) {
      rating = +s.getAttribute("data-n");
      drawRating();
    }
  });
  drawRating();

  /* ---------- Feedback form (saved to the database -- api/feedback-submit.php) ---------- */
  $("fbForm").addEventListener("submit", function (e) {
    e.preventDefault();
    var v = function (id) {
      return $(id).value.trim();
    };
    var err = $("fbError"),
      ok = $("fbOk");
    var msg = !v("fbName")
      ? "Please enter your full name."
      : !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v("fbEmail"))
        ? "Please enter a valid email address."
        : !v("fbService")
          ? "Please select a service."
          : !rating
            ? "Please rate your experience."
            : !v("fbMessage")
              ? "Please write your feedback."
              : "";
    ok.hidden = true;
    if (msg) {
      err.textContent = msg;
      err.hidden = false;
      return;
    }
    err.hidden = true;

    var submitBtn = this.querySelector(".hp-submit");
    if (submitBtn) submitBtn.disabled = true;

    var fd = new FormData();
    fd.append("name", v("fbName"));
    fd.append("email", v("fbEmail"));
    fd.append("phone", v("fbPhone"));
    fd.append("service", v("fbService"));
    fd.append("rating", rating);
    fd.append("message", v("fbMessage"));

    var form = this;
    fetch("../api/public/feedback-submit.php", { method: "POST", body: fd })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (submitBtn) submitBtn.disabled = false;
        if (!data.success) {
          err.textContent =
            data.error || "We couldn't save your feedback. Please try again.";
          err.hidden = false;
          return;
        }
        form.reset();
        rating = 0;
        drawRating();
        ok.hidden = false;
      })
      .catch(function () {
        if (submitBtn) submitBtn.disabled = false;
        err.textContent =
          "Network error. Please check your connection and try again.";
        err.hidden = false;
      });
  });
})();
