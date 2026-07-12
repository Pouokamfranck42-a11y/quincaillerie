/**
 * Scan de code-barres via la caméra, avec l'API native BarcodeDetector du navigateur.
 * Aucune dépendance externe — se dégrade silencieusement (bouton masqué) si le navigateur
 * ne supporte pas l'API (ex. Firefox/Safari sans flag).
 */
(function () {
    function supported() {
        return 'BarcodeDetector' in window && 'mediaDevices' in navigator;
    }

    window.initBarcodeScanner = function (root) {
        const button = root.querySelector('[data-scan-button]');
        const video = root.querySelector('[data-scan-video]');
        const closeButton = root.querySelector('[data-scan-close]');
        const targetSelector = root.dataset.scanTarget;
        const target = document.querySelector(targetSelector);

        if (!supported() || !button || !video || !target) {
            if (button) button.style.display = 'none';
            return;
        }

        let stream = null;
        let detector = null;
        let rafId = null;

        async function start() {
            try {
                detector = new BarcodeDetector({
                    formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'qr_code'],
                });
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                video.srcObject = stream;
                video.style.display = 'block';
                video.play();
                closeButton.style.display = 'inline-flex';
                button.style.display = 'none';
                scanLoop();
            } catch (e) {
                stop();
            }
        }

        async function scanLoop() {
            if (!stream) return;
            try {
                const codes = await detector.detect(video);
                if (codes.length > 0) {
                    target.value = codes[0].rawValue;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                    stop();
                    return;
                }
            } catch (e) {
                // image pas encore prête, on continue
            }
            rafId = requestAnimationFrame(scanLoop);
        }

        function stop() {
            if (rafId) cancelAnimationFrame(rafId);
            if (stream) stream.getTracks().forEach((t) => t.stop());
            stream = null;
            video.style.display = 'none';
            closeButton.style.display = 'none';
            button.style.display = 'inline-flex';
        }

        button.addEventListener('click', start);
        closeButton.addEventListener('click', stop);
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-barcode-scanner]').forEach(window.initBarcodeScanner);
    });
})();
