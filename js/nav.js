
/* SnapVenture - Shared Navigation Behaviour
   Hamburger menu + active link + mobile navigation.
*/

document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    var btn = document.querySelector(".site-header .menu-btn");
    var nav = document.querySelector(".site-header .mobile-nav");

    if (!btn || !nav) return;

    // Remove older click handlers to prevent duplicate toggling
    var clean = btn.cloneNode(true);
    btn.parentNode.replaceChild(clean, btn);
    btn = clean;

    if (!nav.id) nav.id = "mobileNav";

    btn.type = "button";
    btn.setAttribute("aria-label", "Toggle menu");
    btn.setAttribute("aria-controls", nav.id);
    btn.setAttribute("aria-expanded", "false");

    // Open and close mobile navigation
    function setOpen(open) {
        nav.classList.toggle("show", open);
        btn.setAttribute("aria-expanded", open ? "true" : "false");
        btn.textContent = open ? "\u2715" : "\u2630";
    }

    // Hamburger button click
    btn.addEventListener("click", function (e) {
        e.stopPropagation();
        setOpen(!nav.classList.contains("show"));
    });

    // Close menu after clicking a navigation link
    nav.addEventListener("click", function (e) {
        if (e.target.closest("a")) {
            setOpen(false);
        }
    });

    // Close menu when clicking outside the header
    document.addEventListener("click", function (e) {
        if (
            nav.classList.contains("show") &&
            !e.target.closest(".site-header")
        ) {
            setOpen(false);
        }
    });

    // Close menu using Escape key
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && nav.classList.contains("show")) {
            setOpen(false);
            btn.focus();
        }
    });

    // Close mobile menu when screen becomes desktop size
    window.addEventListener("resize", function () {
        if (window.innerWidth > 900) {
            setOpen(false);
        }
    });

    // Automatically identify the active page
    var here = location.pathname.split("/").pop() || "index.php";

    [].forEach.call(
        document.querySelectorAll(".nav-links a, .mobile-nav a"),
        function (a) {
            var file = (a.getAttribute("href") || "")
                .split("?")[0]
                .split("/")
                .pop();

            var on = file === here;

            a.classList.toggle("active", on);

            if (on) {
                a.setAttribute("aria-current", "page");
            } else {
                a.removeAttribute("aria-current");
            }
        }
    );
});
