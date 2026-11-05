(() => {
  "use strict";

  /**
   * Build the desktop navigation disclosure from the existing admin menu.
   *
   * @return {void}
   */
  function initializeAdminNavigation() {
    const adminMenu = document.getElementById("adminmenu");
    const collapseMenu = document.getElementById("collapse-menu");
    const menuIds = ["menu-plugins", "menu-users", "menu-settings"];
    const sourceMenus = menuIds
      .map((menuId) => document.getElementById(menuId))
      .filter((menuItem) => menuItem && menuItem.querySelector(":scope > a"));

    if (
      !adminMenu ||
      !document.getElementById("toplevel_page_project-board") ||
      !sourceMenus.length ||
      document.getElementById("alpaca-utility-menu")
    ) {
      return;
    }

    const utilityItem = document.createElement("li");
    utilityItem.id = "alpaca-utility-menu";
    utilityItem.className = "menu-top alpaca-utility-menu";

    const toggle = document.createElement("button");
    toggle.id = "alpaca-utility-toggle";
    toggle.type = "button";
    toggle.setAttribute(
      "aria-label",
      wp.i18n.__("More administration options", "alpaca-takeover"),
    );
    toggle.setAttribute(
      "title",
      wp.i18n.__("More administration options", "alpaca-takeover"),
    );
    toggle.setAttribute("aria-haspopup", "true");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-controls", "alpaca-utility-panel");
    toggle.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' +
      '<line x1="21" y1="4" x2="14" y2="4"></line>' +
      '<line x1="10" y1="4" x2="3" y2="4"></line>' +
      '<line x1="21" y1="12" x2="12" y2="12"></line>' +
      '<line x1="8" y1="12" x2="3" y2="12"></line>' +
      '<line x1="21" y1="20" x2="16" y2="20"></line>' +
      '<line x1="12" y1="20" x2="3" y2="20"></line>' +
      '<line x1="14" y1="2" x2="14" y2="6"></line>' +
      '<line x1="8" y1="10" x2="8" y2="14"></line>' +
      '<line x1="16" y1="18" x2="16" y2="22"></line>' +
      "</svg>";

    const panel = document.createElement("div");
    panel.id = "alpaca-utility-panel";
    panel.className = "alpaca-utility-menu__panel";
    panel.hidden = true;

    sourceMenus.forEach((sourceMenu) => {
      const sourceLink = sourceMenu.querySelector(":scope > a");
      const sourceLabel = sourceLink.querySelector(".wp-menu-name");
      const labelText = sourceLabel
        ? Array.from(sourceLabel.childNodes)
            .filter((node) => node.nodeType === Node.TEXT_NODE)
            .map((node) => node.textContent)
            .join("")
            .trim()
        : sourceLink.textContent.trim();
      const group = document.createElement("div");
      const heading = document.createElement("span");
      const links = document.createElement("div");
      const submenuLinks = sourceMenu.querySelectorAll(
        ":scope > .wp-submenu > li > a",
      );

      group.className = "alpaca-utility-menu__group";
      group.setAttribute("role", "group");
      group.setAttribute("aria-labelledby", `${sourceMenu.id}-utility-heading`);

      heading.id = `${sourceMenu.id}-utility-heading`;
      heading.className = "alpaca-utility-menu__heading";
      heading.setAttribute("role", "heading");
      heading.setAttribute("aria-level", "2");
      heading.textContent = labelText;

      const updateCount = sourceLabel?.querySelector(".update-plugins");
      if (updateCount) {
        heading.append(updateCount.cloneNode(true));
      }

      links.className = "alpaca-utility-menu__links";

      submenuLinks.forEach((submenuLink) => {
        const itemLink = document.createElement("a");
        itemLink.className = "alpaca-utility-menu__link";
        itemLink.href = submenuLink.href;
        itemLink.textContent = submenuLink.innerText.trim();

        if (
          submenuLink.parentElement.classList.contains("current") ||
          "page" === submenuLink.getAttribute("aria-current")
        ) {
          itemLink.setAttribute("aria-current", "page");
        }

        links.append(itemLink);
      });

      group.append(heading, links);
      panel.append(group);
    });

    utilityItem.append(toggle, panel);
    adminMenu.insertBefore(utilityItem, collapseMenu);

    const setOpen = (isOpen) => {
      toggle.setAttribute("aria-expanded", String(isOpen));
      panel.hidden = !isOpen;
    };

    toggle.addEventListener("click", () => {
      setOpen("true" !== toggle.getAttribute("aria-expanded"));
    });

    utilityItem.addEventListener("keydown", (event) => {
      if (
        "Escape" === event.key &&
        "true" === toggle.getAttribute("aria-expanded")
      ) {
        setOpen(false);
        toggle.focus();
      }
    });

    utilityItem.addEventListener("focusout", (event) => {
      if (!utilityItem.contains(event.relatedTarget)) {
        setOpen(false);
      }
    });

    panel.addEventListener("click", (event) => {
      if (event.target.closest("a")) {
        setOpen(false);
      }
    });

    document.addEventListener("click", (event) => {
      if (!utilityItem.contains(event.target)) {
        setOpen(false);
      }
    });

    const desktopBreakpoint = window.matchMedia("(min-width: 783px)");
    desktopBreakpoint.addEventListener("change", (event) => {
      if (!event.matches) {
        setOpen(false);
      }
    });
  }

  if ("loading" === document.readyState) {
    document.addEventListener("DOMContentLoaded", initializeAdminNavigation, {
      once: true,
    });
  } else {
    initializeAdminNavigation();
  }
})();
