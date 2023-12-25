function loadAll() {
    const $loadAll = document.getElementById("loadall");
    const $trigger = document.getElementById("loadall_btn");
    const $container = document.getElementById("archive");

    async function load() {
        let endpoint = "/actions/api/posts";
        const url = new URL(window.location);
        if (url.pathname.includes("only:")) {
            endpoint += `?section=${url.pathname.split("only:")[1]}`;
        }
        

        const data = await fetch(endpoint).then(r => r.text());
        $container.innerHTML = data;

        $trigger.removeEventListener("click", load);
        $loadAll.remove();
    }

    $trigger.addEventListener("click", load);
}

loadAll();