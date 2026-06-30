function Lightbox() {
    const images = document.querySelectorAll('img:not(.no-lightbox)');
    const lightbox = document.getElementById('lightbox');
    let currentImg = null;

    function clearLightbox() {
        lightbox.innerHTML = '';
    }

    function imgAt(idx) {
        const arr = Array.from(images);
        return idx >= 0 ? arr[idx % arr.length] : arr[arr.length + idx];
    }

    function openLightbox(event) {
        if (!window.matchMedia('(min-width: 769px)').matches) return;

        clearLightbox();
        
        const img = document.createElement('img');
        img.src = event.target.src;
        img.alt = event.target.alt;
        lightbox.appendChild(img);
        
        lightbox.style.display = 'block';
        currentImg = event.target;

        preloadLightbox(getNextImage()?.src);
        preloadLightbox(getPrevImage()?.src);
    }

    function preloadLightbox(src) {
        const img = document.createElement('img');
        img.src = src;
    }

    function closeLightbox() {
        clearLightbox();
        lightbox.style.display = 'none';
        currentImg = null;
    }

    function getNextImage() {
        if (!currentImg) return null;
        const currentIdx = Array.from(images).indexOf(currentImg);
        console.log({currentIdx});
        return imgAt(currentIdx+1);
    }

    function getPrevImage() {
        if (!currentImg) return null;
        const currentIdx = Array.from(images).indexOf(currentImg);
        console.log({currentIdx});
        return imgAt(currentIdx-1);
    }

    function nextImage() {
        const nextImg = getNextImage();
        if (!nextImg) return;

        closeLightbox();
        nextImg.click();
    }

    function prevImage() {
        const prevImg = getPrevImage();
        if (!prevImg) return;

        closeLightbox();
        prevImg.click();
    }

    function handleKeydown(event) {
        event.key === 'Escape' && closeLightbox();
        event.key === 'ArrowRight' && nextImage();
        event.key === 'ArrowLeft' && prevImage();
    }

    images.forEach((img) => img.addEventListener('click', openLightbox));
    images.forEach((img) => img.addEventListener('hover', (e) => preloadLightbox(e.target)));
    lightbox.addEventListener('click', closeLightbox)
    window.addEventListener('keydown', handleKeydown);
}

Lightbox();