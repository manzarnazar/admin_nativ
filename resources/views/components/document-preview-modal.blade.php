<div
    x-data="documentPreviewModal"
    @open-document-modal.window="openModal($event.detail)"
    x-show="show"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.arrow-left.window="if(show) prev()"
    x-on:keydown.arrow-right.window="if(show) next()"
    style="display: none;"
    class="fixed inset-0 z-[9999] flex items-center justify-center p-4 sm:p-6"
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="show = false"
        style="background-color: rgba(0, 0, 0, 0.3) !important; backdrop-filter: blur(2px) !important; -webkit-backdrop-filter: blur(2px) !important;"
        class="fixed inset-0"
    ></div>

    {{-- Modal Card --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.stop
        class="relative z-10 flex w-full max-w-4xl flex-col rounded-2xl bg-white p-5 shadow-2xl dark:bg-gray-900 dark:border dark:border-gray-800"
        style="max-height: 85vh;"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between gap-4 pb-4 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3.5 min-w-0">
                <template x-if="ext === 'pdf'">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-100/80 text-red-600 dark:bg-red-900/30 dark:text-red-400 font-bold text-xs">
                        <x-heroicon-o-document-text class="h-6 w-6" />
                    </div>
                </template>
                <template x-if="ext !== 'pdf'">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100/80 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 font-bold text-xs">
                        <x-heroicon-o-photo class="h-6 w-6" />
                    </div>
                </template>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white truncate">
                        <span x-text="title"></span>
                        <span x-show="gallery.length > 1" class="ml-2 text-xs font-normal text-gray-500" x-text="`(${(currentIndex + 1)} of ${gallery.length})`"></span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate" x-show="subtitle" x-text="subtitle"></p>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <button
                    type="button"
                    @click="downloadFile()"
                    style="background-color: #18181B !important; color: #ffffff !important;"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs sm:text-sm font-semibold transition hover:opacity-90 shadow-sm cursor-pointer"
                >
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" style="color: #ffffff !important;" />
                    Download
                </button>
                <button
                    type="button"
                    @click="show = false"
                    class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-300 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                >
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>
        </div>

        {{-- Body Content --}}
        <div class="relative mt-4">
            <template x-if="gallery.length > 1">
                <button @click.stop="prev()" type="button" class="absolute left-3 top-1/2 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-md text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition z-50">
                    <x-heroicon-o-chevron-left class="h-5 w-5" />
                </button>
            </template>
            <template x-if="gallery.length > 1">
                <button @click.stop="next()" type="button" class="absolute right-3 top-1/2 -translate-y-1/2 flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-md text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition z-50">
                    <x-heroicon-o-chevron-right class="h-5 w-5" />
                </button>
            </template>

            <div class="rounded-xl bg-white p-2 dark:bg-gray-900 flex items-center justify-center border border-gray-200 dark:border-gray-800 overflow-y-auto" style="height: calc(85vh - 140px);">
                <template x-if="ext === 'pdf'">
                    <div x-ref="pdfContainer" class="w-full h-full overflow-y-auto bg-white p-2 flex flex-col items-center"></div>
                </template>
                <template x-if="ext !== 'pdf'">
                    <img :src="url" class="rounded-xl shadow-sm p-2" style="max-width: 100%; max-height: 100%; object-fit: contain; display: block;" />
                </template>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function initDocModal() {
        if (typeof Alpine === 'undefined') return;
        if (window.__documentPreviewModalRegistered) return;
        window.__documentPreviewModalRegistered = true;

        Alpine.data('documentPreviewModal', () => ({
            show: false,
            url: '',
            title: 'Document Preview',
            subtitle: '',
            ext: 'pdf',
            pdfLoading: false,
            gallery: [],
            currentIndex: 0,

            init() {
                if (window.__documentPreviewClickListenerAttached) return;
                window.__documentPreviewClickListenerAttached = true;

                document.addEventListener('click', (e) => {
                    const target = e.target.closest('a[href*="/storage/"], button[title*="Open"], a[title*="Open"], [data-action="open"], a[href*="avatars"], img[src*="/storage/"]');
                    if (target) {
                        if (target.closest('.fi-sidebar, .fi-sidebar-header, .fi-sidebar-header-logo-ctn, .fi-logo, .fi-topbar, [data-no-preview]')) {
                            return;
                        }
                        const clickAttr = target.getAttribute('x-on:click') || target.getAttribute('@click') || target.parentElement?.getAttribute('@click') || target.parentElement?.getAttribute('x-on:click') || '';
                        if (clickAttr.includes('open-document-modal')) {
                            return;
                        }
                        const href = target.getAttribute('href') || target.dataset.url || target.src;
                        if (href && !href.startsWith('data:image/svg')) {
                            const ext = href.split('.').pop().split('?')[0].toLowerCase();
                            if (['pdf', 'jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'].includes(ext) || href.includes('/storage/')) {
                                e.preventDefault();
                                e.stopPropagation();
                                const rawName = href.split('/').pop().split('?')[0];
                                window.dispatchEvent(new CustomEvent('open-document-modal', {
                                    detail: {
                                        url: href,
                                        title: 'Profile Photo / Document Preview',
                                        subtitle: rawName,
                                        ext: ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'].includes(ext) ? ext : (href.toLowerCase().includes('.pdf') ? 'pdf' : 'image')
                                    }
                                }));
                            }
                        }
                    }
                }, true);
            },

            openModal(detail) {
                this.show = true;
                
                // Build gallery from all valid images on the page
                let allTargets = Array.from(document.querySelectorAll('a[href*="/storage/"], button[title*="Open"], a[title*="Open"], [data-action="open"], a[href*="avatars"], img[src*="/storage/"]'));
                allTargets = allTargets.filter(t => !t.closest('.fi-sidebar, .fi-sidebar-header, .fi-sidebar-header-logo-ctn, .fi-logo, .fi-topbar, [data-no-preview], .fi-modal'));
                allTargets = allTargets.filter(t => {
                    const clickAttr = t.getAttribute('x-on:click') || t.getAttribute('@click') || t.parentElement?.getAttribute('@click') || t.parentElement?.getAttribute('x-on:click') || '';
                    return !clickAttr.includes('open-document-modal');
                });

                // Find the clicked element to see if it belongs to a specific gallery
                const clickedElement = allTargets.find(t => {
                    const href = t.getAttribute('href') || t.dataset.url || t.src;
                    return href === detail.url;
                });

                const targetGallery = clickedElement ? clickedElement.getAttribute('data-gallery') : null;

                if (targetGallery) {
                    allTargets = allTargets.filter(t => t.getAttribute('data-gallery') === targetGallery);
                } else {
                    allTargets = allTargets.filter(t => !t.hasAttribute('data-gallery'));
                }

                const rawGallery = allTargets.map(t => {
                    const href = t.getAttribute('href') || t.dataset.url || t.src;
                    if (!href || href.startsWith('data:image/svg')) return null;
                    const ext = href.split('.').pop().split('?')[0].toLowerCase();
                    if (['pdf', 'jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'].includes(ext) || href.includes('/storage/')) {
                        const rawName = href.split('/').pop().split('?')[0];
                        return {
                            url: href,
                            title: 'Document Preview',
                            subtitle: rawName,
                            ext: ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'].includes(ext) ? ext : (href.toLowerCase().includes('.pdf') ? 'pdf' : 'image')
                        };
                    }
                    return null;
                }).filter(Boolean);

                // Deduplicate URLs
                const uniqueGallery = [];
                const seenUrls = new Set();
                rawGallery.forEach(item => {
                    if (!seenUrls.has(item.url)) {
                        seenUrls.add(item.url);
                        uniqueGallery.push(item);
                    }
                });

                let cIndex = uniqueGallery.findIndex(item => item.url === detail.url);
                if (cIndex === -1) {
                    uniqueGallery.push(detail);
                    cIndex = uniqueGallery.length - 1;
                }

                this.gallery = uniqueGallery;
                this.currentIndex = cIndex;
                this.loadCurrent();
            },

            next() {
                if (this.gallery.length <= 1) return;
                this.currentIndex = (this.currentIndex + 1) % this.gallery.length;
                this.loadCurrent();
            },

            prev() {
                if (this.gallery.length <= 1) return;
                this.currentIndex = (this.currentIndex - 1 + this.gallery.length) % this.gallery.length;
                this.loadCurrent();
            },

            loadCurrent() {
                const detail = this.gallery[this.currentIndex];
                this.url = detail.url || '';
                this.title = detail.title || 'Document Preview';
                const sub = (detail.subtitle || '').split(/(Abort|Retry|Remove|Upload|Cancel)/i)[0].trim();
                this.subtitle = sub;
                this.ext = (detail.ext || 'pdf').toLowerCase();
                this.$nextTick(() => { this.renderPdf(); });
            },

            downloadFile() {
                if (!this.url) return;
                const filename = this.subtitle || ('document.' + (this.ext || 'pdf'));

                fetch(this.url)
                    .then(res => res.blob())
                    .then(blob => {
                        const blobUrl = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = blobUrl;
                        link.download = filename;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
                    })
                    .catch(() => {
                        const link = document.createElement('a');
                        link.href = this.url;
                        link.download = filename;
                        link.target = '_blank';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    });
            },

            renderPdf() {
                if (this.ext !== 'pdf' || !this.url) return;
                this.pdfLoading = true;
                const container = this.$refs.pdfContainer;
                if (!container) return;
                container.innerHTML = '<div class="flex items-center justify-center h-full p-8 text-sm text-gray-400">Loading PDF...</div>';

                const loadDoc = () => {
                    if (typeof pdfjsLib === 'undefined') return;
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                    pdfjsLib.getDocument(this.url).promise.then(pdf => {
                        this.pdfLoading = false;
                        container.innerHTML = '';
                        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                            pdf.getPage(pageNum).then(page => {
                                const viewport = page.getViewport({ scale: 1.25 });
                                const canvas = document.createElement('canvas');
                                const context = canvas.getContext('2d');
                                canvas.height = viewport.height;
                                canvas.width = viewport.width;
                                canvas.className = 'mx-auto my-3 rounded-lg shadow-sm bg-white border border-gray-100 max-w-full';
                                container.appendChild(canvas);
                                page.render({ canvasContext: context, viewport: viewport });
                            });
                        }
                    }).catch(() => {
                        this.pdfLoading = false;
                        container.innerHTML = `<iframe src="${this.url}#toolbar=0&navpanes=0&view=FitH" class="w-full h-full border-0 bg-white"></iframe>`;
                    });
                };

                if (typeof pdfjsLib === 'undefined') {
                    if (!document.getElementById('pdfjs-script')) {
                        const script = document.createElement('script');
                        script.id = 'pdfjs-script';
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                        script.onload = loadDoc;
                        document.head.appendChild(script);
                    } else {
                        setTimeout(loadDoc, 200);
                    }
                } else {
                    loadDoc();
                }
            }
        }));
    }

    document.addEventListener('alpine:init', initDocModal);
    if (window.Alpine) initDocModal();
})();
</script>
