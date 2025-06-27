function loadAll() {
    const $loadAll = document.getElementById("loadall");
    const $trigger = document.getElementById("loadall_btn");
    const $container = document.getElementById("archive");

    function getOffset() {
        const $latest = document.querySelector(".latest");
        if (!$latest) return 1;

        return $latest.childElementCount;
    }

    async function load() {
        let endpoint = "/actions/api/posts";
        const url = new URL(window.location);
        const queryParams = new URLSearchParams

        queryParams.append("offset", getOffset());
        if (url.pathname.includes("only:")) {
            queryParams.append(section, url.pathname.split("only:")[1]);
        }
        
        endpoint += `?${queryParams.toString()}`;

        const data = await fetch(endpoint).then(r => r.text());
        $container.innerHTML = data;

        url.searchParams.set("loadall", "true");
        window.history.replaceState({}, "", url);

        $trigger.removeEventListener("click", load);
        $loadAll.remove();
    }

    $trigger.addEventListener("click", load);
}

loadAll();