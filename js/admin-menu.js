(function () {
  "use strict";

  const sidebar = document.querySelector("aside.sidebar");
  if (!sidebar) return;

  sidebar.id = sidebar.id || "adminSidebar";
  const menuButtons = document.querySelectorAll("[data-admin-menu]");

  function setOpen(open) {
    sidebar.classList.toggle("open", open);
    menuButtons.forEach((button) => {
      button.setAttribute("aria-controls", sidebar.id);
      button.setAttribute("aria-expanded", String(open));
    });
  }

  menuButtons.forEach((button) => {
    button.addEventListener("click", () => {
      setOpen(!sidebar.classList.contains("open"));
    });
  });

  document.addEventListener("click", (event) => {
    if (
      sidebar.classList.contains("open") &&
      !sidebar.contains(event.target) &&
      !Array.from(menuButtons).some((button) => button.contains(event.target))
    ) {
      setOpen(false);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && sidebar.classList.contains("open")) {
      setOpen(false);
      menuButtons[0]?.focus();
    }
  });

  sidebar.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => setOpen(false));
  });

  menuButtons.forEach((button) => {
    button.setAttribute("aria-controls", sidebar.id);
    button.setAttribute("aria-expanded", "false");
  });
})();
