import './bootstrap';
import './date-range-filter';

function initFilePondPdfPreviews() {
    document.querySelectorAll('.filepond--root').forEach(root => {
        if (root.__pdfPreviewInitialized) return;
        root.__pdfPreviewInitialized = true;

        const updateItemPreviews = () => {
            root.querySelectorAll('.filepond--item').forEach(itemEl => {
                const text = itemEl.textContent || '';
                const isPdf = text.toLowerCase().includes('.pdf') || itemEl.getAttribute('data-file-type') === 'application/pdf';

                if (isPdf && !itemEl.querySelector('.custom-pdf-img')) {
                    const link = itemEl.querySelector('a[href*="/storage/"], a[href*=".pdf"]');
                    let pdfUrl = link ? link.href : null;

                    if (!pdfUrl) {
                        const fileObj = itemEl._filepondItem?.file;
                        if (fileObj instanceof File || fileObj instanceof Blob) {
                            pdfUrl = URL.createObjectURL(fileObj);
                        }
                    }

                    if (pdfUrl) {
                        const wrapper = itemEl.querySelector('.filepond--file-wrapper') || itemEl;

                        itemEl._pdfSourceUrl = pdfUrl;

                        const generateThumbnail = () => {
                            if (typeof pdfjsLib === 'undefined') return;
                            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                            pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
                                pdf.getPage(1).then(page => {
                                    const viewport = page.getViewport({ scale: 0.8 });
                                    const canvas = document.createElement('canvas');
                                    const context = canvas.getContext('2d');
                                    canvas.height = viewport.height;
                                    canvas.width = viewport.width;

                                    page.render({ canvasContext: context, viewport: viewport }).promise.then(() => {
                                        if (itemEl.querySelector('.custom-pdf-img')) return;
                                        const imgUrl = canvas.toDataURL('image/jpeg', 0.85);
                                        const img = document.createElement('img');
                                        img.className = 'custom-pdf-img';
                                        img.src = imgUrl;
                                        img.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:top;border-radius:12px;z-index:1;pointer-events:none;background:#fff;';
                                        wrapper.appendChild(img);
                                    });
                                });
                            }).catch(() => {
                                // Fallback ignore
                            });
                        };

                        if (typeof pdfjsLib === 'undefined') {
                            if (!document.getElementById('pdfjs-script')) {
                                const script = document.createElement('script');
                                script.id = 'pdfjs-script';
                                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                                script.onload = generateThumbnail;
                                document.head.appendChild(script);
                            } else {
                                setTimeout(generateThumbnail, 300);
                            }
                        } else {
                            generateThumbnail();
                        }
                    }
                }
            });
        };

        const observer = new MutationObserver(updateItemPreviews);
        observer.observe(root, { childList: true, subtree: true });
        updateItemPreviews();
    });
}

document.addEventListener('DOMContentLoaded', initFilePondPdfPreviews);
document.addEventListener('livewire:navigated', initFilePondPdfPreviews);
setInterval(initFilePondPdfPreviews, 400);

// Open system document preview modal when clicking FilePond items or image previews
document.addEventListener('click', (e) => {
    if (e.target.closest('.filepond--action-remove-item, button[title*="Remove"], .filepond--file-action-button[data-action*="remove"]')) {
        return;
    }

    const itemEl = e.target.closest('.filepond--item');
    if (itemEl) {
        const link = itemEl.querySelector('a[href*="/storage/"], a[href*=".pdf"], a[href*=".png"], a[href*=".jpg"], a[href*=".jpeg"], a[href*="http"]');
        const img = itemEl.querySelector('.filepond--image-preview img, img[src*="/storage/"], img[src*="blob:"], img');

        let url = link ? link.href : (img && img.src && !img.src.startsWith('data:image/svg') ? img.src : (itemEl._pdfSourceUrl || null));

        if (!url) {
            const fileObj = itemEl._filepondItem?.file;
            if (fileObj instanceof File || fileObj instanceof Blob) {
                url = URL.createObjectURL(fileObj);
            }
        }

        if (url) {
            e.preventDefault();
            e.stopPropagation();

            const text = itemEl.textContent || '';
            const rawExt = url.split('.').pop().split('?')[0].toLowerCase();
            const ext = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'].includes(rawExt)
                ? rawExt
                : (text.toLowerCase().includes('.pdf') ? 'pdf' : 'image');

            const rawUrlName = url.split('/').pop().split('?')[0];
            const cleanText = text.split(/(Abort|Retry|Remove|Upload|Cancel)/i)[0].trim();
            const filename = (rawUrlName && rawUrlName.length < 60) ? rawUrlName : (cleanText || 'Document');

            window.dispatchEvent(new CustomEvent('open-document-modal', {
                detail: {
                    url: url,
                    title: 'Document Preview',
                    subtitle: filename,
                    ext: ext
                }
            }));
        }
    }
}, true);

document.addEventListener('livewire:navigated', () => {
    document.documentElement.style.overflow = '';
    document.documentElement.style.paddingRight = '';
});
