(() => {
  "use strict";

  const { createElement, useRef, useState } = wp.element;
  const { Notice, RangeControl, SelectControl, Spinner } = wp.components;
  const { __ } = wp.i18n;
  const { addFilter } = wp.hooks;
  const appearance = window.alpacaTakeoverSettings;
  const appearanceTabName = "alpaca-takeover-appearance";

  document.addEventListener("dragstart", (event) => {
    const target = event.target;
    if (!(target instanceof Element) || !event.dataTransfer) {
      return;
    }

    const row = target.closest(".alpaca-status-manager .status-grid-row");
    const ghost = document.querySelector(".alpaca-status-drag-ghost");
    const nameEditor = row
      ? row.querySelector(".alpaca-settings-list-name-editor")
      : null;

    if (!ghost || !nameEditor) {
      return;
    }

    const dragImageWidth = nameEditor.getBoundingClientRect().width;
    if (dragImageWidth <= 0) {
      return;
    }

    ghost.style.width = `${dragImageWidth}px`;
    ghost.style.maxWidth = "none";
    ghost.style.boxSizing = "border-box";

    const text = ghost.querySelector("span:last-child");
    if (text) {
      text.style.flex = "1 1 auto";
      text.style.minWidth = "0";
    }

    try {
      event.dataTransfer.setDragImage(ghost, 16, 16);
    } catch (error) {
      return;
    }
  });

  /**
   * Render the site-wide theme selector.
   *
   * @return {Element} Appearance tab content.
   */
  function AppearanceSettings() {
    const [theme, setTheme] = useState(appearance.theme);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState("");

    const saveTheme = (nextTheme) => {
      const previousTheme = theme;

      setTheme(nextTheme);
      setError("");
      setIsSaving(true);

      wp.apiFetch({
        path: appearance.themePath,
        method: "POST",
        data: { theme: nextTheme },
      })
        .then(() => {
          window.location.reload();
        })
        .catch((requestError) => {
          setTheme(previousTheme);
          setError(
            requestError.message ||
              __("The appearance theme could not be saved.", "alpaca-takeover"),
          );
        })
        .finally(() => {
          setIsSaving(false);
        });
    };

    const content = [
      createElement(SelectControl, {
        key: "theme",
        label: __("Theme", "alpaca-takeover"),
        value: theme,
        options: appearance.themes,
        disabled: !appearance.canManageTheme || isSaving,
        onChange: saveTheme,
      }),
    ];

    if (!appearance.canManageTheme) {
      content.push(
        createElement(
          "p",
          { className: "description", key: "permission" },
          __(
            "Only administrators can change the site-wide theme.",
            "alpaca-takeover",
          ),
        ),
      );
    }

    if (isSaving) {
      content.push(
        createElement(Spinner, {
          key: "saving",
          "aria-label": __("Saving appearance", "alpaca-takeover"),
        }),
      );
    }

    if (error) {
      content.push(
        createElement(
          Notice,
          { key: "error", status: "error", isDismissible: false },
          error,
        ),
      );
    }

    return createElement(
      "div",
      { className: "alpaca-settings-tab-content alpaca-takeover-appearance" },
      content,
    );
  }

  addFilter("alpaca.settings.tabs", "alpaca-takeover/appearance", (tabs) => [
    {
      name: appearanceTabName,
      title: __("Appearance", "alpaca-takeover"),
      className: "alpaca-settings-tab--appearance",
    },
    ...tabs,
  ]);

  addFilter(
    "alpaca.settings.tabContent",
    "alpaca-takeover/appearance-content",
    (content, tab) => {
      if (appearanceTabName !== tab.name) {
        return content;
      }

      return createElement(AppearanceSettings);
    },
  );
})();
