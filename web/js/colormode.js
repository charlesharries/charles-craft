/**
 * Color mode, freely cribbed from my dark mode.
 */
function colorMode() {
    const COLOR = "color";
    const BW = "bw";
    const STORAGE_KEY = "color_theme";
    const toggle = document.getElementById("color-theme-toggle");

    function setTheme(theme) {
        console.log("setting theme", theme);
        localStorage.setItem(STORAGE_KEY, theme);
        const isColor = theme == COLOR

        if (isColor) {
            document.documentElement.style.removeProperty("--saturation")
            toggle.innerHTML = "🌈";
            toggle.setAttribute("aria-label", "enable black/white theme");
        } else {
            localStorage.setItem(STORAGE_KEY, BW);
            toggle.innerHTML = "🏴";
            document.documentElement.style.setProperty("--saturation", "0%")
            toggle.setAttribute("aria-label", "enable color theme");
        }
    }

    function toggleTheme() {
        if (localStorage.getItem(STORAGE_KEY) === COLOR) {
            setTheme(BW);
        } else {
            setTheme(COLOR);
        }
    }

    function getColor() {
        return localStorage.getItem(STORAGE_KEY);
    }

    if (!localStorage.getItem(STORAGE_KEY)) {
        localStorage.setItem(STORAGE_KEY, COLOR);
    }

    toggle.addEventListener("click", (event) => {
        event.preventDefault();
        toggleTheme();
    });

    setTheme(localStorage.getItem(STORAGE_KEY));

    return { COLOR, BW, STORAGE_KEY, toggle, getColor }
}

const ColorMode = colorMode();