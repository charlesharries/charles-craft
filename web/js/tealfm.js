function TealFm() {
    const url = "/api/teal-fm/stats?period=30days";

    async function load() {
        const response = await fetch(url).then(r => r.json());

        renderTotals(response.totals || {});
        renderAlbums(response.albums || []);
        renderRecent(response.recent || []);
    }

    function renderTotals(totals) {
        $totalSongs.innerText = totals.songs ?? 0;
        $totalArtists.innerText = totals.artists ?? 0;
        $totalAlbums.innerText = totals.albums ?? 0;
    }

    function renderAlbums(albums) {
        if (!albums.length) {
            $albumsEmpty.hidden = false;
            return;
        }

        albums.forEach((album, idx) => $albums.append(albumItem(album, idx)));
    }

    function renderRecent(tracks) {
        if (!tracks.length) {
            $recentEmpty.hidden = false;
            return;
        }

        tracks.forEach(track => $recent.append(recentItem(track)));
    }

    const $totalSongs = document.getElementById("tealFm_totalSongs");
    const $totalArtists = document.getElementById("tealFm_totalArtists");
    const $totalAlbums = document.getElementById("tealFm_totalAlbums");
    const $albums = document.getElementById("tealFm_albums");
    const $albumsEmpty = document.getElementById("tealFm_albumsEmpty");
    const $recent = document.getElementById("tealFm_recent");
    const $recentEmpty = document.getElementById("tealFm_recentEmpty");

    load();
}

function albumItem(album, idx) {
    const art = album.mbid
        ? raw(`<img src="/api/album-art/${encodeURIComponent(album.mbid)}" alt="${escapeHtml(album.name)}" width="64" height="64" class="no-lightbox" onerror="this.remove()">`)
        : "";

    return html`
        <li class="flex align-center">
            <div class="monospace muted align-self-start">${idx+1}.</div>
            <div class="rounded ml-sm" style="width: 64px; flex-shrink: 0;">${art}</div>
            <div class="ml-sm flex-1">
                <p class="bold">${album.name}</p>
                <p class="muted text-sm">${album.artist}</p>
                <p class="muted text-sm">${album.playCount === 1 ? "1 play" : `${album.playCount} plays`}</p>
            </div>
        </li>
    `;
}

function recentItem(track) {
    const art = track.mbid
        ? raw(`<img src="/api/album-art/${encodeURIComponent(track.mbid)}" alt="${escapeHtml(track.releaseName ?? track.trackName)}" width="48" height="48" class="no-lightbox" onerror="this.remove()">`)
        : "";

    const title = track.playCount > 1
        ? raw(`<span class="bold">${track.releaseName}</span> &bull; ${track.playCount} plays`)
        : raw(`<span class="bold">${track.trackName}</span>`);

    return html`
        <li class="flex align-center">
            <div class="rounded" style="width: 48px; flex-shrink: 0;">${art}</div>
            <div class="ml-sm flex-1">
                <p>${title}</p>
                <p class="muted text-sm">${track.artist}</p>
            </div>
            <p class="muted text-sm">${timeAgo(track.playedTime)}</p>
        </li>
    `;
}

/**
 * Tagged template that HTML-escapes interpolated values by default, so
 * album/artist names from teal.fm's metadata can't break or inject into the
 * markup. Wrap a value in raw() to insert it unescaped (eg. HTML built from
 * another html`` call).
 */
function html(strings, ...values) {
    const markup = strings.reduce((result, string, i) => {
        const value = values[i - 1];
        const inserted = value !== null && typeof value === "object" && "__html" in value
            ? value.__html
            : escapeHtml(value);

        return result + inserted + string;
    });

    const $template = document.createElement("template");
    $template.innerHTML = markup.trim();

    return $template.content.firstElementChild;
}

function raw(value) {
    return { __html: value };
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");
}

function timeAgo(isoString) {
    if (!isoString) return "";

    const seconds = Math.max(0, (Date.now() - new Date(isoString).getTime()) / 1000);

    if (seconds < 60) return "just now";
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}

TealFm();
