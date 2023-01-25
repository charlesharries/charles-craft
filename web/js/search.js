const Utils = {
    debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },
}

function Search() {
    /** @type {HTMLDialogElement} */
    const dialog = document.getElementById("search");

    /** @type {HTMLInputElement} */
    const searchField = document.getElementById("searchQuery");

    /** @type {HTMLFormElement} */
    const searchForm = document.getElementById("searchForm");

    /** @type {HTMLUListElement} */
    const results = document.createElement("ul");

    initListeners();
    initResults();

    function initListeners() {
        document.addEventListener("keydown", (event) => {
            if (event.key === "k" && (event.ctrlKey || event.metaKey)) {
                dialog.showModal();
            }
            
            if (event.key === "Esc" && dialog.open) {
                dialog.close();
            }
        });

        searchField.addEventListener("input", Utils.debounce(async (event) => {
            clearResults();
            const results = await search();
        }, 500));
    }

    function initResults() {
        dialog.appendChild(results);
    }

    function clearResults() {
        results.innerHTML = "";
    }

    function newResult(result) {
        const el = document.createElement("li");
        el.textContent = result;
        return el;
    }

    async function search() {
        const query = new URLSearchParams({ q: searchField.value });
        const results = await fetch(`${searchForm.action}?${query}`, {
            headers: { Accept: "application/json" }
        }).then(r => r.json());

        console.log({ results });

        return results;
    }
}

Search();