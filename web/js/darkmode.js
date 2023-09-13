/**
 * Dark mode, freely cribbed from Jordan Scales.
 * 
 * @link https://notes.jordanscales.com/40ecf234
 */
function darkMode() {
    const DARK = "dark";
    const LIGHT = "light";
    const STORAGE_KEY = "theme";
    const toggle = document.getElementById("theme-toggle");
    const colorToggle = document.getElementById("color-theme-toggle");

    function setTheme(isDark) {
        if (isDark) {
            localStorage.setItem(STORAGE_KEY, DARK);
            document.documentElement.classList.remove(LIGHT);
            toggle.innerHTML = "🌙";
            toggle.setAttribute("aria-label", "enable light theme");
        } else {
            localStorage.setItem(STORAGE_KEY, LIGHT);
            document.documentElement.classList.add(LIGHT);
            toggle.innerHTML = "☀️";
            toggle.setAttribute("aria-label", "enable dark theme");
        }

        toggleColorLabel(isDark)
    }

    function toggleColorLabel(isDark) {
        if (ColorMode.getColor() !== ColorMode.BW) return;

        ColorMode.toggle.innerHTML = isDark ? "🏴" : "🏳"
    }

    function toggleTheme() {
        if (localStorage.getItem(STORAGE_KEY) === DARK) {
            setTheme(false);
        } else {
            setTheme(true);
        }
    }

    if (!localStorage.getItem(STORAGE_KEY)) {
        const osTheme = window.matchMedia("(prefers-color-scheme: dark)").matches
            ? DARK
            : LIGHT;
        localStorage.setItem(STORAGE_KEY, osTheme);
    }

    toggle.addEventListener("click", (event) => {
        event.preventDefault();
        toggleTheme();
    });

    setTheme(localStorage.getItem(STORAGE_KEY) === "dark");

    return { DARK, LIGHT, STORAGE_KEY, toggle };
}

const DarkMode = darkMode();