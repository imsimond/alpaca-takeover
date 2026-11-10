(() => {
  "use strict";

  const settings = window.alpacaTakeoverOthelloSettings;
  const { createElement, Fragment, useRef, useState } = wp.element;
  const { Notice, RangeControl, Spinner } = wp.components;
  const { __ } = wp.i18n;
  const { addFilter } = wp.hooks;
  const colorPattern = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;
  const luminanceWeights = [0.2126, 0.7152, 0.0722];
  const luminanceHueCenter = 125;
  const luminanceHuePeriod = 360;
  const maxLuminanceAtHueCenter = 0.1;
  const maxLuminanceAtOppositeHue = 0.16;

  function hexToRgb(color) {
    const hexadecimal = color.slice(1);
    const normalizedHex =
      3 === hexadecimal.length
        ? hexadecimal
            .split("")
            .map((digit) => `${digit}${digit}`)
            .join("")
        : hexadecimal;

    return [0, 2, 4].map(
      (offset) => parseInt(normalizedHex.slice(offset, offset + 2), 16) / 255,
    );
  }

  function toLinearChannel(channel) {
    return channel <= 0.04045
      ? channel / 12.92
      : ((channel + 0.055) / 1.055) ** 2.4;
  }

  function fromLinearChannel(channel) {
    return channel <= 0.0031308
      ? channel * 12.92
      : 1.055 * channel ** (1 / 2.4) - 0.055;
  }

  function hexToHsl(color) {
    const [red, green, blue] = hexToRgb(color);
    const maximum = Math.max(red, green, blue);
    const minimum = Math.min(red, green, blue);
    const difference = maximum - minimum;
    const lightness = (maximum + minimum) / 2;
    let hue = 0;
    let saturation = 0;

    if (0 !== difference) {
      saturation =
        lightness > 0.5
          ? difference / (2 - maximum - minimum)
          : difference / (maximum + minimum);

      if (maximum === red) {
        hue = 60 * (((green - blue) / difference) % 6);
      } else if (maximum === green) {
        hue = 60 * ((blue - red) / difference + 2);
      } else {
        hue = 60 * ((red - green) / difference + 4);
      }
    }

    if (hue < 0) {
      hue += 360;
    }

    return {
      hue: Math.round(hue) % 360,
      saturation,
      lightness,
    };
  }

  function hslToHex(hue, saturation, lightness) {
    const chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
    const hueSection = hue / 60;
    const secondary = chroma * (1 - Math.abs((hueSection % 2) - 1));
    let rgbComponents;

    if (hueSection < 1) {
      rgbComponents = [chroma, secondary, 0];
    } else if (hueSection < 2) {
      rgbComponents = [secondary, chroma, 0];
    } else if (hueSection < 3) {
      rgbComponents = [0, chroma, secondary];
    } else if (hueSection < 4) {
      rgbComponents = [0, secondary, chroma];
    } else if (hueSection < 5) {
      rgbComponents = [secondary, 0, chroma];
    } else {
      rgbComponents = [chroma, 0, secondary];
    }

    const match = lightness - chroma / 2;
    const hexadecimalComponents = rgbComponents.map((component) =>
      Math.round((component + match) * 255)
        .toString(16)
        .padStart(2, "0"),
    );

    return `#${hexadecimalComponents.join("")}`;
  }

  function reduceLuminanceForHue(color, hue) {
    const hueRadians =
      (2 * Math.PI * (hue - luminanceHueCenter)) / luminanceHuePeriod;
    const waveProgress = (1 + Math.cos(hueRadians)) / 2;
    const maxLuminance =
      maxLuminanceAtOppositeHue +
      (maxLuminanceAtHueCenter - maxLuminanceAtOppositeHue) * waveProgress;
    const rgb = hexToRgb(color);
    const currentLuminance = rgb.reduce(
      (total, channel, index) =>
        total + toLinearChannel(channel) * luminanceWeights[index],
      0,
    );

    if (currentLuminance <= maxLuminance) {
      return color;
    }

    const luminanceScale = maxLuminance / currentLuminance;
    const adjustedHex = rgb.map((channel) => {
      const adjustedChannel = fromLinearChannel(
        toLinearChannel(channel) * luminanceScale,
      );

      return Math.round(adjustedChannel * 255)
        .toString(16)
        .padStart(2, "0");
    });

    return `#${adjustedHex.join("")}`;
  }

  function applyBoardColor(color) {
    if (!colorPattern.test(color) || !document.body) {
      return;
    }

    const hue = hexToHsl(color).hue;
    const adjustedColor = reduceLuminanceForHue(color, hue);

    document.body.style.setProperty("--alpaca-paper", adjustedColor);
    document.body.style.setProperty(
      "--alpaca-othello-hue-color",
      adjustedColor,
    );
  }

  function BoardHueControl() {
    const initialBoardHsl = hexToHsl(settings.boardColor);
    const [boardHue, setBoardHue] = useState(initialBoardHsl.hue);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState("");
    const saveTimer = useRef(null);
    const pendingColor = useRef(null);
    const isSavingColor = useRef(false);
    const savedColor = useRef(settings.boardColor);
    const boardSaturation = useRef(initialBoardHsl.saturation);
    const boardLightness = useRef(initialBoardHsl.lightness);

    function persistColor() {
      if (isSavingColor.current || null === pendingColor.current) {
        return;
      }

      const nextColor = pendingColor.current;
      pendingColor.current = null;
      isSavingColor.current = true;
      setIsSaving(true);

      wp.apiFetch({
        path: settings.boardColorPath,
        method: "POST",
        data: { color: nextColor },
      })
        .then((response) => {
          savedColor.current = response.color || nextColor;
          setError("");
        })
        .catch((requestError) => {
          if (null === pendingColor.current) {
            const savedBoardHsl = hexToHsl(savedColor.current);
            boardSaturation.current = savedBoardHsl.saturation;
            boardLightness.current = savedBoardHsl.lightness;
            setBoardHue(savedBoardHsl.hue);
            applyBoardColor(savedColor.current);
          }

          setError(
            requestError.message ||
              __(
                "The board background color could not be saved.",
                "alpaca-takeover",
              ),
          );
        })
        .finally(() => {
          isSavingColor.current = false;
          setIsSaving(false);

          if (null !== pendingColor.current) {
            persistColor();
          }
        });
    }

    const changeHue = (nextHue) => {
      const numericHue = Number(nextHue);

      if (!Number.isFinite(numericHue)) {
        return;
      }

      const normalizedHue = ((numericHue % 360) + 360) % 360;
      const nextColor = hslToHex(
        normalizedHue,
        boardSaturation.current,
        boardLightness.current,
      );

      setBoardHue(normalizedHue);
      pendingColor.current = nextColor;
      setError("");
      applyBoardColor(nextColor);

      window.clearTimeout(saveTimer.current);
      saveTimer.current = window.setTimeout(persistColor, 350);
    };

    const content = [
      createElement(
        "div",
        { key: "control-row", className: "alpaca-takeover-hue-control__row" },
        createElement(RangeControl, {
          label: __("Board background color", "alpaca-takeover"),
          value: boardHue,
          min: 0,
          max: 359,
          step: 1,
          onChange: changeHue,
          withInputField: false,
        }),
        isSaving
          ? createElement(Spinner, {
              key: "saving",
              "aria-label": __("Saving appearance", "alpaca-takeover"),
            })
          : null,
      ),
    ];

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
      {
        className:
          "alpaca-settings-tab-content alpaca-takeover-appearance alpaca-takeover-hue-control",
      },
      content,
    );
  }

  function addAppearanceControl(content, tab) {
    if (
      tab.name !== settings.appearanceTabName ||
      !settings.canManageBoardColor
    ) {
      return content;
    }

    return createElement(
      Fragment,
      null,
      content,
      createElement(BoardHueControl),
    );
  }

  if (!settings) {
    return;
  }

  applyBoardColor(settings.boardColor);

  if (settings.appearanceTabName && settings.canManageBoardColor) {
    addFilter(
      "alpaca.settings.tabContent",
      "alpaca-takeover/othello-appearance",
      addAppearanceControl,
    );
  }
})();
