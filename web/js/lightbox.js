function Lightbox() {
    const images = document.querySelectorAll('img:not(.no-lightbox)');
    const lightbox = document.getElementById('lightbox');

    function clearLightbox() {
        lightbox.innerHTML = '';
    }

    function openLightbox(event) {
        clearLightbox();
        
        const img = document.createElement('img');
        img.src = event.target.src;
        img.alt = event.target.alt;
        lightbox.appendChild(img);
        
        lightbox.style.display = 'block';
    }

    function preloadLightbox(event) {
        const img = document.createElement('img');
        img.src = event.target.src;
    }

    function closeLightbox(event) {
        clearLightbox();
        lightbox.style.display = 'none';
    }

    function handleKeydown(event) {
        event.key === 'Escape' && closeLightbox();
    }

    images.forEach((img) => img.addEventListener('click', openLightbox));
    images.forEach((img) => img.addEventListener('hover', preloadLightbox));
    lightbox.addEventListener('click', closeLightbox)
    window.addEventListener('keydown', handleKeydown);
}

Lightbox();