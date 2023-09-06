/**
 * Dark mode, freely cribbed from Jordan Scales.
 * 
 * @link https://notes.jordanscales.com/40ecf234
 */
function darkMode() {
    const COLOR = "color";
    const BW = "bw";
    const STORAGE_KEY = "color_theme";
    const toggle = document.getElementById("color-theme-toggle");

    function setTheme(theme) {
        console.log("setting theme", theme);
        localStorage.setItem(STORAGE_KEY, theme);
        const isColor = theme == COLOR

        if (isColor) {
            toggle.innerHTML = "🏴";
            document.documentElement.style.removeProperty("--saturation")
            toggle.setAttribute("aria-label", "enable black/white theme");
        } else {
            localStorage.setItem(STORAGE_KEY, BW);
            document.documentElement.style.setProperty("--saturation", "0%")
            toggle.innerHTML = "🌈";
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

    if (!localStorage.getItem(STORAGE_KEY)) {
        localStorage.setItem(STORAGE_KEY, COLOR);
    }

    toggle.addEventListener("click", (event) => {
        event.preventDefault();
        toggleTheme();
    });

    setTheme(localStorage.getItem(STORAGE_KEY));
}

darkMode();