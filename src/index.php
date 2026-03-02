<?php /* sent-web — index.php */ ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>sent-web</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/kj-sh604/noir.css@latest/out/noir.min.css">
    <style> :root { --sent-fg: #000000; --sent-bg: #ffffff; --sent-font: 'Noto Color Emoji', 'DejaVu Sans', sans-serif; } body { max-width: 960px; margin: 0 auto; padding: 1rem; } .subtitle { opacity: 0.6; font-size: 0.9em; margin-top: -0.8em; } /* ── controls ── */ #controls { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; margin-bottom: 1rem; padding: 0.75rem; border: 1px solid currentColor; border-radius: 4px; } #controls label { display: flex; align-items: center; gap: 0.4rem; font-size: 0.9rem; } #controls input[type="color"] { width: 2rem; height: 2rem; padding: 0; border: 1px solid currentColor; cursor: pointer; background: none; } #controls select { max-width: 200px; } .upload-area { display: flex; align-items: center; gap: 0.5rem; } #upload-input { display: none; } #upload-status { font-size: 0.85rem; opacity: 0.7; } /* ── editor ── */ #input { width: 100%; min-height: 420px; font-family: monospace; font-size: 0.95rem; resize: vertical; tab-size: 4; } .btn-row { display: flex; gap: 0.5rem; margin-top: 0.5rem; } .hint { font-size: 0.8rem; opacity: 0.5; margin-top: 0.25rem; } /* ── presentation overlay ── */ #presentation { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: flex-start; background: var(--sent-bg); color: var(--sent-fg); cursor: none; overflow: hidden; padding-left: 7.5%; } #presentation.active { display: flex; } #slide-content { text-align: left; white-space: pre-line; word-wrap: break-word; font-family: var(--sent-font); } #slide-content img { display: block; max-width: 85vw; max-height: 85vh; object-fit: contain; } </style>
</head>

<body>

    <header>
        <h1>sent-web</h1>
        <p class="subtitle">suckless's sent tool ported to the very sucky web world</p>
    </header>

    <main>
        <section id="controls">
            <label>
                fg
                <input type="color" id="fg-color" value="#000000">
            </label>
            <label>
                bg
                <input type="color" id="bg-color" value="#ffffff">
            </label>
            <label>
                font
                <select id="font-select">
                    <option value="">loading…</option>
                </select>
            </label>
            <div class="upload-area">
                <button type="button" onclick="document.getElementById('upload-input').click()">upload image</button>
                <input type="file" id="upload-input" accept="image/*">
                <span id="upload-status"></span>
            </div>
        </section>

        <textarea id="input">
sent-web

a port of suckless sent
for the very sucky web 🌎

# this is a comment and will not appear
# the next slide is blank slide since we escaped with \

\

@nyan_819cac51.png

why?
• PPTX sucks
• LATEX sucks
• PDF sucks
• but everything is in 
  the web now 😓

easy to use

▸ one slide per paragraph
▸ lines starting with # are ignored
▸ image slide: @filename
▸ empty slide: just use \

navigate with:
← → ↑ ↓ h j k l
space, enter, backspace
or mouse clicks

press escape to exit

😀😁😂😃😄😅😆😇😈😉😊
emoji just works™

thanks.
questions?</textarea>

        <div class="btn-row">
            <button type="button" id="btn-present" onclick="App.startPresentation()">present</button>
            <button type="button" onclick="App.downloadSent()">download for local sent usage</button>
            <button type="button" onclick="App.exportPDF()">export .pdf</button>
        </div>
        <p class="hint">F5 to present · Esc to exit · ← → h l j k space enter to navigate</p>
    </main>

    <!-- presentation overlay -->
    <div id="presentation">
        <div id="slide-content"></div>
    </div>

    <script>
        // sent-web — vanilla JS presentation engine

        const App = {
            slides: [],
            idx: 0,
            presenting: false,
            loadedFonts: new Set(),

            settings: {
                fg: '#000000',
                bg: '#ffffff',
                fontFamily: '',
                lineSpacing: 1.25,
                usableWidth: 0.85,
                usableHeight: 0.85,
            },

            init() {
                this.restoreState();
                this.loadFonts();
                this.bindEvents();
                this.updateColors();
            },

            restoreState() {
                const saved = localStorage.getItem('sent-web-content');
                if (saved !== null) {
                    document.getElementById('input').value = saved;
                }

                const raw = localStorage.getItem('sent-web-settings');
                if (raw) {
                    try {
                        const s = JSON.parse(raw);
                        if (s.fg) {
                            this.settings.fg = s.fg;
                            document.getElementById('fg-color').value = s.fg;
                        }
                        if (s.bg) {
                            this.settings.bg = s.bg;
                            document.getElementById('bg-color').value = s.bg;
                        }
                        if (s.fontFamily) this.settings.fontFamily = s.fontFamily;
                    } catch (_) {}
                }
            },

            saveSettings() {
                localStorage.setItem('sent-web-settings', JSON.stringify({
                    fg: this.settings.fg,
                    bg: this.settings.bg,
                    fontFamily: this.settings.fontFamily,
                }));
            },

            async loadFonts() {
                try {
                    const res = await fetch('fonts.php');
                    const data = await res.json();
                    const sel = document.getElementById('font-select');
                    sel.innerHTML = '';

                    // preload fallback fonts
                    const notoEmoji = data.find(f => f.family === 'Noto Color Emoji');
                    const dejavu = data.find(f => f.family.startsWith('DejaVu Sans') && !f.family.includes('Mono'));

                    if (notoEmoji) await this.loadFont(notoEmoji);
                    if (dejavu) await this.loadFont(dejavu);

                    // populate dropdown
                    let selectedOpt = null;
                    data.forEach(f => {
                        const opt = document.createElement('option');
                        opt.value = JSON.stringify(f);
                        opt.textContent = f.family;
                        sel.appendChild(opt);

                        // restore saved selection or default to DejaVu Sans
                        if (this.settings.fontFamily && f.family === this.settings.fontFamily) {
                            selectedOpt = opt;
                        } else if (!this.settings.fontFamily && dejavu && f.family === dejavu.family) {
                            selectedOpt = opt;
                        }
                    });

                    if (selectedOpt) selectedOpt.selected = true;
                    this.onFontChange();
                } catch (e) {
                    console.error('Failed to load fonts:', e);
                }
            },

            async loadFont(fontData) {
                if (this.loadedFonts.has(fontData.family)) return;

                try {
                    const url = `font.php?f=${encodeURIComponent(fontData.file)}`;
                    const src = `local('${fontData.family}'), url(${url}) format('${fontData.format}')`;
                    const face = new FontFace(fontData.family, src, {
                        display: 'swap'
                    });
                    const loaded = await face.load();
                    document.fonts.add(loaded);
                    this.loadedFonts.add(fontData.family);
                } catch (e) {
                    console.warn(`Could not load font "${fontData.family}":`, e);
                }
            },

            async onFontChange() {
                const sel = document.getElementById('font-select');
                if (!sel.value) return;

                const fontData = JSON.parse(sel.value);
                this.settings.fontFamily = fontData.family;
                await this.loadFont(fontData);

                const stack = `'${fontData.family}', 'Noto Color Emoji', 'DejaVu Sans', sans-serif`;
                document.documentElement.style.setProperty('--sent-font', stack);

                this.saveSettings();
                if (this.presenting) this.renderSlide();
            },

            bindEvents() {
                document.getElementById('fg-color').addEventListener('input', () => this.updateColors());
                document.getElementById('bg-color').addEventListener('input', () => this.updateColors());
                document.getElementById('font-select').addEventListener('change', () => this.onFontChange());
                document.getElementById('upload-input').addEventListener('change', e => this.handleUpload(e));

                document.getElementById('input').addEventListener('input', () => {
                    localStorage.setItem('sent-web-content', document.getElementById('input').value);
                });

                document.addEventListener('keydown', e => this.handleKeydown(e));

                const pres = document.getElementById('presentation');
                pres.addEventListener('click', e => {
                    if (e.clientX < window.innerWidth / 2) this.navigate(-1);
                    else this.navigate(1);
                });
                pres.addEventListener('wheel', e => {
                    e.preventDefault();
                    this.navigate(e.deltaY > 0 ? 1 : -1);
                }, {
                    passive: false
                });

                window.addEventListener('resize', () => {
                    if (this.presenting) this.renderSlide();
                });

                // stop presentation whenever fullscreen is exited (covers browser-
                // intercepted Escape that never reaches the keydown handler).
                document.addEventListener('fullscreenchange', () => {
                    if (!document.fullscreenElement && this.presenting) {
                        this.stopPresentation();
                    } else if (document.fullscreenElement && this.presenting) {
                        this.renderSlide();
                    }
                });
            },

            updateColors() {
                this.settings.fg = document.getElementById('fg-color').value;
                this.settings.bg = document.getElementById('bg-color').value;
                document.documentElement.style.setProperty('--sent-fg', this.settings.fg);
                document.documentElement.style.setProperty('--sent-bg', this.settings.bg);
                this.saveSettings();
                if (this.presenting) this.renderSlide();
            },

            // sent format parser
            parseSent(text) {
                const slides = [];
                const paragraphs = text.split(/\n{2,}/);

                for (const para of paragraphs) {
                    const rawLines = para.split('\n');
                    const lines = [];
                    let img = null;
                    let firstContent = true;

                    for (const raw of rawLines) {
                        if (raw.trim() === '' || raw.startsWith('#')) continue;

                        let line = raw;

                        if (firstContent && line.startsWith('@')) {
                            img = line.substring(1).trim();
                            firstContent = false;
                            continue;
                        }
                        firstContent = false;

                        // image slides ignore remaining text lines
                        if (img !== null) continue;

                        // strip leading backslash (escape)
                        if (line.startsWith('\\')) {
                            line = line.substring(1);
                        }

                        lines.push(line);
                    }

                    if (img !== null || lines.length > 0) {
                        slides.push({
                            lines,
                            img
                        });
                    }
                }

                return slides;
            },

            // presentation controls
            startPresentation() {
                const text = document.getElementById('input').value;
                this.slides = this.parseSent(text);

                if (this.slides.length === 0) {
                    alert('No slides to present.');
                    return;
                }

                this.idx = 0;
                this.presenting = true;
                document.getElementById('presentation').classList.add('active');
                document.body.style.overflow = 'hidden';

                const el = document.getElementById('presentation');
                if (el.requestFullscreen) el.requestFullscreen().catch(() => {});

                this.renderSlide();
            },

            stopPresentation() {
                this.presenting = false;
                document.getElementById('presentation').classList.remove('active');
                document.body.style.overflow = '';

                if (document.fullscreenElement) {
                    document.exitFullscreen().catch(() => {});
                }
            },

            navigate(dir) {
                if (!this.presenting) return;
                const next = this.idx + dir;
                if (next >= 0 && next < this.slides.length) {
                    this.idx = next;
                    this.renderSlide();
                }
            },

            // rendering engine
            renderSlide() {
                if (!this.presenting || this.slides.length === 0) return;

                const slide = this.slides[this.idx];
                const content = document.getElementById('slide-content');
                const pres = document.getElementById('presentation');

                pres.style.backgroundColor = this.settings.bg;
                pres.style.color = this.settings.fg;

                content.innerHTML = '';

                if (slide.img) {
                    // center image slides — override the text-layout defaults
                    pres.style.justifyContent = 'center';
                    pres.style.alignItems = 'center';
                    pres.style.paddingLeft = '0';
                    const img = document.createElement('img');
                    if (slide.img.startsWith('http://') || slide.img.startsWith('https://')) {
                        img.src = slide.img;
                    } else {
                        img.src = 'uploads/' + slide.img;
                    }
                    img.alt = slide.img;
                    // changed to reflect the fullscreen viewport.
                    const pw = pres.offsetWidth || window.innerWidth;
                    const ph = pres.offsetHeight || window.innerHeight;
                    const maxW = Math.floor(pw * this.settings.usableWidth);
                    const maxH = Math.floor(ph * this.settings.usableHeight);
                    img.style.width = maxW + 'px';
                    img.style.height = maxH + 'px';
                    img.style.maxWidth = maxW + 'px';
                    img.style.maxHeight = maxH + 'px';
                    img.style.objectFit = 'contain';
                    img.style.display = 'block';
                    content.appendChild(img);
                } else {
                    // restore text-layout defaults
                    pres.style.justifyContent = 'flex-start';
                    pres.style.alignItems = 'center';
                    pres.style.paddingLeft = '7.5%';
                    const fontSize = this.calcFontSize(slide.lines);
                    content.style.fontSize = fontSize + 'px';
                    content.style.lineHeight = String(this.settings.lineSpacing);

                    slide.lines.forEach((line, i) => {
                        if (i > 0) content.appendChild(document.createElement('br'));
                        content.appendChild(document.createTextNode(line));
                    });
                }

            },

            calcFontSize(lines) {
                const maxW = window.innerWidth * this.settings.usableWidth;
                const maxH = window.innerHeight * this.settings.usableHeight;
                const font = getComputedStyle(document.documentElement)
                    .getPropertyValue('--sent-font').trim() || 'sans-serif';

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                let lo = 1,
                    hi = 500,
                    best = 1;

                while (lo <= hi) {
                    const mid = Math.floor((lo + hi) / 2);
                    ctx.font = `${mid}px ${font}`;

                    let fits = true;
                    for (const line of lines) {
                        if (ctx.measureText(line).width > maxW) {
                            fits = false;
                            break;
                        }
                    }

                    // height check: line-spacing × (n-1) + 1 base height
                    const totalH = mid * this.settings.lineSpacing * (lines.length - 1) + mid;
                    if (totalH > maxH) fits = false;

                    if (fits) {
                        best = mid;
                        lo = mid + 1;
                    } else {
                        hi = mid - 1;
                    }
                }

                return best;
            },

            // keyboard handler

            handleKeydown(e) {
                // F5 to start presentation from editor
                if (!this.presenting && e.key === 'F5') {
                    e.preventDefault();
                    this.startPresentation();
                    return;
                }

                if (!this.presenting) return;

                switch (e.key) {
                    case 'Escape':
                    case 'q':
                        e.preventDefault();
                        this.stopPresentation();
                        break;
                    case 'ArrowRight':
                    case 'ArrowDown':
                    case ' ':
                    case 'Enter':
                    case 'l':
                    case 'j':
                    case 'n':
                    case 'PageDown':
                        e.preventDefault();
                        this.navigate(1);
                        break;
                    case 'ArrowLeft':
                    case 'ArrowUp':
                    case 'Backspace':
                    case 'h':
                    case 'k':
                    case 'p':
                    case 'PageUp':
                        e.preventDefault();
                        this.navigate(-1);
                        break;
                }
            },

            // image upload

            async handleUpload(e) {
                const file = e.target.files[0];
                if (!file) return;

                const status = document.getElementById('upload-status');
                status.textContent = 'uploading…';

                const fd = new FormData();
                fd.append('image', file);

                try {
                    const res = await fetch('upload.php', {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();

                    if (data.error) {
                        status.textContent = 'error: ' + data.error;
                        return;
                    }

                    // insert @filename at cursor position
                    const ta = document.getElementById('input');
                    const pos = ta.selectionStart;
                    const txt = ta.value;
                    const ins = `\n@${data.filename}\n`;
                    ta.value = txt.substring(0, pos) + ins + txt.substring(pos);
                    ta.selectionStart = ta.selectionEnd = pos + ins.length;
                    ta.focus();

                    localStorage.setItem('sent-web-content', ta.value);
                    status.textContent = `uploaded: ${data.filename}`;
                    setTimeout(() => {
                        status.textContent = '';
                    }, 3000);
                } catch (err) {
                    status.textContent = 'upload failed';
                    console.error(err);
                }

                e.target.value = '';
            },

            // download .sent file for local usage (base64-encoded to preserve unicode and avoid filename issues)
            downloadSent() {
                const text = document.getElementById('input').value;
                const blob = new Blob([text], {
                    type: 'text/plain'
                });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'presentation.sent';
                a.click();
                URL.revokeObjectURL(a.href);
            },

            // download pdf export of the presentation from canvas
            async exportPDF() {
                const text = document.getElementById('input').value;
                const slides = this.parseSent(text);

                if (slides.length === 0) {
                    alert('No slides to export.');
                    return;
                }

                const btn = document.querySelector('button[onclick="App.exportPDF()"]');
                if (btn) {
                    btn.textContent = 'generating pdf…';
                    btn.disabled = true;
                }

                try {
                    if (!window.jspdf) {
                        await new Promise((resolve, reject) => {
                            const s = document.createElement('script');
                            s.src = 'https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js';
                            s.onload = resolve;
                            s.onerror = () => reject(new Error('Failed to load jsPDF'));
                            document.head.appendChild(s);
                        });
                    }

                    // 1440p canvas per slide — rasterise via browser engine
                    // (handles fonts, unicode, emoji, images exactly as the live view does)
                    const W = 2560;
                    const H = 1440;

                    const {
                        jsPDF
                    } = window.jspdf;
                    // px unit + hotfix keeps jsPDF from rescaling our pixel-perfect canvases
                    const pdf = new jsPDF({
                        orientation: 'landscape',
                        unit: 'px',
                        format: [W, H],
                        hotfixes: ['px_scaling'],
                    });

                    for (let i = 0; i < slides.length; i++) {
                        const canvas = await this.renderSlideToCanvas(slides[i], W, H);
                        const imgData = canvas.toDataURL('image/jpeg', 0.93);

                        if (i > 0) pdf.addPage([W, H], 'landscape');
                        pdf.addImage(imgData, 'JPEG', 0, 0, W, H);
                    }

                    const epoch = Math.floor(Date.now() / 1000);
                    const uid = crypto.randomUUID().replace(/-/g, '').slice(0, 8);
                    pdf.save(`sent-web${epoch}-${uid}.pdf`);

                } catch (err) {
                    console.error('PDF export failed:', err);
                    alert('PDF export failed: ' + err.message);
                } finally {
                    if (btn) {
                        btn.textContent = 'export .pdf';
                        btn.disabled = false;
                    }
                }
            },

            // render one slide to an off-screen canvas, mirrors renderSlide() exactly
            async renderSlideToCanvas(slide, W, H) {
                const canvas = document.createElement('canvas');
                canvas.width = W;
                canvas.height = H;
                const ctx = canvas.getContext('2d');

                const usable = this.settings.usableWidth; // 0.85
                const maxW = W * usable;
                const maxH = H * usable;
                const marginX = (W - maxW) / 2;

                // resolve the same font stack the live view uses
                const fontStack = getComputedStyle(document.documentElement)
                    .getPropertyValue('--sent-font').trim() || 'sans-serif';

                // background
                ctx.fillStyle = this.settings.bg;
                ctx.fillRect(0, 0, W, H);

                if (slide.img) {
                    // image slide — draw the actual image
                    await new Promise((resolve) => {
                        const img = new Image();
                        img.crossOrigin = 'anonymous';
                        img.onload = () => {
                            const ratio = img.naturalWidth / img.naturalHeight;
                            let dw = maxW,
                                dh = maxH;
                            if (dw / dh > ratio) dw = dh * ratio;
                            else dh = dw / ratio;
                            ctx.drawImage(img, (W - dw) / 2, (H - dh) / 2, dw, dh);
                            resolve();
                        };
                        img.onerror = resolve; // still produce a page even on failure
                        img.src = (slide.img.startsWith('http://') || slide.img.startsWith('https://')) ?
                            slide.img :
                            'uploads/' + slide.img;
                    });
                } else {
                    // text slide — left-aligned, same sizing logic as live view
                    const fontSize = this.calcFontSizeCanvas(ctx, slide.lines, fontStack, maxW, maxH);

                    ctx.font = `${fontSize}px ${fontStack}`;
                    ctx.fillStyle = this.settings.fg;
                    ctx.textBaseline = 'alphabetic';

                    const lineH = fontSize * this.settings.lineSpacing;
                    const totalH = lineH * (slide.lines.length - 1) + fontSize;
                    const startX = marginX;
                    const startY = (H - totalH) / 2 + fontSize; // first baseline

                    slide.lines.forEach((line, i) => {
                        ctx.fillText(line, startX, startY + i * lineH);
                    });
                }

                return canvas;
            },

            // binary-search font size on a canvas context for given absolute dimensions
            calcFontSizeCanvas(ctx, lines, fontStack, maxW, maxH) {
                let lo = 1,
                    hi = 600,
                    best = 1;

                while (lo <= hi) {
                    const mid = Math.floor((lo + hi) / 2);
                    ctx.font = `${mid}px ${fontStack}`;

                    let fits = true;
                    for (const line of lines) {
                        if (ctx.measureText(line).width > maxW) {
                            fits = false;
                            break;
                        }
                    }

                    const totalH = mid * this.settings.lineSpacing * (lines.length - 1) + mid;
                    if (totalH > maxH) fits = false;

                    if (fits) {
                        best = mid;
                        lo = mid + 1;
                    } else {
                        hi = mid - 1;
                    }
                }

                return best;
            },
        };

        document.addEventListener('DOMContentLoaded', () => App.init());
    </script>
</body>

</html>