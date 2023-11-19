function loadAll() {
    const $loadAll = document.getElementById("loadall");
    const $trigger = document.getElementById("loadall_btn");
    const $container = document.getElementById("archive");

    async function load() {
        const data = await fetch("/actions/api/posts").then(r => r.text());
        $container.innerHTML = data;

        $trigger.removeEventListener("click", load);
        $loadAll.remove();
    }

    $trigger.addEventListener("click", load);
}

loadAll();