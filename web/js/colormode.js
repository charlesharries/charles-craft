/**
 * Color mode, freely cribbed from my dark mode.
 */
function colorMode() {
    const COLOR = "color";
    const BW = "bw";
    const STORAGE_KEY = "color_theme";
    const toggle = document.getElementById("color-theme-toggle");

    function setTheme(theme) {
        localStorage.setItem(STORAGE_KEY, theme);
        const isColor = theme == COLOR

        document.documentElement.classList.remove("color", "bw");

        if (isColor) {
            document.documentElement.style.removeProperty("--saturation")
            document.documentElement.classList.add("color")
            document.querySelectorAll("dialog::backdrop").forEach((d) => {
                d.style.removeProperty("--saturation");
            })
            toggle.innerHTML = "🌈";
            toggle.setAttribute("aria-label", "enable black/white theme");
        } else {
            document.documentElement.style.setProperty("--saturation", "0%")
            document.documentElement.classList.add("bw")
            toggle.innerHTML = DarkMode.getTheme() === DarkMode.DARK
                ? "🏴"
                : "🏳";
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

    EventBus.on("darkmode.toggle", (event) => {
        toggle.innerHTML = event.isDark ? "🏴" : "🏳"
    });

    setTheme(localStorage.getItem(STORAGE_KEY));

    return { COLOR, BW, STORAGE_KEY, toggle, getColor }
}

const ColorMode = colorMode();