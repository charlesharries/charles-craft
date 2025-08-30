/**
 * Auto dark mode that follows user's system theme preference.
 * 
 * Claude made this based on my existing toggleable dark theme
 * @link https://notes.jordanscales.com/40ecf234
 */
function autoDarkMode() {
    const DARK = "dark";
    const LIGHT = "light";

    function setTheme(isDark) {
        if (isDark) {
            document.documentElement.classList.remove(LIGHT);
        } else {
            document.documentElement.classList.add(LIGHT);
        }

        // Possible for EventBus to be undefined on first page load
        if (typeof EventBus != "undefined") {
            EventBus.emit("darkmode.toggle", { isDark });
        }
    }

    function getSystemTheme() {
        return window.matchMedia("(prefers-color-scheme: dark)").matches
            ? DARK
            : LIGHT;
    }

    function updateTheme() {
        const isDark = getSystemTheme() === DARK;
        setTheme(isDark);
    }

    // Set initial theme based on system preference
    updateTheme();

    // Listen for changes to system theme preference
    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
    mediaQuery.addEventListener("change", updateTheme);

    return { 
        DARK, 
        LIGHT, 
        getTheme: getSystemTheme,
        updateTheme
    };
}

const DarkMode = autoDarkMode();