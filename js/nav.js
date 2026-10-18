/* SnapVenture - shared nav behaviour (hamburger + active link).
   Load at the end of <body>, AFTER script.js. Safe to include on every page. */
(function () {
  "use strict";
  var btn = document.querySelector(".site-header .menu-btn");
  var nav = document.querySelector(".site-header .mobile-nav");
  if (!btn || !nav) return;

  // Replace the button with a clean clone so any older toggle handler in
  // script.js is removed (two handlers would open and instantly re-close it).
  var clean = btn.cloneNode(true);
  btn.parentNode.replaceChild(clean, btn);
  btn = clean;

  if (!nav.id) nav.id = "mobileNav";
  btn.type = "button";
  btn.setAttribute("aria-label", "Toggle menu");
  btn.setAttribute("aria-controls", nav.id);
  btn.setAttribute("aria-expanded", "false");

  function setOpen(open) {
    nav.classList.toggle("show", open);
    btn.setAttribute("aria-expanded", open ? "true" : "false");
    btn.textContent = open ? "\u2715" : "\u2630";
  }

  btn.addEventListener("click", function (e) {
    e.stopPropagation();
    setOpen(!nav.classList.contains("show"));
  });
  nav.addEventListener("click", function (e) {
    if (e.target.closest("a")) setOpen(false);
  });
  document.addEventListener("click", function (e) {
    if (nav.classList.contains("show") && !e.target.closest(".site-header"))
      setOpen(false);
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && nav.classList.contains("show")) {
      setOpen(false);
      btn.focus();
    }
  });
  window.addEventListener("resize", function () {
    if (window.innerWidth > 900) setOpen(false);
  });

  // Mark the current page from the URL so "active" is correct on every page.
  var here = location.pathname.split("/").pop() || "index.php";
  [].forEach.call(
    document.querySelectorAll(".nav-links a, .mobile-nav a"),
    function (a) {
      var file = (a.getAttribute("href") || "").split("?")[0].split("/").pop();
      var on = file === here;
      a.classList.toggle("active", on);
      if (on) a.setAttribute("aria-current", "page");
      else a.removeAttribute("aria-current");
    },
  );
})();
