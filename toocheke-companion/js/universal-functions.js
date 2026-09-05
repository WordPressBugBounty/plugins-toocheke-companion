// Click-to-next comic navigation
document.addEventListener('click', function (e) {
    var wrapper = e.target.closest('.click-to-next-wrapper');
    if (!wrapper) return;

    // If the click landed on or inside an existing <a> tag, let it navigate normally
    if (e.target.closest('a')) return;

    var href = wrapper.dataset.nextHref;
    if (href) {
        window.location.href = href;
    }
});