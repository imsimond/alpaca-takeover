(() => {
  "use strict";

  const { createElement, Fragment } = wp.element;

  function addIssueIdDatapoint(originalContent, item) {
    const issueSlug =
      typeof item?.slug === "string" ? item.slug.trim() : "";

    if (issueSlug === "") {
      return originalContent;
    }

    return createElement(
      Fragment,
      null,
      originalContent,
      createElement(
        "span",
        { className: "alpaca-item-id" },
        createElement(
          "span",
          { className: "alpaca-item-id-icon-frame" },
          createElement(
            "svg",
            {
              "aria-hidden": "true",
              className: "alpaca-item-id-icon",
              fill: "none",
              focusable: "false",
              height: 14,
              stroke: "currentColor",
              strokeLinecap: "round",
              strokeLinejoin: "round",
              strokeWidth: 2,
              viewBox: "0 0 24 24",
              width: 14,
            },
            createElement("path", {
              d: "m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z",
            }),
          ),
        ),
        createElement("span", { className: "alpaca-item-id-slug" }, issueSlug),
      ),
    );
  }

  wp.hooks.addFilter(
    "alpaca.item.datapoints",
    "alpaca-takeover/geranium/issue-id",
    addIssueIdDatapoint,
  );
})();
