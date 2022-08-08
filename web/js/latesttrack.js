class LatestTrack {
    url = "/api/latest-tracks";

    /** @type {HTMLElement|null} */
    $image;

    /** @type {HTMLElement|null} */
    $name;

    /** @type {HTMLElement|null} */
    $artist;

    /** @type {HTMLElement|null} */
    $nowPlaying;

    /**
     * Construct the recent tracks component.
     * 
     * @param {HTMLElement} $el 
     */
    constructor($el) {
        this.$el = $el;

        this.$image = this.$el.querySelector("#latestTrack_image");
        this.$name = this.$el.querySelector("#latestTrack_name");
        this.$artist = this.$el.querySelector("#latestTrack_artist");
        this.$nowPlaying = this.$el.querySelector("#latestTrack_nowPlaying");

        this.fetch();
    }

    async fetch() {
        const response = await fetch(this.url).then(r => r.json());

        if (!(response.recenttracks && response.recenttracks.track)) return;

        this.populateTrack(response.recenttracks.track[0]);
    }

    populateTrack(track) {
        this.setCoverArt(track);
        this.setIsNowPlaying(track);

        this.$name.innerText = track.name;
        this.$artist.innerText = track.artist["#text"];
        this.setIsNowPlaying(track);
        
        this.$el.hidden = false;
    }

    setCoverArt(track) {
        const src = track.image.find(i => i.size === "large")["#text"];
        this.$image.src = src;
        this.$image.alt = track.name
        this.$image.width = 64;
        this.$image.height = 64;
    }

    setIsNowPlaying(track) {
        this.$nowPlaying.innerText = this.isNowPlaying(track)
            ? "Now playing"
            : "Recently played";
    }

    isNowPlaying(track) {
        if (!track["@attr"]) return false;
        if (!track["@attr"].nowplaying) return false;

        return track["@attr"].nowplaying === "true";
    }
}

const container = document.getElementById("latestTrack");
container.__component = new LatestTrack(container);