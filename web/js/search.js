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

    /** @type {HTMLTemplateElement} */
    const resultTemplate = document.getElementById("searchResult");

    /** @type {HTMLTemplateElement} */
    const blankResultTemplate = document.getElementById("blankSearchResult");

    const SEARCH_DEBOUNCE = 200;

    initListeners();
    initResults();

    function initListeners() {
        window.addEventListener("keydown", (event) => {
            if (event.key === "k" && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                dialog.showModal();
                searchField.focus();
            }

            if (["ArrowDown", "ArrowUp", "Enter"].includes(event.key)) {
                handleResultNav(event);
            }
        });

        dialog.addEventListener("close", (event) => {
            clearResults();
            searchForm.reset();
        })

        searchForm.addEventListener("submit", (e) => e.preventDefault());

        searchField.addEventListener("input", Utils.debounce(async (event) => {
            clearResults();
            if (searchField.value.length < 3) return;
            
            const searchResults = await search();
            if (searchResults.length == 0 && searchField.value.length > 0) {
                results.appendChild(newBlankResult("No search results."));
                return;
            }

            searchResults.forEach(result => {
                results.appendChild(newResult(result));
            })
        }, SEARCH_DEBOUNCE));
    }

    function initResults() {
        dialog.appendChild(results);
    }

    function clearResults() {
        results.innerHTML = "";
    }

    function newResult(result) {
        const li = resultTemplate.content.cloneNode(true);
        li.querySelector(".searchResult_title").textContent = result.title;
        li.querySelector(".searchResult_body").innerHTML = result.result;
        li.querySelector(".searchResult_link").setAttribute("href", result.url);
        return li;
    }

    function newBlankResult(label) {
        const li = blankResultTemplate.content.cloneNode(true);
        li.querySelector(".searchResult_body").innerHTML = label;
        return li;
    }

    async function search() {
        const query = new URLSearchParams({ q: searchField.value });
        const results = await fetch(`${searchForm.action}?${query}`, {
            headers: { Accept: "application/json" }
        }).then(r => r.json());

        return results;
    }

    function selectedResult() {
        return results.querySelector("[aria-selected='true']");
    }

    /** @param {KeyboardEvent} event */
    function handleResultNav(event) {
        const selected = selectedResult()

        /** @param {HTMLLIElement} element */
        function select(element) {
            if (selected) selected.removeAttribute("aria-selected");
            element.setAttribute("aria-selected", "true");
            element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function scrollDown() {
            if (!selected || selected === results.lastElementChild) {
                select(results.firstElementChild);
                return;
            }

            select(selected.nextElementSibling);
        }

        function scrollUp() {
            if (!selected || selected === results.firstElementChild) {
                select(results.lastElementChild);
                return
            }

            select(selected.previousElementSibling);
        }

        function navigate(event) {
            event.preventDefault();
            
            if (!selected) return;
            const to = selected.querySelector("a");
            if (!to) return;

            to.click();
        }

        if (event.key === "ArrowDown") scrollDown();
        else if (event.key === "ArrowUp") scrollUp();
        else if (selected && event.key === "Enter") navigate(event);
    }
}

Search();