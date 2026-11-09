(() => {
  const labelSelector =
    ".alpaca-label-pill, .components-form-token-field__token";

  const getContrastingTextColor = (backgroundColor) => {
    const colorValues = backgroundColor.match(/[\d.]+/g);

    if (!colorValues || colorValues.length < 3) {
      return "#fff";
    }

    const channels = colorValues.slice(0, 3).map(Number);
    const getRelativeLuminance = (colorChannels) =>
      colorChannels
        .map((channel) => {
          const normalizedChannel = channel / 255;

          return normalizedChannel <= 0.04045
            ? normalizedChannel / 12.92
            : ((normalizedChannel + 0.055) / 1.055) ** 2.4;
        })
        .reduce(
          (total, channel, index) =>
            total + channel * [0.2126, 0.7152, 0.0722][index],
          0,
        );
    const backgroundLuminance = getRelativeLuminance(channels);
    const darkTextLuminance = getRelativeLuminance([0, 0, 0]);
    const darkTextContrast =
      (backgroundLuminance + 0.05) / (darkTextLuminance + 0.05);
    const whiteTextContrast = 1.05 / (backgroundLuminance + 0.05);

    return darkTextContrast >= whiteTextContrast ? "#000" : "#fff";
  };

  const applyLabelTextColor = (label) => {
    const backgroundColor = window.getComputedStyle(label).backgroundColor;

    if (
      "transparent" === backgroundColor ||
      "rgba(0, 0, 0, 0)" === backgroundColor
    ) {
      return;
    }

    let textColor = getContrastingTextColor(backgroundColor);

    if (label.matches('.alpaca-item-deadline[data-deadline-state="late"]')) {
      textColor = "#fff";
    }

    const currentTextColor = label.style.getPropertyValue("color");
    const currentPriority = label.style.getPropertyPriority("color");

    if (textColor !== currentTextColor || "important" !== currentPriority) {
      label.style.setProperty("color", textColor, "important");
    }
  };

  const updateLabels = (node) => {
    if (!(node instanceof Element)) {
      return;
    }

    if (node.matches(labelSelector)) {
      applyLabelTextColor(node);
    }

    node.querySelectorAll(labelSelector).forEach(applyLabelTextColor);
  };

  updateLabels(document.body);

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if ("attributes" === mutation.type) {
        if (mutation.target.matches(labelSelector)) {
          applyLabelTextColor(mutation.target);
        }

        return;
      }

      mutation.addedNodes.forEach(updateLabels);
    });
  });

  observer.observe(document.body, {
    attributes: true,
    attributeFilter: ["style"],
    childList: true,
    subtree: true,
  });
})();
