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
    const projectBoardMenu = document.getElementById(
      "toplevel_page_project-board",
    );

    if (
      !adminMenu ||
      !projectBoardMenu ||
      document.getElementById("alpaca-utility-menu")
    ) {
      return;
    }

    const sourceMenus = Array.from(
      adminMenu.querySelectorAll(":scope > li.menu-top"),
    ).filter(
      (menuItem) =>
        menuItem !== projectBoardMenu &&
        menuItem.querySelector(":scope > a"),
    );

    if (!sourceMenus.length) {
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
      wp.i18n.__("Admin options", "alpaca-takeover"),
    );
    toggle.setAttribute(
      "title",
      wp.i18n.__("Admin options", "alpaca-takeover"),
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

    const toggleLabel = document.createElement("span");
    toggleLabel.className = "alpaca-utility-menu__toggle-label";
    toggleLabel.textContent = wp.i18n.__(
      "Admin options",
      "alpaca-takeover",
    );
    toggle.append(toggleLabel);

    const panel = document.createElement("div");
    panel.id = "alpaca-utility-panel";
    panel.className = "alpaca-utility-menu__panel";
    panel.hidden = true;

    let openSection = null;
    const setSectionOpen = (sectionToggle, links, isOpen) => {
      if (isOpen && openSection && openSection.sectionToggle !== sectionToggle) {
        openSection.sectionToggle.setAttribute("aria-expanded", "false");
        openSection.links.hidden = true;
        openSection.sectionToggle.querySelector(
          ".alpaca-utility-menu__section-indicator",
        ).textContent = "+";
      }

      sectionToggle.setAttribute("aria-expanded", String(isOpen));
      links.hidden = !isOpen;
      sectionToggle.querySelector(
        ".alpaca-utility-menu__section-indicator",
      ).textContent = isOpen ? "-" : "+";

      if (isOpen) {
        openSection = { sectionToggle, links };
      } else if (openSection?.sectionToggle === sectionToggle) {
        openSection = null;
      }
    };

    sourceMenus.forEach((sourceMenu, index) => {
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
      const heading = document.createElement("h2");
      const links = document.createElement("div");
      const submenuLinks = Array.from(sourceMenu.querySelectorAll(
        ":scope > .wp-submenu > li > a",
      ));
      const sourceLinks = submenuLinks.length
        ? [...submenuLinks]
        : [sourceLink];
      const hasSubmenu = submenuLinks.length > 0;
      const sectionId = `alpaca-utility-section-${index}`;
      const sectionToggle = document.createElement("button");

      group.className = "alpaca-utility-menu__group";
      group.classList.add("alpaca-utility-menu__group--collapsible");
      group.setAttribute("role", "group");
      group.setAttribute("aria-labelledby", `${sectionId}-heading`);

      heading.id = `${sectionId}-heading`;
      heading.className = "alpaca-utility-menu__heading";
      const indicator = document.createElement("span");

      sectionToggle.type = "button";
      sectionToggle.className = "alpaca-utility-menu__section-toggle";
      sectionToggle.setAttribute("aria-expanded", "false");
      sectionToggle.setAttribute("aria-controls", `${sectionId}-links`);
      sectionToggle.append(document.createTextNode(labelText));

      indicator.className = "alpaca-utility-menu__section-indicator";
      indicator.setAttribute("aria-hidden", "true");
      indicator.textContent = "+";
      sectionToggle.append(indicator);
      heading.append(sectionToggle);

      const updateCount = sourceLabel?.querySelector(".update-plugins");
      if (updateCount) {
        sectionToggle.append(updateCount.cloneNode(true));
      }

      links.id = `${sectionId}-links`;
      links.className = "alpaca-utility-menu__links";
      links.hidden = true;

      if (
        hasSubmenu &&
        !submenuLinks.some((menuLink) => menuLink.href === sourceLink.href)
      ) {
        sourceLinks.unshift(sourceLink);
      }

      let isCurrentMenu = sourceMenu.classList.contains("current");

      sourceLinks.forEach((menuLink) => {
        const itemLink = document.createElement("a");
        itemLink.className = "alpaca-utility-menu__link";
        itemLink.href = menuLink.href;
        itemLink.textContent =
          menuLink === sourceLink ? labelText : menuLink.innerText.trim();

        const isCurrentLink =
          menuLink.parentElement.classList.contains("current") ||
          "page" === menuLink.getAttribute("aria-current") ||
          (sourceMenu.classList.contains("current") &&
            menuLink.href === sourceLink.href);

        if (isCurrentLink) {
          itemLink.setAttribute("aria-current", "page");
          isCurrentMenu = true;
        }

        links.append(itemLink);
      });

      group.append(heading, links);
      panel.append(group);
      sourceMenu.classList.add("alpaca-utility-menu__source");

      setSectionOpen(sectionToggle, links, isCurrentMenu);
      sectionToggle.addEventListener("click", () => {
        setSectionOpen(
          sectionToggle,
          links,
          "true" !== sectionToggle.getAttribute("aria-expanded"),
        );
      });
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
